<?php

declare(strict_types=1);

namespace App\Services\Refund;

use App\Enums\CreditNoteStatus;
use App\Enums\PaymentMethod;
use App\Enums\RefundStatus;
use App\Enums\RefundTransactionStatus;
use App\Enums\UserRole;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\RefundRequest;
use App\Models\RefundRequestEvent;
use App\Models\RefundTransaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RefundWorkflowService
{
    public function __construct(
        protected RefundNumberGenerator $refundNumberGenerator,
        protected RefundTransactionNumberGenerator $txnNumberGenerator
    ) {}

    /**
     * Authoritatively create a new customer refund request against available credit note balance.
     *
     * @param CreditNote|int $creditNoteInput
     * @param User $requester
     * @param array<string, mixed> $data
     * @return RefundRequest
     *
     * @throws ValidationException
     */
    public function createRefundRequest(CreditNote|int $creditNoteInput, User $requester, array $data): RefundRequest
    {
        $creditNoteId = $creditNoteInput instanceof CreditNote ? $creditNoteInput->id : $creditNoteInput;
        $idempotencyKey = ! empty($data['idempotency_key']) ? trim((string) $data['idempotency_key']) : (string) \Illuminate\Support\Str::uuid();

        return DB::transaction(function () use ($creditNoteId, $requester, $data, $idempotencyKey) {
            // Check idempotency if explicit key provided
            if (! empty($data['idempotency_key'])) {
                $existing = RefundRequest::where('idempotency_key', $idempotencyKey)
                    ->with(['creditNote', 'customer', 'requester', 'events.actor'])
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            /** @var CreditNote|null $creditNote */
            $creditNote = CreditNote::query()
                ->where('id', $creditNoteId)
                ->lockForUpdate()
                ->first();

            if (! $creditNote) {
                throw ValidationException::withMessages([
                    'credit_note_id' => 'The specified credit note does not exist.',
                ]);
            }

            // Lock customer row to ensure deterministic locking hierarchy (Customer -> CreditNote -> RefundRequest)
            Customer::where('id', $creditNote->customer_id)->lockForUpdate()->first();

            // Validate credit note status
            if (! in_array($creditNote->status, [CreditNoteStatus::ISSUED, CreditNoteStatus::PARTIALLY_REFUNDED], true)) {
                throw ValidationException::withMessages([
                    'credit_note_id' => "Credit note {$creditNote->credit_number} is in status [{$creditNote->status->value}] and has no available refundable credit.",
                ]);
            }

            // Monetary amount validation
            $requestedAmount = isset($data['requested_amount']) ? trim((string) $data['requested_amount']) : (isset($data['amount']) ? trim((string) $data['amount']) : '0.00');
            if (bccomp($requestedAmount, '0.00', 2) <= 0) {
                throw ValidationException::withMessages([
                    'requested_amount' => 'Requested refund amount must be greater than zero.',
                ]);
            }

            $availableBalance = (string) $creditNote->remaining_balance;
            if (bccomp($requestedAmount, $availableBalance, 2) > 0) {
                throw ValidationException::withMessages([
                    'requested_amount' => "Requested refund amount ({$requestedAmount}) exceeds available credit note balance ({$availableBalance}).",
                ]);
            }

            // Validate payment method
            $paymentMethodValue = $data['payment_method'] instanceof PaymentMethod
                ? $data['payment_method']->value
                : (string) ($data['payment_method'] ?? '');

            $paymentMethod = PaymentMethod::tryFrom($paymentMethodValue);
            if (! $paymentMethod || ! in_array($paymentMethod, [PaymentMethod::CASH, PaymentMethod::CHEQUE, PaymentMethod::MONEY_ORDER], true)) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Invalid refund payment method. Supported V1 methods: CASH, CHEQUE, MONEY_ORDER.',
                ]);
            }

            $reason = trim((string) ($data['reason'] ?? 'Customer Refund Request'));
            if ($reason === '') {
                throw ValidationException::withMessages([
                    'reason' => 'A valid refund justification reason is required.',
                ]);
            }

            $refundNumber = $this->refundNumberGenerator->generate();
            $now = Carbon::now();

            /** @var RefundRequest $refundRequest */
            $refundRequest = RefundRequest::create([
                'refund_number' => $refundNumber,
                'credit_note_id' => $creditNote->id,
                'customer_id' => $creditNote->customer_id,
                'status' => RefundStatus::REQUESTED,
                'payment_method' => $paymentMethod,
                'amount' => number_format((float) $requestedAmount, 2, '.', ''),
                'reason' => $reason,
                'requested_by' => $requester->id,
                'requested_at' => $now,
                'idempotency_key' => $idempotencyKey,
            ]);

            // Append initial immutable audit event
            RefundRequestEvent::create([
                'refund_request_id' => $refundRequest->id,
                'actor_id' => $requester->id,
                'action' => 'REQUESTED',
                'from_status' => null,
                'to_status' => RefundStatus::REQUESTED->value,
                'notes' => sprintf('Refund request %s submitted for amount %s %s.', $refundRequest->refund_number, $creditNote->currency, $refundRequest->amount),
                'metadata' => [
                    'amount' => (string) $refundRequest->amount,
                    'payment_method' => $refundRequest->payment_method->value,
                    'available_credit_at_request' => $availableBalance,
                ],
                'created_at' => $now,
            ]);

            return $refundRequest->fresh(['creditNote', 'customer', 'requester', 'events.actor']);
        });
    }

    /**
     * Cancel an active refund request before authorization or processing.
     *
     * @param RefundRequest|int $refundRequestInput
     * @param User $actor
     * @param array<string, mixed> $data
     * @return RefundRequest
     *
     * @throws ConflictHttpException
     */
    public function cancelRefund(RefundRequest|int $refundRequestInput, User $actor, array $data = []): RefundRequest
    {
        $refundId = $refundRequestInput instanceof RefundRequest ? $refundRequestInput->id : $refundRequestInput;

        return DB::transaction(function () use ($refundId, $actor, $data) {
            /** @var RefundRequest|null $refundRequest */
            $refundRequest = RefundRequest::query()
                ->where('id', $refundId)
                ->lockForUpdate()
                ->first();

            if (! $refundRequest) {
                throw new ConflictHttpException('Refund request not found.');
            }

            if (! in_array($refundRequest->status, [RefundStatus::REQUESTED, RefundStatus::UNDER_REVIEW], true)) {
                throw new ConflictHttpException(sprintf(
                    'Refund request #%s is in status [%s] and cannot be cancelled.',
                    $refundRequest->refund_number,
                    $refundRequest->status->value
                ));
            }

            $fromStatus = $refundRequest->status;
            $now = Carbon::now();

            $refundRequest->status = RefundStatus::CANCELLED;
            $refundRequest->cancelled_by = $actor->id;
            $refundRequest->cancelled_at = $now;
            $refundRequest->cancellation_reason = $data['reason'] ?? 'Cancelled by user request.';
            $refundRequest->save();

            RefundRequestEvent::create([
                'refund_request_id' => $refundRequest->id,
                'actor_id' => $actor->id,
                'action' => 'CANCELLED',
                'from_status' => $fromStatus->value,
                'to_status' => RefundStatus::CANCELLED->value,
                'notes' => sprintf('Refund request %s cancelled: %s', $refundRequest->refund_number, $refundRequest->cancellation_reason),
                'metadata' => [
                    'reason' => $refundRequest->cancellation_reason,
                ],
                'created_at' => $now,
            ]);

            return $refundRequest->fresh(['creditNote', 'customer', 'requester', 'canceller', 'events.actor']);
        });
    }

    /**
     * Transition a refund request to UNDER_REVIEW state.
     */
    public function reviewRefund(RefundRequest|int $refundRequestInput, User $reviewer, array $data = []): RefundRequest
    {
        $refundId = $refundRequestInput instanceof RefundRequest ? $refundRequestInput->id : $refundRequestInput;

        return DB::transaction(function () use ($refundId, $reviewer, $data) {
            /** @var RefundRequest|null $refundRequest */
            $refundRequest = RefundRequest::query()
                ->where('id', $refundId)
                ->lockForUpdate()
                ->first();

            if (! $refundRequest) {
                throw new ConflictHttpException('Refund request not found.');
            }

            if ($refundRequest->status !== RefundStatus::REQUESTED) {
                throw new ConflictHttpException(sprintf(
                    'Refund request #%s is in status [%s] and cannot be moved to review.',
                    $refundRequest->refund_number,
                    $refundRequest->status->value
                ));
            }

            $fromStatus = $refundRequest->status;
            $now = Carbon::now();

            $refundRequest->status = RefundStatus::UNDER_REVIEW;
            $refundRequest->reviewed_by = $reviewer->id;
            $refundRequest->reviewed_at = $now;
            $refundRequest->save();

            RefundRequestEvent::create([
                'refund_request_id' => $refundRequest->id,
                'actor_id' => $reviewer->id,
                'action' => 'UNDER_REVIEW',
                'from_status' => $fromStatus->value,
                'to_status' => RefundStatus::UNDER_REVIEW->value,
                'notes' => sprintf('Refund request %s is now under operational review.', $refundRequest->refund_number),
                'metadata' => [
                    'reviewer_id' => $reviewer->id,
                ],
                'created_at' => $now,
            ]);

            return $refundRequest->fresh(['creditNote', 'customer', 'requester', 'reviewer', 'events.actor']);
        });
    }

    /**
     * Formally approve a refund request with maker-checker enforcement.
     */
    public function approveRefund(RefundRequest|int $refundRequestInput, User $approver, array $data = []): RefundRequest
    {
        $refundId = $refundRequestInput instanceof RefundRequest ? $refundRequestInput->id : $refundRequestInput;

        return DB::transaction(function () use ($refundId, $approver, $data) {
            /** @var RefundRequest|null $refundRequest */
            $refundRequest = RefundRequest::query()
                ->where('id', $refundId)
                ->lockForUpdate()
                ->first();

            if (! $refundRequest) {
                throw new ConflictHttpException('Refund request not found.');
            }

            // Maker-Checker validation: Requester cannot approve their own refund request
            if ((int) $refundRequest->requested_by === (int) $approver->id && $approver->role !== UserRole::SUPER_ADMIN) {
                throw new ConflictHttpException('Maker-Checker violation: You cannot approve a refund request that you created.');
            }

            if (! in_array($refundRequest->status, [RefundStatus::REQUESTED, RefundStatus::UNDER_REVIEW], true)) {
                throw new ConflictHttpException(sprintf(
                    'Refund request #%s is in status [%s] and cannot be approved.',
                    $refundRequest->refund_number,
                    $refundRequest->status->value
                ));
            }

            // Lock Credit Note and verify available credit is still sufficient
            $creditNote = CreditNote::where('id', $refundRequest->credit_note_id)->lockForUpdate()->first();
            if (! $creditNote) {
                throw new ConflictHttpException('Associated credit note not found.');
            }

            $available = (string) $creditNote->remaining_balance;
            if (bccomp((string) $refundRequest->amount, $available, 2) > 0) {
                throw new ConflictHttpException(sprintf(
                    'Insufficient available credit on credit note #%s. Requested: %s, Available: %s.',
                    $creditNote->credit_number,
                    $refundRequest->amount,
                    $available
                ));
            }

            $fromStatus = $refundRequest->status;
            $now = Carbon::now();

            $refundRequest->status = RefundStatus::APPROVED;
            $refundRequest->approved_by = $approver->id;
            $refundRequest->approved_at = $now;
            $refundRequest->save();

            RefundRequestEvent::create([
                'refund_request_id' => $refundRequest->id,
                'actor_id' => $approver->id,
                'action' => 'APPROVED',
                'from_status' => $fromStatus->value,
                'to_status' => RefundStatus::APPROVED->value,
                'notes' => sprintf('Refund request %s approved for disbursement: %s', $refundRequest->refund_number, $data['notes'] ?? 'Approved.'),
                'metadata' => [
                    'approver_id' => $approver->id,
                    'is_super_admin_override' => (int) $refundRequest->requested_by === (int) $approver->id,
                ],
                'created_at' => $now,
            ]);

            return $refundRequest->fresh(['creditNote', 'customer', 'requester', 'approver', 'events.actor']);
        });
    }

    /**
     * Formally reject a refund request with maker-checker enforcement.
     */
    public function rejectRefund(RefundRequest|int $refundRequestInput, User $rejector, array $data): RefundRequest
    {
        $refundId = $refundRequestInput instanceof RefundRequest ? $refundRequestInput->id : $refundRequestInput;

        return DB::transaction(function () use ($refundId, $rejector, $data) {
            /** @var RefundRequest|null $refundRequest */
            $refundRequest = RefundRequest::query()
                ->where('id', $refundId)
                ->lockForUpdate()
                ->first();

            if (! $refundRequest) {
                throw new ConflictHttpException('Refund request not found.');
            }

            if ((int) $refundRequest->requested_by === (int) $rejector->id && $rejector->role !== UserRole::SUPER_ADMIN) {
                throw new ConflictHttpException('Maker-Checker violation: You cannot reject a refund request that you created.');
            }

            if (! in_array($refundRequest->status, [RefundStatus::REQUESTED, RefundStatus::UNDER_REVIEW], true)) {
                throw new ConflictHttpException(sprintf(
                    'Refund request #%s is in status [%s] and cannot be rejected.',
                    $refundRequest->refund_number,
                    $refundRequest->status->value
                ));
            }

            $rejectionReason = trim((string) ($data['reason'] ?? ''));
            if ($rejectionReason === '') {
                throw ValidationException::withMessages([
                    'reason' => 'A valid rejection reason is required.',
                ]);
            }

            $fromStatus = $refundRequest->status;
            $now = Carbon::now();

            $refundRequest->status = RefundStatus::REJECTED;
            $refundRequest->rejected_by = $rejector->id;
            $refundRequest->rejected_at = $now;
            $refundRequest->rejection_reason = $rejectionReason;
            $refundRequest->save();

            RefundRequestEvent::create([
                'refund_request_id' => $refundRequest->id,
                'actor_id' => $rejector->id,
                'action' => 'REJECTED',
                'from_status' => $fromStatus->value,
                'to_status' => RefundStatus::REJECTED->value,
                'notes' => sprintf('Refund request %s rejected: %s', $refundRequest->refund_number, $rejectionReason),
                'metadata' => [
                    'rejection_reason' => $rejectionReason,
                ],
                'created_at' => $now,
            ]);

            return $refundRequest->fresh(['creditNote', 'customer', 'requester', 'rejector', 'events.actor']);
        });
    }

    /**
     * Authoritatively process settlement for an approved refund request.
     * Prevents race conditions and double refunds using deterministic row-locking:
     * Customer -> CreditNote -> RefundRequest.
     *
     * @param RefundRequest|int $refundRequestInput
     * @param User $processor
     * @param array<string, mixed> $data
     * @return RefundTransaction
     *
     * @throws ConflictHttpException
     */
    public function processRefund(RefundRequest|int $refundRequestInput, User $processor, array $data = []): RefundTransaction
    {
        $refundId = $refundRequestInput instanceof RefundRequest ? $refundRequestInput->id : $refundRequestInput;
        $idempotencyKey = ! empty($data['idempotency_key']) ? trim((string) $data['idempotency_key']) : (string) \Illuminate\Support\Str::uuid();

        return DB::transaction(function () use ($refundId, $processor, $data, $idempotencyKey) {
            // Check transaction idempotency if explicit key provided
            if (! empty($data['idempotency_key'])) {
                $existingTxn = RefundTransaction::where('idempotency_key', $idempotencyKey)
                    ->with(['refundRequest', 'creditNote', 'customer', 'processor'])
                    ->first();

                if ($existingTxn) {
                    return $existingTxn;
                }
            }

            /** @var RefundRequest|null $refundRequest */
            $refundRequest = RefundRequest::query()
                ->where('id', $refundId)
                ->lockForUpdate()
                ->first();

            if (! $refundRequest) {
                throw new ConflictHttpException('Refund request not found.');
            }

            // Idempotency: if request is already PROCESSED, return existing transaction
            if ($refundRequest->status === RefundStatus::PROCESSED) {
                $existingTxn = RefundTransaction::where('refund_request_id', $refundRequest->id)->first();
                if ($existingTxn) {
                    return $existingTxn;
                }
            }

            if ($refundRequest->status !== RefundStatus::APPROVED) {
                throw new ConflictHttpException(sprintf(
                    'Refund request #%s is in status [%s]. Only APPROVED refund requests can be processed for settlement.',
                    $refundRequest->refund_number,
                    $refundRequest->status->value
                ));
            }

            // Deterministic row locking: Customer -> CreditNote
            Customer::where('id', $refundRequest->customer_id)->lockForUpdate()->first();

            /** @var CreditNote $creditNote */
            $creditNote = CreditNote::where('id', $refundRequest->credit_note_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Strict double-refund prevention: verify remaining balance is sufficient
            $requestedAmount = (string) $refundRequest->amount;
            $currentRemaining = (string) $creditNote->remaining_balance;

            if (bccomp($requestedAmount, $currentRemaining, 2) > 0) {
                throw new ConflictHttpException(sprintf(
                    'Double-refund prevention violation: Credit note #%s has only %s remaining balance, but refund request requires %s.',
                    $creditNote->credit_number,
                    $currentRemaining,
                    $requestedAmount
                ));
            }

            $now = Carbon::now();
            $txnNumber = $this->txnNumberGenerator->generate();

            // Create immutable RefundTransaction
            /** @var RefundTransaction $transaction */
            $transaction = RefundTransaction::create([
                'transaction_number' => $txnNumber,
                'refund_request_id' => $refundRequest->id,
                'credit_note_id' => $creditNote->id,
                'customer_id' => $creditNote->customer_id,
                'status' => RefundTransactionStatus::COMPLETED,
                'amount' => $requestedAmount,
                'payment_method' => $refundRequest->payment_method,
                'reference_number' => $data['reference_number'] ?? null,
                'processed_by' => $processor->id,
                'processed_at' => $now,
                'idempotency_key' => $idempotencyKey,
            ]);

            // Atomically update CreditNote allocated and remaining balance
            $newAllocated = bcadd((string) $creditNote->allocated_to_refunds, $requestedAmount, 2);
            $newRemaining = bcsub((string) $creditNote->total_amount, $newAllocated, 2);

            $newCreditStatus = bccomp($newRemaining, '0.00', 2) === 0
                ? CreditNoteStatus::FULLY_REFUNDED
                : CreditNoteStatus::PARTIALLY_REFUNDED;

            $creditNote->allocated_to_refunds = $newAllocated;
            $creditNote->remaining_balance = $newRemaining;
            $creditNote->status = $newCreditStatus;
            $creditNote->save();

            // Update RefundRequest status to PROCESSED
            $fromStatus = $refundRequest->status;
            $refundRequest->status = RefundStatus::PROCESSED;
            $refundRequest->save();

            // Append lifecycle audit event
            RefundRequestEvent::create([
                'refund_request_id' => $refundRequest->id,
                'actor_id' => $processor->id,
                'action' => 'PROCESSED',
                'from_status' => $fromStatus->value,
                'to_status' => RefundStatus::PROCESSED->value,
                'notes' => sprintf('Refund disbursed via transaction %s for amount %s.', $transaction->transaction_number, $transaction->amount),
                'metadata' => [
                    'transaction_number' => $transaction->transaction_number,
                    'amount' => (string) $transaction->amount,
                    'remaining_credit_balance' => $newRemaining,
                ],
                'created_at' => $now,
            ]);

            return $transaction->fresh(['refundRequest', 'creditNote', 'customer', 'processor']);
        });
    }
}
