{{-- Shared styles for statement-style business reports (screen + print) --}}
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/kpi-cards.css') }}">
<link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
<style>
/* ── Filter bar ─────────────────────────────── */
.rpt-filter { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px; padding: 16px 18px; margin-bottom: 20px; }
.rpt-filter form { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.rpt-filter .f-group { display: flex; flex-direction: column; gap: 5px; min-width: 150px; flex: 1 1 150px; }
.rpt-filter .f-group.wide { flex: 2 1 240px; }
.rpt-filter label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--page-text-muted); margin: 0; }
.rpt-filter select, .rpt-filter input { height: 38px; font-size: 13px; border-radius: 8px; border: 1px solid var(--card-border); background: var(--card-bg); color: var(--page-text); padding: 0 10px; width: 100%; }
.rpt-filter .f-actions { display: flex; gap: 8px; }
.rpt-filter .f-actions .btn { height: 38px; border-radius: 8px; display: inline-flex; align-items: center; }
.rpt-filter .custom-dates { display: contents; }
.rpt-filter .custom-dates.hidden .f-group { display: none; }
.rpt-filter .select2-container .select2-selection--single { height: 38px !important; border-color: var(--card-border) !important; border-radius: 8px !important; display: flex; align-items: center; }
.rpt-filter .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
.period-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }
.period-chips a { font-size: 12px; font-weight: 600; padding: 4px 11px; border-radius: 999px; border: 1px solid var(--card-border); color: var(--page-text-secondary); text-decoration: none; background: var(--card-bg); transition: all .15s; }
.period-chips a:hover { border-color: var(--accent-300, #a5b4fc); color: var(--accent-600, #4f46e5); }
.period-chips a.active { background: var(--accent-500, #6366f1); border-color: var(--accent-500, #6366f1); color: #fff; }

/* ── Statement document ─────────────────────── */
.stmt { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px; overflow: hidden; margin-bottom: 24px; }
.stmt-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; padding: 22px 26px; border-bottom: 3px solid var(--accent-500, #6366f1); background: linear-gradient(135deg, rgba(99,102,241,.06), rgba(20,184,166,.04)); }
.stmt-brand { display: flex; gap: 14px; align-items: center; }
.stmt-brand img { max-height: 54px; max-width: 160px; object-fit: contain; }
.stmt-brand .c-name { font-size: 18px; font-weight: 800; color: var(--page-text); line-height: 1.2; }
.stmt-brand .c-meta { font-size: 11.5px; color: var(--page-text-secondary); line-height: 1.5; }
.stmt-title { text-align: right; }
.stmt-title h2 { font-size: 20px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: var(--accent-600, #4f46e5); margin: 0; }
.stmt-title .s-period { font-size: 12px; color: var(--page-text-secondary); margin-top: 4px; }
.stmt-title .s-period b { color: var(--page-text); }

.stmt-info { display: grid; grid-template-columns: 1.2fr 1fr; gap: 0; border-bottom: 1px solid var(--card-border); }
.stmt-info > div { padding: 16px 26px; }
.stmt-info > div + div { border-left: 1px solid var(--card-border); }
.stmt-info .i-lbl { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--page-text-muted); margin-bottom: 6px; }
.stmt-info .i-name { font-size: 16px; font-weight: 700; color: var(--page-text); }
.stmt-info .i-line { font-size: 12.5px; color: var(--page-text-secondary); line-height: 1.6; }
.sum-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 18px; }
.sum-grid .k { font-size: 12.5px; color: var(--page-text-secondary); }
.sum-grid .v { font-size: 13px; font-weight: 700; text-align: right; color: var(--page-text); font-variant-numeric: tabular-nums; }
.sum-grid .v.big { font-size: 15px; color: var(--accent-600, #4f46e5); }

.stmt-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.stmt-table th { background: var(--gray-50, #f8fafc); color: var(--page-text-muted); font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: 10px 12px; border-bottom: 1px solid var(--card-border); text-align: left; white-space: nowrap; }
.stmt-table td { padding: 9px 12px; border-bottom: 1px solid var(--card-border); color: var(--page-text); vertical-align: top; }
.stmt-table tbody tr:nth-child(even) td { background: rgba(148,163,184,.04); }
.stmt-table tbody tr:hover td { background: rgba(99,102,241,.05); }
.stmt-table .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
.stmt-table .muted { color: var(--page-text-muted); }
.stmt-table .dr { color: #047857; font-weight: 600; }
.stmt-table .cr { color: #b91c1c; font-weight: 600; }
.stmt-table tr.row-open td, .stmt-table tr.row-close td { background: rgba(99,102,241,.07) !important; font-weight: 700; }
.stmt-table tfoot td { background: var(--gray-50, #f8fafc); font-weight: 800; border-top: 2px solid var(--card-border); }
.stmt-table a { color: var(--accent-600, #4f46e5); font-weight: 600; text-decoration: none; }
.stmt-table a:hover { text-decoration: underline; }
.bal-tag { display: inline-block; font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 4px; margin-left: 4px; vertical-align: middle; }
.bal-tag.pay { background: #fef2f2; color: #b91c1c; }
.bal-tag.adv { background: #ecfdf5; color: #047857; }
.type-pill { display: inline-block; font-size: 10.5px; font-weight: 700; padding: 2px 8px; border-radius: 999px; }
.type-pill.purchase { background: #fff7ed; color: #c2410c; }
.type-pill.payment { background: #ecfdf5; color: #047857; }
.type-pill.other { background: #f1f5f9; color: #475569; }
.stmt-foot { display: flex; justify-content: space-between; gap: 16px; padding: 14px 26px; font-size: 11px; color: var(--page-text-muted); }
.stmt-empty { padding: 50px 20px; text-align: center; color: var(--page-text-muted); }
.stmt-empty i { font-size: 34px; display: block; margin-bottom: 10px; opacity: .5; }
.trend-up { color: #047857; font-weight: 700; font-size: 11px; }
.trend-down { color: #b91c1c; font-weight: 700; font-size: 11px; }

[data-theme="dark"] .bal-tag.pay, [data-theme="dark"] .type-pill.purchase { background: rgba(239,68,68,.15); }
[data-theme="dark"] .bal-tag.adv, [data-theme="dark"] .type-pill.payment { background: rgba(16,185,129,.15); }
[data-theme="dark"] .type-pill.other { background: rgba(148,163,184,.15); color: #cbd5e1; }

/* ── Format Switcher ────────────────────────── */
.format-switcher { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; background: rgba(99,102,241,.06); border: 1px solid rgba(99,102,241,.18); padding: 8px 14px; border-radius: 12px; margin-bottom: 18px; }
.format-switcher .lbl { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: var(--page-text-muted); }
.btn-format { font-size: 12px; font-weight: 700; padding: 6px 14px; border-radius: 8px; border: 1px solid var(--card-border); background: var(--card-bg); color: var(--page-text-secondary); text-decoration: none; display: inline-flex; align-items: center; transition: all .15s ease; }
.btn-format:hover { border-color: var(--accent-400, #818cf8); color: var(--accent-600, #4f46e5); text-decoration: none; }
.btn-format.active { background: var(--accent-600, #4f46e5); color: #fff; border-color: var(--accent-600, #4f46e5); box-shadow: 0 2px 6px rgba(79,70,229,.25); }

/* ── Clean Typographic Itemized Layout ─────── */
.itemized-product-block { margin-top: 6px; padding-left: 8px; border-left: 2px solid #e2e8f0; }
.item-product-row { display: flex; align-items: baseline; flex-wrap: wrap; gap: 6px; font-size: 12px; margin-top: 4px; line-height: 1.4; }
.item-product-row .bullet { color: #6366f1; font-weight: 800; font-size: 11px; margin-right: 2px; }
.item-product-row .prod-name { font-weight: 700; color: #1e293b; }
.item-product-row .prod-calc { font-weight: 600; color: #4338ca; background: rgba(99,102,241,.08); padding: 1px 6px; border-radius: 4px; font-size: 11.5px; }
.item-serial-row { font-size: 11px; color: #475569; margin-left: 14px; margin-top: 2px; margin-bottom: 5px; line-height: 1.4; }
.item-serial-row .sn-label { font-weight: 700; color: #64748b; font-size: 10.5px; text-transform: uppercase; margin-right: 4px; }
.serial-pill { display: inline-block; font-size: 11px; font-family: 'SFMono-Regular', Consolas, Menlo, monospace; font-weight: 600; background: #f8fafc; color: #0f172a; border: 1px solid #cbd5e1; padding: 1px 6px; border-radius: 4px; margin: 1px 3px 1px 0; }
.serial-pill.returned { background: #fee2e2; border-color: #fca5a5; color: #991b1b; }

/* ── Aging Brackets ─────────────────────────── */
.aging-badge { display: inline-block; font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 6px; text-align: center; }
.aging-badge.bracket-0-30 { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
.aging-badge.bracket-31-60 { background: #fef9c3; color: #a16207; border: 1px solid #fde047; }
.aging-badge.bracket-61-90 { background: #ffedd5; color: #c2410c; border: 1px solid #fdba74; }
.aging-badge.bracket-90-plus { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }

.status-badge-paid { background: #dcfce7; color: #166534; font-weight: 700; font-size: 11px; padding: 2px 8px; border-radius: 4px; }
.status-badge-partial { background: #fef3c7; color: #92400e; font-weight: 700; font-size: 11px; padding: 2px 8px; border-radius: 4px; }
.status-badge-unpaid { background: #fee2e2; color: #991b1b; font-weight: 700; font-size: 11px; padding: 2px 8px; border-radius: 4px; }

[data-theme="dark"] .itemized-product-block { border-left-color: rgba(255,255,255,.15); }
[data-theme="dark"] .item-product-row .prod-name { color: #f1f5f9; }
[data-theme="dark"] .item-product-row .prod-calc { color: #a5b4fc; background: rgba(99,102,241,.2); }
[data-theme="dark"] .item-serial-row { color: #94a3b8; }
[data-theme="dark"] .serial-pill { background: rgba(15,23,42,.8); color: #f1f5f9; border-color: rgba(255,255,255,.15); }

/* ── Print ──────────────────────────────────── */
@media print {
    body, .content-wrapper, .wrapper { background: #fff !important; }
    .main-header, .main-sidebar, .main-footer, .no-print, .crm-page-header, .rpt-filter, .kpi-row, .content-header, .breadcrumb { display: none !important; }
    .content-wrapper { margin-left: 0 !important; padding: 0 !important; min-height: 0 !important; }
    .container-fluid { padding: 0 !important; }
    .stmt { border: 0; border-radius: 0; margin: 0; box-shadow: none; }
    .stmt-head { background: none !important; padding: 0 0 12px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .stmt-info > div { padding: 10px 0; }
    .stmt-info > div + div { padding-left: 16px; }
    .stmt-table { font-size: 10.5px; }
    .stmt-table th, .stmt-table td { padding: 5px 6px; color: #000 !important; }
    .stmt-table th, .stmt-table tfoot td, .stmt-table tr.row-open td, .stmt-table tr.row-close td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .stmt-table thead { display: table-header-group; }
    .stmt-table tr { page-break-inside: avoid; }
    .stmt-table a { color: #000 !important; }
    .stmt-foot { padding: 10px 0; }
    .print-break { page-break-before: always; }
}
</style>
