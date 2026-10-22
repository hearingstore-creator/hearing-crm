<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
@php
    $credit = ($kind ?? 'debit') === 'credit';
    $title = $credit ? 'Credit Voucher' : 'Debit Voucher';
    $amount = $credit ? $voucher->income_amount : ($total ?? $voucher->expense_amount);
    $multi = !$credit && isset($lines) && $lines->count() > 1;
    $base = $credit ? 'admin/income' : 'admin/expense';
    $perm = $credit ? 'income' : 'expense';
    $cancelled = str_contains((string) $voucher->type, 'Reversed') || str_contains((string) $voucher->type, 'Reversal');
    $approved = $voucher->status === 'Approved';
    $manual = $voucher->voucher_no && $voucher->type === null && $voucher->ref_invoice_no === null;
@endphp
<title>{{ $title }} {{ $voucher->voucher_no ?: $voucher->invoice_no }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; background: #f1f3f6; color: #1f2937; margin: 0; padding: 24px; }
    .toolbar { max-width: 780px; margin: 0 auto 14px; display: flex; gap: 8px; justify-content: space-between; }
    .btn { display: inline-block; padding: 8px 14px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; color: #1f2937; text-decoration: none; font-size: 13px; cursor: pointer; }
    .btn.primary { background: #dc2626; border-color: #dc2626; color: #fff; }
    .flash { max-width: 780px; margin: 0 auto 12px; padding: 10px 14px; border-radius: 6px; font-size: 13px; }
    .flash.ok { background: #dcfce7; color: #166534; } .flash.warn { background: #fef3c7; color: #92400e; }
    .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
    .badge.ok { background: #dcfce7; color: #166534; } .badge.pending { background: #fef3c7; color: #92400e; }
    .sheet { max-width: 780px; margin: 0 auto; background: #fff; padding: 36px 40px; border: 1px solid #e2e8f0; position: relative; }
    .head { text-align: center; border-bottom: 2px solid #1f2937; padding-bottom: 12px; margin-bottom: 18px; }
    .head h1 { margin: 0; font-size: 20px; }
    .head h2 { margin: 6px 0 0; font-size: 15px; letter-spacing: 2px; text-transform: uppercase; }
    .meta { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 16px; }
    table.info { width: 100%; border-collapse: collapse; font-size: 13px; }
    table.info th, table.info td { border: 1px solid #cbd5e1; padding: 9px 10px; text-align: left; vertical-align: top; }
    table.info th { width: 26%; background: #f8fafc; font-weight: 600; }
    .amount { font-size: 18px; font-weight: 700; }
    .sign { display: flex; justify-content: space-between; margin-top: 64px; font-size: 12px; gap: 12px; }
    .sign div { flex: 1; text-align: center; border-top: 1px solid #1f2937; padding-top: 6px; }
    .cancelled { position: absolute; top: 40%; left: 50%; transform: translate(-50%, -50%) rotate(-20deg); font-size: 64px; font-weight: 800; color: rgba(220, 38, 38, .18); border: 6px solid rgba(220, 38, 38, .18); padding: 4px 24px; pointer-events: none; }
    @media print { body { background: #fff; padding: 0; } .toolbar, .flash { display: none; } .sheet { border: 0; max-width: none; } }
</style>
</head>
<body>
@if (session('success'))<div class="flash ok">{{ session('success') }}</div>@endif
@if (session('warning'))<div class="flash warn">{{ session('warning') }}</div>@endif
<div class="toolbar">
    <a class="btn" href="{{ url($base) }}">&larr; {{ $title }}s</a>
    <div>
        @if (!$credit && $manual && !$approved && !$cancelled)
            @can('permission', 'expense.edit')
                <a class="btn" href="{{ url('admin/expense/' . $voucher->id . '/edit') }}">Edit</a>
            @endcan
        @endif
        @if ($manual && !$approved && !$cancelled)
            @can('permission', $perm . '.approve')
                <form method="POST" action="{{ route($perm . '.approve', $voucher->id) }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn" onclick="return confirm('Approve this voucher?')">Approve</button>
                </form>
            @endcan
        @endif
        @if ($voucher->attachment_path)
            <a class="btn" target="_blank" href="{{ route($perm . '.attachment', $voucher->id) }}">{{ $credit ? 'Document' : 'Bill / Receipt' }}</a>
        @endif
        <button class="btn primary" onclick="window.print()">Print</button>
    </div>
</div>

<div class="sheet">
    @if ($cancelled)<div class="cancelled">CANCELLED</div>@endif
    <div class="head">
        <h1>{{ config('app.name') }}</h1>
        <h2>{{ $title }}</h2>
    </div>
    <div class="meta">
        <div><strong>Voucher No:</strong> {{ $voucher->voucher_no ?: $voucher->invoice_no }}
            @if ($manual && !$cancelled)
                &nbsp;<span class="badge {{ $approved ? 'ok' : 'pending' }}">{{ $approved ? 'Approved' : 'Pending approval' }}</span>
            @endif
        </div>
        <div><strong>Date:</strong> {{ \Carbon\Carbon::parse($voucher->date)->format('d M Y') }}</div>
    </div>
    <table class="info">
        <tr><th>{{ $credit ? 'Received From' : 'Paid To' }}</th><td>{{ $voucher->payee_name ?: '—' }}</td></tr>
        @if ($multi)
            <tr>
                <th>Lines</th>
                <td style="padding:0;">
                    <table style="width:100%; border-collapse:collapse;">
                        @foreach ($lines as $line)
                            <tr>
                                <td style="border:0; border-bottom:1px solid #e2e8f0; padding:7px 10px;">{{ $line->head->head_name ?? '—' }}@if ($line->details)<div style="color:#64748b; font-size:12px;">{{ $line->details }}</div>@endif</td>
                                <td style="border:0; border-bottom:1px solid #e2e8f0; padding:7px 10px; text-align:right; white-space:nowrap;">৳{{ number_format(abs($line->expense_amount), 2) }}</td>
                            </tr>
                        @endforeach
                    </table>
                </td>
            </tr>
        @else
            <tr><th>{{ $credit ? 'Income Head' : 'Expense Head' }}</th><td>{{ $voucher->head->head_name ?? '—' }}</td></tr>
            <tr><th>Particulars</th><td>{{ $voucher->details ?: '—' }}</td></tr>
        @endif
        <tr>
            <th>{{ $credit ? 'Receipt Mode' : 'Payment Mode' }}</th>
            <td>
                {{ $voucher->payment_method ?: '—' }}
                @if ($voucher->bank_name) &middot; {{ $voucher->bank_name }} @endif
                @if ($voucher->transaction_no) &middot; Ref: {{ $voucher->transaction_no }} @endif
            </td>
        </tr>
        <tr><th>Amount</th><td class="amount">৳{{ number_format(abs($amount), 2) }}</td></tr>
        <tr><th>Amount in Words</th><td>{{ $amountInWords }}</td></tr>
    </table>
    <div class="sign">
        <div>Prepared By<br>{{ $preparedBy->name ?? '' }}</div>
        <div>Checked By</div>
        <div>Approved By<br>{{ $approvedBy->name ?? '' }}@if ($approved && $voucher->approved_at)<br>{{ \Carbon\Carbon::parse($voucher->approved_at)->format('d M Y') }}@endif</div>
        <div>{{ $credit ? 'Paid By' : 'Received By' }}</div>
    </div>
</div>
</body>
</html>
