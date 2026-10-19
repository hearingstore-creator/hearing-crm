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

.td-tiles { display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:16px; margin-bottom:24px; }
.td-tile {
    --c: #6366f1; --bg: rgba(99,102,241,.07); --bd: rgba(99,102,241,.22); --tx: #4338ca;
    position:relative; display:flex; flex-direction:column; justify-content:space-between;
    padding:16px 18px; border-radius:16px;
    background:var(--bg);
    border:1px solid var(--bd);
    color:var(--page-text, #1e293b); text-decoration:none !important;
    transition:transform .2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow .2s cubic-bezier(0.4, 0, 0.2, 1), border-color .2s ease;
    min-height:144px; overflow:hidden;
}
.td-tile:hover {
    transform:translateY(-3px);
    box-shadow:0 12px 26px -8px var(--c);
    border-color:var(--c);
    color:var(--page-text, #1e293b);
}
.td-tile::after {
    content:''; position:absolute; right:-25px; top:-25px; width:96px; height:96px;
    border-radius:50%; background:var(--c); opacity:.08; pointer-events:none;
}
.td-tile .t-top { display:flex; align-items:center; gap:12px; }
.td-tile .t-halo {
    width:42px; height:42px; border-radius:12px;
    display:inline-flex; align-items:center; justify-content:center;
    background:var(--c); color:#ffffff;
    font-size:17px; flex-shrink:0;
    box-shadow:0 6px 14px -5px var(--c);
    transition:transform .2s ease;
}
.td-tile:hover .t-halo { transform:scale(1.08); }
.td-tile .t-top-text, .td-tile .t-top span:last-child {
    font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.05em;
    color:var(--tx); opacity:.95; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.td-tile .t-body { position:relative; z-index:2; margin-top:6px; }
.td-tile .t-val { font-size:26px; font-weight:800; line-height:1.15; letter-spacing:-0.02em; color:var(--page-text, #0f172a); }
.td-tile .t-footer { display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin-top:5px; font-size:12px; }
.td-tile .t-chg {
    display:inline-flex; align-items:center; gap:3px;
    font-size:11px; font-weight:700; padding:2px 8px; border-radius:999px;
    line-height:1.3;
}
.td-tile .t-chg.up   { background:#ecfdf5; color:#047857; }
.td-tile .t-chg.down { background:#fff1f2; color:#be123c; }
.td-tile .t-chg.flat { background:#f1f5f9; color:#475569; }
.td-tile .t-sub { font-size:11.5px; color:var(--page-text-muted, #64748b); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:140px; }
.td-tile .t-sub b { color:var(--tx); font-weight:700; }
.td-tile svg.t-spark {
    position:absolute; right:10px; bottom:8px; width:110px; height:42px;
    overflow:visible; pointer-events:none; z-index:1;
}

/* Category-specific color tokens matching the user's KPI design system */
.td-tile-new_leads, .td-tile-total_leads, .td-tile-leads {
    --c:#6366f1; --bg:rgba(99,102,241,.07); --bd:rgba(99,102,241,.22); --tx:#4338ca;
}
.td-tile-sales_today, .td-tile-todays_sales, .td-tile-sales {
    --c:#10b981; --bg:rgba(16,185,129,.07); --bd:rgba(16,185,129,.22); --tx:#047857;
}
.td-tile-cash_in, .td-tile-total_sales, .td-tile-revenue {
    --c:#8b5cf6; --bg:rgba(139,92,246,.07); --bd:rgba(139,92,246,.22); --tx:#6d28d9;
}
.td-tile-customers, .td-tile-total_customers {
    --c:#3b82f6; --bg:rgba(59,130,246,.07); --bd:rgba(59,130,246,.22); --tx:#1d4ed8;
}
.td-tile-bookings, .td-tile-appointments, .td-tile-pending_services {
    --c:#0ea5e9; --bg:rgba(14,165,233,.07); --bd:rgba(14,165,233,.22); --tx:#0369a1;
}
.td-tile-open_repairs, .td-tile-stock, .td-tile-total_products {
    --c:#f59e0b; --bg:rgba(245,158,11,.08); --bd:rgba(245,158,11,.24); --tx:#b45309;
}
.td-tile-patient_dues, .td-tile-attention, .td-tile-needs_attention {
    --c:#f43f5e; --bg:rgba(244,63,94,.07); --bd:rgba(244,63,94,.22); --tx:#be123c;
}

.td-skel {
    background:linear-gradient(90deg, #f8fafc 25%, #f1f5f9 50%, #f8fafc 75%);
    background-size:200% 100%; animation:tdSk 1.4s infinite;
    border-radius:16px; min-height:144px; border:1px solid #e2e8f0; opacity:.85;
}
@keyframes tdSk { from { background-position:200% 0; } to { background-position:-200% 0; } }

.td-grid { display:grid; grid-template-columns:3fr 2fr; gap:20px; margin-bottom:20px; }
.td-charts { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px; }
@media (max-width:1100px) { .td-grid, .td-charts { grid-template-columns:1fr; } }
.td-card {
    background:#ffffff;
    border:1px solid rgba(226, 232, 240, 0.95);
    border-radius:16px;
    padding:20px;
    min-width:0;
    box-shadow:0 1px 3px rgba(15, 23, 42, 0.04), 0 4px 12px -2px rgba(15, 23, 42, 0.03);
    transition:transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    position:relative;
}
.td-card:hover {
    box-shadow:0 8px 24px -4px rgba(15, 23, 42, 0.08);
}
#td-my-day-card { border-top: 3px solid #6366f1; }
#td-chart-revenue-card { border-top: 3px solid #8b5cf6; }
#td-chart-category-card { border-top: 3px solid #10b981; }
.td-more-card { border-top: 3px solid #0ea5e9; }

.td-card h3 { font-size:14.5px; font-weight:700; margin:0 0 4px; display:flex; align-items:center; gap:10px; color:var(--page-text, #0f172a); }
.td-card h3 .t-halo {
    width:34px; height:34px; border-radius:10px;
    display:inline-flex; align-items:center; justify-content:center;
    font-size:15px; flex-shrink:0;
    box-shadow:0 4px 10px -2px rgba(0,0,0,0.12);
}
.td-card .td-note { font-size:12px; color:var(--page-text-muted, #64748b); margin-bottom:14px; }
.td-card .td-more { font-size:12px; font-weight:600; margin-left:auto; color:var(--accent-600); text-decoration:none !important; }
.td-card .td-more:hover { text-decoration:underline !important; }
.td-chart-box { position:relative; height:260px; min-height:260px; }
.td-empty { padding:24px 8px; text-align:center; font-size:13px; color:var(--page-text-muted); }
.td-err { padding:12px; font-size:13px; color:#b91c1c; }

/* Smart Alerts banner */
.an-alerts { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:20px; }
.an-alert {
    display:inline-flex; align-items:center; gap:9px;
    padding:8px 16px; border-radius:999px;
    font-size:12.5px; font-weight:600;
    text-decoration:none !important;
    background:#ffffff;
    border:1px solid #e2e8f0;
    box-shadow:0 1px 3px rgba(15, 23, 42, 0.04);
    transition:all .18s ease;
    color:var(--page-text, #0f172a);
}
.an-alert:hover {
    transform:translateY(-1px);
    box-shadow:0 4px 12px -2px rgba(15, 23, 42, 0.08);
}
.an-alert:hover {
    transform:translateY(-1px);
    box-shadow:0 4px 12px -2px rgba(15, 23, 42, 0.08);
}
.an-alert i { font-size:12px; }
.an-alert .an-arrow { font-size:10px; opacity:0.5; margin-left:4px; transition:transform .15s ease; }
.an-alert:hover .an-arrow { transform:translateX(2px); opacity:0.9; }

.an-alert.t-danger  { border-color:#fecdd3; background:#fff1f2; color:#9f1239; }
.an-alert.t-danger i { color:#e11d48; }
.an-alert.t-warning { border-color:#fde68a; background:#fffbeb; color:#92400e; }
.an-alert.t-warning i { color:#d97706; }
.an-alert.t-info    { border-color:#bae6fd; background:#f0f9ff; color:#0369a1; }
.an-alert.t-info i    { color:#0284c7; }
.an-alert.t-accent  { border-color:#ddd6fe; background:#f5f3ff; color:#6d28d9; }
.an-alert.t-accent i  { color:#7c3aed; }
.an-alert.t-success { border-color:#a7f3d0; background:#ecfdf5; color:#065f46; }
.an-alert.t-success i { color:#059669; }

.an-dot { width:7px; height:7px; border-radius:999px; display:inline-block; }
.an-dot.danger { background:#e11d48; box-shadow:0 0 0 0 rgba(225, 29, 72, 0.6); animation:tdPulse 1.8s infinite; }
.an-dot.warning { background:#d97706; }
@keyframes tdPulse {
    0% { box-shadow:0 0 0 0 rgba(225, 29, 72, 0.5); }
    70% { box-shadow:0 0 0 6px rgba(225, 29, 72, 0); }
    100% { box-shadow:0 0 0 0 rgba(225, 29, 72, 0); }
}

/* My Day Timeline & Action Hub */
.md-sum { display:flex; gap:10px; flex-wrap:wrap; font-size:12px; margin-bottom:12px; align-items:center; }
.md-pill-todo { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:999px; background:#eef2ff; color:#3730a3; font-weight:600; }
.md-pill-danger { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:999px; background:#fff1f2; color:#be123c; font-weight:700; border:1px solid #fecdd3; }
.md-dot-danger { width:6px; height:6px; border-radius:999px; background:#e11d48; animation:tdPulse 1.6s infinite; }
.md-pill-muted { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:999px; background:#f1f5f9; color:#64748b; font-weight:600; }

.md-filter-bar { display:flex; gap:6px; margin-bottom:12px; flex-wrap:wrap; }
.md-chip {
    border:1px solid #e2e8f0; background:#f8fafc; color:#475569;
    padding:4px 10px; border-radius:8px; font-size:11.5px; font-weight:600;
    cursor:pointer; display:inline-flex; align-items:center; gap:5px;
    transition:all .15s ease;
}
.md-chip:hover { background:#f1f5f9; color:#0f172a; }
.md-chip.active { background:#4f46e5; color:#ffffff; border-color:#4f46e5; }
.md-chip .chip-n { font-size:10px; padding:1px 5px; border-radius:999px; background:rgba(0,0,0,0.08); font-weight:700; }
.md-chip.active .chip-n { background:rgba(255,255,255,0.25); color:#ffffff; }

.md-list { display:flex; flex-direction:column; gap:8px; }
.md-item {
    display:flex; align-items:center; gap:12px;
    padding:10px 12px; border-radius:12px;
    background:#fafbfc; border:1px solid #edf2f7;
    transition:all .22s cubic-bezier(0.4, 0, 0.2, 1);
}
.md-item:hover {
    background:#ffffff;
    border-color:#cbd5e1;
    box-shadow:0 3px 10px -2px rgba(15, 23, 42, 0.06);
    transform:translateY(-1px);
}
.md-item.is-over {
    border-color:#fecdd3;
    background:#fff8f8;
}
.md-item.done {
    opacity:0;
    transform:translateX(24px);
    max-height:0;
    padding-top:0;
    padding-bottom:0;
    margin-top:0;
    margin-bottom:0;
    border:0;
    overflow:hidden;
    pointer-events:none;
}

.md-i {
    width:32px; height:32px; border-radius:10px;
    display:inline-flex; align-items:center; justify-content:center;
    flex-shrink:0; font-size:13px;
}
.md-i.ic-deal { background:#eef2ff; color:#4f46e5; }
.md-i.ic-appointment { background:#f5f3ff; color:#7c3aed; }
.md-i.ic-care { background:#fffbeb; color:#d97706; }
.md-i.ic-support { background:#ecfeff; color:#0891b2; }

.md-t { flex:1; min-width:0; color:var(--page-text, #0f172a); text-decoration:none !important; }
.md-t-row { display:flex; align-items:center; gap:8px; }
.md-t-name { font-size:13px; font-weight:600; color:var(--page-text, #0f172a); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.md-t-sub { display:block; font-size:11.5px; color:var(--page-text-muted, #64748b); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px; }

.md-badge { font-size:10.5px; font-weight:700; padding:2px 7px; border-radius:999px; white-space:nowrap; }
.md-badge.danger { background:#fff1f2; color:#be123c; border:1px solid #fecdd3; }
.md-badge.warning, .md-badge.amber { background:#fffbeb; color:#b45309; }
.md-badge.deal { background:#eef2ff; color:#4338ca; }
.md-badge.appointment { background:#f5f3ff; color:#6d28d9; }
.md-badge.cyan { background:#ecfeff; color:#0e7490; }
.md-badge.purple { background:#faf5ff; color:#7e22ce; }

.md-actions { display:inline-flex; align-items:center; gap:5px; flex-shrink:0; }
.lk-btn {
    width:30px; height:30px; border-radius:8px;
    display:inline-flex; align-items:center; justify-content:center;
    border:1px solid transparent; background:#f1f5f9; color:#475569;
    font-size:12px; cursor:pointer; text-decoration:none !important;
    transition:all .15s ease;
}
.lk-btn:hover { transform:scale(1.06); }
.lk-btn.call { background:#eff6ff; color:#1d4ed8; border-color:#dbeafe; }
.lk-btn.call:hover { background:#1d4ed8; color:#ffffff; }
.lk-btn.wa { background:#ecfdf5; color:#047857; border-color:#a7f3d0; }
.lk-btn.wa:hover { background:#25d366; color:#ffffff; border-color:#25d366; box-shadow:0 3px 10px rgba(37, 211, 102, 0.35); }
.lk-btn.cal { background:#f5f3ff; color:#6d28d9; border-color:#ddd6fe; }
.lk-btn.cal:hover { background:#6d28d9; color:#ffffff; }
.lk-btn.ok { background:#f8fafc; color:#64748b; border-color:#cbd5e1; }
.lk-btn.ok:hover { background:#059669; color:#ffffff; border-color:#059669; box-shadow:0 3px 10px rgba(5, 150, 105, 0.3); }

/* Minimal fallback toast */
.td-toast {
    position:fixed; bottom:24px; right:24px; z-index:999999;
    background:#0f172a; color:#ffffff; padding:10px 18px; border-radius:10px;
    font-size:13px; font-weight:600; display:flex; align-items:center; gap:8px;
    box-shadow:0 10px 25px -5px rgba(0,0,0,0.3);
    animation:tdToastIn .25s ease;
}
.td-toast.error { background:#be123c; }
@keyframes tdToastIn { from { transform:translateY(16px); opacity:0; } to { transform:translateY(0); opacity:1; } }
.td-panel[hidden] { display:none; }
.td-today { display:flex; flex-direction:column; }
.td-today > * { order:5; }
.td-today.td-myday-first > .td-grid { order:1; }
.td-today.td-myday-first > #td-alerts { order:2; }
.td-pin { margin-left:auto; border:0; background:transparent; color:var(--page-text-muted); font-size:12px; font-weight:600; padding:6px 10px; border-radius:8px; white-space:nowrap; cursor:pointer; align-self:center; }
.td-pin:hover { background:var(--accent-50); color:var(--accent-700); }
.td-pin.on { color:var(--accent-600); }
.td-pin i { margin-right:6px; }
.td-stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(190px, 1fr)); gap:14px; margin-bottom:20px; }
.td-stat {
    border-radius:14px; padding:14px 16px;
    background:#ffffff; border:1px solid #e2e8f0;
    transition:transform .18s ease, box-shadow .18s ease;
}
.td-stat:hover { transform:translateY(-2px); box-shadow:0 8px 20px -6px rgba(0,0,0,0.08); }
.td-stat:nth-child(1) { background:rgba(99,102,241,.07); border-color:rgba(99,102,241,.22); }
.td-stat:nth-child(1) span { color:#4338ca; }
.td-stat:nth-child(2) { background:rgba(16,185,129,.07); border-color:rgba(16,185,129,.22); }
.td-stat:nth-child(2) span { color:#047857; }
.td-stat:nth-child(3) { background:rgba(14,165,233,.07); border-color:rgba(14,165,233,.22); }
.td-stat:nth-child(3) span { color:#0369a1; }
.td-stat:nth-child(4) { background:rgba(244,63,94,.07); border-color:rgba(244,63,94,.22); }
.td-stat:nth-child(4) span { color:#be123c; }
.td-stat:nth-child(5) { background:rgba(139,92,246,.07); border-color:rgba(139,92,246,.22); }
.td-stat:nth-child(5) span { color:#6d28d9; }
.td-stat span { display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--page-text-muted); }
.td-stat b { display:block; font-size:22px; font-weight:800; margin-top:4px; color:#0f172a; }
.td-stat small { display:block; font-size:11.5px; color:#64748b; margin-top:2px; }
.td-stat small.up, td.up { color:#059669; } .td-stat small.down, td.down { color:#e11d48; }
.td-row {
    display:flex; align-items:center; gap:10px;
    padding:8px 6px; border-bottom:1px solid #f1f5f9;
    font-size:13px; border-radius:8px;
    transition:background .15s ease;
}
.td-row:last-child { border-bottom:0; }
.td-row:hover { background:#f8fafc; }
.td-row .td-name { flex:1; min-width:0; color:var(--page-text, #0f172a); text-decoration:none !important; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.td-row .td-sub { display:block; font-weight:400; font-size:11.5px; color:var(--page-text-muted, #64748b); overflow:hidden; text-overflow:ellipsis; margin-top:2px; }
.td-row small { color:var(--page-text-muted, #64748b); font-size:11.5px; white-space:nowrap; }
.td-row b { white-space:nowrap; color:var(--page-text, #0f172a); }
.td-meter { flex:0 0 28%; height:8px; border-radius:99px; background:#e2e8f0; overflow:hidden; }
.td-meter i { display:block; height:100%; border-radius:99px; transition:width .4s cubic-bezier(0.4, 0, 0.2, 1); }
.td-row-acts { display:inline-flex; align-items:center; gap:5px; margin-left:auto; flex-shrink:0; }

.td-table { width:100%; font-size:13px; border-collapse:separate; border-spacing:0; }
.td-table th {
    text-align:left; font-size:11.5px; text-transform:uppercase; letter-spacing:.04em;
    color:var(--page-text-muted, #64748b); padding:10px 14px;
    background:#f8fafc; border-bottom:1px solid #e2e8f0;
}
.td-table th:first-child { border-top-left-radius:10px; border-bottom-left-radius:10px; }
.td-table th:last-child { border-top-right-radius:10px; border-bottom-right-radius:10px; }
.td-table td { padding:10px 14px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
.td-table tr:hover td { background:#fafbfc; }

/* Rank badges for leaderboard */
.rank-badge {
    width:24px; height:24px; border-radius:999px;
    display:inline-flex; align-items:center; justify-content:center;
    font-size:11px; font-weight:700; margin-right:8px;
}
.rank-1 { background:#fef3c7; color:#b45309; border:1px solid #fde68a; }
.rank-2 { background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; }
.rank-3 { background:#ffedd5; color:#c2410c; border:1px solid #fed7aa; }
.rank-n { background:#f8fafc; color:#94a3b8; }

/* Patient Hearing Journey Tracker Component */
.td-journey-strip {
    display:grid; grid-template-columns:repeat(auto-fit, minmax(130px, 1fr));
    gap:12px; margin-bottom:16px; position:relative;
}
.td-journey-step {
    background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px;
    padding:14px 10px; text-align:center; position:relative;
    transition:all .18s ease;
}
.td-journey-step:hover {
    background:#ffffff; border-color:#cbd5e1;
    box-shadow:0 4px 12px -2px rgba(15, 23, 42, 0.06);
    transform:translateY(-2px);
}
.td-journey-step .js-num {
    position:absolute; top:8px; left:10px; font-size:10px; font-weight:700;
    color:#94a3b8; text-transform:uppercase; letter-spacing:.05em;
}
.td-journey-step .js-halo {
    width:36px; height:36px; border-radius:10px; margin:4px auto 8px;
    display:inline-flex; align-items:center; justify-content:center;
    font-size:15px;
}
.td-journey-step .js-count { font-size:22px; font-weight:800; line-height:1.2; color:#0f172a; }
.td-journey-step .js-name { font-size:11.5px; font-weight:600; color:#64748b; margin-top:2px; }
.td-journey-step .js-pct {
    display:inline-block; font-size:10px; font-weight:700; padding:1px 6px;
    border-radius:999px; background:#ecfdf5; color:#047857; margin-top:6px;
}

.td-charts .td-card .td-more { margin-left:auto; }
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
     data-tab="{{ url('admin/dashboard/today/tab') }}" data-start="{{ $startTab }}" data-pin="{{ route('dashboard.today.pin') }}" data-token="{{ csrf_token() }}"
     data-branch="{{ $branch }}" data-period="{{ $period }}"
     data-deals="{{ url('admin/deals') }}"
     data-customers="{{ url('admin/customers') }}">

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
                <select name="period" id="td-period" aria-label="Period" onchange="this.form.submit()">
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
            @if ($user->hasAccess('reports_hub.index'))
                <a href="{{ route('reports.hub') }}" class="btn btn-outline-secondary btn-sm" id="td-reports"><i class="fas fa-th-large mr-1"></i>Reports</a>
            @endif
            <a href="{{ route('dashboard.legacy') }}" class="btn btn-outline-secondary btn-sm" id="td-switch-legacy" title="Open the old dashboard"><i class="fas fa-history mr-1"></i>Old dashboard</a>
        </div>
    </div>

    <nav class="td-tabs" aria-label="Dashboard sections" role="tablist">
        <a href="#today" class="td-tab active" role="tab" data-tab="today" aria-selected="true" id="td-tab-today">Today</a>
        @foreach ($tabs as $key => $tabLabel)
            <a href="#{{ $key }}" class="td-tab" role="tab" data-tab="{{ $key }}" aria-selected="false" id="td-tab-{{ $key }}">{{ $tabLabel }}</a>
        @endforeach
        <button type="button" class="td-pin" id="td-pin" title="Open the dashboard on the tab you are viewing next time"><i class="fas fa-thumbtack"></i><span>Make this my start tab</span></button>
    </nav>

    <div class="td-panel td-today {{ $myDayFirst ? 'td-myday-first' : '' }}" id="td-panel-today">
    <section class="td-tiles" id="td-tiles" aria-live="polite">
        @for ($i = 0; $i < 6; $i++)<div class="td-skel"></div>@endfor
    </section>

    @if ($can('smart_alerts'))
        <div id="td-alerts" data-block-name="alerts" style="margin-bottom:16px;"></div>
    @endif

    <div class="td-grid">
        <div class="td-card" id="td-my-day-card">
            <h3>
                <span class="t-halo" style="width:34px; height:34px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; background:#6366f1; color:#ffffff; font-size:15px; box-shadow:0 4px 12px -3px #6366f1;"><i class="fas fa-calendar-check"></i></span>
                My day
                <a href="{{ route('dashboard.legacy') }}#my-day" class="td-more" id="td-my-day-full">Full list & actions &rarr;</a>
            </h3>
            <div class="td-note">Your own calls, visits and after-sales care, overdue first</div>
            <div id="td-my-day" data-block-name="my-day"><div class="td-empty">Loading…</div></div>
        </div>
        <div class="td-card" id="td-chart-revenue-card">
            <h3>
                <span class="t-halo" style="width:34px; height:34px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; background:#8b5cf6; color:#ffffff; font-size:15px; box-shadow:0 4px 12px -3px #8b5cf6;"><i class="fas fa-chart-line"></i></span>
                Sales this month vs last
            </h3>
            <div class="td-note" id="td-rev-note">Running total by day of month</div>
            @if ($can('revenue_chart'))
                <div class="td-chart-box"><canvas id="td-chart-revenue_month" data-chart-name="revenue_month" aria-label="Sales this month against last month"></canvas></div>
            @else
                <div class="td-empty">You do not have access to this chart.</div>
            @endif
        </div>
    </div>

    <div class="td-charts">
        <div class="td-card" id="td-chart-category-card">
            <h3>
                <span class="t-halo" style="width:34px; height:34px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; background:#10b981; color:#ffffff; font-size:15px; box-shadow:0 4px 12px -3px #10b981;"><i class="fas fa-chart-pie"></i></span>
                Sales by category
            </h3>
            <div class="td-note" id="td-cat-note">{{ $periods[$period] }}</div>
            @if ($can('top_products'))
                <div class="td-chart-box"><canvas id="td-chart-sales_by_category" data-chart-name="sales_by_category" aria-label="Sales by product category"></canvas></div>
            @else
                <div class="td-empty">You do not have access to this chart.</div>
            @endif
        </div>
        <div class="td-card td-more-card">
            <h3>
                <span class="t-halo" style="width:34px; height:34px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; background:#0ea5e9; color:#ffffff; font-size:15px; box-shadow:0 4px 12px -3px #0ea5e9;"><i class="fas fa-compass"></i></span>
                More on the old dashboard
            </h3>
            <div class="td-note">These move into the tabs above in the next phases</div>
            <div class="md-list">
                <a class="md-item" href="{{ route('dashboard.legacy') }}#analytics" id="td-more-analytics"><span class="md-i"><i class="fas fa-chart-bar"></i></span><span class="md-t"><b>Analytics widgets</b><span>Customers, products, doctors, stock, aging</span></span></a>
                <a class="md-item" href="{{ route('dashboard.legacy') }}#my-day" id="td-more-lookout"><span class="md-i"><i class="fas fa-binoculars"></i></span><span class="md-t"><b>Daily lookout</b><span>Tasks, tickets, follow-ups, warranty and battery</span></span></a>
            </div>
        </div>
    </div>
    </div>

    @foreach ($tabs as $key => $tabLabel)
        <div class="td-panel" id="td-panel-{{ $key }}" role="tabpanel" hidden><div class="td-empty">Loading…</div></div>
    @endforeach
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script src="{{ asset('assets/js/dashboard-today.js') }}"></script>
@endpush
