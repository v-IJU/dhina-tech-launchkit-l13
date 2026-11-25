<?php

declare(strict_types=1);

namespace App\Modules\Saas\Controllers\Admin;

use App\Core\DataTable\DataTable;
use App\Http\Controllers\Controller;
use App\Modules\Saas\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * SaaS > Invoices: every bill to every tenant. The invoice page prints as a PDF (browser "Save as PDF").
 */
class InvoiceController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:saas.view', only: ['index', 'show']),
            new Middleware('permission:saas.edit', only: ['markPaid', 'void']),
        ];
    }

    public function index(Request $request): Response
    {
        return DataTable::of(Invoice::query()->with('tenant'), $request)
            ->search(fn ($query, string $term) => $query->where('number', 'like', "%{$term}%")->orWhere('gateway_payment_id', 'like', "%{$term}%")->orWhereHas('tenant', fn ($t) => $t->search($term)))
            ->sortable(['created_at', 'paid_at', 'total', 'number'], default: 'created_at')
            ->status('status', Invoice::STATUSES)
            ->filter('gateway', 'Gateway', ['razorpay' => 'Razorpay', 'stripe' => 'Stripe', 'manual' => 'Manual'])
            ->dateRange('created_at', 'Date')
            ->export('invoices', [
                'Number' => 'number', 'Date' => 'created_at', 'Tenant' => 'tenant.name', 'GSTIN' => 'billing.gstin', 'Description' => 'description',
                'Amount' => fn (Invoice $i) => $i->amount / 100, 'Tax' => fn (Invoice $i) => $i->tax / 100, 'Total' => fn (Invoice $i) => $i->total / 100,
                'Currency' => 'currency', 'Status' => 'status', 'Gateway' => 'gateway', 'Payment ID' => 'gateway_payment_id', 'Paid at' => 'paid_at',
            ], 'saas.export')
            ->render('saas::admin.invoices.index', 'saas::admin.invoices._table');
    }

    public function show(Invoice $invoice): View
    {
        return view('saas::invoice', ['invoice' => $invoice->load(['tenant', 'plan']), 'back' => route('admin.saas.invoices.index')]);
    }

    public function markPaid(Request $request, Invoice $invoice): RedirectResponse
    {
        $reference = $request->validate(['reference' => ['nullable', 'string', 'max:100']])['reference'] ?? null;
        $invoice->forceFill(['status' => 'paid', 'paid_at' => now(), 'gateway' => $invoice->gateway ?? 'manual', 'gateway_payment_id' => $invoice->gateway_payment_id ?? $reference])->save();

        return back()->with('success', "Invoice {$invoice->number} marked as paid.");
    }

    public function void(Invoice $invoice): RedirectResponse
    {
        abort_if($invoice->status === 'paid' && $invoice->gateway !== 'manual', 422, 'Refund gateway payments in the gateway dashboard first.');
        $invoice->forceFill(['status' => 'void'])->save();

        return back()->with('success', "Invoice {$invoice->number} voided.");
    }
}
