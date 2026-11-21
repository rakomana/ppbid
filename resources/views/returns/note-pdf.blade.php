@php
    use App\Invoices\Invoice;

    /** @var \App\Models\ReturnRequest $return */
    $listing = $return->listing;
    $buyer = $return->user;
    $c = config('invoice');
    $address = array_filter(array_map('trim', explode('|', (string) $c['company_address'])));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Replacement note {{ $return->noteNumber() }}</title>
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
                <div class="title">REPLACEMENT NOTE</div>
                <table class="meta" style="margin-top: 8px;">
                    <tr><td class="label">Note no.</td><td class="num"><strong>{{ $return->noteNumber() }}</strong></td></tr>
                    <tr><td class="label">Return no.</td><td class="num">{{ $return->number() }}</td></tr>
                    <tr><td class="label">Replaced on</td><td class="num">{{ $return->replaced_at->format('j M Y') }}</td></tr>
                    <tr><td class="label">Original invoice</td><td class="num">{{ Invoice::numberFor($listing->id) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table style="margin-top: 26px;">
        <tr>
            <td style="width: 55%;">
                <div class="section-label">Buyer</div>
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
                <th class="num" style="width: 90px;">Logged</th>
                <th class="num" style="width: 90px;">Replaced</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>{{ $listing->title }}</strong><br>
                    <span class="muted">From a lot of {{ $listing->quantity }} {{ Str::plural('item', $listing->quantity) }}.</span><br>
                    <span class="muted">Reason: {{ $return->reasonLabel() }}@if ($return->details) — {{ $return->details }}@endif</span>
                </td>
                <td class="num">{{ $return->quantity_requested }}</td>
                <td class="num"><strong>{{ $return->units() }}</strong></td>
            </tr>
        </tbody>
    </table>

    @if ($return->admin_note)
        <div class="box" style="margin-top: 26px;">
            <div class="section-label">Note</div>
            {{ $return->admin_note }}
        </div>
    @endif

    <div class="footer">
        Faulty units were exchanged for working units. No refund or credit applies, and invoice {{ Invoice::numberFor($listing->id) }} is unchanged.
    </div>
</body>
</html>
