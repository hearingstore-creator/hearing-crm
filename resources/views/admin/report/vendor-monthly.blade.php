@extends('admin.layouts.master')
@section('page_title', 'Branch Monthly Report')
@section('breadcrumb_parent', 'Reports')
@section('breadcrumb_parent_url', url('admin/report/vendor-monthly'))

@push('css')
    @include('admin.report.partials.report-styles')
    <style>
        .vm-table th.grp { text-align: center; background: rgba(99,102,241,.06); color: var(--accent-600, #4f46e5); border-bottom: 1px solid var(--card-border); }
        .vm-table th.grp-sales { background: rgba(16,185,129,.07); color: #047857; }
        .vm-table th.grp-cost { background: rgba(249,115,22,.07); color: #c2410c; }
        .vm-table th.grp-res { background: rgba(139,92,246,.07); color: #6d28d9; }
        .vm-table td.sep, .vm-table th.sep { border-left: 1px solid var(--card-border); }
        .vm-chart { padding: 18px 22px 8px; height: 300px; }
        .month-nav { display: inline-flex; align-items: center; gap: 4px; }
        .month-nav a { width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--card-border); border-radius: 8px; color: var(--page-text-secondary); background: var(--card-bg); }
        .month-nav a:hover { color: var(--accent-600, #4f46e5); border-color: var(--accent-300, #a5b4fc); }
        .neg { color: #b91c1c !important; }
        @media print { .vm-chart { display: none; } .vm-table { font-size: 9.5px; } }
    </style>
@endpush

@php
    $money = fn ($v) => '৳' . number_format((float) $v, 0);
    $q = request()->except(['export', 'month']);
    $prevMonth = $mStart->copy()->subMonthNoOverflow()->format('Y-m');
    $nextMonth = $mStart->copy()->addMonthNoOverflow()->format('Y-m');
    $change = function ($now, $before) {
        if ($before <= 0) return $now > 0 ? '<span class="trend-up"><i class="fas fa-arrow-up"></i> new</span>' : '<span class="muted">no change</span>';
        $p = round(($now - $before) / $before * 100);
        return $p >= 0 ? '<span class="trend-up"><i class="fas fa-arrow-up"></i> ' . $p . '%</span> vs last month'
                       : '<span class="trend-down"><i class="fas fa-arrow-down"></i> ' . abs($p) . '%</span> vs last month';
    };
    $scopeName = $isVendor ? auth()->user()->name : ($vendorFilter === 'hq' ? 'Head Office' : (in_array($vendorFilter, ['all', '', null], true) ? 'All branches' : optional($vendors->firstWhere('id', (int) $vendorFilter))->name));
@endphp

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-store-alt" style="color: var(--accent-500); margin-right: 8px;"></i>Branch Monthly Report</h1>
            <p>Branch-wise leads, sales, collection, purchases, expenses and profit for the month</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/report/vendor-monthly') . '?' . http_build_query(request()->except('export') + ['month' => $month, 'export' => 'csv']) }}" class="btn btn-secondary btn-sm" id="btn-export-csv">
                <i class="fas fa-file-csv mr-1"></i>Export CSV
            </a>
            <button type="button" onclick="window.print()" class="btn btn-primary btn-sm" id="btn-print"><i class="fas fa-print mr-1"></i>Print</button>
        </div>
    </div>

    <div class="rpt-filter crm-animate-in delay-1">
        <form method="GET" action="{{ url('admin/report/vendor-monthly') }}">
            <div class="f-group" style="flex:0 0 auto;">
                <label for="f-month">Month</label>
                <div class="month-nav">
                    <a href="{{ url('admin/report/vendor-monthly') . '?' . http_build_query($q + ['month' => $prevMonth]) }}" title="Previous month" id="btn-prev-month"><i class="fas fa-chevron-left"></i></a>
                    <input type="month" name="month" id="f-month" value="{{ $month }}" max="{{ now()->format('Y-m') }}" style="width:170px;">
                    <a href="{{ url('admin/report/vendor-monthly') . '?' . http_build_query($q + ['month' => $nextMonth]) }}" title="Next month" id="btn-next-month"><i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
            @unless ($isVendor)
                <div class="f-group">
                    <label for="f-vendor">Branch</label>
                    <select name="vendor_id" id="f-vendor" class="select">
                        <option value="all">All branches</option>
                        <option value="hq" {{ $vendorFilter === 'hq' ? 'selected' : '' }}>Head Office</option>
                        @foreach ($vendors as $v)
                            <option value="{{ $v->id }}" {{ (string) $vendorFilter === (string) $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endunless
            <div class="f-actions">
                <button type="submit" class="btn btn-primary btn-sm" id="btn-filter"><i class="fas fa-search mr-1"></i>Show</button>
                <a href="{{ url('admin/report/vendor-monthly') }}" class="btn btn-outline-secondary btn-sm" title="Reset"><i class="fas fa-undo"></i></a>
            </div>
        </form>
    </div>

    <div class="kpi-row crm-animate-in">
        @include('admin.partials.kpi-card', ['color' => 'green', 'icon' => 'fas fa-chart-line', 'label' => 'Sales', 'value' => $money($totals['sales_amount']), 'sub' => $change($totals['sales_amount'], $prevTotals['sales_amount'])])
        @include('admin.partials.kpi-card', ['color' => 'teal', 'icon' => 'fas fa-hand-holding-usd', 'label' => 'Collected', 'value' => $money($totals['sales_paid']), 'sub' => 'Due <b>' . $money($totals['sales_due']) . '</b>'])
        @include('admin.partials.kpi-card', ['color' => 'orange', 'icon' => 'fas fa-shopping-basket', 'label' => 'Purchases', 'value' => $money($totals['purchase_amount']), 'sub' => $change($totals['purchase_amount'], $prevTotals['purchase_amount'])])
        @include('admin.partials.kpi-card', ['color' => $totals['net'] >= 0 ? 'violet' : 'rose', 'icon' => 'fas fa-coins', 'label' => 'Est. Net (GP − Exp.)', 'value' => $money($totals['net']), 'sub' => 'Gross profit <b>' . $money($totals['gross_profit']) . '</b> · Exp. <b>' . $money($totals['expenses']) . '</b>'])
        @include('admin.partials.kpi-card', ['color' => 'indigo', 'icon' => 'fas fa-user-plus', 'label' => 'New Leads', 'value' => number_format($totals['leads']), 'sub' => '<b>' . number_format($totals['customers']) . '</b> new customers · ' . $change($totals['leads'], $prevTotals['leads'])])
    </div>

    <div class="stmt crm-animate-in delay-2" id="print-area">
        @include('admin.report.partials.statement-header', [
            'title' => 'Branch Monthly Report',
            'subtitle' => 'Month: <b>' . $mStart->format('F Y') . '</b><br>' . e($scopeName ?? 'All branches'),
        ])

        <div style="overflow-x:auto;">
            <table class="stmt-table vm-table" id="tbl-vendor-monthly">
                <thead>
                    <tr>
                        <th rowspan="2" style="vertical-align:bottom;">Branch</th>
                        <th colspan="3" class="grp sep">CRM</th>
                        <th colspan="4" class="grp grp-sales sep">Sales</th>
                        <th colspan="2" class="grp grp-cost sep">Purchases</th>
                        <th colspan="4" class="grp grp-res sep">Profitability</th>
                    </tr>
                    <tr>
                        <th class="num sep">Leads</th><th class="num">Customers</th><th class="num">Appts</th>
                        <th class="num sep">Invoices</th><th class="num">Sales</th><th class="num">Collected</th><th class="num">Due</th>
                        <th class="num sep">Amount</th><th class="num">Paid</th>
                        <th class="num sep">Est. COGS</th><th class="num">Gross Profit</th><th class="num">Expenses</th><th class="num">Net</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td>
                                @if (!$isVendor)
                                    <a href="{{ url('admin/report/vendor-monthly') . '?' . http_build_query(['month' => $month, 'vendor_id' => $r->branch->key]) }}">{{ $r->branch->name }}</a>
                                @else
                                    <b>{{ $r->branch->name }}</b>
                                @endif
                            </td>
                            <td class="num sep">{{ number_format($r->leads) }}</td>
                            <td class="num">{{ number_format($r->customers) }}</td>
                            <td class="num">{{ number_format($r->appointments) }}</td>
                            <td class="num sep">{{ number_format($r->sales_count) }}</td>
                            <td class="num"><b>{{ $money($r->sales_amount) }}</b></td>
                            <td class="num dr">{{ $money($r->sales_paid) }}</td>
                            <td class="num {{ $r->sales_due > 0.5 ? 'cr' : 'muted' }}">{{ $money($r->sales_due) }}</td>
                            <td class="num sep">{{ $money($r->purchase_amount) }}</td>
                            <td class="num muted">{{ $money($r->purchase_paid) }}</td>
                            <td class="num sep muted">{{ $money($r->cogs) }}</td>
                            <td class="num {{ $r->gross_profit < 0 ? 'neg' : '' }}">{{ $money($r->gross_profit) }}</td>
                            <td class="num muted">{{ $money($r->expenses) }}</td>
                            <td class="num {{ $r->net < 0 ? 'neg' : 'dr' }}"><b>{{ $money($r->net) }}</b></td>
                        </tr>
                    @empty
                        <tr><td colspan="14" class="stmt-empty"><i class="far fa-folder-open"></i>No branches to show.</td></tr>
                    @endforelse
                </tbody>
                @if ($rows->count() > 1)
                    <tfoot>
                        <tr>
                            <td>Total ({{ $rows->count() }} branches)</td>
                            <td class="num sep">{{ number_format($totals['leads']) }}</td>
                            <td class="num">{{ number_format($totals['customers']) }}</td>
                            <td class="num">{{ number_format($totals['appointments']) }}</td>
                            <td class="num sep">{{ number_format($totals['sales_count']) }}</td>
                            <td class="num">{{ $money($totals['sales_amount']) }}</td>
                            <td class="num">{{ $money($totals['sales_paid']) }}</td>
                            <td class="num">{{ $money($totals['sales_due']) }}</td>
                            <td class="num sep">{{ $money($totals['purchase_amount']) }}</td>
                            <td class="num">{{ $money($totals['purchase_paid']) }}</td>
                            <td class="num sep">{{ $money($totals['cogs']) }}</td>
                            <td class="num">{{ $money($totals['gross_profit']) }}</td>
                            <td class="num">{{ $money($totals['expenses']) }}</td>
                            <td class="num">{{ $money($totals['net']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        {{-- 12-month trend --}}
        <div class="stmt-info print-break" style="grid-template-columns:1fr; border-top:1px solid var(--card-border);">
            <div>
                <div class="i-lbl">{{ $mStart->format('Y') }} month-by-month trend · {{ $scopeName ?? 'All branches' }}</div>
            </div>
        </div>
        <div class="vm-chart no-print"><canvas id="trendChart"></canvas></div>
        <div style="overflow-x:auto;">
            <table class="stmt-table" id="tbl-vendor-trend">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th class="num">Leads</th><th class="num">Customers</th><th class="num">Invoices</th>
                        <th class="num">Sales</th><th class="num">Collected</th><th class="num">Purchases</th>
                        <th class="num">Expenses</th><th class="num">Gross Profit</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($trend as $t)
                        <tr @if ($t['key'] === $month) style="font-weight:700;" @endif>
                            <td><a href="{{ url('admin/report/vendor-monthly') . '?' . http_build_query($q + ['month' => $t['key']]) }}">{{ $t['month'] }}</a></td>
                            <td class="num">{{ number_format($t['leads']) }}</td>
                            <td class="num">{{ number_format($t['customers']) }}</td>
                            <td class="num">{{ number_format($t['sales_count']) }}</td>
                            <td class="num"><b>{{ $money($t['sales_amount']) }}</b></td>
                            <td class="num dr">{{ $money($t['sales_paid']) }}</td>
                            <td class="num">{{ $money($t['purchase_amount']) }}</td>
                            <td class="num muted">{{ $money($t['expenses']) }}</td>
                            <td class="num {{ $t['gross_profit'] < 0 ? 'neg' : '' }}">{{ $money($t['gross_profit']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td>Year to date</td>
                        <td class="num">{{ number_format(array_sum(array_column($trend, 'leads'))) }}</td>
                        <td class="num">{{ number_format(array_sum(array_column($trend, 'customers'))) }}</td>
                        <td class="num">{{ number_format(array_sum(array_column($trend, 'sales_count'))) }}</td>
                        <td class="num">{{ $money(array_sum(array_column($trend, 'sales_amount'))) }}</td>
                        <td class="num">{{ $money(array_sum(array_column($trend, 'sales_paid'))) }}</td>
                        <td class="num">{{ $money(array_sum(array_column($trend, 'purchase_amount'))) }}</td>
                        <td class="num">{{ $money(array_sum(array_column($trend, 'expenses'))) }}</td>
                        <td class="num">{{ $money(array_sum(array_column($trend, 'gross_profit'))) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="stmt-foot">
            <span>Sales by invoice date · Est. COGS = qty × purchase price on sold items · Expenses exclude purchase postings.</span>
            <span>Generated {{ now()->format('d M Y, h:i A') }} by {{ auth()->user()->name }}</span>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/plugins/select2/js/select2.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('trendChart');
    if (!el || typeof Chart === 'undefined') return;
    const t = @json($trend);
    new Chart(el.getContext('2d'), {
        type: 'bar',
        data: {
            labels: t.map(r => r.month),
            datasets: [
                { label: 'Sales', data: t.map(r => r.sales_amount), backgroundColor: 'rgba(16,185,129,.75)', borderRadius: 6, maxBarThickness: 26 },
                { label: 'Purchases', data: t.map(r => r.purchase_amount), backgroundColor: 'rgba(249,115,22,.65)', borderRadius: 6, maxBarThickness: 26 },
                { label: 'Gross Profit', data: t.map(r => r.gross_profit), type: 'line', borderColor: '#6366f1', backgroundColor: '#6366f1', tension: .35, pointRadius: 3, borderWidth: 2 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } },
                       tooltip: { callbacks: { label: c => c.dataset.label + ': ৳' + Number(c.raw).toLocaleString() } } },
            scales: { y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,.12)' }, ticks: { callback: v => v >= 1000 ? '৳' + (v / 1000) + 'k' : '৳' + v } },
                      x: { grid: { display: false } } }
        }
    });
});
</script>
@endpush
