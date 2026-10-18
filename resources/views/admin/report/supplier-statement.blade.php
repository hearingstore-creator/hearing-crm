@extends('admin.layouts.master')
@section('page_title', 'Supplier Statement')
@section('breadcrumb_parent', 'Reports')
@section('breadcrumb_parent_url', url('admin/report/supplier-statement'))

@push('css')
    @include('admin.report.partials.report-styles')
    <style>
        .badge-primary-light { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }
        .badge-danger-light { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-success-light { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    </style>
@endpush

@php
    $money = fn ($v) => '৳' . number_format((float) $v, 2);
    $balTag = fn ($v) => abs($v) < 0.005 ? '' : ($v > 0 ? '' : '<span class="bal-tag adv">(Advance)</span>');
    $periodText = $start->format('d M Y') . ' — ' . $end->format('d M Y');
    $query = request()->except(['export', 'page']);
    $currentFormat = $format ?? 'standard';
@endphp

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-file-invoice" style="color: var(--accent-500); margin-right: 8px;"></i>Supplier Statement</h1>
            <p>
                @if($currentFormat === 'itemized')
                    Detailed statement with purchased products, returned items, and serial numbers (SN)
                @elseif($currentFormat === 'aging')
                    Bill-by-bill outstanding balance and aging analysis (credit control)
                @else
                    Bank-statement style ledger of purchases, debit notes, and payments per supplier
                @endif
            </p>
        </div>
        <div class="header-actions">
            @if ($supplier)
                <a href="{{ url('admin/report/supplier-statement') . '?' . http_build_query(\Illuminate\Support\Arr::except($query, ['supplier_id'])) }}" class="btn btn-secondary btn-sm" id="btn-all-suppliers">
                    <i class="fas fa-list mr-1"></i>All Suppliers
                </a>
            @endif
            <a href="{{ url('admin/report/supplier-statement') . '?' . http_build_query(array_merge($query, ['export' => 'csv'])) }}" class="btn btn-secondary btn-sm" id="btn-export-csv">
                <i class="fas fa-file-csv mr-1"></i>Export CSV
            </a>
            <button type="button" onclick="window.print()" class="btn btn-primary btn-sm" id="btn-print">
                <i class="fas fa-print mr-1"></i>Print
            </button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="rpt-filter crm-animate-in delay-1">
        <form method="GET" action="{{ url('admin/report/supplier-statement') }}">
            <input type="hidden" name="format" value="{{ $currentFormat }}" id="input-format">
            <div class="f-group wide">
                <label for="f-supplier">Supplier</label>
                <select name="supplier_id" id="f-supplier" class="select">
                    <option value="">All suppliers (summary)</option>
                    @foreach ($suppliers as $s)
                        <option value="{{ $s->id }}" {{ optional($supplier)->id == $s->id ? 'selected' : '' }}>
                            {{ $s->supplier_name }}{{ $s->vendor_name ? ' — ' . $s->vendor_name : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            @include('admin.report.partials.period-filter')
            <div class="f-actions">
                <button type="submit" class="btn btn-primary btn-sm" id="btn-filter"><i class="fas fa-search mr-1"></i>Show</button>
                <a href="{{ url('admin/report/supplier-statement') }}" class="btn btn-outline-secondary btn-sm" title="Reset"><i class="fas fa-undo"></i></a>
            </div>
        </form>
        <div class="period-chips">
            @foreach (['this_month', 'last_month', 'this_quarter', 'this_year', 'last_year'] as $p)
                <a href="{{ url('admin/report/supplier-statement') . '?' . http_build_query(array_merge(\Illuminate\Support\Arr::except($query, ['start_date', 'end_date']), ['period' => $p])) }}" class="{{ $period === $p ? 'active' : '' }}">{{ $periods[$p] }}</a>
            @endforeach
        </div>
    </div>

    @if ($supplier)
        {{-- Format Switcher Pills (Fix: use array_merge so format is guaranteed to switch) --}}
        <div class="format-switcher crm-animate-in">
            <span class="lbl"><i class="fas fa-layer-group text-primary mr-1"></i>Statement Format:</span>
            <a href="{{ url('admin/report/supplier-statement') . '?' . http_build_query(array_merge($query, ['format' => 'standard'])) }}" class="btn-format {{ $currentFormat === 'standard' ? 'active' : '' }}">
                <i class="fas fa-file-invoice mr-1"></i>Standard Ledger
            </a>
            <a href="{{ url('admin/report/supplier-statement') . '?' . http_build_query(array_merge($query, ['format' => 'itemized'])) }}" class="btn-format {{ $currentFormat === 'itemized' ? 'active' : '' }}">
                <i class="fas fa-boxes mr-1"></i>Itemized (Products & SN)
            </a>
            <a href="{{ url('admin/report/supplier-statement') . '?' . http_build_query(array_merge($query, ['format' => 'aging'])) }}" class="btn-format {{ $currentFormat === 'aging' ? 'active' : '' }}">
                <i class="fas fa-history mr-1"></i>Bill-by-Bill Aging
            </a>
        </div>

        {{-- ══════════════════════════════════════════════════════════════ --}}
        {{-- FORMAT 3: AGING & BILL-BY-BILL                                --}}
        {{-- ══════════════════════════════════════════════════════════════ --}}
        @if ($currentFormat === 'aging')
            <div class="kpi-row crm-animate-in">
                @include('admin.partials.kpi-card', ['color' => 'indigo', 'icon' => 'fas fa-file-invoice-dollar', 'label' => 'Total Invoiced', 'value' => $money($agingSummary['totalInvoiced']), 'sub' => 'All historical purchases'])
                @include('admin.partials.kpi-card', ['color' => 'green', 'icon' => 'fas fa-check-circle', 'label' => 'Total Paid', 'value' => $money($agingSummary['totalPaid']), 'sub' => 'Paid against purchases'])
                @include('admin.partials.kpi-card', ['color' => 'orange', 'icon' => 'fas fa-undo-alt', 'label' => 'Returns Deducted', 'value' => $money($agingSummary['totalReturned']), 'sub' => 'Debit note adjustments'])
                @include('admin.partials.kpi-card', ['color' => $agingSummary['totalDue'] > 0 ? 'rose' : 'teal', 'icon' => 'fas fa-balance-scale', 'label' => 'Net Outstanding Due', 'value' => $money($agingSummary['totalDue']), 'sub' => 'Unpaid balance on bills'])
            </div>

            {{-- Aging Brackets Strip --}}
            <div class="card mb-3 crm-animate-in delay-1" style="border-radius: 12px; border: 1px solid var(--card-border); background: var(--card-bg);">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap: 12px;">
                        <div class="d-flex align-items-center flex-wrap" style="gap: 16px;">
                            <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: var(--page-text-muted);">
                                <i class="fas fa-chart-bar mr-1 text-primary"></i>Aging Overdue Breakdown:
                            </span>
                            <div>
                                <span class="aging-badge bracket-0-30 mr-1">0-30 Days</span>
                                <strong style="font-size: 13px;">{{ $money($agingSummary['bracket_0_30']) }}</strong>
                            </div>
                            <div>
                                <span class="aging-badge bracket-31-60 mr-1">31-60 Days</span>
                                <strong style="font-size: 13px;">{{ $money($agingSummary['bracket_31_60']) }}</strong>
                            </div>
                            <div>
                                <span class="aging-badge bracket-61-90 mr-1">61-90 Days</span>
                                <strong style="font-size: 13px;">{{ $money($agingSummary['bracket_61_90']) }}</strong>
                            </div>
                            <div>
                                <span class="aging-badge bracket-90-plus mr-1">90+ Days</span>
                                <strong style="font-size: 13px; color: #b91c1c;">{{ $money($agingSummary['bracket_90_plus']) }}</strong>
                            </div>
                        </div>

                        <div>
                            @if(request()->boolean('unpaid_only'))
                                <a href="{{ url('admin/report/supplier-statement') . '?' . http_build_query(\Illuminate\Support\Arr::except($query, ['unpaid_only'])) }}" class="btn btn-outline-secondary btn-xs font-weight-bold">
                                    <i class="fas fa-list mr-1"></i>Show All Bills
                                </a>
                            @else
                                <a href="{{ url('admin/report/supplier-statement') . '?' . http_build_query(array_merge($query, ['unpaid_only' => 1])) }}" class="btn btn-outline-danger btn-xs font-weight-bold">
                                    <i class="fas fa-filter mr-1"></i>Show Unpaid Bills Only
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="stmt crm-animate-in delay-2" id="print-area">
                @include('admin.report.partials.statement-header', ['title' => 'Supplier Bill-by-Bill Aging Statement', 'subtitle' => 'As of: <b>' . $end->format('d M Y') . '</b> · Supplier: <b>' . $supplier->supplier_name . '</b>'])

                <div style="overflow-x:auto;">
                    <table class="stmt-table" id="tbl-aging">
                        <thead>
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th style="width: 95px;">Bill Date</th>
                                <th>Supplier Cash Memo / Ref No</th>
                                <th>Internal Bill #</th>
                                <th class="num">Total Bill (৳)</th>
                                <th class="num">Paid (৳)</th>
                                <th class="num">Returns (৳)</th>
                                <th class="num">Net Due (৳)</th>
                                <th style="text-align: center; width: 85px;">Age</th>
                                <th style="text-align: center; width: 110px;">Aging Bracket</th>
                                <th style="text-align: center; width: 85px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($agingBills as $idx => $b)
                                <tr>
                                    <td class="muted">{{ $idx + 1 }}</td>
                                    <td class="muted">{{ \Carbon\Carbon::parse($b->date)->format('d M Y') }}</td>
                                    <td>
                                        @if (!empty($b->supplier_memo_no))
                                            <div class="font-weight-bold" style="font-size: 13.5px; color: #0f172a;">
                                                <i class="fas fa-receipt text-primary mr-1"></i>{{ $b->supplier_memo_no }}
                                            </div>
                                        @else
                                            <span class="muted">— (No Memo)</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ url('admin/purchase/' . $b->id) }}" target="_blank" class="font-weight-600">
                                            #{{ $b->purchase_invoice_no }}
                                        </a>
                                    </td>
                                    <td class="num">{{ $money($b->total_amount) }}</td>
                                    <td class="num dr">{{ $money($b->paid) }}</td>
                                    <td class="num muted">{{ $b->returned > 0 ? $money($b->returned) : '—' }}</td>
                                    <td class="num {{ $b->due > 0 ? 'cr' : 'dr' }}">
                                        <b>{{ $money($b->due) }}</b>
                                    </td>
                                    <td style="text-align: center;" class="muted">{{ $b->age_days }}d</td>
                                    <td style="text-align: center;">
                                        <span class="aging-badge {{ $b->bracket === '0-30 Days' ? 'bracket-0-30' : ($b->bracket === '31-60 Days' ? 'bracket-31-60' : ($b->bracket === '61-90 Days' ? 'bracket-61-90' : 'bracket-90-plus')) }}">
                                            {{ $b->bracket }}
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        @if ($b->status === 'Paid')
                                            <span class="status-badge-paid">Paid</span>
                                        @elseif ($b->status === 'Partial')
                                            <span class="status-badge-partial">Partial</span>
                                        @else
                                            <span class="status-badge-unpaid">Unpaid</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="stmt-empty">
                                        <i class="far fa-check-circle text-success"></i>
                                        No outstanding purchase bills found for this supplier.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($agingBills->isNotEmpty())
                            <tfoot>
                                <tr>
                                    <td colspan="4">Total · {{ $agingBills->count() }} Purchase Bills</td>
                                    <td class="num">{{ $money($agingSummary['totalInvoiced']) }}</td>
                                    <td class="num dr">{{ $money($agingSummary['totalPaid']) }}</td>
                                    <td class="num">{{ $money($agingSummary['totalReturned']) }}</td>
                                    <td class="num cr"><b>{{ $money($agingSummary['totalDue']) }}</b></td>
                                    <td colspan="3"></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

                <div class="stmt-foot">
                    <span>Aging calculated from Bill Date to today. Net Due = Total Bill − Returns Deducted − Paid Amount.</span>
                    <span>Generated {{ now()->format('d M Y, h:i A') }} by {{ auth()->user()->name }}</span>
                </div>
            </div>

        {{-- ══════════════════════════════════════════════════════════════ --}}
        {{-- FORMAT 1 & 2: STANDARD LEDGER & ITEMIZED (PRODUCTS & SN)       --}}
        {{-- ══════════════════════════════════════════════════════════════ --}}
        @else
            <div class="kpi-row crm-animate-in">
                @include('admin.partials.kpi-card', ['color' => 'slate', 'icon' => 'fas fa-flag', 'label' => 'Opening Balance', 'value' => $money($opening), 'sub' => 'as of ' . $start->format('d M Y')])
                @include('admin.partials.kpi-card', ['color' => 'orange', 'icon' => 'fas fa-shopping-basket', 'label' => 'Purchased', 'value' => $money($totalPurchased), 'sub' => '<b>' . $invoiceCount . '</b> invoices in period'])
                @include('admin.partials.kpi-card', ['color' => 'green', 'icon' => 'fas fa-money-bill-wave', 'label' => 'Paid', 'value' => $money($totalPaid), 'sub' => '<b>' . $rows->where('debit', '>', 0)->count() . '</b> payment entries'])
                @include('admin.partials.kpi-card', ['color' => $closing > 0 ? 'rose' : 'teal', 'icon' => 'fas fa-balance-scale', 'label' => $closing >= 0 ? 'Closing Payable' : 'Closing Advance', 'value' => $money(abs($closing)), 'sub' => 'as of ' . $end->format('d M Y')])
            </div>

            <div class="stmt crm-animate-in delay-2" id="print-area">
                @include('admin.report.partials.statement-header', [
                    'title' => ($currentFormat === 'itemized') ? 'Supplier Itemized Statement (Products & Serials)' : 'Supplier Financial Statement',
                    'subtitle' => 'Statement period: <b>' . $periodText . '</b>'
                ])

                <div class="stmt-info">
                    <div>
                        <div class="i-lbl">Statement for</div>
                        <div class="i-name">{{ $supplier->supplier_name }}</div>
                        <div class="i-line">
                            @if ($supplier->address1){{ $supplier->address1 }}<br>@endif
                            @if ($supplier->mobile_no || $supplier->phone)<i class="fas fa-phone-alt" style="font-size:10px;"></i> {{ $supplier->mobile_no ?: $supplier->phone }}@endif
                            @if ($supplier->email1) &nbsp;·&nbsp; {{ $supplier->email1 }}@endif
                            <br>Branch: <b>{{ $supplier->vendor_name ?? 'Head Office' }}</b>
                        </div>
                    </div>
                    <div>
                        <div class="i-lbl">Account summary</div>
                        <div class="sum-grid">
                            <span class="k">Opening balance</span><span class="v">{{ $money($opening) }}</span>
                            <span class="k">(+) Purchases</span><span class="v">{{ $money($totalPurchased) }}</span>
                            <span class="k">(−) Payments & Returns</span><span class="v">{{ $money($totalPaid) }}</span>
                            <span class="k"><b>Closing Balance ({{ $closing >= 0 ? 'Payable' : 'Advance' }})</b></span><span class="v big {{ $closing >= 0 ? 'cr' : 'dr' }}">{{ $money(abs($closing)) }}</span>
                        </div>
                    </div>
                </div>

                @if ($currentFormat === 'itemized')
                    <div class="p-2 px-3 border-bottom d-flex justify-content-between align-items-center bg-light no-print">
                        <small class="text-muted"><i class="fas fa-info-circle mr-1 text-primary"></i>Itemized breakdown includes purchased products, returned items, rates, and individual serial numbers.</small>
                        <div>
                            <button type="button" class="btn btn-xs btn-outline-secondary font-weight-bold" id="btnToggleAllItems">
                                <i class="fas fa-compress-arrows-alt mr-1"></i>Collapse All Details
                            </button>
                        </div>
                    </div>
                @endif

                <div style="overflow-x:auto;">
                    <table class="stmt-table" id="tbl-statement">
                        <thead>
                            <tr>
                                <th style="width:95px;">Date</th>
                                <th>Particulars & Transaction Details</th>
                                <th style="width: 170px;">Supplier Memo / Ref No</th>
                                <th style="width: 110px;">Type</th>
                                <th class="num">Paid / Ret (Dr)</th>
                                <th class="num">Purchased (Cr)</th>
                                <th class="num">Balance (Payable)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="row-open">
                                <td>{{ $start->format('d M Y') }}</td>
                                <td colspan="5">Opening Balance (brought forward)</td>
                                <td class="num">{{ $money(abs($opening)) }} {!! $balTag($opening) !!}</td>
                            </tr>
                            @forelse ($rows as $r)
                                @php
                                    $t = strtolower((string) $r->type);
                                    $isDebitNote = ($t === 'debit note' || strpos(strtolower((string) $r->details), 'debit note') !== false);
                                    $pill = $isDebitNote ? 'other' : ($r->debit > 0 ? 'payment' : ($t === 'purchase' ? 'purchase' : 'other'));
                                    $pillText = $isDebitNote ? 'Debit Note' : ($r->debit > 0 ? ($t === 'purchase' ? 'Paid on purchase' : 'Payment') : ($r->type ?: 'Entry'));
                                    $hasItems = isset($r->items) && $r->items->isNotEmpty();
                                @endphp
                                <tr>
                                    <td class="muted">{{ \Carbon\Carbon::parse($r->date)->format('d M Y') }}</td>
                                    <td>
                                        <div class="font-weight-600" style="color: #1e293b;">
                                            @if (strtolower((string) $r->type) === 'purchase')
                                                <span><i class="fas fa-shopping-bag text-warning mr-1"></i>Purchase Bill</span>
                                                @if (!empty($r->supplier_memo_no))
                                                    <span class="badge badge-primary-light ml-1" style="font-size: 11px; font-weight: 700;">
                                                        <i class="fas fa-receipt mr-1"></i>Cash Memo: {{ $r->supplier_memo_no }}
                                                    </span>
                                                @endif
                                            @elseif ($isDebitNote)
                                                <span><i class="fas fa-undo-alt text-danger mr-1"></i>Purchase Return (Debit Note)</span>
                                                @if (!empty($r->supplier_memo_no))
                                                    <span class="badge badge-danger-light ml-1" style="font-size: 11px; font-weight: 700;">
                                                        <i class="fas fa-tag mr-1"></i>Ref Memo: {{ $r->supplier_memo_no }}
                                                    </span>
                                                @endif
                                            @elseif (strtolower((string) $r->type) === 'payment')
                                                <span><i class="fas fa-money-bill-wave text-success mr-1"></i>Payment to Supplier</span>
                                                @if (!empty($r->payment_method))
                                                    <span class="badge badge-light border ml-1" style="font-size: 10px;">{{ $r->payment_method }}</span>
                                                @endif
                                                @if (!empty($r->supplier_memo_no))
                                                    <span class="badge badge-success-light ml-1" style="font-size: 11px; font-weight: 700;">
                                                        <i class="fas fa-receipt mr-1"></i>Slip/Memo: {{ $r->supplier_memo_no }}
                                                    </span>
                                                @endif
                                            @else
                                                <span>{{ $r->details }}</span>
                                            @endif
                                        </div>

                                        {{-- Secondary notes if custom text exists --}}
                                        @php
                                            $rawDetails = trim((string) $r->details);
                                            $isGeneric = in_array($rawDetails, [
                                                'Purchase invoice no - ' . $r->ref_invoice_no,
                                                'Payment ref no - ' . $r->ref_invoice_no,
                                                'Purchase Return',
                                                'Debit Note'
                                            ]);
                                        @endphp
                                        @if (!empty($rawDetails) && !$isGeneric)
                                            <div class="text-muted" style="font-size: 11px; margin-top: 2px;">
                                                {{ $rawDetails }}
                                            </div>
                                        @endif

                                        {{-- If Itemized Format and items exist, show clean typographic product and serial lines --}}
                                        @if ($currentFormat === 'itemized' && $hasItems)
                                            <div class="itemized-container">
                                                <div class="itemized-product-block">
                                                    @foreach ($r->items as $it)
                                                        <div class="item-product-row">
                                                            <span class="bullet">•</span>
                                                            <span class="prod-name">{{ $it->product_name }}</span>
                                                            <span class="prod-calc">{{ (float) $it->qty }} {{ (float) $it->qty > 1 ? 'pcs' : 'pc' }} @ ৳{{ number_format($it->purchase_price, 2) }} (= ৳{{ number_format(($it->qty ?: 1) * $it->purchase_price, 2) }})</span>
                                                        </div>
                                                        @if (!empty($it->serials) && count($it->serials) > 0)
                                                            <div class="item-serial-row">
                                                                <span class="sn-label">SN:</span>
                                                                @foreach ($it->serials as $sn)
                                                                    <span class="serial-pill {{ $isDebitNote ? 'returned' : '' }}">{{ $sn }}</span>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Prominent Supplier Memo / Reference Column --}}
                                    <td>
                                        @if (!empty($r->supplier_memo_no))
                                            <div style="font-size: 13.5px; font-weight: 800; color: #0f172a; line-height: 1.2;">
                                                <i class="fas fa-receipt text-primary mr-1"></i>{{ $r->supplier_memo_no }}
                                            </div>
                                            <small class="text-muted d-block" style="font-size: 11px; margin-top: 2px;">
                                                @if ($r->purchase_id)
                                                    <a href="{{ url('admin/purchase/' . $r->purchase_id) }}" target="_blank" class="text-muted">
                                                        Bill #{{ $r->ref_invoice_no }}
                                                    </a>
                                                @elseif ($isDebitNote && !empty($r->return_id))
                                                    <a href="{{ route('purchase-returns.show', $r->return_id) }}" target="_blank" class="text-muted">
                                                        DN #{{ $r->ref_invoice_no }}
                                                    </a>
                                                @elseif ($r->ref_invoice_no)
                                                    Ref: {{ $r->ref_invoice_no }}
                                                @endif
                                            </small>
                                        @else
                                            @if ($r->purchase_id)
                                                <a href="{{ url('admin/purchase/' . $r->purchase_id) }}" target="_blank" class="font-weight-bold" style="font-size: 13px;">
                                                    Bill #{{ $r->ref_invoice_no }}
                                                </a>
                                            @elseif ($isDebitNote && !empty($r->return_id))
                                                <a href="{{ route('purchase-returns.show', $r->return_id) }}" target="_blank" class="font-weight-bold" style="font-size: 13px;">
                                                    DN #{{ $r->ref_invoice_no }}
                                                </a>
                                            @else
                                                <span class="font-weight-bold" style="font-size: 13px;">{{ $r->ref_invoice_no ?: '—' }}</span>
                                            @endif
                                            <small class="text-muted d-block" style="font-size: 10px;">(No Memo No)</small>
                                        @endif
                                    </td>

                                    <td><span class="type-pill {{ $pill }}">{{ $pillText }}</span></td>
                                    <td class="num {{ $r->debit > 0 ? 'dr' : 'muted' }}">{{ $r->debit > 0 ? $money($r->debit) : '—' }}</td>
                                    <td class="num {{ $r->credit > 0 ? 'cr' : 'muted' }}">{{ $r->credit > 0 ? $money($r->credit) : '—' }}</td>
                                    <td class="num"><b>{{ $money(abs($r->balance)) }}</b> {!! $balTag($r->balance) !!}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="stmt-empty"><i class="far fa-folder-open"></i>No transactions in this period.</td></tr>
                            @endforelse
                            <tr class="row-close">
                                <td>{{ $end->format('d M Y') }}</td>
                                <td colspan="5">Closing Balance (carried forward)</td>
                                <td class="num">{{ $money(abs($closing)) }} {!! $balTag($closing) !!}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4">Period totals · {{ $rows->count() }} entries</td>
                                <td class="num">{{ $money($totalPaid) }}</td>
                                <td class="num">{{ $money($totalPurchased) }}</td>
                                <td class="num">{{ $money(abs($closing)) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="stmt-foot">
                    <span>Balance = Opening + Purchases − Payments & Returns. "Payable" means amount owed to the supplier.</span>
                    <span>Generated {{ now()->format('d M Y, h:i A') }} by {{ auth()->user()->name }}</span>
                </div>
            </div>
        @endif

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- ALL SUPPLIERS SUMMARY                                          --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    @else
        <div class="kpi-row crm-animate-in">
            @include('admin.partials.kpi-card', ['color' => 'indigo', 'icon' => 'fas fa-truck-loading', 'label' => 'Suppliers', 'value' => number_format($summary->count()), 'sub' => 'with activity or balance'])
            @include('admin.partials.kpi-card', ['color' => 'orange', 'icon' => 'fas fa-shopping-basket', 'label' => 'Purchased', 'value' => $money($sumPurchased), 'sub' => $periods[$period]])
            @include('admin.partials.kpi-card', ['color' => 'green', 'icon' => 'fas fa-money-bill-wave', 'label' => 'Paid', 'value' => $money($sumPaid), 'sub' => $periods[$period]])
            @include('admin.partials.kpi-card', ['color' => 'rose', 'icon' => 'fas fa-balance-scale', 'label' => 'Total Payable', 'value' => $money($sumClosing), 'sub' => 'as of ' . $end->format('d M Y')])
        </div>

        <div class="stmt crm-animate-in delay-2" id="print-area">
            @include('admin.report.partials.statement-header', ['title' => 'Supplier Balance Summary', 'subtitle' => 'Period: <b>' . $periodText . '</b>'])
            <div style="overflow-x:auto;">
                <table class="stmt-table" id="tbl-supplier-summary">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Supplier</th>
                            <th>Branch</th>
                            <th class="num">Opening</th>
                            <th class="num">Purchased (Cr)</th>
                            <th class="num">Paid (Dr)</th>
                            <th class="num">Closing (Payable)</th>
                            <th class="no-print" style="width:190px; text-align: center;">Statements</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($summary as $i => $s)
                            @php
                                $baseLink = url('admin/report/supplier-statement') . '?' . http_build_query(array_merge($query, ['supplier_id' => $s->id]));
                            @endphp
                            <tr>
                                <td class="muted">{{ $i + 1 }}</td>
                                <td><a href="{{ $baseLink }}">{{ $s->supplier_name }}</a></td>
                                <td class="muted">{{ $s->vendor_name ?? 'Head Office' }}</td>
                                <td class="num">{{ $money($s->opening) }}</td>
                                <td class="num cr">{{ $money($s->purchased) }}</td>
                                <td class="num dr">{{ $money($s->paid) }}</td>
                                <td class="num"><b>{{ $money(abs($s->closing)) }}</b> {!! $balTag($s->closing) !!}</td>
                                <td class="no-print" style="text-align: center;">
                                    <div class="btn-group btn-group-xs" role="group">
                                        <a href="{{ $baseLink . '&format=standard' }}" class="btn btn-outline-primary btn-xs" title="Standard Ledger">
                                            <i class="fas fa-file-invoice"></i> Ledger
                                        </a>
                                        <a href="{{ $baseLink . '&format=itemized' }}" class="btn btn-outline-info btn-xs" title="Itemized with Serials">
                                            <i class="fas fa-boxes"></i> Items
                                        </a>
                                        <a href="{{ $baseLink . '&format=aging' }}" class="btn btn-outline-warning btn-xs" title="Aging Analysis">
                                            <i class="fas fa-history"></i> Aging
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="stmt-empty"><i class="far fa-folder-open"></i>No supplier activity in this period.
                                <a href="{{ url('admin/report/supplier-statement') . '?' . http_build_query(array_merge($query, ['show_all' => 1])) }}">Show all suppliers</a></td></tr>
                        @endforelse
                    </tbody>
                    @if ($summary->isNotEmpty())
                        <tfoot>
                            <tr>
                                <td colspan="3">Total</td>
                                <td class="num">{{ $money($sumOpening) }}</td>
                                <td class="num">{{ $money($sumPurchased) }}</td>
                                <td class="num">{{ $money($sumPaid) }}</td>
                                <td class="num">{{ $money($sumClosing) }}</td>
                                <td class="no-print"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
            <div class="stmt-foot">
                <span>
                    @if (!request()->boolean('show_all'))
                        Suppliers with no activity and zero balance are hidden ·
                        <a class="no-print" href="{{ url('admin/report/supplier-statement') . '?' . http_build_query(array_merge($query, ['show_all' => 1])) }}">show all</a>
                    @endif
                </span>
                <span>Generated {{ now()->format('d M Y, h:i A') }} by {{ auth()->user()->name }}</span>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/plugins/select2/js/select2.min.js') }}"></script>
<script>
$(document).ready(function() {
    $('.select').select2({ width: '100%' });

    var itemsCollapsed = false;
    $('#btnToggleAllItems').on('click', function() {
        itemsCollapsed = !itemsCollapsed;
        if (itemsCollapsed) {
            $('.itemized-container').slideUp(200);
            $(this).html('<i class="fas fa-expand-arrows-alt mr-1"></i>Expand All Details');
        } else {
            $('.itemized-container').slideDown(200);
            $(this).html('<i class="fas fa-compress-arrows-alt mr-1"></i>Collapse All Details');
        }
    });
});
</script>
@endpush
