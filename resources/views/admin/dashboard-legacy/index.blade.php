@extends('admin.layouts.master')
@section('page_title', 'Dashboard')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/dashboard-analytics.css') }}">
<style>
/* ── Layout ─────────────────────────────────── */
.dash-grid { display: grid; gap: 20px; margin-bottom: 24px; }
.dash-g4 { grid-template-columns: repeat(4, 1fr); }
.dash-g3 { grid-template-columns: repeat(3, 1fr); }
.dash-g2 { grid-template-columns: 1fr 1fr; }
.dash-g21 { grid-template-columns: 2fr 1fr; }
@media(max-width:1200px) { .dash-g4 { grid-template-columns: repeat(2, 1fr); } }
@media(max-width:900px)  { .dash-g4,.dash-g3,.dash-g2,.dash-g21 { grid-template-columns: 1fr; } }
.dash-fade { animation: dashUp .45s ease both; }
@keyframes dashUp { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:none; } }

.scope-chip { display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:600;padding:4px 10px;border-radius:999px;background:var(--accent-50);color:var(--accent-700);border:1px solid var(--accent-100);margin-left:8px;vertical-align:middle; }

/* ── KPI cards (light tinted) ───────────────── */
.kpi-row { display:grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap:16px; margin-bottom:24px; }
.kpi {
    --c: var(--accent-500); --bg: rgba(99,102,241,.07); --bd: rgba(99,102,241,.18); --tx: var(--accent-700);
    position:relative; display:flex; gap:14px; align-items:flex-start;
    padding:18px; border-radius:14px; background:var(--bg); border:1px solid var(--bd);
    text-decoration:none !important; color:var(--page-text); overflow:hidden;
    transition: transform .18s ease, box-shadow .18s ease;
}
.kpi:hover { transform: translateY(-2px); box-shadow: 0 10px 22px -12px var(--c); color: var(--page-text); }
.kpi::after { content:''; position:absolute; right:-28px; top:-28px; width:96px; height:96px; border-radius:50%; background:var(--c); opacity:.07; }
.kpi .k-ic { width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;background:var(--c);color:#fff;box-shadow:0 6px 14px -6px var(--c); }
.kpi .k-lbl { font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--tx);opacity:.85; }
.kpi .k-val { font-size:24px;font-weight:800;line-height:1.15;margin-top:2px;color:var(--page-text); }
.kpi .k-sub { font-size:11.5px;color:var(--page-text-secondary);margin-top:3px; }
.kpi .k-sub b { color:var(--tx); }
.kpi .k-go { position:absolute; right:14px; bottom:12px; font-size:11px; color:var(--tx); opacity:0; transition:opacity .15s; }
.kpi:hover .k-go { opacity:.9; }
.kpi.k-indigo { --c:#6366f1; --bg:rgba(99,102,241,.07); --bd:rgba(99,102,241,.2); --tx:#4338ca; }
.kpi.k-green  { --c:#10b981; --bg:rgba(16,185,129,.07); --bd:rgba(16,185,129,.2); --tx:#047857; }
.kpi.k-blue   { --c:#3b82f6; --bg:rgba(59,130,246,.07); --bd:rgba(59,130,246,.2); --tx:#1d4ed8; }
.kpi.k-amber  { --c:#f59e0b; --bg:rgba(245,158,11,.08); --bd:rgba(245,158,11,.22); --tx:#b45309; }
.kpi.k-rose   { --c:#f43f5e; --bg:rgba(244,63,94,.07); --bd:rgba(244,63,94,.2); --tx:#be123c; }
.kpi.k-violet { --c:#8b5cf6; --bg:rgba(139,92,246,.07); --bd:rgba(139,92,246,.2); --tx:#6d28d9; }

/* ── Insights / metrics ─────────────────────── */
.insight-msg { display:flex;align-items:flex-start;gap:10px;padding:10px 14px;border-radius:8px;margin-bottom:8px;font-size:13px;line-height:1.5; }
.insight-msg:last-child { margin-bottom:0; }
.insight-msg.up { background:var(--success-50);color:var(--success-700); }
.insight-msg.down { background:var(--danger-50);color:var(--danger-700); }
.insight-msg.warn { background:var(--warning-50);color:var(--warning-600); }
.insight-msg.info { background:var(--accent-50);color:var(--accent-700); }
.insight-msg i { margin-top:3px;flex-shrink:0;font-size:12px; }
.metric-box { text-align:center;padding:14px;border-radius:10px;background:var(--gray-50);border:1px solid var(--gray-100); }
.metric-box .m-val { font-size:20px;font-weight:700;color:var(--page-text);line-height:1;margin-bottom:4px; }
.metric-box .m-label { font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--page-text-muted); }

/* ── Pipeline ───────────────────────────────── */
.pipe-step { display:flex;align-items:center;gap:10px;margin-bottom:6px;text-decoration:none !important; }
.pipe-step:last-child { margin-bottom:0; }
.pipe-label { width:110px;flex-shrink:0;font-size:12px;font-weight:500;color:var(--page-text-secondary); }
.pipe-track { flex:1;height:24px;background:var(--gray-100);border-radius:6px;overflow:hidden; }
.pipe-fill { height:100%;border-radius:6px;display:flex;align-items:center;justify-content:flex-end;padding:0 8px;font-size:11px;font-weight:600;color:#fff;min-width:30px;transition:width .6s ease; }
.pipe-num { width:36px;text-align:right;font-size:13px;font-weight:600;color:var(--page-text); }
.pipe-step:hover .pipe-label { color:var(--accent-600); }

.ledger-row { display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--gray-100);font-size:13px; }
.ledger-row:last-child { border-bottom:none; }

/* ── Tabs ───────────────────────────────────── */
.lookout-tabs, .inv-tabs { display:flex;gap:4px;overflow-x:auto;border-bottom:2px solid var(--gray-100); }
.lookout-tab, .inv-tab {
    padding:9px 14px;font-size:12px;font-weight:600;color:var(--page-text-muted);cursor:pointer;white-space:nowrap;
    border:none;background:none;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .15s;display:flex;align-items:center;gap:6px;
}
.lookout-tab:hover, .inv-tab:hover { color:var(--accent-600); }
.lookout-tab.active, .inv-tab.active { color:var(--accent-600);border-bottom-color:var(--accent-500); }
.tab-count { font-size:10px;padding:1px 7px;border-radius:10px;font-weight:700;background:var(--gray-100);color:var(--page-text-muted); }
.lookout-tab.active .tab-count { background:var(--accent-100);color:var(--accent-700); }
.tab-count.hot { background:var(--danger-50) !important; color:var(--danger-600) !important; }
.lookout-panel, .inv-panel { display:none;padding:8px 20px 14px; max-height: 460px; overflow-y:auto; }
.lookout-panel.active, .inv-panel.active { display:block; }

/* ── Lookout rows ───────────────────────────── */
.lk { display:flex;align-items:center;gap:12px;padding:11px 8px;border-bottom:1px solid var(--gray-100);font-size:13px;border-radius:10px;transition:background .15s, opacity .3s, transform .3s; }
.lk:last-child { border-bottom:none; }
.lk:hover { background:var(--gray-50); }
.lk.done { opacity:0; transform:translateX(20px); }
.lk .li-icon { width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0; }
.lk .li-main { flex:1;min-width:0;text-decoration:none !important; color:inherit; }
.lk .li-title { font-weight:600;color:var(--page-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.lk a.li-main:hover .li-title { color:var(--accent-600); }
.lk .li-sub { font-size:11.5px;color:var(--page-text-muted);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.li-badge { font-size:10.5px;padding:3px 9px;border-radius:20px;font-weight:700;white-space:nowrap; }
.lk-actions { display:flex;align-items:center;gap:6px;flex-shrink:0; }
.lk-btn {
    width:32px;height:32px;border-radius:9px;border:1px solid var(--card-border);background:var(--card-bg);
    display:inline-flex;align-items:center;justify-content:center;font-size:12px;color:var(--page-text-secondary);
    cursor:pointer;transition:all .15s;text-decoration:none !important;
}
.lk-btn:hover { border-color:var(--accent-300);color:var(--accent-600); }
.lk-btn.ok:hover { background:var(--success-500);border-color:var(--success-500);color:#fff; }
.lk-btn.call:hover { background:var(--info-500);border-color:var(--info-500);color:#fff; }
.lk-select {
    height:32px;border-radius:9px;border:1px solid var(--card-border);background:var(--card-bg);color:var(--page-text);
    font-size:12px;font-weight:600;padding:0 8px;cursor:pointer;max-width:130px;
}
.lk-empty { text-align:center;padding:28px;color:var(--page-text-muted);font-size:13px; }
.lk-empty i { font-size:22px;display:block;margin-bottom:8px; }
.lk-more { display:block;text-align:center;font-size:12px;font-weight:600;color:var(--accent-600);padding:10px;text-decoration:none !important; }

/* ── Toast ──────────────────────────────────── */
#dashToast { position:fixed;right:24px;bottom:24px;z-index:2000;display:flex;flex-direction:column;gap:8px; }
.dt { padding:11px 16px;border-radius:10px;font-size:13px;font-weight:600;color:#fff;box-shadow:0 10px 24px -8px rgba(0,0,0,.35);animation:dashUp .25s ease; display:flex;gap:8px;align-items:center; }
.dt.ok { background:#059669; } .dt.err { background:#dc2626; }

/* ── Super My Day & Lookout Hub ── */
.myday { margin-bottom:24px; overflow:hidden; position:relative; }
.myday::before { content:''; position:absolute; inset:0 0 auto 0; height:3px; background:linear-gradient(90deg,#6366f1,#8b5cf6,#ec4899); }
.myday-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:18px 20px 12px; flex-wrap:wrap; border-bottom:1px solid var(--gray-100); }
.myday-head h2 { margin:0; font-size:17px; font-weight:800; color:var(--page-text); display:flex; align-items:center; gap:8px; }
.myday-stat-pills { display:inline-flex; align-items:center; gap:6px; flex-wrap:wrap; }
.md-pill { font-size:11.5px; font-weight:700; padding:2px 9px; border-radius:12px; background:var(--gray-100); color:var(--page-text-secondary); display:inline-flex; align-items:center; gap:5px; }
.md-pill-danger { background:var(--danger-50); color:var(--danger-600); }
.md-pill-muted { background:var(--gray-50); color:var(--page-text-muted); }

/* Switcher */
.myday-segmented { display:inline-flex; background:var(--gray-100); padding:3px; border-radius:10px; gap:3px; }
.md-seg-btn { border:none; background:none; font-size:12px; font-weight:700; color:var(--page-text-muted); padding:6px 13px; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:all .15s; }
.md-seg-btn:hover { color:var(--page-text); }
.md-seg-btn.active { background:var(--card-bg, #fff); color:var(--accent-600); box-shadow:0 1px 3px rgba(0,0,0,.08); }
.md-seg-count { font-size:10px; font-weight:800; padding:1px 6px; border-radius:10px; background:var(--gray-200); color:var(--page-text-secondary); }
.md-seg-btn.active .md-seg-count { background:var(--accent-100); color:var(--accent-700); }

/* Filter Chips */
.md-filter-bar { display:flex; gap:6px; padding:10px 18px; overflow-x:auto; border-bottom:1px solid var(--gray-100); background:var(--page-surface); }
.md-chip { border:1px solid var(--card-border); background:var(--card-bg); color:var(--page-text-secondary); font-size:12px; font-weight:600; padding:4px 12px; border-radius:20px; cursor:pointer; display:inline-flex; align-items:center; gap:6px; white-space:nowrap; transition:all .15s; }
.md-chip:hover { border-color:var(--accent-300); color:var(--accent-600); }
.md-chip.active { background:var(--accent-600); border-color:var(--accent-600); color:#fff; }
.md-chip.active .chip-n { background:rgba(255,255,255,.25); color:#fff; }
.chip-n { font-size:10.5px; font-weight:800; padding:1px 6px; border-radius:10px; background:var(--gray-100); color:var(--page-text-muted); }

/* Tags & Icons for After-Sales */
.md-type-tag { font-size:10px; font-weight:700; padding:1px 6px; border-radius:5px; text-transform:uppercase; letter-spacing:.3px; }
.tag-deal { background:var(--accent-50); color:var(--accent-700); }
.tag-appt { background:var(--warning-50); color:var(--warning-700); }
.tag-service { background:#e0f2fe; color:#0369a1; }
.tag-warranty { background:#fee2e2; color:#b91c1c; }
.tag-birthday { background:#f3e8ff; color:#7e22ce; }
.tag-battery { background:#fef3c7; color:#b45309; }

.ic-service { background:#e0f2fe !important; color:#0284c7 !important; }
.ic-warranty { background:#fee2e2 !important; color:#dc2626 !important; }
.ic-birthday { background:#f3e8ff !important; color:#9333ea !important; }
.ic-battery { background:#fef3c7 !important; color:#d97706 !important; }

.myday-list { padding:0 12px 12px; max-height:460px; overflow-y:auto; }
.md-row { display:flex; align-items:center; gap:12px; padding:10px; border-radius:12px; transition:background .15s, opacity .3s, transform .3s; }
.md-row + .md-row { border-top:1px solid var(--gray-100); }
.md-row:hover { background:var(--gray-50); }
.md-row.done { opacity:0; transform:translateX(20px); }
.md-time { width:62px; flex-shrink:0; font-size:12px; font-weight:700; color:var(--page-text-secondary); text-align:right; }
.md-row.is-over .md-time { color:var(--danger-600); }
.md-ic { width:34px; height:34px; flex-shrink:0; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:13px; background:var(--accent-50); color:var(--accent-600); }
.md-row.is-over .md-ic { background:var(--danger-50); color:var(--danger-600); }
.md-row.is-appt .md-ic { background:var(--warning-50); color:var(--warning-600); }
.md-main { flex:1; min-width:0; display:flex; flex-direction:column; text-decoration:none !important; color:inherit; }
.md-main b { font-size:13.5px; color:var(--page-text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.md-main span { font-size:11.5px; color:var(--page-text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px; }
a.md-main:hover b { color:var(--accent-600); }
.md-actions { display:flex; gap:6px; flex-shrink:0; }
.md-badge-over { background:var(--danger-50); color:var(--danger-600); }
.lk-btn.wa:hover { background:#25d366; border-color:#25d366; color:#fff; }
[data-theme="dark"] .myday-head { border-bottom-color:rgba(148,163,184,.12); }
[data-theme="dark"] .myday-segmented { background:rgba(148,163,184,.12); }
[data-theme="dark"] .md-filter-bar { border-bottom-color:rgba(148,163,184,.12); }
[data-theme="dark"] .md-row + .md-row { border-top-color:rgba(148,163,184,.12); }
[data-theme="dark"] .md-row:hover { background:rgba(148,163,184,.06); }
@media (max-width:600px) { .md-time { width:48px; font-size:11px; } .md-badge-over { display:none; } }
</style>
@endpush

@section('content')
@php
    use App\Support\DataScope;
    $can   = fn ($p) => DataScope::can('dashboard.' . $p);
    $canP  = fn ($p) => DataScope::can($p);
    $ov    = $statistics['overview'] ?? [];
    $hour  = (int) date('H');
    $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
    $val   = fn ($s) => is_object($s) && property_exists($s, 'value') ? $s->value : (string) $s;
    $label = fn ($s) => ucwords(str_replace('_', ' ', $val($s)));
@endphp

<div class="container-fluid" style="padding: 0 24px;">

    {{-- ═══ HEADER ═══ --}}
    <div class="crm-page-header dash-fade">
        <div class="page-title-group">
            <h1>{{ $greeting }}, {{ Auth::user()->name ?? 'there' }} 👋</h1>
            <p>{{ now()->format('l, F d, Y') }} · Updated {{ now()->format('h:i A') }}
                <span class="scope-chip" title="Numbers on this dashboard are filtered to this scope"><i class="fas fa-filter"></i>{{ $statistics['scope_label'] ?? 'All data' }}</span>
            </p>
        </div>
        <div class="header-actions">
            <a href="{{ route('dashboard.new') }}" class="btn btn-outline-secondary btn-sm" id="btn-dash-switch-new" title="Preview the new Today dashboard"><i class="fas fa-columns mr-1"></i>New dashboard</a>
            @if ($canP('add_appointment.create'))
                <a href="{{ url('admin/appointments/create') }}" class="btn btn-secondary btn-sm" id="btn-dash-add-appointment"><i class="far fa-calendar-plus mr-1"></i>Add Appointment</a>
            @endif
            @if ($canP('add_lead.create'))
                <a href="{{ url('admin/leads/create') }}" class="btn btn-secondary btn-sm" id="btn-dash-add-lead"><i class="fas fa-user-plus mr-1"></i>Add Lead</a>
            @endif
            @if ($canP('add_sale.create'))
                <a href="{{ url('admin/sale/create') }}" class="btn btn-primary btn-sm" id="btn-dash-new-sale"><i class="fas fa-plus mr-1"></i>New Sale</a>
            @endif
        </div>
    </div>

    {{-- ═══ KPI CARDS ═══ --}}
    <div class="kpi-row dash-fade" style="animation-delay:.04s;">
        @if ($can('total_leads'))
            <a href="{{ url('admin/leads') }}" class="kpi k-indigo" id="kpi-leads">
                <span class="k-ic"><i class="fas fa-user-tie"></i></span>
                <div>
                    <div class="k-lbl">Total Leads</div>
                    <div class="k-val">{{ number_format($ov['total_leads'] ?? 0) }}</div>
                    <div class="k-sub"><b>+{{ $ov['today_leads'] ?? 0 }}</b> today · {{ $statistics['leads']['this_month'] ?? 0 }} this month</div>
                </div>
                <span class="k-go">View <i class="fas fa-arrow-right"></i></span>
            </a>
        @endif
        @if ($can('todays_sales'))
            <a href="{{ url('admin/sale?start_date=' . now()->toDateString() . '&end_date=' . now()->toDateString()) }}" class="kpi k-green" id="kpi-today-sales">
                <span class="k-ic"><i class="fas fa-cash-register"></i></span>
                <div>
                    <div class="k-lbl">Today's Sales</div>
                    <div class="k-val">৳{{ number_format($ov['today_sales'] ?? 0) }}</div>
                    <div class="k-sub"><b>{{ $ov['today_sales_count'] ?? 0 }}</b> invoice(s) · Due ৳{{ number_format($statistics['sales']['today']['due'] ?? 0) }}</div>
                </div>
                <span class="k-go">View <i class="fas fa-arrow-right"></i></span>
            </a>
        @endif
        @if ($can('total_sales'))
            <a href="{{ url('admin/sale') }}" class="kpi k-violet" id="kpi-revenue">
                <span class="k-ic"><i class="fas fa-chart-line"></i></span>
                <div>
                    <div class="k-lbl">Total Revenue</div>
                    <div class="k-val">৳{{ number_format($ov['total_sales'] ?? 0) }}</div>
                    <div class="k-sub">This month <b>৳{{ number_format($statistics['sales']['month']['amount'] ?? 0) }}</b></div>
                </div>
                <span class="k-go">View <i class="fas fa-arrow-right"></i></span>
            </a>
        @endif
        @if ($can('total_customers'))
            <a href="{{ url('admin/customers') }}" class="kpi k-blue" id="kpi-customers">
                <span class="k-ic"><i class="fas fa-users"></i></span>
                <div>
                    <div class="k-lbl">Total Customers</div>
                    <div class="k-val">{{ number_format($ov['total_customers'] ?? 0) }}</div>
                    <div class="k-sub"><b>+{{ $statistics['customers']['today'] ?? 0 }}</b> today · {{ $statistics['customers']['this_month'] ?? 0 }} this month</div>
                </div>
                <span class="k-go">View <i class="fas fa-arrow-right"></i></span>
            </a>
        @endif
        @if ($can('total_products'))
            <a href="{{ url('admin/report/stock') }}" class="kpi k-amber" id="kpi-stock">
                <span class="k-ic"><i class="fas fa-boxes"></i></span>
                <div>
                    <div class="k-lbl">Stock · {{ $ov['total_products'] ?? 0 }} Products</div>
                    <div class="k-val">{{ number_format($statistics['inventory']['stock_units'] ?? 0) }} <small style="font-size:13px;font-weight:600;">pcs</small></div>
                    <div class="k-sub">Value <b>৳{{ number_format($statistics['inventory']['stock_value'] ?? 0) }}</b></div>
                </div>
                <span class="k-go">View <i class="fas fa-arrow-right"></i></span>
            </a>
        @endif
        @if ($can('follow_ups') || $can('total_tasks') || $can('total_tickets'))
            @php
                $fuOver = $can('follow_ups') ? ($statistics['follow_ups']['overdue'] ?? 0) : 0;
                $tkOver = $can('total_tasks') ? ($statistics['tasks']['overdue'] ?? 0) : 0;
                $tiAct  = $can('total_tickets') ? ($statistics['tickets']['active'] ?? 0) : 0;
            @endphp
            <a href="#my-day" class="kpi k-rose" id="kpi-attention">
                <span class="k-ic"><i class="fas fa-bell"></i></span>
                <div>
                    <div class="k-lbl">Needs Attention</div>
                    <div class="k-val">{{ $fuOver + $tkOver + $tiAct }}</div>
                    <div class="k-sub">
                        @if ($can('follow_ups'))<b>{{ $fuOver }}</b> follow-ups · @endif
                        @if ($can('total_tasks'))<b>{{ $tkOver }}</b> tasks · @endif
                        @if ($can('total_tickets'))<b>{{ $tiAct }}</b> tickets @endif
                    </div>
                </div>
                <span class="k-go">Open <i class="fas fa-arrow-down"></i></span>
            </a>
        @endif
        <a href="{{ route('sales.pending-services') }}" class="kpi k-blue" id="kpi-pending-services">
            <span class="k-ic" style="background:#0284c7; box-shadow:0 6px 14px -6px #0284c7;"><i class="fas fa-hand-holding-medical"></i></span>
            <div>
                <div class="k-lbl" style="color:#0369a1;">Pending Services</div>
                <div class="k-val">{{ number_format($ov['pending_services_count'] ?? 0) }}</div>
                <div class="k-sub"><b>{{ $ov['booked_services_count'] ?? 0 }}</b> booked · Action needed</div>
            </div>
            <span class="k-go" style="color:#0284c7;">View <i class="fas fa-arrow-right"></i></span>
        </a>
    </div>

    {{-- ═══ SMART ALERTS (Phase 1) ═══ --}}
    @include('admin.dashboard-legacy.partials.smart-alerts')

    {{-- ═══ SUPER MY DAY & LOOKOUT HUB (Phase 3) ═══ --}}
    @include('admin.dashboard-legacy.partials.my-day')


    {{-- ═══ INSIGHTS + INVENTORY ═══ --}}
    @if ($can('marketing_insights') || $can('inventory'))
    <div class="dash-grid {{ $can('marketing_insights') && $can('inventory') ? 'dash-g2' : '' }} dash-fade" style="animation-delay:.10s;">
        @if ($can('marketing_insights'))
        <div class="crm-table-card">
            <div class="crm-table-header"><div class="table-title-group"><h2><i class="fas fa-lightbulb" style="color:var(--warning-500);"></i> Marketing Insights</h2></div></div>
            <div style="padding:20px;">
                @php $ins = $statistics['insights'] ?? []; $lv = $ins['lead_velocity'] ?? 0; $rg = $ins['revenue_growth'] ?? 0; @endphp
                <div class="dash-grid dash-g4" style="margin-bottom:16px;gap:10px;">
                    <div class="metric-box"><div class="m-val" style="color:var(--accent-600);">৳{{ number_format($ins['avg_revenue_per_customer'] ?? 0) }}</div><div class="m-label">Avg Rev / Customer</div></div>
                    <div class="metric-box"><div class="m-val" style="color:{{ $lv >= 0 ? 'var(--success-600)' : 'var(--danger-600)' }};">{{ $lv >= 0 ? '+' : '' }}{{ $lv }}%</div><div class="m-label">Lead Velocity</div></div>
                    <div class="metric-box"><div class="m-val" style="color:{{ $rg >= 0 ? 'var(--success-600)' : 'var(--danger-600)' }};">{{ $rg >= 0 ? '+' : '' }}{{ $rg }}%</div><div class="m-label">Revenue Growth</div></div>
                    <div class="metric-box"><div class="m-val">{{ number_format($statistics['performance']['averages']['conversion_rate'] ?? 0, 1) }}%</div><div class="m-label">Conversion Rate</div></div>
                </div>
                @forelse (($ins['messages'] ?? []) as $msg)
                    <div class="insight-msg {{ $msg['type'] }}"><i class="{{ $msg['icon'] }}"></i><span>{{ $msg['text'] }}</span></div>
                @empty
                    <div class="insight-msg info"><i class="fas fa-info-circle"></i><span>Add leads and sales to generate insights.</span></div>
                @endforelse
            </div>
        </div>
        @endif

        @if ($can('inventory'))
        @php $inv = $statistics['inventory'] ?? []; @endphp
        <div class="crm-table-card">
            <div class="crm-table-header" style="padding-bottom:0;border-bottom:none;">
                <div class="table-title-group"><h2><i class="fas fa-warehouse" style="color:var(--danger-500);"></i> Inventory Alerts</h2></div>
                <a href="{{ url('admin/report/stock') }}" style="font-size:11px;color:var(--accent-500);font-weight:600;text-decoration:none;">Stock report →</a>
            </div>
            <div style="padding:0 20px;">
                <div class="inv-tabs" id="invTabs">
                    <button type="button" class="inv-tab active" data-inv="low">Low Stock <span class="tab-count">{{ $inv['low_stock']['count'] ?? 0 }}</span></button>
                    <button type="button" class="inv-tab" data-inv="fast">Fast Moving <span class="tab-count">{{ $inv['fast_moving']['count'] ?? 0 }}</span></button>
                    <button type="button" class="inv-tab" data-inv="needed">Purchase Needed <span class="tab-count">{{ $inv['purchase_needed']['count'] ?? 0 }}</span></button>
                </div>
            </div>
            @foreach (['low' => ['low_stock', 'fas fa-box-open', 'danger', 'All stocked up! 🎉'], 'fast' => ['fast_moving', 'fas fa-fire', 'success', 'No sales data in last 30 days'], 'needed' => ['purchase_needed', 'fas fa-shopping-cart', 'warning', 'No purchase needed']] as $k => [$src, $ic, $tone, $empty])
            <div class="inv-panel {{ $k === 'low' ? 'active' : '' }}" id="inv-{{ $k }}">
                @forelse (collect($inv[$src]['products'] ?? [])->take(8) as $item)
                    <div class="lk">
                        <div class="li-icon" style="background:var(--{{ $tone }}-50);color:var(--{{ $tone }}-600);"><i class="{{ $ic }}"></i></div>
                        <div class="li-main"><div class="li-title">{{ $item['product_name'] }}</div><div class="li-sub">{{ $item['category'] }}</div></div>
                        <span class="li-badge" style="background:var(--{{ $tone }}-50);color:var(--{{ $tone }}-600);">{{ $k === 'fast' ? $item['qty_sold'] . ' sold' : $item['stock'] . ' pcs' }}</span>
                        @if ($k === 'needed' && $canP('add_purchase.create'))
                            <div class="lk-actions"><a href="{{ url('admin/purchase/create') }}" class="lk-btn" title="Create purchase"><i class="fas fa-cart-plus"></i></a></div>
                        @endif
                    </div>
                @empty
                    <div class="lk-empty">{{ $empty }}</div>
                @endforelse
            </div>
            @endforeach
        </div>
        @endif
    </div>
    @endif

    {{-- ═══ REVENUE CHART + LEAD PIPELINE ═══ --}}
    @if ($can('revenue_chart') || $can('lead_pipeline'))
    <div class="dash-grid {{ $can('revenue_chart') && $can('lead_pipeline') ? 'dash-g21' : '' }} dash-fade" style="animation-delay:.13s;">
        @if ($can('revenue_chart'))
        <div class="crm-table-card">
            <div class="crm-table-header">
                <div class="table-title-group"><h2><i class="fas fa-chart-area" style="color:var(--accent-500);"></i> Revenue vs Purchases <small id="revRangeLabel" style="font-size:11px;color:var(--page-text-muted);font-weight:500;">last 6 months</small></h2></div>
                <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;font-size:11px;color:var(--page-text-muted);">
                    @if (!empty($analytics['revenue_extended']) && empty($analytics['revenue_extended']['error']))
                        <div class="an-seg-ctrl" id="revRange" role="group" aria-label="Chart range"><button type="button" class="active" data-range="6">6M</button><button type="button" data-range="12">12M</button></div>
                        <label class="an-ly" for="revLy"><input type="checkbox" id="revLy"> vs last year</label>
                    @endif
                    <span><span style="display:inline-block;width:8px;height:8px;background:#6366f1;border-radius:2px;margin-right:4px;"></span>Sales</span>
                    <span><span style="display:inline-block;width:8px;height:8px;background:#10b981;border-radius:2px;margin-right:4px;"></span>Purchases</span>
                </div>
            </div>
            <div style="padding:20px;"><div style="height:280px;"><canvas id="revenueChart"></canvas></div></div>
        </div>
        @endif
        @if ($can('lead_pipeline'))
        <div class="crm-table-card">
            <div class="crm-table-header"><div class="table-title-group"><h2><i class="fas fa-filter" style="color:var(--info-500);"></i> Lead Pipeline</h2></div><a href="{{ url('admin/leads') }}" style="font-size:11px;color:var(--accent-500);font-weight:600;text-decoration:none;">View All →</a></div>
            <div style="padding:20px;">
                @php
                    $funnel = collect($statistics['charts']['lead_status_distribution'] ?? [])->keyBy(fn ($r) => $val($r->lead_status));
                    $totalLeads = max($ov['total_leads'] ?? 1, 1);
                    $fColors = ['#818cf8','#38bdf8','#a78bfa','#22d3ee','#fbbf24','#fb923c','#f472b6','#34d399','#f87171'];
                @endphp
                @foreach (\App\Enums\LeadStatus::cases() as $i => $st)
                    @php $cnt = $funnel[$st->value]->count ?? 0; $pct = round($cnt / $totalLeads * 100); @endphp
                    <a class="pipe-step" href="{{ url('admin/leads?lead_status=' . $st->value) }}" title="Show {{ $st->label() }} leads">
                        <span class="pipe-label">{{ $st->label() }}</span>
                        <div class="pipe-track"><div class="pipe-fill" style="width:{{ $cnt ? max($pct, 8) : 0 }}%;background:{{ $fColors[$i % count($fColors)] }};">{{ $cnt ? $pct . '%' : '' }}</div></div>
                        <span class="pipe-num">{{ $cnt }}</span>
                    </a>
                @endforeach
                @php $temp = $analytics['lead_temperature'] ?? null; @endphp
                @if ($temp && empty($temp['error']))
                    <div class="an-temp" title="Open leads by temperature">
                        <a href="{{ url('admin/leads?lead_temperature=hot') }}" class="hot" id="temp-hot"><span><i class="fas fa-fire mr-1"></i>Hot</span><b>{{ $temp['hot'] }}</b></a>
                        <a href="{{ url('admin/leads?lead_temperature=warm') }}" class="warm" id="temp-warm"><span><i class="fas fa-sun mr-1"></i>Warm</span><b>{{ $temp['warm'] }}</b></a>
                        <a href="{{ url('admin/leads?lead_temperature=cold') }}" class="cold" id="temp-cold"><span><i class="fas fa-snowflake mr-1"></i>Cold</span><b>{{ $temp['cold'] }}</b></a>
                    </div>
                @endif
                <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--gray-100);display:flex;justify-content:space-between;font-size:12px;color:var(--page-text-muted);">
                    <span><strong style="color:var(--page-text);">{{ $statistics['leads']['this_month'] ?? 0 }}</strong> new this month</span>
                    <span><strong style="color:var(--success-600);">{{ $statistics['leads']['converted'] ?? 0 }}</strong> converted</span>
                </div>
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- ═══ LEAD CHARTS ═══ --}}
    @if ($can('lead_charts'))
    <div class="dash-grid dash-g3 dash-fade" style="animation-delay:.16s;">
        @foreach ([['chartType','legendType','fas fa-user-tag','#0d9488','Leads by Type','Leads'], ['chartSource','legendSource','fas fa-bullhorn','var(--accent-500)','Leads by Source','Sources'], ['chartInquiry','legendInquiry','fas fa-search','var(--warning-500)','Inquiry For','Inquiries']] as [$cid, $lid, $ic, $clr, $ttl, $sub])
        <div class="crm-table-card">
            <div class="crm-table-header"><div class="table-title-group"><h2><i class="{{ $ic }}" style="color:{{ $clr }};"></i> {{ $ttl }}</h2></div></div>
            <div style="padding:20px;display:flex;align-items:center;justify-content:center;min-height:180px;">
                <div style="width:140px;height:140px;position:relative;"><canvas id="{{ $cid }}"></canvas><div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;pointer-events:none;"><div style="font-size:18px;font-weight:700;color:var(--page-text);">{{ $ov['total_leads'] ?? 0 }}</div><div style="font-size:9px;color:var(--page-text-muted);text-transform:uppercase;font-weight:600;">{{ $sub }}</div></div></div>
                <div id="{{ $lid }}" style="margin-left:16px;display:flex;flex-direction:column;gap:6px;"></div>
            </div>
        </div>
        @endforeach
    </div>
    @php $roi = $analytics['source_roi'] ?? null; @endphp
    @if ($roi && empty($roi['error']) && count($roi['rows'] ?? []))
    <div class="crm-table-card dash-fade" id="source-roi" style="animation-delay:.17s;margin-bottom:24px;">
        <div class="crm-table-header"><div class="table-title-group"><h2><i class="fas fa-funnel-dollar" style="color:var(--accent-500);"></i> Lead Source ROI <small style="font-size:11px;color:var(--page-text-muted);font-weight:500;">which sources bring paying customers</small></h2></div></div>
        <div style="padding:12px 20px 18px;overflow-x:auto;">
            <table class="an-table">
                <thead><tr><th>Source</th><th class="c">Leads</th><th class="c">Won</th><th class="c">Conversion</th><th class="c">Buyers</th><th style="min-width:200px;">Revenue</th></tr></thead>
                <tbody>
                    @foreach ($roi['rows'] as $r)
                        <tr>
                            <td style="font-weight:600;">@if ($r['key'] !== 'none')<a href="{{ url('admin/leads?source_of_entry=' . $r['key']) }}">{{ $r['source'] }}</a>@else{{ $r['source'] }}@endif</td>
                            <td class="c">{{ $r['leads'] }}</td><td class="c">{{ $r['won'] }}</td>
                            <td class="c"><span class="an-chip {{ $r['conversion'] >= 30 ? 'up' : ($r['conversion'] > 0 ? 'flat' : 'down') }}">{{ $r['conversion'] }}%</span></td>
                            <td class="c">{{ $r['buyers'] }}</td>
                            <td><b>৳{{ number_format($r['revenue']) }}</b><div class="an-inline-bar"><span style="width:{{ $r['revenue'] ? max(round($r['revenue'] / $roi['max'] * 100), 3) : 0 }}%;background:var(--accent-500);"></span></div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
    @endif

    {{-- ═══ FINANCIAL + STAFF PERFORMANCE ═══ --}}
    @if ($can('net_balance') || $can('performance'))
    <div class="dash-grid {{ $can('net_balance') && $can('performance') ? 'dash-g2' : '' }} dash-fade" style="animation-delay:.19s;margin-bottom:32px;">
        @if ($can('net_balance'))
        <div class="crm-table-card">
            <div class="crm-table-header"><div class="table-title-group"><h2><i class="fas fa-balance-scale" style="color:var(--success-500);"></i> Financial Summary</h2></div>@if (!empty($analytics['receivables_aging']) && empty($analytics['receivables_aging']['error']))<a href="#w-receivables_aging" style="font-size:11px;color:var(--accent-500);font-weight:600;text-decoration:none;" id="link-dues-aging">View dues aging →</a>@endif</div>
            <div style="padding:20px;">
                <div class="ledger-row"><span style="color:var(--page-text-muted);">Total Sales</span><span style="font-weight:600;">৳{{ number_format($ov['total_sales'] ?? 0) }}</span></div>
                <div class="ledger-row"><span style="color:var(--page-text-muted);">Collected</span><span style="font-weight:600;color:var(--success-600);">৳{{ number_format($statistics['sales']['total']['paid'] ?? 0) }}</span></div>
                <div class="ledger-row"><span style="color:var(--page-text-muted);">Customer Due</span><span style="font-weight:600;color:var(--danger-600);">৳{{ number_format($statistics['sales']['total']['due'] ?? 0) }}</span></div>
                @if ($can('total_purchases'))
                    <div class="ledger-row"><span style="color:var(--page-text-muted);">Purchases</span><span style="font-weight:600;">৳{{ number_format($statistics['purchases']['total']['amount'] ?? 0) }}</span></div>
                    <div class="ledger-row"><span style="color:var(--page-text-muted);">Supplier Due</span><span style="font-weight:600;color:var(--danger-600);">৳{{ number_format($statistics['purchases']['total']['due'] ?? 0) }}</span></div>
                @endif
                @php $np = $statistics['financials']['net_profit'] ?? 0; @endphp
                <div class="ledger-row" style="border-top:2px solid var(--gray-200);margin-top:4px;padding-top:10px;"><span style="font-weight:600;color:var(--page-text);">Net Balance (Month)</span><span style="font-weight:700;color:{{ $np >= 0 ? 'var(--success-600)' : 'var(--danger-600)' }};">৳{{ number_format($np) }}</span></div>
            </div>
        </div>
        @endif
        @if ($can('performance'))
        <div class="crm-table-card">
            <div class="crm-table-header"><div class="table-title-group"><h2><i class="fas fa-award" style="color:var(--warning-500);"></i> Staff Performance</h2></div></div>
            <div style="padding:20px;">
                @php $staffX = (!empty($analytics['staff_extra']) && empty($analytics['staff_extra']['error'])) ? $analytics['staff_extra'] : null; @endphp
                <table style="width:100%;">
                    <thead><tr>
                        <th style="font-size:10px;font-weight:600;text-transform:uppercase;color:var(--page-text-muted);padding:4px 0 8px;border-bottom:1px solid var(--gray-200);text-align:left;">Staff</th>
                        <th style="font-size:10px;font-weight:600;text-transform:uppercase;color:var(--page-text-muted);padding:4px 0 8px;border-bottom:1px solid var(--gray-200);text-align:center;">Leads</th>
                        <th style="font-size:10px;font-weight:600;text-transform:uppercase;color:var(--page-text-muted);padding:4px 0 8px;border-bottom:1px solid var(--gray-200);text-align:center;">Conv.</th>
                        <th style="font-size:10px;font-weight:600;text-transform:uppercase;color:var(--page-text-muted);padding:4px 0 8px;border-bottom:1px solid var(--gray-200);text-align:center;">Follow-ups</th>
                        <th style="font-size:10px;font-weight:600;text-transform:uppercase;color:var(--page-text-muted);padding:4px 0 8px;border-bottom:1px solid var(--gray-200);text-align:right;">Revenue</th>
                        @if ($staffX)
                            <th style="font-size:10px;font-weight:600;text-transform:uppercase;color:var(--page-text-muted);padding:4px 0 8px;border-bottom:1px solid var(--gray-200);text-align:center;" title="Completed appointments">Appts</th>
                            <th style="font-size:10px;font-weight:600;text-transform:uppercase;color:var(--page-text-muted);padding:4px 0 8px;border-bottom:1px solid var(--gray-200);text-align:right;" title="Average order value">AOV</th>
                            <th style="font-size:10px;font-weight:600;text-transform:uppercase;color:var(--page-text-muted);padding:4px 0 8px;border-bottom:1px solid var(--gray-200);text-align:right;" title="Total discount given">Disc.</th>
                        @endif
                    </tr></thead>
                    <tbody>
                        @forelse (collect($statistics['performance']['staff_performance'] ?? [])->take(6) as $s)
                            @php $cr = $s['conversion_rate']; $tone = $cr >= 50 ? 'success' : ($cr >= 20 ? 'warning' : 'danger'); @endphp
                            <tr>
                                <td style="font-size:13px;padding:9px 0;border-bottom:1px solid var(--gray-100);"><div style="display:flex;align-items:center;gap:8px;"><div style="width:28px;height:28px;border-radius:8px;background:var(--accent-100);color:var(--accent-700);display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;">{{ strtoupper(substr($s['staff_name'], 0, 2)) }}</div><span style="font-weight:500;">{{ $s['staff_name'] }}</span></div></td>
                                <td style="font-size:13px;padding:9px 0;text-align:center;border-bottom:1px solid var(--gray-100);">{{ $s['total_leads'] }}</td>
                                <td style="padding:9px 0;text-align:center;border-bottom:1px solid var(--gray-100);"><span style="font-size:11px;padding:2px 8px;border-radius:20px;font-weight:600;background:var(--{{ $tone }}-50);color:var(--{{ $tone }}-600);">{{ $cr }}%</span></td>
                                <td style="font-size:13px;padding:9px 0;text-align:center;border-bottom:1px solid var(--gray-100);">{{ $s['follow_ups_completed'] }}</td>
                                <td style="font-size:13px;padding:9px 0;text-align:right;font-weight:600;border-bottom:1px solid var(--gray-100);">৳{{ number_format($s['total_revenue']) }}</td>
                                @if ($staffX)
                                    @php $sx = $staffX[(string) $s['staff_id']] ?? ['appointments' => 0, 'aov' => 0, 'discount' => 0]; @endphp
                                    <td style="font-size:13px;padding:9px 0;text-align:center;border-bottom:1px solid var(--gray-100);">{{ $sx['appointments'] }}</td>
                                    <td style="font-size:12px;padding:9px 0;text-align:right;border-bottom:1px solid var(--gray-100);">৳{{ number_format($sx['aov']) }}</td>
                                    <td style="font-size:12px;padding:9px 0;text-align:right;border-bottom:1px solid var(--gray-100);color:{{ $sx['discount'] > 0 ? 'var(--warning-600)' : 'var(--page-text-muted)' }};">৳{{ number_format($sx['discount']) }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $staffX ? 8 : 5 }}" style="text-align:center;color:var(--page-text-muted);padding:24px;">No data yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- ═══ BUSINESS ANALYTICS (Phase 1) ═══ --}}
    @include('admin.dashboard-legacy.partials.analytics')

    @if (!$can('index') && !auth()->user()?->isSuperAdmin())
        <div class="lk-empty">No dashboard widgets are enabled for your role yet. Ask an administrator to enable them in <b>Roles → Manage Permissions → Dashboard</b>.</div>
    @endif
</div>

{{-- Complete follow-up modal --}}
<div class="modal fade" id="completeFollowUpModal" tabindex="-1" role="dialog" aria-labelledby="cfuTitle">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width:460px;">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;">
            <div class="modal-header">
                <h5 class="modal-title" id="cfuTitle"><i class="fas fa-check-circle mr-2" style="color:var(--success-500);"></i>Complete follow-up</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p style="font-size:13px;color:var(--page-text-secondary);margin-bottom:10px;">Lead: <b id="cfuLead"></b></p>
                <label for="cfuNotes" style="font-size:12px;font-weight:600;color:var(--page-text-secondary);">Outcome / notes (optional)</label>
                <textarea id="cfuNotes" class="form-control" rows="3" placeholder="e.g. Talked to customer, interested in trial next week"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="cfuSave"><i class="fas fa-check mr-1"></i>Mark Complete</button>
            </div>
        </div>
    </div>
</div>
<div id="dashToast"></div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const CSRF = @json(csrf_token());

    /* ── Toast ── */
    function toast(msg, ok = true) {
        const el = document.createElement('div');
        el.className = 'dt ' + (ok ? 'ok' : 'err');
        el.innerHTML = '<i class="fas ' + (ok ? 'fa-check-circle' : 'fa-exclamation-circle') + '"></i>' + msg;
        document.getElementById('dashToast').appendChild(el);
        setTimeout(() => el.remove(), 3200);
    }

    /* ── AJAX helper ── */
    async function send(url, method, body) {
        const res = await fetch(url, {
            method: method,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body || {})
        });
        if (!res.ok) {
            let m = 'Could not update (' + res.status + ')';
            try { const j = await res.json(); if (j.message) m = j.message; } catch (e) {}
            throw new Error(res.status === 403 ? 'You do not have permission for this action' : m);
        }
        return res.json().catch(() => ({}));
    }

    /* Remove a row and decrement its tab counter */
    function finishRow(row) {
        const key = row.dataset.row;
        row.classList.add('done');
        setTimeout(() => {
            const panel = row.parentElement;
            row.remove();
            const badge = document.querySelector('[data-count-for="' + key + '"]');
            if (badge) badge.textContent = Math.max(0, parseInt(badge.textContent || '0', 10) - 1);
            if (panel && !panel.querySelector('.lk')) {
                const empty = document.createElement('div');
                empty.className = 'lk-empty';
                empty.innerHTML = '<i class="fas fa-check-circle" style="color:var(--success-500);"></i>All done here 🎉';
                panel.prepend(empty);
            }
        }, 300);
    }

    /* ── Tabs ── */
    document.querySelectorAll('#lookoutTabs .lookout-tab').forEach(tab => tab.addEventListener('click', function () {
        document.querySelectorAll('#lookoutTabs .lookout-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        document.querySelectorAll('.lookout-panel').forEach(p => p.classList.remove('active'));
        const p = document.getElementById('panel-' + this.dataset.tab); if (p) p.classList.add('active');
    }));
    document.querySelectorAll('#invTabs .inv-tab').forEach(tab => tab.addEventListener('click', function () {
        document.querySelectorAll('#invTabs .inv-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        document.querySelectorAll('.inv-panel').forEach(p => p.classList.remove('active'));
        const p = document.getElementById('inv-' + this.dataset.inv); if (p) p.classList.add('active');
    }));

    /* ── Status dropdowns (appointments / tasks / tickets) ── */
    document.querySelectorAll('.js-status').forEach(sel => {
        sel.dataset.prev = sel.value;
        sel.addEventListener('change', async function () {
            const row = this.closest('.lk');
            this.disabled = true;
            try {
                await send(this.dataset.url, this.dataset.method, { status: this.value });
                toast('Status updated to "' + this.options[this.selectedIndex].text + '"');
                this.dataset.prev = this.value;
                if ((this.dataset.done || '').split(',').includes(this.value)) finishRow(row);
            } catch (e) {
                this.value = this.dataset.prev;
                toast(e.message, false);
            } finally { this.disabled = false; }
        });
    });

    /* ── One-click complete (tasks) ── */
    document.querySelectorAll('.js-quick').forEach(btn => btn.addEventListener('click', async function () {
        const row = this.closest('.lk');
        this.disabled = true;
        try {
            await send(this.dataset.url, this.dataset.method, { status: this.dataset.status });
            toast('Marked as complete');
            finishRow(row);
        } catch (e) { toast(e.message, false); this.disabled = false; }
    }));

    /* ── Complete follow-up (modal with outcome note) ── */
    let fuBtn = null;
    document.querySelectorAll('.js-complete-followup').forEach(btn => btn.addEventListener('click', function () {
        fuBtn = this;
        document.getElementById('cfuLead').textContent = this.dataset.name;
        document.getElementById('cfuNotes').value = '';
        $('#completeFollowUpModal').modal('show');
    }));
    document.getElementById('cfuSave').addEventListener('click', async function () {
        if (!fuBtn) return;
        this.disabled = true;
        try {
            await send(@json(url('admin/leads/follow-ups')) + '/' + fuBtn.dataset.id + '/complete', 'PATCH', { outcome_notes: document.getElementById('cfuNotes').value });
            $('#completeFollowUpModal').modal('hide');
            toast('Follow-up completed');
            finishRow(fuBtn.closest('.lk'));
        } catch (e) { toast(e.message, false); }
        finally { this.disabled = false; }
    });

    /* ── Super My Day: View Switcher (My Tasks vs Branch Lookout) ── */
    document.querySelectorAll('#myDaySwitcher .md-seg-btn').forEach(btn => btn.addEventListener('click', function () {
        document.querySelectorAll('#myDaySwitcher .md-seg-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const view = this.dataset.view;
        const pTasks = document.getElementById('pane-my-tasks');
        const pLookout = document.getElementById('pane-branch-lookout');
        if (pTasks && pLookout) {
            if (view === 'personal') {
                pTasks.style.display = 'block';
                pLookout.style.display = 'none';
            } else {
                pTasks.style.display = 'none';
                pLookout.style.display = 'block';
            }
        }
    }));

    /* ── Super My Day: Category Filter Chips ── */
    document.querySelectorAll('#mdCategoryChips .md-chip').forEach(chip => chip.addEventListener('click', function () {
        document.querySelectorAll('#mdCategoryChips .md-chip').forEach(c => c.classList.remove('active'));
        this.classList.add('active');
        const cat = this.dataset.cat;
        document.querySelectorAll('#myday-list .md-row').forEach(row => {
            if (cat === 'all' || row.dataset.cat === cat) {
                row.style.display = 'flex';
            } else {
                row.style.display = 'none';
            }
        });
    }));

    /* ── Decrement badge counters on item completion ── */
    function decrementMyDayCounts() {
        ['stat-todo-count', 'count-personal-badge', 'chip-count-all'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                const c = parseInt(el.textContent) || 0;
                if (c > 0) el.textContent = c - 1;
            }
        });
    }

    /* ── My Day: one-click done (Deal activities) ── */
    const DEALS = @json(url('admin/deals'));
    document.querySelectorAll('.js-md-done').forEach(btn => btn.addEventListener('click', async function () {
        const row = this.closest('.md-row');
        this.disabled = true;
        try {
            await send(DEALS + '/activities/' + this.dataset.id + '/complete', 'POST', {});
            toast('Marked as done');
            row.classList.add('done');
            decrementMyDayCounts();
            setTimeout(() => row.remove(), 300);
        } catch (e) { toast(e.message, false); this.disabled = false; }
    }));

    /* ── Phase 3 After-Sales: one-click done (Service / Warranty / Birthdays) ── */
    const CUSTOMERS = @json(url('admin/customers'));
    document.querySelectorAll('.js-after-sales-done').forEach(btn => btn.addEventListener('click', async function () {
        const row = this.closest('.md-row');
        const custId = this.dataset.customerId;
        const type = this.dataset.type;
        this.disabled = true;
        try {
            const res = await send(CUSTOMERS + '/' + custId + '/after-sales-action', 'POST', { type: type });
            toast(res.message || 'After-sales touch recorded to timeline');
            row.classList.add('done');
            decrementMyDayCounts();
            setTimeout(() => row.remove(), 300);
        } catch (e) {
            toast(e.message, false);
            this.disabled = false;
        }
    }));

    /* ── Call / WhatsApp: open the app and log the contact on the deal (never blocks the link) ── */
    document.querySelectorAll('.js-contact').forEach(a => a.addEventListener('click', function () {
        if (!this.dataset.deal) return;
        send(DEALS + '/' + this.dataset.deal + '/contact', 'POST', { channel: this.dataset.channel }).catch(() => {});
    }));

    /* ── Charts ── */
    if (typeof Chart === 'undefined') return;
    const css = n => getComputedStyle(document.documentElement).getPropertyValue(n).trim();
    const tip = { backgroundColor: css('--card-bg') || '#fff', titleColor: css('--page-text') || '#1e293b', bodyColor: css('--page-text-secondary') || '#64748b', borderColor: css('--card-border') || '#e2e8f0', borderWidth: 1, padding: 10 };
    const colors = ['#6366f1','#0d9488','#d97706','#e11d48','#7c3aed','#2563eb','#059669','#ea580c','#be185d','#4f46e5'];

    const rEl = document.getElementById('revenueChart');
    if (rEl) {
        const ctx = rEl.getContext('2d');
        const grad = ctx.createLinearGradient(0, 0, 0, 280);
        grad.addColorStop(0, 'rgba(99,102,241,0.18)'); grad.addColorStop(1, 'rgba(99,102,241,0)');
        const sd = @json($statistics['charts']['monthly_sales'] ?? []);
        const pd = @json($statistics['charts']['monthly_purchases'] ?? []);
        const ds = [{ label: 'Sales', data: sd.map(i => i.total), borderColor: '#6366f1', backgroundColor: grad, borderWidth: 2.5, tension: .4, fill: true, pointBackgroundColor: '#fff', pointBorderColor: '#6366f1', pointBorderWidth: 2, pointRadius: 4 }];
        @if ($can('total_purchases'))
        ds.push({ label: 'Purchases', data: pd.map(i => i.total), borderColor: '#10b981', borderWidth: 2, borderDash: [5, 5], tension: .4, fill: false, pointBackgroundColor: '#fff', pointBorderColor: '#10b981', pointBorderWidth: 2, pointRadius: 4 });
        @endif
        window.dashRevenueChart = new Chart(ctx, { type: 'line', data: { labels: sd.map(i => i.month), datasets: ds }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: Object.assign({}, tip, { callbacks: { label: c => c.dataset.label + ': ৳' + Number(c.raw).toLocaleString() } }) }, scales: { y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,0.08)' }, ticks: { color: '#94a3b8', font: { size: 11 }, callback: v => v >= 1000 ? '৳' + (v / 1000) + 'k' : '৳' + v } }, x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 11 } } } } } });
    }

    function donut(canvasId, legendId, rows, key, offset) {
        const el = document.getElementById(canvasId);
        if (!el || !rows || !rows.length) { const l = document.getElementById(legendId); if (l) l.innerHTML = '<span style="font-size:12px;color:var(--page-text-muted);">No data</span>'; return; }
        const labels = rows.map(i => { let v = i[key]; if (v && typeof v === 'object') v = Array.isArray(v) ? v.join(', ') : Object.values(v).join(', '); return v ? String(v).replace(/[\[\]"]/g, '').replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'Other'; });
        const c = labels.map((_, i) => colors[(i + offset) % colors.length]);
        new Chart(el.getContext('2d'), { type: 'doughnut', data: { labels, datasets: [{ data: rows.map(i => i.count), backgroundColor: c, borderWidth: 0, hoverOffset: 4 }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { display: false }, tooltip: tip } } });
        document.getElementById(legendId).innerHTML = labels.map((l, i) => '<div style="display:flex;align-items:center;gap:6px;"><span style="width:8px;height:8px;border-radius:2px;background:' + c[i] + ';flex-shrink:0;"></span><span style="font-size:11px;color:var(--page-text-secondary);">' + l + ' <b>' + rows[i].count + '</b></span></div>').join('');
    }
    donut('chartType', 'legendType', @json($statistics['charts']['lead_type_distribution'] ?? []), 'lead_type', 0);
    donut('chartSource', 'legendSource', @json($statistics['charts']['lead_source_distribution'] ?? []), 'source_of_entry', 2);
    donut('chartInquiry', 'legendInquiry', @json($statistics['charts']['lead_inquiry_distribution'] ?? []), 'inquiry_for', 4);
});
</script>
@endpush
