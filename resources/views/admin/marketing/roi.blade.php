@extends('admin.layouts.master')
@section('page_title', 'Marketing Return')
@section('breadcrumb_parent', 'Marketing')
@section('breadcrumb_parent_url', url('admin/marketing/roi'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/kpi-cards.css') }}">
<style>
.mr-filters { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
.mr-filters .form-control { width: auto; }
.mr-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin: 4px 0 12px; }
.mr-tabs a { font-size: 12.5px; padding: 4px 12px; border-radius: 20px; border: 1px solid var(--gray-200); color: var(--page-text); text-decoration: none; }
.mr-tabs a.active { background: var(--accent-600, #2563eb); border-color: var(--accent-600, #2563eb); color: #fff; }
.mr-table th, .mr-table td { white-space: nowrap; font-size: 12.5px; }
.mr-table td.num, .mr-table th.num { text-align: right; font-variant-numeric: tabular-nums; }
.mr-good { color: var(--success-700, #15803d); font-weight: 600; } .mr-bad { color: var(--danger-600, #dc2626); font-weight: 600; }
.mr-note { font-size: 12px; color: var(--page-text-muted); }
.mr-quality { display: flex; gap: 16px; align-items: center; flex-wrap: wrap; }
.mr-bar { height: 8px; background: var(--gray-100); border-radius: 6px; overflow: hidden; width: 200px; }
</style>
@endpush

@php
    $money = fn ($v) => $v === null ? '—' : '৳' . number_format((float) $v);
    $x = fn ($v) => $v === null ? '—' : number_format((float) $v, 2) . '×';
    $q = fn (array $extra) => url('admin/marketing/roi') . '?' . http_build_query(array_filter(array_merge(request()->only(['from', 'to', 'group', 'basis', 'touch', 'branch']), $extra), fn ($v) => $v !== null && $v !== ''));
    $groups = ['channel' => 'Channel', 'campaign' => 'Campaign', 'branch' => 'Branch', 'month' => 'Month'];
    $t = $total;
@endphp

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-bullseye" style="color: var(--accent-500); margin-right: 8px;"></i>Marketing Return</h1>
            <p>{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }} · {{ $basis === 'cohort' ? 'what the enquiries of this period bought (any time)' : 'sales made in this period' }} · credit to the {{ $touch === 'first' ? 'first touch' : 'last enquiry' }}</p>
        </div>
        <form method="GET" class="mr-filters">
            <input type="hidden" name="group" value="{{ $group }}">
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm" aria-label="From">
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm" aria-label="To">
            <select name="basis" class="form-control form-control-sm" aria-label="Basis">
                <option value="period" @selected($basis === 'period')>Sales in period</option>
                <option value="cohort" @selected($basis === 'cohort')>By enquiry month</option>
            </select>
            <select name="touch" class="form-control form-control-sm" aria-label="Credit">
                <option value="last" @selected($touch === 'last')>Last enquiry</option>
                <option value="first" @selected($touch === 'first')>First touch</option>
            </select>
            @if ($isHq)
                <select name="branch" class="form-control form-control-sm" aria-label="Branch">
                    <option value="">All branches</option>
                    @foreach ($branches as $bid => $bname)<option value="{{ $bid }}" @selected((string) $branch === (string) $bid)>{{ $bname }}</option>@endforeach
                </select>
            @endif
            <button class="btn btn-primary btn-sm" type="submit" id="mrApply"><i class="fas fa-filter mr-1"></i>Apply</button>
            @can('permission', 'marketing_roi.export')
                <a href="{{ route('marketing.roi.export', request()->query()) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-download mr-1"></i>CSV</a>
            @endcan
        </form>
    </div>

    <div class="kpi-row crm-animate-in delay-1">
        @include('admin.partials.kpi-card', ['id' => 'mr-kpi-spend', 'color' => 'indigo', 'icon' => 'fas fa-wallet',
            'label' => 'Acquisition cost', 'value' => $money($t['acquisition_cost']), 'sub' => $money($t['spend']) . ' ads + ' . $money($t['commission']) . ' referral commission'])
        @include('admin.partials.kpi-card', ['id' => 'mr-kpi-enq', 'color' => 'slate', 'icon' => 'fas fa-user-plus',
            'label' => 'Enquiries', 'value' => number_format($t['enquiries']), 'sub' => 'cost per enquiry ' . $money($t['cost_per_enquiry'])])
        @include('admin.partials.kpi-card', ['id' => 'mr-kpi-sales', 'color' => 'green', 'icon' => 'fas fa-shopping-bag',
            'label' => 'Sales', 'value' => number_format($t['sales']), 'sub' => 'cost per sale ' . $money($t['cost_per_sale'])])
        @include('admin.partials.kpi-card', ['id' => 'mr-kpi-roas', 'color' => ($t['roas'] !== null && $t['roas'] < 1) ? 'rose' : 'violet', 'icon' => 'fas fa-chart-line',
            'label' => 'Revenue / cost (ROAS)', 'value' => $x($t['roas']), 'sub' => $money($t['revenue']) . ' revenue · ' . $money($t['gross_profit']) . ' gross profit'])
    </div>

    <div class="crm-table-card" style="padding: 12px 16px; margin-bottom: 12px;">
        <div class="mr-quality">
            <strong style="font-size:13px;">Enquiries with a known source:</strong>
            <div class="mr-bar"><div style="height:100%; width:{{ $quality['pct'] ?? 0 }}%; background:{{ ($quality['pct'] ?? 0) >= 85 ? 'var(--success-500)' : 'var(--warning-500)' }};"></div></div>
            <span>{{ $quality['pct'] !== null ? $quality['pct'] . '%' : '—' }} ({{ $quality['with_source'] }} of {{ $quality['enquiries'] }})</span>
            @if (count($unattributed))
                <a href="#mrUnknown" data-toggle="collapse" class="mr-note">Show enquiries without a source ({{ count($unattributed) }}{{ count($unattributed) >= 50 ? '+' : '' }})</a>
            @endif
        </div>
        @if (count($unattributed))
        <div class="collapse" id="mrUnknown">
            <table class="table table-sm mt-2 mb-0" style="font-size:12.5px;">
                <thead><tr><th>Patient</th><th>Owner</th><th>Date</th><th></th></tr></thead>
                <tbody>
                @foreach ($unattributed as $u)
                    <tr><td>{{ $u['name'] }}</td><td>{{ $u['owner'] }}</td><td>{{ $u['date'] }}</td><td><a href="{{ url('admin/leads/' . $u['lead_id'] . '/edit') }}">Add source</a></td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    <div class="mr-tabs">
        @foreach ($groups as $k => $label)<a href="{{ $q(['group' => $k]) }}" class="{{ $group === $k ? 'active' : '' }}">By {{ strtolower($label) }}</a>@endforeach
    </div>

    <div class="crm-table-card" style="padding: 0;">
        <div class="table-responsive">
            <table class="table table-sm mb-0 mr-table">
                <thead>
                    <tr>
                        <th>{{ $groups[$group] }}</th>
                        <th class="num">Ad spend</th><th class="num">Commission</th>
                        <th class="num">Enquiries</th><th class="num">Appoint&shy;ments</th><th class="num">Trials</th><th class="num">Sales</th>
                        <th class="num">Revenue</th><th class="num">Gross profit</th>
                        <th class="num">Per enquiry</th><th class="num">Per appointment</th><th class="num">Per sale</th>
                        <th class="num">ROAS</th><th class="num">Profit / ৳</th><th class="num">Enquiry → sale</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td>{{ $r['label'] }}</td>
                        <td class="num">{{ $money($r['spend']) }}</td><td class="num">{{ $money($r['commission']) }}</td>
                        <td class="num">{{ number_format($r['enquiries']) }}</td><td class="num">{{ number_format($r['appointments']) }}</td><td class="num">{{ number_format($r['trials']) }}</td><td class="num">{{ number_format($r['sales']) }}</td>
                        <td class="num">{{ $money($r['revenue']) }}</td><td class="num">{{ $money($r['gross_profit']) }}</td>
                        <td class="num">{{ $money($r['cost_per_enquiry']) }}</td><td class="num">{{ $money($r['cost_per_appointment']) }}</td><td class="num">{{ $money($r['cost_per_sale']) }}</td>
                        <td class="num {{ $r['roas'] === null ? '' : ($r['roas'] >= 1 ? 'mr-good' : 'mr-bad') }}">{{ $x($r['roas']) }}</td>
                        <td class="num {{ $r['poas'] === null ? '' : ($r['poas'] >= 1 ? 'mr-good' : 'mr-bad') }}">{{ $x($r['poas']) }}</td>
                        <td class="num">{{ $r['enquiry_to_sale'] !== null ? $r['enquiry_to_sale'] . '%' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="15" class="text-center text-muted" style="padding: 30px;">Nothing in this period yet.</td></tr>
                @endforelse
                </tbody>
                @if (count($rows))
                <tfoot>
                    <tr style="font-weight:700;">
                        <td>Total</td>
                        <td class="num">{{ $money($t['spend']) }}</td><td class="num">{{ $money($t['commission']) }}</td>
                        <td class="num">{{ number_format($t['enquiries']) }}</td><td class="num">{{ number_format($t['appointments']) }}</td><td class="num">{{ number_format($t['trials']) }}</td><td class="num">{{ number_format($t['sales']) }}</td>
                        <td class="num">{{ $money($t['revenue']) }}</td><td class="num">{{ $money($t['gross_profit']) }}</td>
                        <td class="num">{{ $money($t['cost_per_enquiry']) }}</td><td class="num">{{ $money($t['cost_per_appointment']) }}</td><td class="num">{{ $money($t['cost_per_sale']) }}</td>
                        <td class="num">{{ $x($t['roas']) }}</td><td class="num">{{ $x($t['poas']) }}</td>
                        <td class="num">{{ $t['enquiry_to_sale'] !== null ? $t['enquiry_to_sale'] . '%' : '—' }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    <p class="mr-note mt-3">
        <strong>How it is counted.</strong>
        Revenue is invoiced sales after returns; gross profit takes off the purchase cost of the devices sold.
        Acquisition cost = ad spend (Monthly Spend) + referral commission on the sales counted here.
        Per sale and ROAS use the acquisition cost; per enquiry and per appointment use ad spend only.
        Appointments and trials are enquiries that reached the Assessment and Trial stages.
        A sale is credited to the enquiry it came from; a sale without one goes to how the patient first reached you, then to the doctor or referrer on the invoice, otherwise to “Unknown / direct”.
        With “By enquiry month”, sales are those of the period’s enquiries whenever they bought, which suits decisions that take weeks.
    </p>
</div>
@endsection
