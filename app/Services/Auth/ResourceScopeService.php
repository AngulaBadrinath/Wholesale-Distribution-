<?php

namespace App\Services\Auth;

use App\Enums\AccountStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\InventoryBalance;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ReturnItem;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ResourceScopeService
{
    public function __construct(
        protected PermissionService $permissionService,
    ) {}

    /**
     * Determine whether the authenticated user has access to a specific customer record.
     */
    public function canAccessCustomer(User $user, Customer|int $customer): bool
    {
        if (! $this->isUserActive($user)) {
            return false;
        }

        $customerId = $customer instanceof Customer ? $customer->id : (int) $customer;
        $salesmanId = $customer instanceof Customer ? $customer->salesman_id : Customer::where('id', $customerId)->value('salesman_id');

        if ($user->role === UserRole::SALESMAN) {
            return (int) $salesmanId === (int) $user->id;
        }

        return $this->permissionService->has($user, Permission::CUSTOMER_VIEW);
    }

    /**
     * Determine whether the authenticated user has access to a specific order record.
     */
    public function canAccessOrder(User $user, Order|int $order): bool
    {
        if (! $this->isUserActive($user)) {
            return false;
        }

        if ($order instanceof Order) {
            $orderSalesmanId = $order->salesman_id;
        } else {
            $orderSalesmanId = Order::where('id', (int) $order)->value('salesman_id');
        }

        if ($user->role === UserRole::SALESMAN) {
            return (int) $orderSalesmanId === (int) $user->id;
        }

        return $this->permissionService->has($user, Permission::ORDER_VIEW);
    }

    /**
     * Determine whether the authenticated user has access to a specific delivery mission.
     */
    public function canAccessDelivery(User $user, Delivery|int $delivery): bool
    {
        if (! $this->isUserActive($user)) {
            return false;
        }

        $driverId = $delivery instanceof Delivery ? $delivery->driver_id : Delivery::where('id', (int) $delivery)->value('driver_id');

        if ($user->role === UserRole::DELIVERY_PARTNER) {
            return (int) $driverId === (int) $user->id;
        }

        return $this->permissionService->has($user, Permission::DELIVERY_VIEW)
            || $this->permissionService->has($user, Permission::DELIVERY_UPDATE);
    }

    /**
     * Determine whether the authenticated user has access to a specific return request.
     */
    public function canAccessReturn(User $user, ReturnRequest|int $returnRequest): bool
    {
        if (! $this->isUserActive($user)) {
            return false;
        }

        if ($user->role === UserRole::SALESMAN) {
            if ($returnRequest instanceof ReturnRequest) {
                if ((int) $returnRequest->created_by === (int) $user->id) {
                    return true;
                }
                $customerSalesmanId = $returnRequest->customer?->salesman_id ?? Customer::where('id', $returnRequest->customer_id)->value('salesman_id');

                return (int) $customerSalesmanId === (int) $user->id;
            }

            return ReturnRequest::query()
                ->where('id', (int) $returnRequest)
                ->where(function (Builder $q) use ($user) {
                    $q->where('created_by', $user->id)
                        ->orWhereHas('customer', fn ($cq) => $cq->where('salesman_id', $user->id))
                        ->orWhereHas('order', fn ($oq) => $oq->where('salesman_id', $user->id));
                })
                ->exists();
        }

        return $this->permissionService->has($user, Permission::RETURN_REVIEW)
            || $this->permissionService->has($user, Permission::RETURN_REQUEST)
            || $this->permissionService->has($user, Permission::RETURN_APPROVE);
    }

    /**
     * Determine whether the authenticated user has access to a specific payment record.
     */
    public function canAccessPayment(User $user, Payment|int $payment): bool
    {
        if (! $this->isUserActive($user)) {
            return false;
        }

        if ($user->role === UserRole::SALESMAN) {
            if ($payment instanceof Payment) {
                if ((int) $payment->recorded_by === (int) $user->id) {
                    return true;
                }
                $customerSalesmanId = $payment->customer?->salesman_id ?? Customer::where('id', $payment->customer_id)->value('salesman_id');

                return (int) $customerSalesmanId === (int) $user->id;
            }

            return Payment::query()
                ->where('id', (int) $payment)
                ->where(function (Builder $q) use ($user) {
                    $q->where('recorded_by', $user->id)
                        ->orWhereHas('customer', fn ($cq) => $cq->where('salesman_id', $user->id))
                        ->orWhereHas('order', fn ($oq) => $oq->where('salesman_id', $user->id));
                })
                ->exists();
        }

        return $this->permissionService->has($user, Permission::PAYMENT_VIEW);
    }

    /**
     * Determine whether the authenticated user has access to a specific invoice.
     */
    public function canAccessInvoice(User $user, Invoice|int $invoice): bool
    {
        if (! $this->isUserActive($user)) {
            return false;
        }

        if ($user->role === UserRole::SALESMAN) {
            if ($invoice instanceof Invoice) {
                $customerSalesmanId = $invoice->customer?->salesman_id ?? Customer::where('id', $invoice->customer_id)->value('salesman_id');

                return (int) $customerSalesmanId === (int) $user->id;
            }

            return Invoice::query()
                ->where('id', (int) $invoice)
                ->whereHas('customer', fn ($cq) => $cq->where('salesman_id', $user->id))
                ->exists();
        }

        return $this->permissionService->has($user, Permission::INVOICE_VIEW);
    }

    /**
     * Determine whether the authenticated user has access to a specific inventory balance.
     */
    public function canAccessInventoryBalance(User $user, InventoryBalance|int $balance): bool
    {
        if (! $this->isUserActive($user)) {
            return false;
        }

        return $this->permissionService->has($user, Permission::INVENTORY_VIEW);
    }

    /**
     * Determine whether the authenticated user has access to an order adjustment.
     */
    public function canAccessAdjustment(User $user, OrderAdjustment|int $adjustment): bool
    {
        if (! $this->isUserActive($user)) {
            return false;
        }

        if ($user->role === UserRole::SALESMAN) {
            if ($adjustment instanceof OrderAdjustment) {
                if ((int) $adjustment->requested_by === (int) $user->id) {
                    return true;
                }
                $orderSalesmanId = $adjustment->order?->salesman_id ?? Order::where('id', $adjustment->order_id)->value('salesman_id');

                return (int) $orderSalesmanId === (int) $user->id;
            }

            return OrderAdjustment::query()
                ->where('id', (int) $adjustment)
                ->where(function (Builder $q) use ($user) {
                    $q->where('requested_by', $user->id)
                        ->orWhereHas('order', fn ($oq) => $oq->where('salesman_id', $user->id));
                })
                ->exists();
        }

        return $this->permissionService->has($user, Permission::ORDER_ADJUST_REVIEW)
            || $this->permissionService->has($user, Permission::ORDER_ADJUST_REQUEST);
    }

    /**
     * Query scoping helper: apply authoritative customer scope.
     */
    public function scopeCustomers(Builder $query, User $user): Builder
    {
        if ($user->role === UserRole::SALESMAN) {
            return $query->where('salesman_id', $user->id);
        }

        return $query;
    }

    /**
     * Query scoping helper: apply authoritative order scope.
     */
    public function scopeOrders(Builder $query, User $user): Builder
    {
        if ($user->role === UserRole::SALESMAN) {
            return $query->where('salesman_id', $user->id);
        }

        return $query;
    }

    /**
     * Query scoping helper: apply authoritative delivery scope.
     */
    public function scopeDeliveries(Builder $query, User $user): Builder
    {
        if ($user->role === UserRole::DELIVERY_PARTNER) {
            return $query->where('driver_id', $user->id);
        }

        return $query;
    }

    /**
     * Query scoping helper: apply authoritative return request scope.
     */
    public function scopeReturns(Builder $query, User $user): Builder
    {
        if ($user->role === UserRole::SALESMAN) {
            return $query->where(function (Builder $q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhereHas('customer', fn ($cq) => $cq->where('salesman_id', $user->id))
                    ->orWhereHas('order', fn ($oq) => $oq->where('salesman_id', $user->id));
            });
        }

        return $query;
    }

    /**
     * Query scoping helper: apply authoritative payment scope.
     */
    public function scopePayments(Builder $query, User $user): Builder
    {
        if ($user->role === UserRole::SALESMAN) {
            return $query->where(function (Builder $q) use ($user) {
                $q->where('recorded_by', $user->id)
                    ->orWhereHas('customer', fn ($cq) => $cq->where('salesman_id', $user->id))
                    ->orWhereHas('order', fn ($oq) => $oq->where('salesman_id', $user->id));
            });
        }

        return $query;
    }

    /**
     * Query scoping helper: apply authoritative invoice scope.
     */
    public function scopeInvoices(Builder $query, User $user): Builder
    {
        if ($user->role === UserRole::SALESMAN) {
            return $query->whereHas('customer', fn ($cq) => $cq->where('salesman_id', $user->id));
        }

        return $query;
    }

    /**
     * Query scoping helper: apply authoritative inventory balance scope.
     */
    public function scopeInventoryBalances(Builder $query, User $user): Builder
    {
        return $query;
    }

    /**
     * Nested resource verification: Order -> OrderAdjustment.
     */
    public function verifyOrderAdjustmentOwnership(OrderAdjustment $adjustment, Order $order): bool
    {
        return (int) $adjustment->order_id === (int) $order->id;
    }

    /**
     * Nested resource verification: Order -> OrderItem.
     */
    public function verifyOrderItemOwnership(OrderItem $item, Order $order): bool
    {
        return (int) $item->order_id === (int) $order->id;
    }

    /**
     * Nested resource verification: Delivery -> DeliveryItem.
     */
    public function verifyDeliveryItemOwnership(DeliveryItem $item, Delivery $delivery): bool
    {
        return (int) $item->delivery_id === (int) $delivery->id;
    }

    /**
     * Nested resource verification: ReturnRequest -> ReturnItem.
     */
    public function verifyReturnItemOwnership(ReturnItem $item, ReturnRequest $returnRequest): bool
    {
        return (int) $item->return_request_id === (int) $returnRequest->id;
    }

    /**
     * Nested resource verification: Invoice -> InvoiceItem.
     */
    public function verifyInvoiceItemOwnership(InvoiceItem $item, Invoice $invoice): bool
    {
        return (int) $item->invoice_id === (int) $invoice->id;
    }

    /**
     * Check whether a user is active.
     */
    protected function isUserActive(User $user): bool
    {
        if ($user->status instanceof AccountStatus) {
            return $user->status === AccountStatus::ACTIVE;
        }

        return $user->status === AccountStatus::ACTIVE->value;
    }
}
