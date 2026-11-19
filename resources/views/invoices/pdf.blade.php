@php
    use App\Support\Money;

    /** @var \App\Invoices\Invoice $invoice */
    $listing = $invoice->listing;
    $buyer = $invoice->buyer;
    $c = config('invoice');
    $address = array_filter(array_map('trim', explode('|', (string) $c['company_address'])));
    $hasBank = $c['bank_name'] || $c['bank_account_number'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->number() }}</title>
    <style>
        @page { margin: 36px 42px; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 10.5px; color: #27272a; line-height: 1.45; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; padding: 0; }
        .muted { color: #71717a; }
        .brand { font-size: 22px; font-weight: bold; color: #18181b; }
        .title { font-size: 26px; font-weight: bold; color: #ea580c; text-align: right; letter-spacing: 1px; }
        .meta td { padding: 2px 0; }
        .meta td.label { color: #71717a; padding-right: 12px; text-align: right; }
        .section-label { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px; color: #ea580c; margin-bottom: 4px; }
        .items { margin-top: 26px; }
        .items th { background: #fff7ed; color: #9a3412; font-size: 9px; text-transform: uppercase; letter-spacing: 0.6px; text-align: left; padding: 8px 10px; border-bottom: 1px solid #fed7aa; }
        .items td { padding: 10px; border-bottom: 1px solid #e4e4e7; }
        .items th.num { text-align: right; }
        .bank td.muted { width: 130px; }
        .num { text-align: right; white-space: nowrap; }
        .totals { margin-top: 10px; }
        .totals td { padding: 5px 10px; }
        .totals .grand td { font-size: 13px; font-weight: bold; color: #18181b; border-top: 2px solid #18181b; padding-top: 8px; }
        .box { border: 1px solid #e4e4e7; border-radius: 6px; padding: 12px 14px; }
        .footer { margin-top: 28px; padding-top: 10px; border-top: 1px solid #e4e4e7; color: #71717a; font-size: 9.5px; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td style="width: 55%;">
                <div class="brand">{{ $c['company_name'] }}</div>
                <div class="muted" style="margin-top: 8px;">
                    @foreach ($address as $line)
                        {{ $line }}<br>
                    @endforeach
                    @if ($c['company_phone']) Tel: {{ $c['company_phone'] }}<br> @endif
                    @if ($c['company_email']) {{ $c['company_email'] }}<br> @endif
                    @if ($c['company_registration']) Reg. no: {{ $c['company_registration'] }} @endif
                </div>
            </td>
            <td style="width: 45%;">
                <div class="title">INVOICE</div>
                <table class="meta" style="margin-top: 8px;">
                    <tr><td class="label">Invoice no.</td><td class="num"><strong>{{ $invoice->number() }}</strong></td></tr>
                    <tr><td class="label">Invoice date</td><td class="num">{{ $invoice->date()->format('j M Y') }}</td></tr>
                    <tr><td class="label">Due date</td><td class="num">{{ $invoice->dueDate()->format('j M Y') }}</td></tr>
                    <tr><td class="label">Payment reference</td><td class="num">{{ $invoice->number() }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table style="margin-top: 26px;">
        <tr>
            <td style="width: 55%;">
                <div class="section-label">Bill to</div>
                <strong>{{ $buyer->name }}</strong><br>
                {{ $buyer->email }}<br>
                <span class="muted">Bidder {{ $buyer->bidder_number }}</span>
            </td>
            <td style="width: 45%;">
                <div class="section-label">Auction</div>
                Listing #{{ $listing->id }}<br>
                <span class="muted">Ended {{ $listing->ends_at->format('j M Y H:i') }}</span>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                <th class="num" style="width: 70px;">Qty</th>
                <th class="num" style="width: 120px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>{{ $listing->title }}</strong><br>
                    <span class="muted">Sold as one lot of {{ $listing->quantity }} {{ Str::plural('item', $listing->quantity) }} (winning bid).</span>
                    @if ($listing->description)
                        <br><span class="muted">{{ $listing->description }}</span>
                    @endif
                </td>
                <td class="num">1 lot</td>
                <td class="num">{{ Money::format($invoice->amount) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td style="width: 55%;"></td>
            <td class="muted">Subtotal</td>
            <td class="num">{{ Money::format($invoice->amount) }}</td>
        </tr>
        <tr>
            <td></td>
            <td class="muted">VAT</td>
            <td class="num muted">Not charged</td>
        </tr>
        <tr class="grand">
            <td style="border: 0;"></td>
            <td>Total due</td>
            <td class="num">{{ Money::format($invoice->amount) }}</td>
        </tr>
    </table>

    @if ($hasBank)
        <div class="box" style="margin-top: 26px;">
            <div class="section-label">Banking details (EFT)</div>
            <table class="meta bank">
                @if ($c['bank_account_name'])<tr><td class="muted">Account name</td><td>{{ $c['bank_account_name'] }}</td></tr>@endif
                @if ($c['bank_name'])<tr><td class="muted">Bank</td><td>{{ $c['bank_name'] }}</td></tr>@endif
                @if ($c['bank_account_number'])<tr><td class="muted">Account number</td><td>{{ $c['bank_account_number'] }}</td></tr>@endif
                @if ($c['bank_branch_code'])<tr><td class="muted">Branch code</td><td>{{ $c['bank_branch_code'] }}</td></tr>@endif
                <tr><td class="muted">Reference</td><td><strong>{{ $invoice->number() }}</strong></td></tr>
            </table>
        </div>
    @endif

    <div class="footer">
        @if ($c['footer_note']){{ $c['footer_note'] }}<br>@endif
        Payment is due by {{ $invoice->dueDate()->format('j F Y') }}. All amounts are in South African Rand (ZAR).
    </div>
</body>
</html>
