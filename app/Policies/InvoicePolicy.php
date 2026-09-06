<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Auth\ResourceScopeService;

class InvoicePolicy
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ResourceScopeService $resourceScopeService,
    ) {}

    /**
     * Determine whether the user can view any invoices.
     */
    public function viewAny(User $user): bool
    {
        return $this->permissionService->has($user, Permission::INVOICE_VIEW);
    }

    /**
     * Determine whether the user can view the specific invoice.
     * Enforces resource scoping for salesmen.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        if (! $this->permissionService->has($user, Permission::INVOICE_VIEW)) {
            return false;
        }

        return $this->resourceScopeService->canAccessInvoice($user, $invoice);
    }

    /**
     * Determine whether the user can print the specific invoice.
     */
    public function print(User $user, Invoice $invoice): bool
    {
        if (! $this->permissionService->has($user, Permission::INVOICE_PRINT)) {
            return false;
        }

        return $this->resourceScopeService->canAccessInvoice($user, $invoice);
    }

    /**
     * Determine whether the user can download the PDF for the specific invoice.
     */
    public function download(User $user, Invoice $invoice): bool
    {
        if (! $this->permissionService->has($user, Permission::INVOICE_DOWNLOAD)) {
            return false;
        }

        return $this->resourceScopeService->canAccessInvoice($user, $invoice);
    }

    /**
     * Determine whether the user can generate an invoice for an order.
     */
    public function generate(User $user): bool
    {
        return $this->permissionService->has($user, Permission::ORDER_APPROVE);
    }
}
