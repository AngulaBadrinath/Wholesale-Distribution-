<?php

declare(strict_types=1);

namespace App\Services\Credit;

use App\Enums\CreditNoteStatus;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestEvent;
use App\Models\User;
use App\Services\System\CompanyInformationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditNoteService
{
    public function __construct(
        protected CreditEligibilityService $eligibilityService,
        protected CreditNoteNumberGenerator $numberGenerator,
        protected CompanyInformationService $companyInformationService
    ) {}

    /**
     * Generate an authoritative, immutable Credit Note from an approved Return Request.
     *
     * @param ReturnRequest|int $returnRequestInput
     * @param User $issuer
     * @param array<string, mixed> $options
     * @return CreditNote
     *
     * @throws ValidationException
     */
    public function generateCreditNote(ReturnRequest|int $returnRequestInput, User $issuer, array $options = []): CreditNote
    {
        $returnId = $returnRequestInput instanceof ReturnRequest ? $returnRequestInput->id : $returnRequestInput;
        $idempotencyKey = ! empty($options['idempotency_key']) ? trim((string) $options['idempotency_key']) : (string) \Illuminate\Support\Str::uuid();

        return DB::transaction(function () use ($returnId, $issuer, $options, $idempotencyKey) {
            // Idempotency check by key if supplied
            if ($idempotencyKey !== null && $idempotencyKey !== '') {
                $existing = CreditNote::where('idempotency_key', $idempotencyKey)
                    ->with(['items', 'customer', 'order', 'returnRequest', 'issuer'])
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            /** @var ReturnRequest|null $returnRequest */
            $returnRequest = ReturnRequest::query()
                ->where('id', $returnId)
                ->with(['customer', 'order.invoice', 'items.orderItem'])
                ->lockForUpdate()
                ->first();

            if (! $returnRequest) {
                throw ValidationException::withMessages([
                    'return_request' => 'The specified return request does not exist.',
                ]);
            }

            // If already processed for credit, return existing credit note
            if ($returnRequest->is_credit_processed && $returnRequest->credit_note_id) {
                $existing = CreditNote::with(['items', 'customer', 'order', 'returnRequest', 'issuer'])
                    ->find($returnRequest->credit_note_id);

                if ($existing) {
                    return $existing;
                }
            }

            // Calculate eligibility authoritatively using domain rules and snapshots
            $eligibility = $this->eligibilityService->calculateReturnEligibility($returnRequest);

            $customer = $returnRequest->customer;
            if (! $customer) {
                throw ValidationException::withMessages([
                    'customer' => 'Return request must have an associated customer.',
                ]);
            }

            // Snapshot company profile
            $company = $this->companyInformationService->get();
            $companyAddress = trim(sprintf(
                '%s%s, %s, %s %s, %s',
                $company->address_line1,
                $company->address_line2 ? ' '.$company->address_line2 : '',
                $company->city,
                $company->state,
                $company->postal_code,
                $company->country
            ));

            // Generate canonical credit note number
            $creditNumber = $this->numberGenerator->generate();
            $now = Carbon::now();

            // Find associated invoice if exists on order
            $invoiceId = $returnRequest->order?->invoice?->id;

            /** @var CreditNote $creditNote */
            $creditNote = CreditNote::create([
                'credit_number' => $creditNumber,
                'customer_id' => $customer->id,
                'order_id' => $returnRequest->order_id,
                'invoice_id' => $invoiceId,
                'return_request_id' => $returnRequest->id,
                'status' => CreditNoteStatus::ISSUED,
                'currency' => $eligibility['currency'],
                'subtotal' => $eligibility['eligible_subtotal'],
                'tax_total' => $eligibility['eligible_tax'],
                'total_amount' => $eligibility['eligible_total'],
                'allocated_to_refunds' => '0.00',
                'remaining_balance' => $eligibility['eligible_total'],
                'reason' => $options['reason'] ?? $returnRequest->notes ?? 'Customer Return Credit',
                'issued_by' => $issuer->id,
                'issued_at' => $now,
                'customer_name_snapshot' => $customer->name,
                'customer_code_snapshot' => $customer->code ?? $customer->customer_code,
                'customer_contact_snapshot' => $customer->contact_name ?? $customer->contact_person,
                'customer_email_snapshot' => $customer->email,
                'customer_phone_snapshot' => $customer->phone,
                'billing_address_line1_snapshot' => $customer->billing_address_line1,
                'billing_city_snapshot' => $customer->billing_city,
                'billing_state_snapshot' => $customer->billing_state,
                'billing_postal_code_snapshot' => $customer->billing_postal_code,
                'billing_country_snapshot' => $customer->billing_country,
                'company_legal_name_snapshot' => $company->company_name ?? $company->name ?? 'Wholesale Distribution Corp',
                'company_address_snapshot' => $companyAddress,
                'company_tax_id_snapshot' => $company->tax_id,
                'idempotency_key' => $idempotencyKey,
            ]);

            // Create immutable items
            foreach ($eligibility['items'] as $itemData) {
                if ($itemData['eligible_quantity'] <= 0) {
                    continue;
                }

                CreditNoteItem::create([
                    'credit_note_id' => $creditNote->id,
                    'order_item_id' => $itemData['order_item_id'],
                    'return_request_item_id' => $itemData['return_request_item_id'],
                    'product_id' => $itemData['product_id'],
                    'product_name_snapshot' => $itemData['product_name_snapshot'],
                    'sku_snapshot' => $itemData['sku_snapshot'],
                    'quantity' => $itemData['eligible_quantity'],
                    'unit_price_snapshot' => $itemData['unit_price_snapshot'],
                    'tax_rate_snapshot' => $itemData['tax_rate_snapshot'],
                    'tax_amount_snapshot' => $itemData['tax_amount_snapshot'],
                    'line_subtotal' => $itemData['line_subtotal'],
                    'line_total' => $itemData['line_total'],
                ]);
            }

            // Update return request linkage
            $returnRequest->is_credit_processed = true;
            $returnRequest->credit_note_id = $creditNote->id;
            $returnRequest->save();

            // Record return lifecycle event
            ReturnRequestEvent::create([
                'return_request_id' => $returnRequest->id,
                'actor_id' => $issuer->id,
                'event_type' => 'CREDIT_NOTE_ISSUED',
                'payload' => [
                    'credit_note_id' => $creditNote->id,
                    'credit_number' => $creditNote->credit_number,
                    'total_amount' => $creditNote->total_amount,
                    'currency' => $creditNote->currency,
                ],
                'created_at' => Carbon::now(),
            ]);

            return $creditNote->fresh(['items', 'customer', 'order', 'returnRequest', 'issuer']);
        });
    }
}
