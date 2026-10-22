<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Quotation {{ $proposal->proposal_no }} – {{ $setting->company_name ?? 'Hearing Care' }}</title>
    <style>
        :root { --ink: #0f172a; --muted: #64748b; --line: #e2e8f0; --accent: #2563eb; --ok: #059669; --bad: #dc2626; --bg: #f8fafc; --card: #ffffff; }
        @media (prefers-color-scheme: dark) { :root { --ink: #e2e8f0; --muted: #94a3b8; --line: #334155; --accent: #60a5fa; --ok: #34d399; --bad: #f87171; --bg: #0f172a; --card: #1e293b; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--ink); font: 15px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .wrap { max-width: 720px; margin: 0 auto; padding: 24px 16px 48px; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 20px; margin-bottom: 16px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .muted { color: var(--muted); font-size: 13px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 8px 6px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
        td.n, th.n { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .total { font-size: 18px; font-weight: 700; }
        .btn { display: inline-block; border: 0; border-radius: 10px; padding: 11px 18px; font-size: 15px; font-weight: 600; cursor: pointer; text-decoration: none; }
        .btn-ok { background: var(--ok); color: #fff; } .btn-pay { background: var(--accent); color: #fff; } .btn-ghost { background: transparent; color: var(--muted); border: 1px solid var(--line); }
        input[type=text], textarea { width: 100%; padding: 10px; border: 1px solid var(--line); border-radius: 8px; background: var(--bg); color: var(--ink); font: inherit; }
        .row { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .msg { padding: 12px 14px; border-radius: 10px; margin-bottom: 16px; }
        .msg-ok { background: rgba(5, 150, 105, .12); color: var(--ok); } .msg-bad { background: rgba(220, 38, 38, .12); color: var(--bad); }
        details summary { cursor: pointer; color: var(--muted); }
        .pre { white-space: pre-line; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="muted">{{ $setting->company_name ?? '' }}@if ($setting?->phone) · {{ $setting->phone }}@endif</div>
        <h1>Quotation {{ $proposal->proposal_no }}</h1>
        <div class="muted">
            For {{ $proposal->recipient_display_name }} · {{ $proposal->proposal_date?->format('d M Y') }}
            @if ($proposal->valid_until) · valid until {{ $proposal->valid_until->format('d M Y') }}@endif
        </div>
        @if ($proposal->title)<p style="margin:10px 0 0;"><b>{{ $proposal->title }}</b></p>@endif
    </div>

    @if (session('done'))<div class="msg msg-ok" role="status">{{ session('done') }}</div>@endif
    @if (session('info'))<div class="msg msg-bad" role="status">{{ session('info') }}</div>@endif
    @if ($errors->any())<div class="msg msg-bad" role="alert">{{ $errors->first() }}</div>@endif

    <div class="card">
        <table>
            <thead><tr><th>Item</th><th class="n">Qty</th><th class="n">Price</th><th class="n">Total</th></tr></thead>
            <tbody>
                @foreach ($proposal->items as $item)
                    <tr>
                        <td>{{ $item->item_name }}@if ($item->description)<div class="muted">{{ $item->description }}</div>@endif @if ($item->warranty_months)<div class="muted">{{ $item->warranty_months }} months warranty</div>@endif</td>
                        <td class="n">{{ rtrim(rtrim(number_format((float) $item->qty, 2), '0'), '.') }}</td>
                        <td class="n">৳{{ number_format((float) $item->unit_price, 2) }}</td>
                        <td class="n">৳{{ number_format((float) $item->total_price, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div style="margin-top:12px; text-align:right;">
            @if ((float) $proposal->discount_amount > 0)<div class="muted">Discount: −৳{{ number_format((float) $proposal->discount_amount, 2) }}</div>@endif
            @if ((float) $proposal->vat_amount > 0)<div class="muted">VAT: ৳{{ number_format((float) $proposal->vat_amount, 2) }}</div>@endif
            <div class="total">Total ৳{{ number_format((float) ($proposal->grand_total ?: $proposal->total_amount), 2) }}</div>
        </div>
        @if ($proposal->terms_and_conditions)
            <details style="margin-top:12px;"><summary>Terms and conditions</summary><div class="pre muted" style="margin-top:6px;">{{ $proposal->terms_and_conditions }}</div></details>
        @endif
    </div>

    @if ($answered)
        <div class="card"><b>{{ $proposal->status === 'Declined' ? 'You declined this quotation.' : 'You accepted this quotation.' }}</b>
            <div class="muted">{{ $proposal->responded_at?->format('d M Y, h:i A') }}. Call us at {{ $setting->phone ?? 'our centre' }} if anything changes.</div>
            @if ($proposal->payment_url && $proposal->status !== 'Declined')<p><a class="btn btn-pay" href="{{ $proposal->payment_url }}" rel="noopener" target="_blank">Pay now</a></p>@endif
        </div>
    @elseif ($expired)
        <div class="card"><b>This quotation has expired.</b><div class="muted">Please call {{ $setting->phone ?? 'us' }} for an updated one.</div></div>
    @else
        <div class="card">
            <form method="POST" action="{{ url('q/' . $proposal->public_token . '/accept') }}">
                @csrf
                <label for="q-name"><b>Accept this quotation</b></label>
                <input type="text" id="q-name" name="name" maxlength="120" placeholder="Your full name" value="{{ old('name', $proposal->recipient_display_name) }}" style="margin:8px 0;">
                <label class="muted" style="display:block; margin-bottom:10px;"><input type="checkbox" name="agree" value="1"> I accept the items, price and terms above.</label>
                <div class="row">
                    <button type="submit" class="btn btn-ok">Accept</button>
                    @if ($proposal->payment_url)<a class="btn btn-pay" href="{{ $proposal->payment_url }}" rel="noopener" target="_blank">Pay advance</a>@endif
                </div>
            </form>
            <details style="margin-top:14px;">
                <summary>Not for you? Tell us why</summary>
                <form method="POST" action="{{ url('q/' . $proposal->public_token . '/decline') }}" style="margin-top:8px;">
                    @csrf
                    <textarea name="reason" rows="2" maxlength="500" placeholder="e.g. price, want to think, another model"></textarea>
                    <div style="margin-top:8px;"><button type="submit" class="btn btn-ghost">Decline</button></div>
                </form>
            </details>
        </div>
    @endif
</div>
</body>
</html>
