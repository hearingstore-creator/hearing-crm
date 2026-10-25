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
    $setting = \App\Models\Setting::first() ?? new \App\Models\Setting();
@endphp
<title>{{ $title }} {{ $voucher->voucher_no ?: $voucher->invoice_no }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- Google Font: Inter -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<!-- Font Awesome 6 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    :root {
        --voucher-accent: {{ $credit ? '#059669' : '#dc2626' }};
        --voucher-accent-hover: {{ $credit ? '#047857' : '#b91c1c' }};
        --voucher-accent-gradient: {{ $credit ? 'linear-gradient(90deg, #10b981, #059669 50%, #047857)' : 'linear-gradient(90deg, #ef4444, #dc2626 50%, #b91c1c)' }};
        --voucher-tint-bg: {{ $credit ? '#ecfdf5' : '#fef2f2' }};
        --voucher-tint-border: {{ $credit ? '#a7f3d0' : '#fecaca' }};
    }
    * { box-sizing: border-box; }
    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: #f1f5f9;
        color: #1e293b;
        margin: 0;
        padding: 24px 16px 40px;
        -webkit-font-smoothing: antialiased;
    }

    /* Toolbar (No Print) */
    .toolbar {
        max-width: 840px;
        margin: 0 auto 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
        background: #ffffff;
        padding: 12px 18px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }
    .toolbar-left, .toolbar-right {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #334155;
        transition: all 0.15s ease;
        line-height: 1.4;
    }
    .btn:hover {
        background: #f8fafc;
        border-color: #94a3b8;
        color: #0f172a;
    }
    .btn-primary {
        background: var(--voucher-accent);
        border-color: var(--voucher-accent);
        color: #ffffff !important;
    }
    .btn-primary:hover {
        background: var(--voucher-accent-hover);
        border-color: var(--voucher-accent-hover);
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
    }
    .btn-outline-primary {
        border-color: #cbd5e1;
        color: #334155;
    }
    .btn-outline-primary:hover {
        border-color: var(--voucher-accent);
        color: var(--voucher-accent);
        background: var(--voucher-tint-bg);
    }
    .btn-success {
        background: #10b981;
        border-color: #10b981;
        color: #ffffff !important;
    }
    .btn-success:hover {
        background: #059669;
        border-color: #059669;
    }

    /* Flash Messages */
    .flash {
        max-width: 840px;
        margin: 0 auto 14px;
        padding: 12px 18px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .flash.ok { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .flash.warn { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }

    /* Badge */
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 11.5px;
        font-weight: 700;
        letter-spacing: 0.3px;
    }
    .badge-ok { background: #dcfce7; color: #166534; }
    .badge-pending { background: #fef3c7; color: #92400e; }
    .badge-cancelled { background: #fee2e2; color: #991b1b; }

    /* Sheet (Voucher Paper) */
    .sheet {
        max-width: 840px;
        margin: 0 auto;
        background: #ffffff;
        padding: 44px 50px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
        position: relative;
        overflow: hidden;
    }
    .sheet-top-accent {
        height: 6px;
        width: 100%;
        position: absolute;
        top: 0;
        left: 0;
        background: var(--voucher-accent-gradient);
    }

    /* Parties / Summary Grid */
    .voucher-grid {
        display: grid;
        grid-template-columns: 1.2fr 1fr;
        gap: 20px;
        margin: 20px 0;
        padding: 18px 22px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
    }
    .voucher-field {
        margin-bottom: 7px;
        font-size: 13px;
        color: #334155;
        line-height: 1.5;
    }
    .voucher-field:last-child {
        margin-bottom: 0;
    }
    .voucher-field strong {
        color: #0f172a;
        display: inline-block;
        min-width: 125px;
        font-weight: 600;
    }

    /* Tables */
    .voucher-table {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
        font-size: 13px;
    }
    .voucher-table th {
        background: #f8fafc;
        color: #475569;
        font-weight: 700;
        font-size: 11.5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 11px 14px;
        border: 1px solid #e2e8f0;
        text-align: left;
    }
    .voucher-table td {
        padding: 12px 14px;
        border: 1px solid #e2e8f0;
        color: #1e293b;
        vertical-align: top;
        line-height: 1.45;
    }
    .voucher-table th.col-amount, .voucher-table td.col-amount {
        text-align: right;
        white-space: nowrap;
    }
    .voucher-table tr.total-row td {
        background: #f8fafc;
        font-weight: 700;
        font-size: 13.5px;
        border-top: 2px solid #cbd5e1;
    }

    /* Single Item Info Table */
    table.info {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        margin: 18px 0;
    }
    table.info th, table.info td {
        border: 1px solid #e2e8f0;
        padding: 11px 15px;
        text-align: left;
        vertical-align: top;
    }
    table.info th {
        width: 24%;
        background: #f8fafc;
        font-weight: 600;
        color: #475569;
    }
    table.info td {
        color: #0f172a;
    }

    /* Amount Banner */
    .amount-banner {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        margin: 22px 0;
        padding: 18px 24px;
        border-radius: 8px;
        background: var(--voucher-tint-bg);
        border: 1px solid var(--voucher-tint-border);
        border-left: 5px solid var(--voucher-accent);
    }
    .amount-info .a-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        margin-bottom: 4px;
    }
    .amount-info .a-words {
        font-size: 13.5px;
        font-weight: 600;
        color: #0f172a;
        font-style: italic;
    }
    .amount-figure {
        text-align: right;
        flex-shrink: 0;
    }
    .amount-figure .a-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        margin-bottom: 2px;
    }
    .amount-figure .a-val {
        font-size: 26px;
        font-weight: 800;
        color: var(--voucher-accent);
        letter-spacing: -0.5px;
    }

    /* Signatures */
    .signatures-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-top: 64px;
        padding-top: 10px;
    }
    .sign-block {
        text-align: center;
    }
    .sign-line {
        border-top: 1px dashed #94a3b8;
        margin-bottom: 8px;
    }
    .sign-title {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
    }
    .sign-name {
        font-size: 12px;
        font-weight: 600;
        color: #0f172a;
        margin-top: 4px;
        min-height: 18px;
    }
    .sign-date {
        font-size: 11px;
        color: #64748b;
        margin-top: 2px;
    }

    /* Cancelled watermark */
    .cancelled-watermark {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-25deg);
        font-size: 68px;
        font-weight: 900;
        letter-spacing: 4px;
        color: rgba(220, 38, 38, 0.13);
        border: 6px solid rgba(220, 38, 38, 0.13);
        padding: 8px 32px;
        border-radius: 12px;
        pointer-events: none;
        text-transform: uppercase;
        z-index: 10;
    }

    @media (max-width: 768px) {
        body { padding: 12px 8px; }
        .sheet { padding: 24px 18px; }
        .voucher-grid { grid-template-columns: 1fr; gap: 12px; padding: 14px 16px; }
        .signatures-row { grid-template-columns: 1fr 1fr; gap: 32px 16px; margin-top: 40px; }
        .amount-banner { flex-direction: column; align-items: flex-start; }
        .amount-figure { text-align: left; margin-top: 8px; }
    }

    /* Print Styles */
    @page {
        size: A4 portrait;
        margin: 12mm 15mm;
    }
    @media print {
        body {
            background: #ffffff !important;
            padding: 0 !important;
            color: #000000 !important;
            font-size: 11pt !important;
        }
        .toolbar, .flash, .no-print {
            display: none !important;
        }
        .sheet {
            box-shadow: none !important;
            border: none !important;
            border-radius: 0 !important;
            padding: 0 !important;
            max-width: 100% !important;
            margin: 0 !important;
        }
        .sheet-top-accent {
            display: none !important;
        }
        .voucher-grid {
            padding: 12px 14px !important;
            margin: 14px 0 !important;
            background: #f8fafc !important;
        }
        .voucher-table th, .voucher-table td {
            padding: 8px 12px !important;
        }
        .amount-banner {
            padding: 12px 16px !important;
            margin: 16px 0 !important;
        }
        .signatures-row {
            margin-top: 48px !important;
            page-break-inside: avoid;
        }
        .voucher-table tr {
            page-break-inside: avoid;
        }
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }
</style>
</head>
<body>

@if (session('success'))
    <div class="flash ok"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
@endif
@if (session('warning'))
    <div class="flash warn"><i class="fas fa-exclamation-triangle"></i> {{ session('warning') }}</div>
@endif

{{-- Top Toolbar (No Print) --}}
<div class="toolbar no-print">
    <div class="toolbar-left">
        <a class="btn" href="{{ url($base) }}">
            <i class="fas fa-arrow-left"></i>
            <span>{{ $title }}s</span>
        </a>
        <span class="badge {{ $approved ? 'badge-ok' : ($cancelled ? 'badge-cancelled' : 'badge-pending') }}">
            <i class="fas {{ $approved ? 'fa-check-circle' : ($cancelled ? 'fa-ban' : 'fa-clock') }}"></i>
            {{ $approved ? 'Approved' : ($cancelled ? 'Cancelled' : 'Pending Approval') }}
        </span>
    </div>
    <div class="toolbar-right">
        @if (!$credit && $manual && !$approved && !$cancelled)
            @can('permission', 'expense.edit')
                <a class="btn btn-outline-primary" href="{{ url('admin/expense/' . $voucher->id . '/edit') }}">
                    <i class="fas fa-edit"></i>
                    <span>Edit</span>
                </a>
            @endcan
        @endif
        @if ($manual && !$approved && !$cancelled)
            @can('permission', $perm . '.approve')
                <form method="POST" action="{{ route($perm . '.approve', $voucher->id) }}" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn-success" onclick="return confirm('Approve this voucher?')">
                        <i class="fas fa-check-circle"></i>
                        <span>Approve</span>
                    </button>
                </form>
            @endcan
        @endif
        @if ($voucher->attachment_path)
            <a class="btn btn-outline-primary" target="_blank" href="{{ route($perm . '.attachment', $voucher->id) }}">
                <i class="fas fa-paperclip"></i>
                <span>{{ $credit ? 'Document' : 'Bill / Receipt' }}</span>
            </a>
        @endif
        <button class="btn btn-primary" onclick="window.print()">
            <i class="fas fa-print"></i>
            <span>Print {{ $title }}</span>
        </button>
    </div>
</div>

{{-- Voucher Sheet --}}
<div class="sheet">
    <div class="sheet-top-accent"></div>

    @if ($cancelled)
        <div class="cancelled-watermark">CANCELLED</div>
    @endif

    {{-- Official Dual-Branding Document Header --}}
    @include('admin.partials.document_header', [
        'vendor'       => $voucher->vendor,
        'setting'      => $setting,
        'documentType' => strtoupper($title),
        'documentNo'   => '#' . ($voucher->voucher_no ?: $voucher->invoice_no),
        'date'         => $voucher->date,
        'status'       => $approved ? 'Approved' : ($cancelled ? 'Cancelled' : ($voucher->voucher_no ? 'Pending Approval' : 'Posted')),
        'statusClass'  => $approved ? 'completed' : ($cancelled ? 'cancelled' : ($voucher->voucher_no ? 'partial' : 'completed')),
    ])

    {{-- Parties & Payment Overview Card --}}
    <div class="voucher-grid">
        <div>
            <div class="voucher-field">
                <strong>{{ $credit ? 'Received From:' : 'Paid To:' }}</strong>
                <span>{{ $voucher->payee_name ?: '—' }}</span>
            </div>
            @unless ($multi)
                <div class="voucher-field">
                    <strong>{{ $credit ? 'Income Head:' : 'Expense Head:' }}</strong>
                    <span>{{ $voucher->head->head_name ?? '—' }}</span>
                </div>
            @endunless
            @if ($voucher->ref_invoice_no)
                <div class="voucher-field">
                    <strong>Reference:</strong>
                    <span>{{ $voucher->type ?? 'Invoice' }} #{{ $voucher->ref_invoice_no }}</span>
                </div>
            @endif
        </div>
        <div>
            <div class="voucher-field">
                <strong>{{ $credit ? 'Receipt Mode:' : 'Payment Mode:' }}</strong>
                <span>{{ $voucher->payment_method ?: 'Cash' }}</span>
            </div>
            @if ($voucher->bank_name)
                <div class="voucher-field">
                    <strong>Bank / Wallet:</strong>
                    <span>{{ $voucher->bank_name }}</span>
                </div>
            @endif
            @if ($voucher->transaction_no)
                <div class="voucher-field">
                    <strong>Cheque / Ref No:</strong>
                    <span>{{ $voucher->transaction_no }}</span>
                </div>
            @endif
        </div>
    </div>

    {{-- Items / Multi-lines Table or Single Line Remarks --}}
    @if ($multi)
        <table class="voucher-table">
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th style="width: 35%;">Expense Head</th>
                    <th>Particulars / Remarks</th>
                    <th class="col-amount" style="width: 140px;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lines as $idx => $line)
                    <tr>
                        <td style="color: #64748b; font-weight: 500;">{{ $idx + 1 }}</td>
                        <td style="font-weight: 600; color: #0f172a;">
                            {{ $line->head->head_name ?? '—' }}
                        </td>
                        <td style="color: #475569;">
                            {{ $line->details ?: '—' }}
                        </td>
                        <td class="col-amount" style="font-weight: 600; color: #0f172a;">
                            ৳{{ number_format(abs($line->expense_amount), 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="3" style="text-align: right; text-transform: uppercase; letter-spacing: 0.5px;">Total Amount:</td>
                    <td class="col-amount" style="color: var(--voucher-accent); font-size: 15px;">৳{{ number_format(abs($total), 2) }}</td>
                </tr>
            </tfoot>
        </table>
    @else
        <table class="info">
            <tr>
                <th>Particulars / Remarks</th>
                <td>{{ $voucher->details ?: '—' }}</td>
            </tr>
        </table>
    @endif

    {{-- Amount Highlight Banner --}}
    <div class="amount-banner">
        <div class="amount-info">
            <div class="a-label">Amount in Words (In Taka)</div>
            <div class="a-words">{{ $amountInWords }}</div>
        </div>
        <div class="amount-figure">
            <div class="a-label">Total {{ $title }}</div>
            <div class="a-val">৳{{ number_format(abs($amount), 2) }}</div>
        </div>
    </div>

    {{-- Signatures Block --}}
    <div class="signatures-row">
        <div class="sign-block">
            <div class="sign-line"></div>
            <div class="sign-title">Prepared By</div>
            <div class="sign-name">{{ $preparedBy->name ?? '—' }}</div>
        </div>
        <div class="sign-block">
            <div class="sign-line"></div>
            <div class="sign-title">Checked By</div>
            <div class="sign-name">—</div>
        </div>
        <div class="sign-block">
            <div class="sign-line"></div>
            <div class="sign-title">Approved By</div>
            <div class="sign-name">{{ $approvedBy->name ?? '—' }}</div>
            @if ($approved && $voucher->approved_at)
                <div class="sign-date">{{ \Carbon\Carbon::parse($voucher->approved_at)->format('d M Y') }}</div>
            @endif
        </div>
        <div class="sign-block">
            <div class="sign-line"></div>
            <div class="sign-title">{{ $credit ? 'Deposited / Paid By' : 'Received By' }}</div>
            <div class="sign-name">—</div>
        </div>
    </div>
</div>

</body>
</html>
