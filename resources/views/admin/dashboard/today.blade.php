@extends('admin.layouts.master')
@section('page_title', 'Dashboard')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/dashboard-analytics.css') }}">
<style>
.td-wrap { padding: 0 24px; }
.td-picks { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.td-picks select { height:32px; font-size:13px; border-radius:8px; border:1px solid var(--card-border); background:var(--card-bg); color:var(--page-text); padding:0 8px; }
.scope-chip { display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:600;padding:4px 10px;border-radius:999px;background:var(--accent-50);color:var(--accent-700);border:1px solid var(--accent-100);margin-left:8px;vertical-align:middle; }

.td-tabs { display:flex; gap:4px; border-bottom:1px solid var(--card-border); margin:4px 0 20px; overflow-x:auto; }
.td-tab { padding:9px 16px; font-size:13.5px; font-weight:600; color:var(--page-text-muted); border-bottom:2px solid transparent; white-space:nowrap; text-decoration:none !important; }
.td-tab:hover { color:var(--page-text); }
.td-tab.active { color:var(--accent-600); border-bottom-color:var(--accent-500); }
.td-tab .soon { font-size:10px; font-weight:600; margin-left:6px; padding:1px 6px; border-radius:999px; background:var(--accent-50); color:var(--accent-700); }

.td-tiles { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:16px; margin-bottom:20px; }
.td-tile { position:relative; display:block; padding:16px 16px 12px; border-radius:14px; background:var(--card-bg); border:1px solid var(--card-border); color:var(--page-text); text-decoration:none !important; transition:transform .15s ease, box-shadow .15s ease; min-height:136px; }
.td-tile:hover { transform:translateY(-2px); box-shadow:0 10px 22px -14px rgba(15,23,42,.45); color:var(--page-text); }
.td-tile .t-top { display:flex; align-items:center; gap:8px; font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--page-text-muted); }
.td-tile .t-top i { color:var(--accent-500); }
.td-tile .t-val { font-size:26px; font-weight:800; line-height:1.15; margin-top:6px; }
.td-tile .t-chg { font-size:12px; font-weight:600; margin-top:2px; }
.td-tile .t-chg.up { color:#059669; } .td-tile .t-chg.down { color:#e11d48; } .td-tile .t-chg.flat { color:var(--page-text-muted); }
.td-tile .t-sub { font-size:11.5px; color:var(--page-text-muted); margin-top:2px; }
.td-tile svg.t-spark { position:absolute; right:14px; bottom:14px; width:96px; height:32px; overflow:visible; }
.td-tile svg.t-spark polyline { fill:none; stroke:var(--accent-500); stroke-width:2; stroke-linejoin:round; stroke-linecap:round; }
.td-tile svg.t-spark circle { fill:var(--accent-500); }
.td-skel { background:linear-gradient(90deg, var(--card-border) 25%, transparent 50%, var(--card-border) 75%); background-size:200% 100%; animation:tdSk 1.4s infinite; border-radius:14px; min-height:136px; opacity:.5; }
@keyframes tdSk { from { background-position:200% 0; } to { background-position:-200% 0; } }

.td-grid { display:grid; grid-template-columns:3fr 2fr; gap:20px; margin-bottom:20px; }
.td-charts { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px; }
@media (max-width:1100px) { .td-grid, .td-charts { grid-template-columns:1fr; } }
.td-card { background:var(--card-bg); border:1px solid var(--card-border); border-radius:14px; padding:16px; min-width:0; }
.td-card h3 { font-size:14px; font-weight:700; margin:0 0 2px; display:flex; align-items:center; gap:8px; }
.td-card h3 i { color:var(--accent-500); }
.td-card .td-note { font-size:12px; color:var(--page-text-muted); margin-bottom:10px; }
.td-card .td-more { font-size:12px; font-weight:600; margin-left:auto; }
.td-chart-box { position:relative; height:250px; }
.td-empty { padding:24px 8px; text-align:center; font-size:13px; color:var(--page-text-muted); }
.td-err { padding:12px; font-size:13px; color:#b91c1c; }

.md-list { display:flex; flex-direction:column; }
.md-item { display:flex; align-items:center; gap:10px; padding:9px 4px; border-bottom:1px solid var(--card-border); }
.md-item:last-child { border-bottom:0; }
.md-item .md-i { width:30px; height:30px; border-radius:9px; display:flex; align-items:center; justify-content:center; background:var(--accent-50); color:var(--accent-600); flex-shrink:0; font-size:13px; }
.md-item .md-t { flex:1; min-width:0; color:var(--page-text); text-decoration:none !important; }
.md-item .md-t b { display:block; font-size:13px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.md-item .md-t span { display:block; font-size:11.5px; color:var(--page-text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.md-badge { font-size:10.5px; font-weight:700; padding:2px 8px; border-radius:999px; white-space:nowrap; background:var(--accent-50); color:var(--accent-700); }
.md-badge.danger { background:rgba(225,29,72,.1); color:#be123c; }
.md-badge.warning, .md-badge.amber { background:rgba(217,119,6,.12); color:#b45309; }
.md-badge.purple { background:rgba(124,58,237,.1); color:#6d28d9; }
.md-wa { color:#16a34a; font-size:16px; padding:0 4px; }
.md-sum { display:flex; gap:14px; flex-wrap:wrap; font-size:12px; color:var(--page-text-muted); margin-bottom:6px; }
.md-sum b { color:var(--page-text); }
</style>
@endpush

@section('content')
@php
    $hour = (int) date('H');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $user = Auth::user();
@endphp

<div class="container-fluid td-wrap" id="dashboard-today"
     data-tiles="{{ route('dashboard.today.tiles') }}"
     data-block="{{ url('admin/dashboard/today/block') }}"
     data-chart="{{ url('admin/dashboard/today/chart') }}"
     data-branch="{{ $branch }}" data-period="{{ $period }}">

    <div class="crm-page-header">
        <div class="page-title-group">
            <h1>{{ $greeting }}, {{ $user->name ?? 'there' }}</h1>
            <p>{{ now()->format('l, d F Y') }}
                <span class="scope-chip" title="Numbers on this page are filtered to this scope"><i class="fas fa-filter"></i>{{ $branches ? ($branches[$branch] ?? 'Head office') : $scopeLabel }}</span>
            </p>
        </div>
        <div class="header-actions">
            <form method="get" class="td-picks" id="td-picks">
                @if ($branches)
                    <select name="branch" id="td-branch" aria-label="Branch" onchange="this.form.submit()">
                        @foreach ($branches as $k => $name)
                            <option value="{{ $k }}" @selected((string) $k === (string) $branch)>{{ $name }}</option>
                        @endforeach
                    </select>
                @endif
                <select name="period" id="td-period" aria-label="Period for the category chart" onchange="this.form.submit()">
                    @foreach ($periods as $k => $label)
                        <option value="{{ $k }}" @selected($k === $period)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
            @if ($user->hasAccess('add_appointment.create'))
                <a href="{{ url('admin/appointments/create') }}" class="btn btn-secondary btn-sm" id="td-add-appointment"><i class="far fa-calendar-plus mr-1"></i>Appointment</a>
            @endif
            @if ($user->hasAccess('add_lead.create'))
                <a href="{{ url('admin/leads/create') }}" class="btn btn-secondary btn-sm" id="td-add-lead"><i class="fas fa-user-plus mr-1"></i>Lead</a>
            @endif
            @if ($user->hasAccess('add_sale.create'))
                <a href="{{ url('admin/sale/create') }}" class="btn btn-primary btn-sm" id="td-new-sale"><i class="fas fa-plus mr-1"></i>New sale</a>
            @endif
            <a href="{{ route('dashboard.legacy') }}" class="btn btn-outline-secondary btn-sm" id="td-switch-legacy" title="Open the old dashboard"><i class="fas fa-history mr-1"></i>Old dashboard</a>
        </div>
    </div>

    <nav class="td-tabs" aria-label="Dashboard sections">
        <a href="#" class="td-tab active" aria-current="page" id="td-tab-today">Today</a>
        <a href="{{ route('dashboard.legacy') }}#analytics" class="td-tab" id="td-tab-sales" title="Coming in phase 3; opens the old dashboard for now">Sales &amp; Money<span class="soon">soon</span></a>
        <a href="{{ route('dashboard.legacy') }}#analytics" class="td-tab" id="td-tab-care" title="Coming in phase 3; opens the old dashboard for now">Patients &amp; Care<span class="soon">soon</span></a>
        <a href="{{ route('dashboard.legacy') }}#analytics" class="td-tab" id="td-tab-pipeline" title="Coming in phase 4; opens the old dashboard for now">Pipeline<span class="soon">soon</span></a>
        @if ($branches)
            <a href="{{ route('dashboard.legacy') }}#analytics" class="td-tab" id="td-tab-branches" title="Coming in phase 5; opens the old dashboard for now">Branches<span class="soon">soon</span></a>
        @endif
    </nav>

    <section class="td-tiles" id="td-tiles" aria-live="polite">
        @for ($i = 0; $i < 6; $i++)<div class="td-skel"></div>@endfor
    </section>

    @if ($can('smart_alerts'))
        <div id="td-alerts" data-block-name="alerts" style="margin-bottom:16px;"></div>
    @endif

    <div class="td-grid">
        <div class="td-card" id="td-my-day-card">
            <h3><i class="fas fa-tasks"></i>My day<a href="{{ route('dashboard.legacy') }}#my-day" class="td-more" id="td-my-day-full">Full list and actions</a></h3>
            <div class="td-note">Your own calls, visits and after-sales care, overdue first</div>
            <div id="td-my-day" data-block-name="my-day"><div class="td-empty">Loading…</div></div>
        </div>
        <div class="td-card">
            <h3><i class="fas fa-chart-line"></i>Sales this month vs last</h3>
            <div class="td-note" id="td-rev-note">Running total by day of month</div>
            @if ($can('revenue_chart'))
                <div class="td-chart-box"><canvas id="td-chart-revenue_month" data-chart-name="revenue_month" aria-label="Sales this month against last month"></canvas></div>
            @else
                <div class="td-empty">You do not have access to this chart.</div>
            @endif
        </div>
    </div>

    <div class="td-charts">
        <div class="td-card">
            <h3><i class="fas fa-chart-pie"></i>Sales by category</h3>
            <div class="td-note" id="td-cat-note">{{ $periods[$period] }}</div>
            @if ($can('top_products'))
                <div class="td-chart-box"><canvas id="td-chart-sales_by_category" data-chart-name="sales_by_category" aria-label="Sales by product category"></canvas></div>
            @else
                <div class="td-empty">You do not have access to this chart.</div>
            @endif
        </div>
        <div class="td-card">
            <h3><i class="fas fa-compass"></i>More on the old dashboard</h3>
            <div class="td-note">These move into the tabs above in the next phases</div>
            <div class="md-list">
                <a class="md-item" href="{{ route('dashboard.legacy') }}#analytics" id="td-more-analytics"><span class="md-i"><i class="fas fa-chart-bar"></i></span><span class="md-t"><b>Analytics widgets</b><span>Customers, products, doctors, stock, aging</span></span></a>
                <a class="md-item" href="{{ route('dashboard.legacy') }}#my-day" id="td-more-lookout"><span class="md-i"><i class="fas fa-binoculars"></i></span><span class="md-t"><b>Daily lookout</b><span>Tasks, tickets, follow-ups, warranty and battery</span></span></a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script src="{{ asset('assets/js/dashboard-today.js') }}"></script>
@endpush
