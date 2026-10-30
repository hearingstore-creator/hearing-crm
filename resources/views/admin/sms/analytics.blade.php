@extends('admin.layouts.master')
@section('page_title', 'SMS Analytics')
@section('breadcrumb_parent', 'SMS Centre')
@section('breadcrumb_parent_url', url('admin/sms'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<style>
/* ───────── SMS Analytics (view only – all figures come from the controller unchanged) ───────── */
.sa { --sa-blue:#3b82f6; --sa-green:#22c55e; --sa-red:#ef4444; --sa-purple:#8b5cf6; --sa-amber:#f59e0b; --sa-teal:#14b8a6; --sa-gray:#94a3b8; }
.sa-card { background: var(--card-bg, #fff); border:1px solid var(--card-border, #eef0f4); border-radius:16px; box-shadow:0 1px 2px rgba(16,24,40,.04); margin-bottom:20px; overflow:hidden; }
.sa-card-h { display:flex; align-items:center; gap:10px; padding:15px 20px; border-bottom:1px solid var(--gray-100, #eef0f4); flex-wrap:wrap; }
.sa-card-h .ic { width:30px; height:30px; border-radius:9px; display:inline-flex; align-items:center; justify-content:center; font-size:13px; color:#fff; }
.sa-card-h h6 { margin:0; font-size:14px; font-weight:700; color: var(--page-text, #1f2937); }
.sa-card-h .sa-sub { font-size:12px; color: var(--page-text-muted, #6b7280); }
.sa-card-h .sa-spacer { margin-left:auto; }
.sa-card-b { padding:20px; }

/* period + header buttons */
.period-selector { display:flex; gap:3px; background: var(--gray-100, #eef0f4); border-radius:10px; padding:3px; }
.period-btn { padding:7px 15px; border:0; border-radius:8px; font-size:12.5px; font-weight:700; cursor:pointer; background:transparent; color: var(--page-text-muted, #6b7280); transition: all .15s; }
.period-btn:hover { color: var(--page-text, #1f2937); }
.period-btn.active { background: var(--card-bg, #fff); color: var(--accent-600, #4f46e5); box-shadow:0 1px 3px rgba(16,24,40,.12); }
.period-btn:focus-visible { outline:2px solid var(--accent-500, #6366f1); outline-offset:1px; }

/* KPI cards */
.sa-kpis { display:grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap:14px; margin-bottom:20px; }
.sa-kpi { position:relative; background: var(--card-bg, #fff); border:1px solid var(--card-border, #eef0f4); border-radius:16px; padding:16px 18px 14px; overflow:hidden; transition: transform .2s, box-shadow .2s, border-color .2s; cursor:default; }
.sa-kpi::before { content:''; position:absolute; inset:0 auto 0 0; width:4px; background: var(--c); border-radius:4px 0 0 4px; }
.sa-kpi::after { content:''; position:absolute; right:-24px; top:-24px; width:90px; height:90px; border-radius:50%; background: var(--c); opacity:.07; transition: transform .35s, opacity .35s; }
.sa-kpi:hover { transform: translateY(-3px); box-shadow:0 14px 28px -14px rgba(16,24,40,.28); border-color: var(--c); }
.sa-kpi:hover::after { transform: scale(1.5); opacity:.12; }
.sa-kpi-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
.sa-kpi-ic { width:36px; height:36px; border-radius:11px; display:inline-flex; align-items:center; justify-content:center; font-size:15px; background: color-mix(in srgb, var(--c) 14%, transparent); color: var(--c); }
.sa-kpi-val { font-size:28px; font-weight:800; letter-spacing:-.025em; line-height:1; color: var(--page-text, #1f2937); font-variant-numeric: tabular-nums; }
.sa-kpi-lbl { margin-top:6px; font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color: var(--page-text-muted, #6b7280); }

/* trend chart */
.sa-seg { display:inline-flex; padding:3px; gap:2px; background: var(--gray-100, #eef0f4); border-radius:10px; }
.sa-seg button { border:0; background:transparent; padding:5px 12px; border-radius:7px; font-size:12px; font-weight:700; color: var(--page-text-muted, #6b7280); cursor:pointer; transition: all .15s; }
.sa-seg button.on { background: var(--card-bg, #fff); color: var(--page-text, #1f2937); box-shadow:0 1px 3px rgba(16,24,40,.12); }
.sa-seg button:focus-visible { outline:2px solid var(--accent-500, #6366f1); outline-offset:1px; }
.sa-chart { position:relative; height:250px; }
.sa-chart svg { width:100%; height:100%; display:block; overflow:visible; }
.sa-grid-line { stroke: var(--gray-100, #eef0f4); stroke-width:1; }
.sa-axis { font-size:10.5px; fill: var(--page-text-muted, #6b7280); }
.sa-bar { fill: var(--accent-500, #6366f1); opacity:.85; transition: opacity .15s, fill .2s; cursor:pointer; }
.sa-bar:hover, .sa-bar.hot { opacity:1; fill: var(--accent-600, #4f46e5); }
.sa-tip { position:absolute; pointer-events:none; z-index:5; background:#111827; color:#fff; padding:8px 11px; border-radius:10px; font-size:12px; line-height:1.45; box-shadow:0 10px 24px -8px rgba(0,0,0,.45); opacity:0; transform: translate(-50%, -6px); transition: opacity .12s; white-space:nowrap; }
.sa-tip.show { opacity:1; }
.sa-tip b { display:block; font-size:12.5px; }
.sa-tip small { color:#cbd5e1; }
.sa-empty { text-align:center; padding:34px 16px; color: var(--page-text-muted, #6b7280); font-size:13px; }
.sa-empty i { display:block; font-size:28px; margin-bottom:8px; color: var(--gray-300, #cbd5e1); }

/* monthly table (collapsible) */
.sa-more { border-top:1px solid var(--gray-100, #eef0f4); }
.sa-more > summary { list-style:none; cursor:pointer; padding:12px 20px; font-size:12.5px; font-weight:700; color: var(--page-text, #1f2937); display:flex; align-items:center; gap:8px; user-select:none; }
.sa-more > summary::-webkit-details-marker { display:none; }
.sa-more > summary:hover { background: var(--gray-50, #fafbfc); }
.sa-more > summary .chev { font-size:10px; color: var(--page-text-muted, #6b7280); transition: transform .2s; }
.sa-more[open] > summary .chev { transform: rotate(90deg); }
.sa-more-b { padding:0 20px 16px; }
.sa-more-b table { font-size:12.5px; margin-bottom:6px; }
.sa-foot-note { font-size:11.5px; color: var(--page-text-muted, #6b7280); line-height:1.5; }

/* donut */
.sa-donut-wrap { display:flex; align-items:center; gap:22px; flex-wrap:wrap; justify-content:center; }
.sa-donut { position:relative; width:170px; height:170px; flex:0 0 auto; }
.sa-donut svg { width:100%; height:100%; transform: rotate(-90deg); }
.sa-seg-ring { fill:none; stroke-width:20; transition: stroke-width .15s, opacity .15s; cursor:pointer; }
.sa-donut.has-hover .sa-seg-ring { opacity:.35; }
.sa-donut.has-hover .sa-seg-ring.hot { opacity:1; stroke-width:24; }
.sa-donut-c { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; pointer-events:none; }
.sa-donut-c b { font-size:24px; font-weight:800; letter-spacing:-.02em; color: var(--page-text, #1f2937); line-height:1.1; }
.sa-donut-c span { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color: var(--page-text-muted, #6b7280); }
.sa-legend { flex:1; min-width:170px; display:flex; flex-direction:column; gap:6px; }
.sa-leg { display:flex; align-items:center; gap:9px; padding:7px 10px; border-radius:10px; cursor:pointer; border:1px solid transparent; transition: background .15s, border-color .15s; font-size:13px; }
.sa-leg:hover, .sa-leg.hot { background: var(--gray-50, #f6f7fb); border-color: var(--gray-100, #eef0f4); }
.sa-dot { width:10px; height:10px; border-radius:50%; flex:0 0 auto; }
.sa-leg .nm { font-weight:700; color: var(--page-text, #1f2937); }
.sa-leg .ct { margin-left:auto; font-size:12px; font-weight:600; color: var(--page-text-muted, #6b7280); font-variant-numeric: tabular-nums; white-space:nowrap; }

/* delivery stacked bar */
.sa-stack { display:flex; height:16px; border-radius:99px; overflow:hidden; background: var(--gray-100, #eef0f4); gap:2px; }
.sa-stack > span { display:block; height:100%; min-width:0; transition: flex-grow .5s ease, opacity .15s, transform .15s; cursor:pointer; }
.sa-stack.has-hover > span { opacity:.35; }
.sa-stack.has-hover > span.hot { opacity:1; transform: scaleY(1.25); }
.sa-dgrid { display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:10px; margin-top:16px; }
.sa-dcell { padding:11px 13px; border:1px solid var(--gray-100, #eef0f4); border-radius:12px; cursor:pointer; transition: border-color .15s, background .15s, transform .15s; background: var(--card-bg, #fff); }
.sa-dcell:hover, .sa-dcell.hot { border-color: var(--c); background: color-mix(in srgb, var(--c) 6%, transparent); transform: translateY(-1px); }
.sa-dcell .k { display:flex; align-items:center; gap:7px; font-size:11.5px; font-weight:700; color: var(--page-text-muted, #6b7280); }
.sa-dcell .v { margin-top:5px; font-size:22px; font-weight:800; letter-spacing:-.02em; color: var(--page-text, #1f2937); font-variant-numeric: tabular-nums; }
.sa-rate { display:flex; align-items:center; gap:14px; margin-bottom:16px; }
.sa-rate-n { font-size:34px; font-weight:800; letter-spacing:-.03em; color: var(--page-text, #1f2937); line-height:1; }
.sa-rate-l { font-size:12px; color: var(--page-text-muted, #6b7280); line-height:1.4; }

/* follow-up ring */
.fu-progress { display:flex; align-items:center; gap:18px; }
.fu-ring { position:relative; width:92px; height:92px; flex:0 0 auto; }
.fu-ring svg { transform: rotate(-90deg); width:100%; height:100%; }
.fu-ring-bg { fill:none; stroke: var(--gray-100, #eef0f4); stroke-width:7; }
.fu-ring-fill { fill:none; stroke: var(--success-500, #22c55e); stroke-width:7; stroke-linecap:round; transition: stroke-dashoffset 1s cubic-bezier(.2,.8,.2,1); }
.fu-ring-text { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:800; color: var(--success-600, #16a34a); }
.fu-stats { flex:1; }
.fu-stat-row { display:flex; justify-content:space-between; padding:5px 0; font-size:13px; }
.fu-stat-label { color: var(--page-text-muted, #6b7280); font-weight:600; }
.fu-stat-value { font-weight:800; }

/* campaigns */
.sa-tools { display:flex; flex-wrap:wrap; gap:10px; align-items:center; padding:14px 20px; border-bottom:1px solid var(--gray-100, #eef0f4); background: var(--gray-50, #fafbfc); }
.sa-search { position:relative; flex:1 1 180px; min-width:160px; }
.sa-search i { position:absolute; left:11px; top:50%; transform:translateY(-50%); font-size:12px; color: var(--page-text-muted, #9aa1ae); pointer-events:none; }
.sa-search input { width:100%; height:36px; padding:0 12px 0 32px; border:1px solid var(--gray-200, #e5e7eb); border-radius:10px; font-size:13px; background: var(--card-bg, #fff); color: var(--page-text, #1f2937); }
.sa-search input:focus { outline:none; border-color: var(--accent-500, #6366f1); box-shadow:0 0 0 3px rgba(99,102,241,.12); }
.sa-pills { display:flex; flex-wrap:wrap; gap:6px; }
.sa-pill { border:1px solid var(--gray-200, #e5e7eb); background: var(--card-bg, #fff); border-radius:999px; padding:5px 12px; font-size:12px; font-weight:700; color: var(--page-text-muted, #6b7280); cursor:pointer; transition: all .15s; }
.sa-pill:hover { color: var(--page-text, #1f2937); border-color: var(--gray-300, #cbd5e1); }
.sa-pill.on { background: var(--accent-500, #6366f1); border-color: var(--accent-500, #6366f1); color:#fff; }
.sa-pill:focus-visible { outline:2px solid var(--accent-500, #6366f1); outline-offset:1px; }
.sa-camp { display:grid; grid-template-columns: minmax(0,1.6fr) minmax(130px,1fr) auto auto; gap:14px; align-items:center; padding:14px 20px; border-bottom:1px solid var(--gray-100, #eef0f4); transition: background .15s; }
.sa-camp:last-of-type { border-bottom:0; }
.sa-camp:hover { background: var(--gray-50, #fafbfc); }
.sa-camp-name { font-weight:700; font-size:13.5px; color: var(--page-text, #1f2937); word-break:break-word; }
.sa-camp-name .fu { display:inline-block; margin-left:5px; background: var(--success-100, #dcfce7); color: var(--success-700, #15803d); font-size:9.5px; font-weight:800; padding:1px 6px; border-radius:5px; vertical-align:middle; }
.sa-camp-date { margin-top:3px; font-size:11.5px; color: var(--page-text-muted, #6b7280); }
.sa-mini { height:7px; border-radius:99px; background: var(--gray-100, #eef0f4); overflow:hidden; display:flex; }
.sa-mini > i { display:block; height:100%; }
.sa-camp-nums { margin-top:6px; display:flex; gap:12px; font-size:11.5px; color: var(--page-text-muted, #6b7280); font-variant-numeric: tabular-nums; flex-wrap:wrap; }
.sa-camp-nums b { color: var(--page-text, #1f2937); }
.sa-camp-cost { text-align:right; }
.sa-camp-cost b { display:block; font-size:14px; font-weight:800; color: var(--page-text, #1f2937); }
.sa-camp-cost small { font-size:10px; font-weight:700; text-transform:uppercase; color: var(--page-text-muted, #6b7280); letter-spacing:.04em; }
.campaign-badge { padding:4px 11px; border-radius:99px; font-size:10.5px; font-weight:800; text-transform:uppercase; letter-spacing:.03em; white-space:nowrap; }
.sa-nomatch { display:none; }

.sa-reveal { opacity:0; transform: translateY(12px); animation: sa-rise .55s cubic-bezier(.2,.8,.2,1) forwards; animation-delay: calc(var(--i, 0) * .07s); }
@keyframes sa-rise { to { opacity:1; transform:none; } }

@media (max-width: 767px) {
    .sa-camp { grid-template-columns: 1fr auto; }
    .sa-camp .sa-camp-bar { grid-column: 1 / -1; order: 3; }
    .sa-dgrid { grid-template-columns: 1fr 1fr; }
}
@media (prefers-reduced-motion: reduce) {
    .sa *, .sa *::before, .sa *::after { animation-duration:.01ms !important; transition-duration:.01ms !important; }
    .sa-reveal { opacity:1; transform:none; }
}
</style>
@endpush

@section('content')
@php
    $typeColors = ['bulk' => '#3b82f6', 'single' => '#8b5cf6', 'automation' => '#f59e0b', 'automated' => '#f59e0b', 'transactional' => '#14b8a6'];
    $typeTotal  = array_sum($data['messages_by_type']) ?: 1;

    $fuDone  = $data['follow_ups_completed'] ?? 0;
    $fuPend  = $data['follow_ups_pending'] ?? 0;
    $fuTotal = $fuPend + $fuDone;
    $fuPct   = $fuTotal > 0 ? round($fuDone / $fuTotal * 100) : 0;
    $fuCirc  = 2 * 3.14159 * 32;
    $fuOff   = $fuCirc * (1 - $fuPct / 100);

    $dlvTotal = ($delivery['delivered'] + $delivery['failed'] + $delivery['pending'] + $delivery['unchecked']) ?: 0;
    $dlvParts = [
        ['key' => 'delivered', 'label' => 'Delivered',            'color' => '#22c55e', 'icon' => 'fa-check-circle',        'val' => $delivery['delivered']],
        ['key' => 'failed',    'label' => 'Not delivered',        'color' => '#ef4444', 'icon' => 'fa-times-circle',        'val' => $delivery['failed']],
        ['key' => 'pending',   'label' => 'Awaiting report',      'color' => '#f59e0b', 'icon' => 'fa-hourglass-half',      'val' => $delivery['pending']],
        ['key' => 'unchecked', 'label' => 'Not checked / unclear','color' => '#94a3b8', 'icon' => 'fa-question-circle',     'val' => $delivery['unchecked']],
    ];

    $kpis = [
        ['Total Sent',          $data['total_sent'],                'fa-paper-plane',    '#3b82f6', true],
        ['Delivered',           $data['successful_sent'],           'fa-check-circle',   '#22c55e', true],
        ['Failed',              $data['failed_sent'],               'fa-times-circle',   '#ef4444', true],
        ['Delivery Rate',       $data['delivery_rate'] . '%',       'fa-percentage',     '#8b5cf6', false],
        ['Campaigns',           $data['bulk_campaigns'],            'fa-bullhorn',       '#f59e0b', true],
        ['Follow-ups Created',  $data['follow_ups_created'] ?? 0,   'fa-calendar-check', '#14b8a6', true],
    ];

    $statusColors = [
        'completed' => 'background:var(--success-100);color:var(--success-700);',
        'sending'   => 'background:var(--info-100,#DBEAFE);color:var(--info-600,#2563EB);',
        'draft'     => 'background:var(--gray-100);color:var(--page-text-muted);',
        'failed'    => 'background:var(--danger-100);color:var(--danger-700);',
    ];
    $campaignRows = $campaigns->map(function ($c) {
        $s = $c->status instanceof \App\Enums\SmsCampaignStatus ? $c->status->value : $c->status;
        return ['c' => $c, 'status' => $s];
    });
    $campaignStatuses = $campaignRows->pluck('status')->unique()->values();

    // chart data (read-only copies of what the controller already computed)
    $chartMonths = collect($months)->map(fn ($m) => [
        'label' => $m['label'], 'messages' => (int) $m['messages'], 'parts' => (int) $m['parts'], 'cost' => (float) $m['cost'],
    ])->values();
@endphp

<div class="container-fluid sa" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-chart-line" style="color: var(--accent-500); margin-right: 8px;"></i>SMS Analytics</h1>
            <p>Campaign performance and delivery insights</p>
        </div>
        <div class="header-actions" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <form method="GET" id="periodForm">
                <div class="period-selector" role="group" aria-label="Period">
                    @foreach(['7_days' => '7D', '30_days' => '30D', '90_days' => '90D', '12_months' => '12M'] as $val => $label)
                        <button type="submit" name="period" value="{{ $val }}" class="period-btn {{ $period == $val ? 'active' : '' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </form>
            <a href="{{ url('admin/sms-log') }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-list mr-1"></i>View Logs</a>
            <a href="{{ url('admin/sms') }}" class="btn btn-primary btn-sm"><i class="fas fa-comment-dots mr-1"></i>SMS Centre</a>
        </div>
    </div>

    @include('admin.alert_message')

    {{-- KPI cards --}}
    <div class="sa-kpis">
        @foreach($kpis as $i => [$label, $value, $icon, $color, $countable])
            <div class="sa-kpi sa-reveal" style="--c: {{ $color }}; --i: {{ $i }};">
                <div class="sa-kpi-top"><span class="sa-kpi-ic"><i class="fas {{ $icon }}" aria-hidden="true"></i></span></div>
                @if($countable)
                    <div class="sa-kpi-val" data-count="{{ (int) $value }}">{{ number_format($value) }}</div>
                @else
                    <div class="sa-kpi-val">{{ $value }}</div>
                @endif
                <div class="sa-kpi-lbl">{{ $label }}</div>
            </div>
        @endforeach
    </div>

    <div class="row">
        {{-- Monthly trend --}}
        <div class="col-lg-8">
            <section class="sa-card sa-reveal" style="--i:6;">
                <div class="sa-card-h">
                    <span class="ic" style="background: var(--accent-500);"><i class="fas fa-chart-bar"></i></span>
                    <h6>Monthly activity</h6>
                    <span class="sa-sub">Hover a bar for details</span>
                    <div class="sa-seg sa-spacer" id="saMetric" role="group" aria-label="Chart metric">
                        <button type="button" data-m="messages" class="on">Messages</button>
                        <button type="button" data-m="parts">SMS parts</button>
                        <button type="button" data-m="cost">Cost ≈</button>
                    </div>
                </div>
                <div class="sa-card-b">
                    @if($chartMonths->isEmpty())
                        <div class="sa-empty"><i class="fas fa-chart-bar"></i>No monthly data yet.</div>
                    @else
                        <div class="sa-chart" id="saChart"><div class="sa-tip" id="saTip"></div></div>
                    @endif
                </div>
                <details class="sa-more">
                    <summary><i class="fas fa-chevron-right chev"></i> Show monthly table</summary>
                    <div class="sa-more-b">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead><tr><th>Month</th><th class="text-right">Messages</th><th class="text-right">SMS parts</th><th class="text-right">Cost ≈</th></tr></thead>
                                <tbody>
                                    @foreach (array_reverse($months) as $m)
                                        <tr><td>{{ $m['label'] }}</td><td class="text-right">{{ number_format($m['messages']) }}</td><td class="text-right">{{ number_format($m['parts']) }}</td><td class="text-right">৳{{ number_format($m['cost'], 2) }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="sa-foot-note">Cost uses the per-SMS price set in the Automation tab; the gateway invoice is the final figure. Delivery shows only messages the gateway has reported on.</div>
                    </div>
                </details>
            </section>
        </div>

        {{-- Messages by type (donut) --}}
        <div class="col-lg-4">
            <section class="sa-card sa-reveal" style="--i:7;">
                <div class="sa-card-h">
                    <span class="ic" style="background: var(--accent-500);"><i class="fas fa-chart-pie"></i></span>
                    <h6>Messages by Type</h6>
                </div>
                <div class="sa-card-b">
                    @if(count($data['messages_by_type']) === 0)
                        <div class="sa-empty"><i class="fas fa-chart-pie"></i>No data for this period.</div>
                    @else
                        <div class="sa-donut-wrap">
                            <div class="sa-donut" id="saDonut">
                                <svg viewBox="0 0 100 100" aria-hidden="true">
                                    <circle cx="50" cy="50" r="38" fill="none" stroke="var(--gray-100, #eef0f4)" stroke-width="20"></circle>
                                    @php $acc = 0; $circ = 2 * 3.14159 * 38; @endphp
                                    @foreach($data['messages_by_type'] as $type => $count)
                                        @php
                                            $frac = $count / $typeTotal;
                                            $len  = $frac * $circ;
                                            $off  = -$acc * $circ;
                                            $acc += $frac;
                                        @endphp
                                        <circle class="sa-seg-ring" data-idx="{{ $loop->index }}" cx="50" cy="50" r="38"
                                                stroke="{{ $typeColors[$type] ?? '#6b7280' }}"
                                                stroke-dasharray="{{ max($len - 0.8, 0) }} {{ $circ }}"
                                                stroke-dashoffset="{{ $off }}"></circle>
                                    @endforeach
                                </svg>
                                <div class="sa-donut-c"><b id="saDonutN">{{ number_format(array_sum($data['messages_by_type'])) }}</b><span id="saDonutL">Total</span></div>
                            </div>
                            <div class="sa-legend" id="saLegend">
                                @foreach($data['messages_by_type'] as $type => $count)
                                    <div class="sa-leg" data-idx="{{ $loop->index }}" data-name="{{ ucfirst($type) }}" data-count="{{ $count }}" tabindex="0">
                                        <span class="sa-dot" style="background: {{ $typeColors[$type] ?? '#6b7280' }};"></span>
                                        <span class="nm">{{ ucfirst($type) }}</span>
                                        <span class="ct">{{ number_format($count) }} ({{ round(($count / $typeTotal) * 100, 1) }}%)</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>

    <div class="row">
        {{-- Delivery + follow-ups --}}
        <div class="col-lg-5">
            <section class="sa-card sa-reveal" style="--i:8;">
                <div class="sa-card-h">
                    <span class="ic" style="background: var(--success-500);"><i class="fas fa-check-double"></i></span>
                    <h6>Delivery report</h6>
                    <span class="sa-sub">Last 30 days</span>
                </div>
                <div class="sa-card-b">
                    <div class="sa-rate">
                        <div class="sa-rate-n">{{ $delivery['rate'] !== null ? $delivery['rate'] . '%' : '—' }}</div>
                        <div class="sa-rate-l">Delivery rate<br>of messages the gateway has reported on</div>
                    </div>
                    <div class="sa-stack" id="saStack" aria-hidden="true">
                        @foreach($dlvParts as $p)
                            <span data-key="{{ $p['key'] }}" style="flex-grow: {{ $dlvTotal > 0 ? $p['val'] : 0 }}; flex-basis:0; background: {{ $p['color'] }};" title="{{ $p['label'] }}: {{ number_format($p['val']) }}"></span>
                        @endforeach
                    </div>
                    <div class="sa-dgrid" id="saDgrid">
                        @foreach($dlvParts as $p)
                            <div class="sa-dcell" data-key="{{ $p['key'] }}" style="--c: {{ $p['color'] }};" tabindex="0">
                                <div class="k"><span class="sa-dot" style="background: {{ $p['color'] }};"></span>{{ $p['label'] }}</div>
                                <div class="v">{{ number_format($p['val']) }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="sa-card sa-reveal" style="--i:9;">
                <div class="sa-card-h">
                    <span class="ic" style="background: var(--success-500);"><i class="fas fa-phone-alt"></i></span>
                    <h6>Follow-up Completion</h6>
                </div>
                <div class="sa-card-b">
                    <div class="fu-progress">
                        <div class="fu-ring">
                            <svg viewBox="0 0 80 80" aria-hidden="true">
                                <circle class="fu-ring-bg" cx="40" cy="40" r="32"></circle>
                                <circle class="fu-ring-fill" id="saFuRing" cx="40" cy="40" r="32" stroke-dasharray="{{ $fuCirc }}" stroke-dashoffset="{{ $fuCirc }}" data-target="{{ $fuOff }}"></circle>
                            </svg>
                            <span class="fu-ring-text">{{ $fuPct }}%</span>
                        </div>
                        <div class="fu-stats">
                            <div class="fu-stat-row"><span class="fu-stat-label"><i class="fas fa-circle mr-1" style="color:var(--success-500);font-size:8px;"></i>Completed</span><span class="fu-stat-value" style="color:var(--success-600);">{{ $fuDone }}</span></div>
                            <div class="fu-stat-row"><span class="fu-stat-label"><i class="fas fa-circle mr-1" style="color:var(--warning-500);font-size:8px;"></i>Pending</span><span class="fu-stat-value" style="color:var(--warning-600);">{{ $fuPend }}</span></div>
                            <div class="fu-stat-row" style="border-top:1px solid var(--gray-100);margin-top:4px;padding-top:6px;"><span class="fu-stat-label">Total</span><span class="fu-stat-value">{{ $fuTotal }}</span></div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        {{-- Recent campaigns --}}
        <div class="col-lg-7">
            <section class="sa-card sa-reveal" style="--i:10;">
                <div class="sa-card-h">
                    <span class="ic" style="background: var(--warning-500);"><i class="fas fa-bullhorn"></i></span>
                    <h6>Recent Campaigns</h6>
                    <span style="background:var(--warning-50);color:var(--warning-600);font-size:11px;font-weight:700;padding:2px 10px;border-radius:20px;" id="saCampCount">{{ $campaigns->count() }}</span>
                </div>

                @if($campaigns->count() > 0)
                    <div class="sa-tools">
                        <label class="sa-search mb-0"><i class="fas fa-search" aria-hidden="true"></i><input type="search" id="saCampSearch" placeholder="Search campaigns…" aria-label="Search campaigns"></label>
                        <div class="sa-pills" id="saCampPills" role="group" aria-label="Filter by status">
                            <button type="button" class="sa-pill on" data-status="all">All</button>
                            @foreach($campaignStatuses as $st)
                                <button type="button" class="sa-pill" data-status="{{ $st }}">{{ ucfirst($st) }}</button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div id="saCampList">
                    @forelse($campaignRows as $row)
                        @php
                            $campaign = $row['c']; $statusVal = $row['status'];
                            $rec  = (int) $campaign->total_recipients;
                            $sent = (int) $campaign->sent_count;
                            $fail = (int) $campaign->failed_count;
                            $den  = max($rec, $sent + $fail, 1);
                        @endphp
                        <div class="sa-camp" data-name="{{ strtolower($campaign->name) }}" data-status="{{ $statusVal }}">
                            <div>
                                <div class="sa-camp-name">
                                    <i class="fas fa-envelope mr-1" style="color:var(--accent-400);" aria-hidden="true"></i>{{ $campaign->name }}
                                    @if($campaign->create_follow_up)<span class="fu">+FU</span>@endif
                                </div>
                                <div class="sa-camp-date">{{ $campaign->created_at->format('d M Y') }}</div>
                            </div>
                            <div class="sa-camp-bar" title="{{ $sent }} sent · {{ $fail }} failed · {{ $rec }} recipients">
                                <div class="sa-mini"><i style="width: {{ $sent / $den * 100 }}%; background:#22c55e;"></i><i style="width: {{ $fail / $den * 100 }}%; background:#ef4444;"></i></div>
                                <div class="sa-camp-nums">
                                    <span><b>{{ $rec }}</b> recipients</span>
                                    <span style="color:var(--success-600);"><b style="color:var(--success-600);">{{ $sent }}</b> sent</span>
                                    <span style="color:var(--danger-600);"><b style="color:var(--danger-600);">{{ $fail }}</b> failed</span>
                                </div>
                            </div>
                            <div class="sa-camp-cost" title="{{ $campaignCost[$campaign->id]['parts'] ?? 0 }} SMS parts">
                                <b>৳{{ number_format($campaignCost[$campaign->id]['cost'] ?? 0, 2) }}</b><small>Cost ≈</small>
                            </div>
                            <div><span class="campaign-badge" style="{{ $statusColors[$statusVal] ?? 'background:var(--gray-100);color:var(--page-text-muted);' }}">{{ ucfirst($statusVal) }}</span></div>
                        </div>
                    @empty
                        <div class="sa-empty"><i class="fas fa-bullhorn"></i>No campaigns yet</div>
                    @endforelse
                    <div class="sa-empty sa-nomatch" id="saCampNone"><i class="far fa-folder-open"></i>No campaigns match.</div>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var DATA = @json($chartMonths);
    var TYPES = @json(collect($data['messages_by_type'])->map(fn ($c, $t) => ['name' => ucfirst($t), 'count' => (int) $c])->values());
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function fmt(n, d) { return Number(n).toLocaleString(undefined, { minimumFractionDigits: d || 0, maximumFractionDigits: d || 0 }); }

    /* ── KPI count-up ── */
    document.querySelectorAll('.sa-kpi-val[data-count]').forEach(function (el) {
        var target = parseInt(el.getAttribute('data-count'), 10) || 0;
        if (reduce || target < 2) return;
        var start = null, dur = 900;
        el.textContent = '0';
        function step(ts) {
            if (start === null) start = ts;
            var p = Math.min((ts - start) / dur, 1), e = 1 - Math.pow(1 - p, 3);
            el.textContent = fmt(Math.round(target * e));
            if (p < 1) requestAnimationFrame(step); else el.textContent = fmt(target);
        }
        requestAnimationFrame(step);
    });

    /* ── Follow-up ring animates in ── */
    var ring = document.getElementById('saFuRing');
    if (ring) { setTimeout(function () { ring.style.strokeDashoffset = ring.getAttribute('data-target'); }, 150); }

    /* ── Monthly bar chart (SVG, no library) ── */
    var host = document.getElementById('saChart'), tip = document.getElementById('saTip');
    var metric = 'messages';
    function draw() {
        if (!host || !DATA.length) return;
        var W = host.clientWidth || 600, H = host.clientHeight || 250;
        var pad = { l: 46, r: 8, t: 12, b: 28 }, iw = W - pad.l - pad.r, ih = H - pad.t - pad.b;
        var vals = DATA.map(function (d) { return d[metric]; });
        var max = Math.max.apply(null, vals.concat([1]));
        // nice top
        var mag = Math.pow(10, Math.floor(Math.log10(max))), top = Math.ceil(max / mag * 2) / 2 * mag; if (top < max) top = max;
        var isCost = metric === 'cost';
        var svg = '<svg viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="none" role="img" aria-label="Monthly ' + metric + '">';
        for (var g = 0; g <= 4; g++) {
            var y = pad.t + ih - (ih * g / 4), v = top * g / 4;
            svg += '<line class="sa-grid-line" x1="' + pad.l + '" x2="' + (W - pad.r) + '" y1="' + y + '" y2="' + y + '"/>' +
                   '<text class="sa-axis" x="' + (pad.l - 8) + '" y="' + (y + 3.5) + '" text-anchor="end">' + (isCost ? '৳' : '') + fmt(v, isCost && top < 10 ? 2 : 0) + '</text>';
        }
        var n = DATA.length, slot = iw / n, bw = Math.min(46, slot * 0.6);
        DATA.forEach(function (d, i) {
            var h = top ? (d[metric] / top) * ih : 0, x = pad.l + slot * i + (slot - bw) / 2, y = pad.t + ih - h;
            svg += '<rect class="sa-bar" data-i="' + i + '" x="' + x + '" y="' + y + '" width="' + bw + '" height="' + Math.max(h, d[metric] > 0 ? 2 : 0) + '" rx="6"/>' +
                   '<text class="sa-axis" x="' + (x + bw / 2) + '" y="' + (H - 8) + '" text-anchor="middle">' + d.label + '</text>';
        });
        host.querySelectorAll('svg').forEach(function (s) { s.remove(); });
        host.insertAdjacentHTML('afterbegin', svg + '</svg>');
    }
    if (host) {
        host.addEventListener('mousemove', function (e) {
            var bar = e.target.closest ? e.target.closest('.sa-bar') : null;
            host.querySelectorAll('.sa-bar.hot').forEach(function (b) { b.classList.remove('hot'); });
            if (!bar) { tip.classList.remove('show'); return; }
            bar.classList.add('hot');
            var d = DATA[+bar.getAttribute('data-i')], r = host.getBoundingClientRect(), br = bar.getBoundingClientRect();
            tip.innerHTML = '<b>' + d.label + '</b>' + fmt(d.messages) + ' messages<br>' + fmt(d.parts) + ' SMS parts<br><small>≈ ৳' + fmt(d.cost, 2) + '</small>';
            tip.style.left = (br.left - r.left + br.width / 2) + 'px';
            tip.style.top = (br.top - r.top - tip.offsetHeight - 4) + 'px';
            tip.classList.add('show');
        });
        host.addEventListener('mouseleave', function () { tip.classList.remove('show'); host.querySelectorAll('.sa-bar.hot').forEach(function (b) { b.classList.remove('hot'); }); });
        document.getElementById('saMetric').addEventListener('click', function (e) {
            var b = e.target.closest('button[data-m]'); if (!b) return;
            this.querySelectorAll('button').forEach(function (x) { x.classList.remove('on'); });
            b.classList.add('on'); metric = b.getAttribute('data-m'); draw();
        });
        var rt; window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(draw, 120); });
        draw();
    }

    /* ── Donut ↔ legend hover ── */
    var donut = document.getElementById('saDonut');
    if (donut) {
        var dn = document.getElementById('saDonutN'), dl = document.getElementById('saDonutL'), total0 = dn.textContent;
        function hotType(idx) {
            donut.classList.toggle('has-hover', idx !== null);
            donut.querySelectorAll('.sa-seg-ring').forEach(function (c) { c.classList.toggle('hot', +c.getAttribute('data-idx') === idx); });
            document.querySelectorAll('#saLegend .sa-leg').forEach(function (l) { l.classList.toggle('hot', +l.getAttribute('data-idx') === idx); });
            if (idx === null) { dn.textContent = total0; dl.textContent = 'Total'; }
            else { dn.textContent = fmt(TYPES[idx].count); dl.textContent = TYPES[idx].name; }
        }
        ['mouseover', 'focusin'].forEach(function (ev) {
            donut.addEventListener(ev, function (e) { var c = e.target.closest('.sa-seg-ring'); if (c) hotType(+c.getAttribute('data-idx')); });
            document.getElementById('saLegend').addEventListener(ev, function (e) { var l = e.target.closest('.sa-leg'); if (l) hotType(+l.getAttribute('data-idx')); });
        });
        ['mouseleave', 'focusout'].forEach(function (ev) {
            donut.addEventListener(ev, function () { hotType(null); });
            document.getElementById('saLegend').addEventListener(ev, function () { hotType(null); });
        });
    }

    /* ── Delivery bar ↔ cards hover ── */
    var stack = document.getElementById('saStack'), dgrid = document.getElementById('saDgrid');
    if (stack && dgrid) {
        function hotKey(k) {
            stack.classList.toggle('has-hover', !!k);
            stack.querySelectorAll('span').forEach(function (s) { s.classList.toggle('hot', s.getAttribute('data-key') === k); });
            dgrid.querySelectorAll('.sa-dcell').forEach(function (c) { c.classList.toggle('hot', c.getAttribute('data-key') === k); });
        }
        ['mouseover', 'focusin'].forEach(function (ev) {
            dgrid.addEventListener(ev, function (e) { var c = e.target.closest('.sa-dcell'); if (c) hotKey(c.getAttribute('data-key')); });
        });
        ['mouseleave', 'focusout'].forEach(function (ev) { dgrid.addEventListener(ev, function () { hotKey(null); }); });
        stack.style.pointerEvents = 'auto';
        stack.addEventListener('mouseover', function (e) { var s = e.target.closest('span'); if (s) hotKey(s.getAttribute('data-key')); });
        stack.addEventListener('mouseleave', function () { hotKey(null); });
    }

    /* ── Campaign search + status filter ── */
    var list = document.getElementById('saCampList');
    if (list) {
        var search = document.getElementById('saCampSearch'), pills = document.getElementById('saCampPills');
        var none = document.getElementById('saCampNone'), count = document.getElementById('saCampCount'), status = 'all';
        function applyFilter() {
            var q = (search ? search.value : '').trim().toLowerCase(), shown = 0;
            list.querySelectorAll('.sa-camp').forEach(function (r) {
                var ok = (status === 'all' || r.getAttribute('data-status') === status) && (!q || r.getAttribute('data-name').indexOf(q) !== -1);
                r.style.display = ok ? '' : 'none'; if (ok) shown++;
            });
            if (none) none.style.display = (shown === 0 && list.querySelector('.sa-camp')) ? 'block' : 'none';
            if (count) count.textContent = shown;
        }
        if (search) search.addEventListener('input', applyFilter);
        if (pills) pills.addEventListener('click', function (e) {
            var b = e.target.closest('.sa-pill'); if (!b) return;
            pills.querySelectorAll('.sa-pill').forEach(function (x) { x.classList.remove('on'); });
            b.classList.add('on'); status = b.getAttribute('data-status'); applyFilter();
        });
    }
})();
</script>
@endpush
