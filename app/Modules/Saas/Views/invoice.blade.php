{{-- Printable invoice (SaaS console and the tenant's Billing page). Browser "Print → Save as PDF" for a PDF. --}}
@php
    $seller = \App\Core\Tenancy\Tenancy::central(fn () => [
        'name' => setting('billing_company') ?: setting('site_name', config('app.name')),
        'gstin' => setting('billing_gstin'),
        'address' => setting('billing_address'),
        'email' => setting('contact_email'),
        'tax' => setting('billing_tax_label', 'GST'),
    ]);
    $buyer = $invoice->billing ?? [];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $invoice->number }}</title>
    <style>
        body { font: 14px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #0f172a; background: #f1f5f9; margin: 0; }
        .bar { max-width: 820px; margin: 24px auto 0; display: flex; gap: 8px; justify-content: flex-end; }
        .bar a, .bar button { font: inherit; padding: 8px 14px; border-radius: 8px; border: 1px solid #cbd5e1; background: #fff; color: #0f172a; text-decoration: none; cursor: pointer; }
        .bar button { background: #2563eb; border-color: #2563eb; color: #fff; }
        .paper { max-width: 820px; margin: 12px auto 40px; background: #fff; padding: 48px; border-radius: 12px; box-shadow: 0 1px 3px rgba(15, 23, 42, .08); }
        .row { display: flex; justify-content: space-between; gap: 24px; }
        h1 { font-size: 28px; margin: 0; letter-spacing: -.02em; }
        .muted { color: #64748b; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 99px; font-size: 12px; font-weight: 600; }
        .paid { background: #dcfce7; color: #166534; } .open { background: #fef3c7; color: #92400e; } .void { background: #e2e8f0; color: #475569; }
        table { width: 100%; border-collapse: collapse; margin-top: 32px; }
        th { text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #64748b; border-bottom: 2px solid #e2e8f0; padding: 8px 0; }
        td { padding: 12px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        .right { text-align: right; }
        .totals { width: 320px; margin-left: auto; margin-top: 16px; }
        .totals div { display: flex; justify-content: space-between; padding: 6px 0; }
        .totals .grand { font-size: 18px; font-weight: 700; border-top: 2px solid #0f172a; margin-top: 6px; padding-top: 10px; }
        @media print { body { background: #fff; } .bar { display: none; } .paper { box-shadow: none; margin: 0; padding: 0; } }
    </style>
</head>
<body>
<div class="bar">
    <a href="{{ $back }}">← Back</a>
    <button type="button" id="print">Print / Save as PDF</button>
</div>
<div class="paper">
    <div class="row">
        <div>
            <h1>{{ $invoice->status === 'paid' ? 'Tax invoice' : 'Invoice' }}</h1>
            <div class="muted">{{ $invoice->number }}</div>
            <div style="margin-top: 8px"><span class="badge {{ $invoice->status }}">{{ \App\Modules\Saas\Models\Invoice::STATUSES[$invoice->status] ?? $invoice->status }}</span></div>
        </div>
        <div class="right">
            <strong>{{ $seller['name'] }}</strong><br>
            @if ($seller['address'])<span class="muted" style="white-space: pre-line">{{ $seller['address'] }}</span><br>@endif
            @if ($seller['gstin'])<span class="muted">GSTIN: {{ $seller['gstin'] }}</span><br>@endif
            @if ($seller['email'])<span class="muted">{{ $seller['email'] }}</span>@endif
        </div>
    </div>

    <div class="row" style="margin-top: 40px">
        <div>
            <div class="muted" style="font-size: 12px; text-transform: uppercase">Billed to</div>
            <strong>{{ $buyer['name'] ?? $invoice->tenant?->name }}</strong><br>
            <span class="muted">{{ $buyer['email'] ?? $invoice->tenant?->email }}</span><br>
            @if (! empty($buyer['address']))<span class="muted" style="white-space: pre-line">{{ $buyer['address'] }}</span><br>@endif
            @if (! empty($buyer['gstin']))<span class="muted">GSTIN: {{ $buyer['gstin'] }}</span>@endif
        </div>
        <div class="right">
            <div><span class="muted">Date:</span> {{ $invoice->created_at?->format('d M Y') }}</div>
            @if ($invoice->paid_at)<div><span class="muted">Paid:</span> {{ $invoice->paid_at->format('d M Y') }}</div>@endif
            @if ($invoice->due_at && $invoice->status === 'open')<div><span class="muted">Due:</span> {{ $invoice->due_at->format('d M Y') }}</div>@endif
            @if ($invoice->gateway)<div><span class="muted">Payment:</span> {{ ucfirst($invoice->gateway) }} {{ $invoice->gateway_payment_id }}</div>@endif
        </div>
    </div>

    <table>
        <thead><tr><th>Description</th><th class="right">Amount</th></tr></thead>
        <tbody><tr><td>{{ $invoice->description }}</td><td class="right">{{ $invoice->money('amount') }}</td></tr></tbody>
    </table>

    <div class="totals">
        <div><span class="muted">Subtotal</span><span>{{ $invoice->money('amount') }}</span></div>
        @if ((float) $invoice->tax_rate > 0)
            <div><span class="muted">{{ $seller['tax'] }} {{ rtrim(rtrim((string) $invoice->tax_rate, '0'), '.') }}%</span><span>{{ $invoice->money('tax') }}</span></div>
        @endif
        <div class="grand"><span>Total</span><span>{{ $invoice->money() }}</span></div>
    </div>

    <p class="muted" style="margin-top: 48px; font-size: 12px">Thank you for your business. This is a computer-generated invoice and needs no signature.</p>
</div>
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">document.getElementById('print').addEventListener('click', () => window.print());</script>
</body>
</html>
