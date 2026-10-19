@extends('admin.layouts.master')
@section('page_title', 'Profit Statement')
@section('breadcrumb_parent', 'Reports')
@section('breadcrumb_parent_url', url('admin/reports'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<style>
    .ps-bar { display:flex; flex-wrap:wrap; gap:8px; align-items:center; padding:10px 14px; border-bottom:1px solid var(--card-border); }
    .ps-bar .form-control { width:auto; }
    .ps-kpis { display:grid; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr)); gap:10px; margin:0 0 14px; }
    .ps-kpi { background:var(--card-bg); border:1px solid var(--card-border); border-radius:12px; padding:12px 14px; }
    .ps-kpi small { color:var(--page-text-muted); display:block; }
    .ps-kpi b { font-size:20px; }
    .ps-kpi span { font-size:12px; }
    .ps-up { color:#059669; } .ps-down { color:#be123c; }
    .ps-table { width:100%; border-collapse:collapse; font-size:13.5px; }
    .ps-table th, .ps-table td { padding:7px 12px; border-bottom:1px solid var(--card-border); }
    .ps-table th { text-align:right; font-size:12px; color:var(--page-text-muted); text-transform:uppercase; letter-spacing:.03em; }
    .ps-table th:first-child, .ps-table td:first-child { text-align:left; }
    .ps-table td { text-align:right; font-variant-numeric:tabular-nums; }
    .ps-head td { font-weight:700; background:var(--accent-50); text-align:left !important; }
    .ps-total td { font-weight:600; border-top:1px solid var(--page-text-muted); }
    .ps-grand td { font-weight:700; font-size:15px; background:var(--accent-50); }
    .ps-neg { color:#be123c; }
    .ps-note { font-size:12.5px; color:var(--page-text-muted); padding:12px 14px; }
</style>
@endpush

@php
    $fmt = fn ($v) => $v < 0 ? '(' . number_format(abs($v), 2) . ')' : number_format($v, 2);
    $cols = $statement['columns'];
    $s = $statement['summary'];
    $delta = function ($now, $before) {
        if (! $before) { return null; }
        $p = round(($now - $before) / abs($before) * 100);
        return ($p >= 0 ? '+' : '') . $p . '% vs previous';
    };
@endphp

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-file-invoice-dollar" style="color: var(--accent-500); margin-right: 8px;"></i>Profit Statement</h1>
            <p>{{ $start->format('d M Y') }} to {{ $end->format('d M Y') }} · income and cost by invoice date, expenses by their category</p>
        </div>
        <div class="header-actions">
            <a href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}" class="btn btn-secondary btn-sm" id="btn-export-xlsx"><i class="fas fa-file-excel mr-1"></i>Excel</a>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-secondary btn-sm" id="btn-export-csv"><i class="fas fa-file-csv mr-1"></i>CSV</a>
        </div>
    </div>

    <div class="ps-kpis" id="ps-kpis">
        @foreach ([['Net sales', $s['net_sales'], $previous['net_sales']], ['Gross profit', $s['gross_profit'], $previous['gross_profit']], ['Net profit', $s['net_profit'], $previous['net_profit']]] as [$label, $now, $before])
            @php $d = $delta($now, $before); @endphp
            <div class="ps-kpi">
                <small>{{ $label }}</small>
                <b class="{{ $now < 0 ? 'ps-neg' : '' }}">{{ $fmt($now) }}</b>
                @if ($d)<span class="{{ $now >= $before ? 'ps-up' : 'ps-down' }}">{{ $d }}</span>@endif
            </div>
        @endforeach
        <div class="ps-kpi"><small>Net margin</small><b>{{ $s['margin'] === null ? '—' : $s['margin'] . '%' }}</b></div>
    </div>

    <div class="crm-table-card crm-animate-in delay-1">
        <form method="GET" action="{{ url('admin/report/profit-statement') }}" class="ps-bar" id="ps-filters">
            <select name="period" class="form-control form-control-sm" onchange="this.form.submit()">
                @foreach ($periods as $key => $label)<option value="{{ $key }}" @selected($period === $key)>{{ $label }}</option>@endforeach
            </select>
            @if ($period === 'custom')
                <input type="date" name="start_date" value="{{ $start->toDateString() }}" class="form-control form-control-sm">
                <input type="date" name="end_date" value="{{ $end->toDateString() }}" class="form-control form-control-sm">
                <button class="btn btn-secondary btn-sm" type="submit">Apply</button>
            @endif
            @if ($branches)
                <select name="vendor_id" class="form-control form-control-sm" onchange="this.form.submit()">
                    <option value="all">All branches</option>
                    <option value="hq" @selected($branch === 'hq')>Head office</option>
                    @foreach ($branches as $id => $name)<option value="{{ $id }}" @selected($branch === (string) $id)>{{ $name }}</option>@endforeach
                </select>
            @endif
        </form>

        <div style="overflow-x:auto;">
            <table class="ps-table" id="profit-table">
                <thead>
                    <tr>
                        <th></th>
                        @foreach ($cols as $name)<th>{{ $name }}</th>@endforeach
                        @if (count($cols) > 1)<th>Total</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($statement['rows'] as $r)
                        @if ($r['kind'] === 'head')
                            <tr class="ps-head"><td colspan="{{ count($cols) + (count($cols) > 1 ? 2 : 1) }}">{{ $r['label'] }}</td></tr>
                        @else
                            <tr class="{{ $r['kind'] === 'total' ? 'ps-total' : ($r['kind'] === 'grand' ? 'ps-grand' : '') }}" data-line="{{ \Illuminate\Support\Str::slug($r['label']) }}">
                                <td style="padding-left:{{ 12 + $r['indent'] * 18 }}px;">{{ $r['label'] }}</td>
                                @foreach ($r['vals'] as $v)<td class="{{ $v < 0 && $r['kind'] !== 'line' ? 'ps-neg' : '' }}">{{ $fmt($v) }}</td>@endforeach
                                @if (count($cols) > 1)<td class="{{ $r['total'] < 0 && $r['kind'] !== 'line' ? 'ps-neg' : '' }}"><b>{{ $fmt($r['total']) }}</b></td>@endif
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="ps-note" id="ps-checks">
            <b>How to read this.</b> Sales, returns and product cost are counted in the month of the invoice, so they do not depend on when money was received. Supplier payments, doctor payments, customer refunds, sale receipts and advances are cash movements of items counted above, so they are not repeated as expenses.
            @if ($statement['memo']['commission_paid_in_period'])
                <div>Referral commission actually paid out in this period: <b>{{ $fmt($statement['memo']['commission_paid_in_period']) }}</b> (the statement shows what was owed).</div>
            @endif
            @if ($statement['checks']['lines_without_cost'])
                <div class="ps-down"><b>{{ $statement['checks']['lines_without_cost'] }}</b> sold product line(s) have no purchase cost recorded, so profit may look higher than it is.</div>
            @endif
            @if ($statement['checks']['excluded'])
                <div style="margin-top:6px;">Ledger movements left out on purpose:
                    @foreach ($statement['checks']['excluded'] as $x)<span style="margin-right:12px;">{{ $x['what'] }} ({{ strtolower($x['side']) }}) {{ $fmt($x['amount']) }}</span>@endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
