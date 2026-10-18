@extends('admin.layouts.master')
@section('page_title', 'Doctor & Referral Statement')
@section('breadcrumb_parent', 'Reports')
@section('breadcrumb_parent_url', url('admin/report/doctor-statement'))

@push('css')
    @include('admin.report.partials.report-styles')
    <style>
        .badge-primary-light { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }
        .badge-danger-light { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-success-light { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }

        .btn-subfilter { font-size: 11.5px; font-weight: 700; padding: 4px 10px; border-radius: 6px; border: 1px solid var(--card-border); background: var(--card-bg); color: var(--page-text-secondary); text-decoration: none; display: inline-flex; align-items: center; transition: all .15s ease; }
        .btn-subfilter:hover { border-color: var(--accent-400, #818cf8); color: var(--accent-600, #4f46e5); text-decoration: none; }
        .btn-subfilter.active { background: #1e293b; color: #fff; border-color: #0f172a; }

        /* Clean, Compact Itemized Referral Layout (Matching Supplier Statement) */
        .patient-line { display: flex; align-items: baseline; flex-wrap: wrap; gap: 6px; font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 2px; }
        .patient-phone { font-size: 11px; font-weight: 600; color: #64748b; }
        .compact-item-list { margin-top: 4px; padding-left: 8px; border-left: 2px solid #e2e8f0; }
        .compact-item-row { display: flex; align-items: baseline; flex-wrap: wrap; gap: 6px; font-size: 12px; margin-top: 2px; line-height: 1.4; }
        .compact-item-row .bullet { font-size: 11px; font-weight: 800; margin-right: 2px; }
        .compact-item-row .bullet.prod { color: #6366f1; }
        .compact-item-row .bullet.serv { color: #0d9488; }
        .compact-item-row .item-title { font-weight: 600; color: #1e293b; }
        .compact-item-row .item-meta { font-size: 11px; color: #475569; background: rgba(0,0,0,.04); padding: 1px 6px; border-radius: 4px; font-weight: 600; }

        /* Serial Number Monospace Badges (Exact Supplier Statement Style) */
        .item-serial-wrap { font-size: 11px; color: #475569; margin-left: 14px; margin-top: 2px; margin-bottom: 4px; display: flex; align-items: center; flex-wrap: wrap; gap: 4px; }
        .sn-tag { font-size: 10px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: .03em; margin-right: 2px; }
        .serial-pill { display: inline-flex; align-items: center; gap: 3px; font-size: 11px; font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; font-weight: 600; background: #f8fafc; color: #0f172a; border: 1px solid #cbd5e1; padding: 1px 6px; border-radius: 4px; line-height: 1.3; }
        .serial-pill i { font-size: 9.5px; color: #6366f1; }

        [data-theme="dark"] .patient-line { color: #f8fafc; }
        [data-theme="dark"] .compact-item-list { border-left-color: rgba(255,255,255,.15); }
        [data-theme="dark"] .compact-item-row .item-title { color: #f1f5f9; }
        [data-theme="dark"] .compact-item-row .item-meta { background: rgba(255,255,255,.08); color: #cbd5e1; }
        [data-theme="dark"] .serial-pill { background: rgba(15,23,42,.8); color: #f1f5f9; border-color: rgba(255,255,255,.15); }
        [data-theme="dark"] .btn-subfilter.active { background: #38bdf8; color: #0f172a; border-color: #38bdf8; }
    </style>
@endpush

@php
    $money = fn ($v) => '৳' . number_format((float) $v, 2);
    $balTag = fn ($v) => abs($v) < 0.005 ? '' : ($v > 0 ? '' : '<span class="bal-tag adv">(Advance)</span>');
    $periodText = $start->format('d M Y') . ' — ' . $end->format('d M Y');
    $query = request()->except(['export', 'page']);
    $currentFormat = $format ?? 'standard';
    $currentItemType = $itemType ?? 'all';
@endphp

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-user-md" style="color: var(--accent-500); margin-right: 8px;"></i>Doctor & Referral Statement</h1>
            <p>
                @if($currentFormat === 'itemized')
                    Itemized statement of patient referrals, hearing aids/services sold, rates, and commissions
                @elseif($currentFormat === 'monthly')
                    Month-by-month referral performance, commission earnings, and settlement summary
                @else
                    Bank-statement style financial ledger of doctor commissions, adjustments, and payments
                @endif
            </p>
        </div>
        <div class="header-actions">
            @if ($doctor)
                <a href="{{ url('admin/report/doctor-statement') . '?' . http_build_query(\Illuminate\Support\Arr::except($query, ['doctor_id'])) }}" class="btn btn-secondary btn-sm" id="btn-all-doctors">
                    <i class="fas fa-list mr-1"></i>All Doctors
                </a>
                <a href="{{ route('doctors.payment.create', ['doctor_id' => $doctor->id]) }}" class="btn btn-success btn-sm">
                    <i class="fas fa-hand-holding-usd mr-1"></i>Settle & Pay Doctor
                </a>
            @endif
            <a href="{{ url('admin/report/doctor-statement') . '?' . http_build_query(array_merge($query, ['export' => 'csv'])) }}" class="btn btn-secondary btn-sm" id="btn-export-csv">
                <i class="fas fa-file-csv mr-1"></i>Export CSV
            </a>
            <button type="button" onclick="window.print()" class="btn btn-primary btn-sm" id="btn-print">
                <i class="fas fa-print mr-1"></i>Print
            </button>
        </div>
    </div>

    {{-- Filters Card --}}
    <div class="rpt-filter crm-animate-in delay-1">
        <form method="GET" action="{{ url('admin/report/doctor-statement') }}">
            <input type="hidden" name="format" value="{{ $currentFormat }}" id="input-format">
            <div class="f-group wide">
                <label for="f-doctor">Doctor / Referrer</label>
                <select name="doctor_id" id="f-doctor" class="select">
                    <option value="">-- All Doctors (Summary) --</option>
                    @foreach ($doctors as $d)
                        <option value="{{ $d->id }}" {{ $doctor && $doctor->id === $d->id ? 'selected' : '' }}>
                            {{ $d->doctor_name }} ({{ $d->qualification ?? 'MBBS' }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="f-group">
                <label for="f-period">Period Preset</label>
                <select name="period" id="f-period">
                    @foreach ($periods as $k => $label)
                        <option value="{{ $k }}" {{ $period === $k ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="f-group date">
                <label for="f-start">Start Date</label>
                <input type="date" name="start_date" id="f-start" value="{{ $start->toDateString() }}">
            </div>
            <div class="f-group date">
                <label for="f-end">End Date</label>
                <input type="date" name="end_date" id="f-end" value="{{ $end->toDateString() }}">
            </div>
            <div class="f-actions">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter mr-1"></i>Apply</button>
                <a href="{{ url('admin/report/doctor-statement') }}" class="btn btn-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>

    {{-- Statement View --}}
    @if ($doctor)

        {{-- Format Switcher Tabs & Category Sub-Filters --}}
        <div class="format-switcher crm-animate-in delay-1 no-print">
            <span class="lbl"><i class="fas fa-layer-group mr-1"></i>Statement Format:</span>
            <a href="{{ url('admin/report/doctor-statement') . '?' . http_build_query(array_merge($query, ['format' => 'standard'])) }}"
               class="btn-format {{ $currentFormat === 'standard' ? 'active' : '' }}">
                <i class="fas fa-file-invoice mr-1"></i>Standard Ledger
            </a>
            <a href="{{ url('admin/report/doctor-statement') . '?' . http_build_query(array_merge($query, ['format' => 'itemized'])) }}"
               class="btn-format {{ $currentFormat === 'itemized' ? 'active' : '' }}">
                <i class="fas fa-boxes mr-1"></i>Itemized (Patients & Services)
            </a>
            <a href="{{ url('admin/report/doctor-statement') . '?' . http_build_query(array_merge($query, ['format' => 'monthly'])) }}"
               class="btn-format {{ $currentFormat === 'monthly' ? 'active' : '' }}">
                <i class="fas fa-calendar-alt mr-1"></i>Month-wise Performance
            </a>

            @if ($currentFormat === 'itemized')
                <div class="ml-auto d-flex align-items-center flex-wrap" style="gap: 6px;">
                    <span class="lbl"><i class="fas fa-filter mr-1"></i>Category Filter:</span>
                    <a href="{{ url('admin/report/doctor-statement') . '?' . http_build_query(array_merge($query, ['item_type' => 'all'])) }}"
                       class="btn-subfilter {{ $currentItemType === 'all' ? 'active' : '' }}">
                        All (Combined)
                    </a>
                    <a href="{{ url('admin/report/doctor-statement') . '?' . http_build_query(array_merge($query, ['item_type' => 'products'])) }}"
                       class="btn-subfilter {{ $currentItemType === 'products' ? 'active' : '' }}" title="Show only sales with Hearing Aids & Products">
                        <i class="fas fa-boxes mr-1" style="color: #6366f1;"></i>Products ({{ $totalProductItems }})
                    </a>
                    <a href="{{ url('admin/report/doctor-statement') . '?' . http_build_query(array_merge($query, ['item_type' => 'services'])) }}"
                       class="btn-subfilter {{ $currentItemType === 'services' ? 'active' : '' }}" title="Show only sales with Clinical Tests & Services">
                        <i class="fas fa-stethoscope mr-1" style="color: #14b8a6;"></i>Services ({{ $totalServiceItems }})
                    </a>
                </div>
            @endif
        </div>

        {{-- ══════════════════════════════════════════════════════════════ --}}
        {{-- FORMAT 3: MONTH-WISE PERFORMANCE SUMMARY                       --}}
        {{-- ══════════════════════════════════════════════════════════════ --}}
        @if ($currentFormat === 'monthly')
            <div class="kpi-row crm-animate-in">
                @include('admin.partials.kpi-card', ['color' => 'slate', 'icon' => 'fas fa-flag', 'label' => 'Opening Balance', 'value' => $money($opening), 'sub' => 'as of ' . $start->format('d M Y')])
                @include('admin.partials.kpi-card', [
                    'color' => 'indigo',
                    'icon' => 'fas fa-hand-holding-usd',
                    'label' => 'Total Commission',
                    'value' => $money($totalCommission),
                    'sub' => '📦 ' . $money($totalProductCommission) . ' (' . $totalProductItems . ' pcs) · 🩺 ' . $money($totalServiceCommission) . ' (' . $totalServiceItems . ' tests)'
                ])
                @include('admin.partials.kpi-card', ['color' => 'green', 'icon' => 'fas fa-money-bill-wave', 'label' => 'Total Paid', 'value' => $money($totalPaid), 'sub' => $allRows->where('debit', '>', 0)->count() . ' disbursements'])
                @include('admin.partials.kpi-card', ['color' => $closing > 0 ? 'rose' : 'teal', 'icon' => 'fas fa-balance-scale', 'label' => $closing >= 0 ? 'Closing Balance (Payable)' : 'Closing Balance (Advance)', 'value' => $money(abs($closing)), 'sub' => 'as of ' . $end->format('d M Y')])
            </div>

            <div class="stmt crm-animate-in delay-2" id="print-area">
                @include('admin.report.partials.statement-header', [
                    'title' => 'Doctor Month-wise Settlement & Performance Summary',
                    'subtitle' => 'Period: <b>' . $periodText . '</b> · Doctor: <b>' . $doctor->doctor_name . '</b>'
                ])

                <div style="overflow-x:auto;">
                    <table class="stmt-table" id="tbl-monthly">
                        <thead>
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th>Month</th>
                                <th style="text-align: center; width: 120px;">Referred Sales</th>
                                <th class="num" style="width: 100px;">Products (Pcs)</th>
                                <th class="num">Product Comm. (৳)</th>
                                <th class="num" style="width: 100px;">Services (Tests)</th>
                                <th class="num">Service Comm. (৳)</th>
                                <th class="num">Total Comm. (৳)</th>
                                <th class="num">Paid (৳)</th>
                                <th class="num">Net Change (৳)</th>
                                <th class="no-print" style="width: 120px; text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($monthlySummary as $idx => $m)
                                <tr>
                                    <td class="muted">{{ $idx + 1 }}</td>
                                    <td class="font-weight-bold" style="font-size: 13.5px; color: #0f172a;">
                                        <i class="far fa-calendar-alt text-primary mr-1"></i>{{ $m->month_label }}
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge badge-light border font-weight-bold">{{ $m->referred_count }} Invoices</span>
                                    </td>
                                    <td class="num font-weight-bold" style="color: #4338ca;">
                                        {{ $m->product_count }} <small class="text-muted">pcs</small>
                                    </td>
                                    <td class="num cr">{{ $money($m->product_commission) }}</td>
                                    <td class="num font-weight-bold" style="color: #0d9488;">
                                        {{ $m->service_count }} <small class="text-muted">tests</small>
                                    </td>
                                    <td class="num cr">{{ $money($m->service_commission) }}</td>
                                    <td class="num cr font-weight-bold">{{ $money($m->commission_earned) }}</td>
                                    <td class="num dr font-weight-bold">{{ $money($m->paid_amount) }}</td>
                                    <td class="num {{ $m->net_change > 0 ? 'cr' : ($m->net_change < 0 ? 'dr' : 'muted') }}">
                                        <b>{{ $money(abs($m->net_change)) }}</b>
                                        <small class="text-muted">({{ $m->net_change >= 0 ? 'Due' : 'Surplus' }})</small>
                                    </td>
                                    <td class="no-print" style="text-align: center;">
                                        <a href="{{ route('doctors.payment.create', ['doctor_id' => $doctor->id]) }}" class="btn btn-outline-success btn-xs font-weight-bold">
                                            <i class="fas fa-hand-holding-usd mr-1"></i>Settle
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="stmt-empty">
                                        <i class="far fa-folder-open text-muted"></i>
                                        No monthly transactions found for this doctor in the selected period.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($monthlySummary->isNotEmpty())
                            <tfoot>
                                <tr>
                                    <td colspan="3">Period Totals</td>
                                    <td class="num font-weight-bold">{{ $monthlySummary->sum('product_count') }} pcs</td>
                                    <td class="num cr">{{ $money($monthlySummary->sum('product_commission')) }}</td>
                                    <td class="num font-weight-bold">{{ $monthlySummary->sum('service_count') }} tests</td>
                                    <td class="num cr">{{ $money($monthlySummary->sum('service_commission')) }}</td>
                                    <td class="num cr font-weight-bold">{{ $money($monthlySummary->sum('commission_earned')) }}</td>
                                    <td class="num dr font-weight-bold">{{ $money($monthlySummary->sum('paid_amount')) }}</td>
                                    <td class="num font-weight-bold">{{ $money($monthlySummary->sum('commission_earned') - $monthlySummary->sum('paid_amount')) }}</td>
                                    <td class="no-print"></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

                <div class="stmt-foot">
                    <span>Net Change = Commissions Earned in Month − Payments Disbursed in Month.</span>
                    <span>Generated {{ now()->format('d M Y, h:i A') }} by {{ auth()->user()->name }}</span>
                </div>
            </div>

        {{-- ══════════════════════════════════════════════════════════════ --}}
        {{-- FORMAT 1 & 2: STANDARD LEDGER & ITEMIZED (PATIENTS & SERVICES) --}}
        {{-- ══════════════════════════════════════════════════════════════ --}}
        @else
            <div class="kpi-row crm-animate-in">
                @include('admin.partials.kpi-card', ['color' => 'slate', 'icon' => 'fas fa-flag', 'label' => 'Opening Balance', 'value' => $money($opening), 'sub' => 'as of ' . $start->format('d M Y')])
                @include('admin.partials.kpi-card', [
                    'color' => 'indigo',
                    'icon' => 'fas fa-hand-holding-usd',
                    'label' => 'Commission Earned',
                    'value' => $money($totalCommission),
                    'sub' => '📦 ' . $money($totalProductCommission) . ' (' . $totalProductItems . ' pcs) · 🩺 ' . $money($totalServiceCommission) . ' (' . $totalServiceItems . ' tests)'
                ])
                @include('admin.partials.kpi-card', ['color' => 'green', 'icon' => 'fas fa-money-bill-wave', 'label' => 'Total Paid', 'value' => $money($totalPaid), 'sub' => $allRows->where('debit', '>', 0)->count() . ' disbursements'])
                @include('admin.partials.kpi-card', ['color' => $closing > 0 ? 'rose' : 'teal', 'icon' => 'fas fa-balance-scale', 'label' => $closing >= 0 ? 'Closing Balance (Payable)' : 'Closing Balance (Advance)', 'value' => $money(abs($closing)), 'sub' => 'as of ' . $end->format('d M Y')])
            </div>

            <div class="stmt crm-animate-in delay-2" id="print-area">
                @include('admin.report.partials.statement-header', [
                    'title' => ($currentFormat === 'itemized') ? 'Doctor Itemized Referral Statement (Patients, Products & Services)' : 'Doctor Referral Financial Statement',
                    'subtitle' => 'Statement period: <b>' . $periodText . '</b>'
                ])

                <div class="stmt-info">
                    <div>
                        <div class="i-lbl">Referral Doctor Information</div>
                        <div class="i-name">{{ $doctor->doctor_name }}</div>
                        <div class="i-line">
                            @if ($doctor->qualification)<strong>{{ $doctor->qualification }}</strong><br>@endif
                            @if ($doctor->address){{ $doctor->address }}<br>@endif
                            @if ($doctor->contact_number)<i class="fas fa-phone-alt" style="font-size:10px;"></i> {{ $doctor->contact_number }}@endif
                            @if ($doctor->email) &nbsp;·&nbsp; {{ $doctor->email }}@endif
                            <br>Affiliated Branch: <b>{{ $doctor->vendor_name ?? 'Head Office' }}</b>
                        </div>
                    </div>
                    <div>
                        <div class="i-lbl">Account summary</div>
                        <div class="sum-grid">
                            <span class="k">Opening balance</span><span class="v">{{ $money($opening) }}</span>
                            <span class="k">(+) Product Commissions</span><span class="v cr">{{ $money($totalProductCommission) }} <small class="text-muted">({{ $totalProductItems }} pcs)</small></span>
                            <span class="k">(+) Service Commissions</span><span class="v cr">{{ $money($totalServiceCommission) }} <small class="text-muted">({{ $totalServiceItems }} tests)</small></span>
                            <span class="k">(+) Total Commissions</span><span class="v font-weight-bold">{{ $money($totalCommission) }}</span>
                            <span class="k">(−) Payments & Deductions</span><span class="v dr">{{ $money($totalPaid) }}</span>
                            <span class="k"><b>Closing Balance ({{ $closing >= 0 ? 'Payable' : 'Advance' }})</b></span>
                            <span class="v big {{ $closing >= 0 ? 'cr' : 'dr' }}">{{ $money(abs($closing)) }}</span>
                        </div>
                    </div>
                </div>

                @if ($currentFormat === 'itemized')
                    <div class="p-2 px-3 border-bottom d-flex justify-content-between align-items-center bg-light no-print">
                        <small class="text-muted"><i class="fas fa-info-circle mr-1 text-primary"></i>Itemized breakdown includes patient names, referred hearing aid models, quantities, and rates.</small>
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
                                <th style="width: 170px;">Reference / Voucher #</th>
                                <th style="width: 110px;">Type</th>
                                <th class="num">Paid (Dr)</th>
                                <th class="num">Commission (Cr)</th>
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
                                    $isAdjustment = ($t === 'commission adjustment' || strpos(strtolower((string) $r->details), 'adjustment') !== false || strpos(strtolower((string) $r->details), 'return') !== false);
                                    $isPayment = ($t === 'payment' || $r->debit > 0);
                                    $pill = $isAdjustment ? 'other' : ($isPayment ? 'payment' : 'purchase');
                                    $pillText = $isAdjustment ? 'Adjustment' : ($isPayment ? 'Payment' : 'Referral');
                                    $hasItems = ($r->has_products ?? false) || ($r->has_services ?? false);
                                @endphp
                                <tr>
                                    <td class="muted">{{ \Carbon\Carbon::parse($r->date)->format('d M Y') }}</td>
                                    <td>
                                        @if ($isPayment)
                                            <div class="font-weight-600" style="color: #1e293b;">
                                                <span><i class="fas fa-money-bill-wave text-success mr-1"></i>Payment to Doctor</span>
                                                @if (!empty($r->payment_method))
                                                    <span class="badge badge-light border ml-1" style="font-size: 10px;">{{ $r->payment_method }}</span>
                                                @endif
                                                @if (!empty($r->bank_name))
                                                    <span class="text-muted small ml-1">({{ $r->bank_name }})</span>
                                                @endif
                                            </div>
                                        @elseif ($isAdjustment)
                                            <div class="font-weight-600" style="color: #dc2626;">
                                                <span><i class="fas fa-undo-alt text-danger mr-1"></i>Commission Adjustment (Return)</span>
                                            </div>
                                        @else
                                            <div class="patient-line">
                                                <span><i class="fas fa-user-check text-primary mr-1"></i>{{ $r->sale ? $r->sale->patient_name : 'Referred Patient' }}</span>
                                                @if (!empty($r->sale?->customer?->lead?->phone_number))
                                                    <span class="patient-phone">({{ $r->sale->customer->lead->phone_number }})</span>
                                                @elseif (!empty($r->sale?->customer?->phone_number))
                                                    <span class="patient-phone">({{ $r->sale->customer->phone_number }})</span>
                                                @endif
                                            </div>
                                        @endif

                                        {{-- Secondary text notes --}}
                                        @if (!empty($r->details) && !str_starts_with(strtolower(trim($r->details)), 'referred sale') && !str_starts_with(strtolower(trim($r->details)), 'commission'))
                                            <div class="text-muted" style="font-size: 11px; margin-top: 2px;">
                                                {{ $r->details }}
                                            </div>
                                        @endif

                                        {{-- ── ITEMIZED: CLEAN TYPOGRAPHIC BREAKDOWN ── --}}
                                        @if ($currentFormat === 'itemized' && $hasItems)
                                            <div class="compact-item-list itemized-container">
                                                {{-- Hearing Aids & Products --}}
                                                @if ($r->has_products && ($currentItemType === 'all' || $currentItemType === 'products'))
                                                    @foreach ($r->products as $it)
                                                        <div class="compact-item-row">
                                                            <span class="bullet prod">•</span>
                                                            <span class="item-title">{{ $it->product_name }}</span>
                                                            <span class="item-meta">{{ (float) $it->qty }} {{ (float) $it->qty > 1 ? 'pcs' : 'pc' }} @ ৳{{ number_format($it->mrp_price, 2) }}</span>
                                                            @if (!empty($it->discount) && $it->discount > 0)
                                                                <span class="text-muted" style="font-size: 10.5px;">(Disc: -৳{{ number_format($it->discount, 2) }})</span>
                                                            @endif
                                                        </div>
                                                        @if (!empty($it->serials) && count($it->serials) > 0)
                                                            <div class="item-serial-wrap">
                                                                <span class="sn-tag">SN:</span>
                                                                @foreach ($it->serials as $sn)
                                                                    <span class="serial-pill"><i class="fas fa-barcode"></i>{{ $sn }}</span>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                @endif

                                                {{-- Diagnostic & Clinical Services --}}
                                                @if ($r->has_services && ($currentItemType === 'all' || $currentItemType === 'services'))
                                                    @foreach ($r->services as $srv)
                                                        <div class="compact-item-row">
                                                            <span class="bullet serv">•</span>
                                                            <span class="item-title">{{ $srv->service_type_name }}</span>
                                                            <span class="item-meta">{{ (float) $srv->qty }} {{ (float) $srv->qty > 1 ? 'tests' : 'test' }} @ ৳{{ number_format($srv->service_price, 2) }}</span>
                                                            @if (!empty($srv->discount) && $srv->discount > 0)
                                                                <span class="text-muted" style="font-size: 10.5px;">(Disc: -৳{{ number_format($srv->discount, 2) }})</span>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                @endif
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Reference Column --}}
                                    <td>
                                        @if ($isPayment)
                                            <div class="font-weight-bold" style="font-size: 13px;">
                                                <i class="fas fa-receipt text-success mr-1"></i>{{ $r->ref_invoice_no }}
                                            </div>
                                            @if (!empty($r->transaction_no))
                                                <small class="text-muted d-block" style="font-size: 10.5px;">Trx: {{ $r->transaction_no }}</small>
                                            @endif
                                        @elseif (!empty($r->sale))
                                            <a href="{{ url('admin/sale/' . $r->sale->id) }}" target="_blank" class="font-weight-bold" style="font-size: 13px;">
                                                Bill #{{ $r->ref_invoice_no }}
                                            </a>
                                            <small class="text-muted d-block" style="font-size: 11px;">Sale Total: ৳{{ number_format($r->sale->total_amount, 2) }}</small>
                                        @else
                                            <span class="font-weight-bold" style="font-size: 13px;">{{ $r->ref_invoice_no ?: '—' }}</span>
                                        @endif
                                    </td>

                                    <td><span class="type-pill {{ $pill }}">{{ $pillText }}</span></td>
                                    <td class="num {{ $r->debit > 0 ? 'dr' : 'muted' }}">{{ $r->debit > 0 ? $money($r->debit) : '—' }}</td>
                                    <td class="num {{ $r->credit > 0 ? 'cr' : 'muted' }}">{{ $r->credit > 0 ? $money($r->credit) : '—' }}</td>
                                    <td class="num"><b>{{ $money(abs($r->balance)) }}</b> {!! $balTag($r->balance) !!}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="stmt-empty"><i class="far fa-folder-open"></i>No referral transactions in this period.</td></tr>
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
                                <td class="num">{{ $money($totalCommission) }}</td>
                                <td class="num">{{ $money(abs($closing)) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="stmt-foot">
                    <span>Balance = Opening + Commissions Earned − Payments & Adjustments. "Payable" means commission owed to doctor.</span>
                    <span>Generated {{ now()->format('d M Y, h:i A') }} by {{ auth()->user()->name }}</span>
                </div>
            </div>
        @endif

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- ALL DOCTORS REFERRAL & COMMISSION SUMMARY                     --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    @else
        <div class="kpi-row crm-animate-in">
            @include('admin.partials.kpi-card', ['color' => 'indigo', 'icon' => 'fas fa-user-md', 'label' => 'Active Doctors', 'value' => number_format($summary->count()), 'sub' => 'with activity or balance'])
            @include('admin.partials.kpi-card', ['color' => 'blue', 'icon' => 'fas fa-shopping-bag', 'label' => 'Referred Sales', 'value' => $money($sumSales), 'sub' => $periods[$period]])
            @include('admin.partials.kpi-card', ['color' => 'orange', 'icon' => 'fas fa-hand-holding-usd', 'label' => 'Commission Earned', 'value' => $money($sumCommission), 'sub' => $periods[$period]])
            @include('admin.partials.kpi-card', ['color' => 'green', 'icon' => 'fas fa-money-bill-wave', 'label' => 'Total Paid', 'value' => $money($sumPaid), 'sub' => $periods[$period]])
            @include('admin.partials.kpi-card', ['color' => 'rose', 'icon' => 'fas fa-balance-scale', 'label' => 'Total Payable', 'value' => $money($sumClosing), 'sub' => 'as of ' . $end->format('d M Y')])
        </div>

        <div class="stmt crm-animate-in delay-2" id="print-area">
            @include('admin.report.partials.statement-header', [
                'title' => 'Doctor & Referral Commission Summary',
                'subtitle' => 'Period: <b>' . $periodText . '</b> · Branch: <b>' . (auth()->user()->isVendor() ? auth()->user()->name : 'All Branches') . '</b>'
            ])

            <div style="overflow-x:auto;">
                <table class="stmt-table" id="tbl-doctor-summary">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Doctor Name</th>
                            <th>Branch</th>
                            <th class="num">Referred Sales</th>
                            <th class="num">Opening Due</th>
                            <th class="num">Commission (Cr)</th>
                            <th class="num">Paid (Dr)</th>
                            <th class="num">Closing (Payable)</th>
                            <th class="no-print" style="width:230px; text-align: center;">Statements & Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($summary as $i => $d)
                            @php
                                $baseLink = url('admin/report/doctor-statement') . '?' . http_build_query(array_merge($query, ['doctor_id' => $d->id]));
                            @endphp
                            <tr>
                                <td class="muted">{{ $i + 1 }}</td>
                                <td>
                                    <a href="{{ $baseLink }}" class="font-weight-bold" style="font-size: 13.5px; color: #0f172a;">
                                        {{ $d->doctor_name }}
                                    </a>
                                    @if ($d->qualification)
                                        <small class="text-muted d-block" style="font-size: 11px;">{{ $d->qualification }}</small>
                                    @endif
                                </td>
                                <td class="muted">{{ $d->vendor_name ?? 'Head Office' }}</td>
                                <td class="num font-weight-bold" style="color: #1e293b;">{{ $money($d->sales_value) }}</td>
                                <td class="num">{{ $money($d->opening) }}</td>
                                <td class="num cr">{{ $money($d->commission) }}</td>
                                <td class="num dr">{{ $money($d->paid) }}</td>
                                <td class="num font-weight-bold" style="color: #b91c1c;">
                                    <b>{{ $money(abs($d->closing)) }}</b> {!! $balTag($d->closing) !!}
                                </td>
                                <td class="no-print" style="text-align: center;">
                                    <div class="btn-group btn-group-xs" role="group">
                                        <a href="{{ $baseLink . '&format=standard' }}" class="btn btn-outline-primary btn-xs" title="Standard Ledger">
                                            <i class="fas fa-file-invoice"></i> Ledger
                                        </a>
                                        <a href="{{ $baseLink . '&format=itemized' }}" class="btn btn-outline-info btn-xs" title="Itemized with Patients">
                                            <i class="fas fa-boxes"></i> Items
                                        </a>
                                        <a href="{{ $baseLink . '&format=monthly' }}" class="btn btn-outline-secondary btn-xs" title="Monthly Summary">
                                            <i class="fas fa-calendar-alt"></i> Monthly
                                        </a>
                                        <a href="{{ route('doctors.payment.create', ['doctor_id' => $d->id]) }}" class="btn btn-success btn-xs" title="Settle & Pay">
                                            <i class="fas fa-hand-holding-usd"></i> Pay
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="stmt-empty">
                                    <i class="far fa-folder-open text-muted"></i>No doctor activity in this period.
                                    <a href="{{ url('admin/report/doctor-statement') . '?' . http_build_query(array_merge($query, ['show_all' => 1])) }}">Show all doctors</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($summary->isNotEmpty())
                        <tfoot>
                            <tr>
                                <td colspan="3">Total · {{ $summary->count() }} Doctors</td>
                                <td class="num">{{ $money($sumSales) }}</td>
                                <td class="num">{{ $money($sumOpening) }}</td>
                                <td class="num">{{ $money($sumCommission) }}</td>
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
                        Doctors with no activity and zero balance are hidden ·
                        <a class="no-print" href="{{ url('admin/report/doctor-statement') . '?' . http_build_query(array_merge($query, ['show_all' => 1])) }}">show all</a>
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
