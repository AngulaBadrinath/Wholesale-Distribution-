<?php

namespace App\Http\Controllers\Salesman;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SalesmanInvoiceController extends Controller
{
    /**
     * Display a listing of invoices scoped to the authenticated salesman's customer portfolio.
     */
    public function index(Request $request): Response
    {
        $status = $request->query('status');
        $paymentStatus = $request->query('payment_status');
        $search = $request->query('search');
        $perPage = (int) $request->query('per_page', 25);

        $query = Invoice::query()
            ->with(['customer', 'order'])
            ->forUser($request->user())
            ->orderBy('id', 'desc');

        if ($status && in_array($status, InvoiceStatus::values(), true)) {
            $query->where('status', $status);
        }

        if ($paymentStatus && in_array($paymentStatus, PaymentStatus::values(), true)) {
            $query->where('payment_status', $paymentStatus);
        }

        if ($search && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('invoice_number', 'ILIKE', $term)
                    ->orWhere('customer_name_snapshot', 'ILIKE', $term)
                    ->orWhere('customer_code_snapshot', 'ILIKE', $term)
                    ->orWhereHas('order', fn ($oq) => $oq->where('order_number', 'ILIKE', $term));
            });
        }

        $invoices = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Admin/Invoices/Index', [
            'invoices' => $invoices,
            'filters' => [
                'status' => $status,
                'payment_status' => $paymentStatus,
                'search' => $search,
                'per_page' => $perPage,
            ],
            'statuses' => InvoiceStatus::values(),
            'paymentStatuses' => PaymentStatus::values(),
            'isSalesmanView' => true,
        ]);
    }

    /**
     * Display the specified invoice detail view for assigned customers.
     * Enforces fail-closed Anti-IDOR (404 on unassigned).
     */
    public function show(int $id, Request $request): Response
    {
        /** @var Invoice|null $invoice */
        $invoice = Invoice::query()
            ->with(['items.product', 'order.payments', 'customer', 'creator'])
            ->forUser($request->user())
            ->find($id);

        if (! $invoice) {
            throw new NotFoundHttpException('Invoice not found.');
        }

        return Inertia::render('Admin/Invoices/Show', [
            'invoice' => $invoice,
            'isSalesmanView' => true,
        ]);
    }
}
