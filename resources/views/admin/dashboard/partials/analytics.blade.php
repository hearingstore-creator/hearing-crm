{{-- ═══ BUSINESS ANALYTICS (Phase 1) ═══
     Additive section: each card is gated by its own dashboard.<key> permission.
     Data comes from App\Services\DashboardAnalyticsService (read-only). --}}
@php
    $an      = $analytics ?? [];
    $on      = fn ($k) => !empty($an[$k]) && empty($an[$k]['restricted']);
    $tk      = fn ($v) => '৳' . number_format((float) $v);
    $num     = fn ($v) => number_format((float) $v);
    $pLabel  = $an['period_label'] ?? '';
    $canAud  = \App\Support\DataScope::can('dashboard.audience_builder');
    $groups  = [
        'customers' => ['top_customers', 'customer_rfm', 'new_vs_returning', 'dormant_customers', 'membership_breakdown', 'points_liability', 'birthdays', 'customer_geography'],
        'hearing'   => ['upgrade_due', 'binaural_opportunity', 'service_due', 'test_to_sale_funnel'],
        'sales'     => ['top_products', 'gross_profit', 'aov_trend', 'payment_mix', 'receivables_aging', 'discount_leakage', 'branch_leaderboard'],
        'marketing' => ['audience_builder', 'top_doctors', 'inactive_doctors', 'referral_trend', 'lead_response_time', 'lost_reasons', 'commission_due'],
        'ops'       => ['dead_stock', 'branch_stock', 'supplier_aging', 'appointment_utilisation', 'ticket_sla', 'cash_today', 'expense_revenue'],
    ];
    $anyGroup = fn ($g) => collect($groups[$g])->contains(fn ($k) => $on($k));
    $anyAll   = collect(array_keys($groups))->contains(fn ($g) => $anyGroup($g));
    $err      = '<div class="an-err"><i class="fas fa-exclamation-triangle mr-1"></i>This widget could not be loaded. Please try again later.</div>';
    $agingMeta = [
        '0_30'    => ['0–30 days',  '#10b981', 'var(--success-50)', 'var(--success-700)'],
        '31_60'   => ['31–60 days', '#f59e0b', 'var(--warning-50)', 'var(--warning-600)'],
        '61_90'   => ['61–90 days', '#f97316', '#fff7ed', '#c2410c'],
        '90_plus' => ['90+ days',   '#e11d48', 'var(--danger-50)', 'var(--danger-700)'],
    ];
@endphp

@if ($anyAll)
<section id="analytics" class="dash-fade" style="animation-delay:.2s;margin-bottom:32px;">

    <div class="an-head">
        <div>
            <h2><i class="fas fa-chart-pie" style="color:var(--accent-500);"></i>Business Analytics <span class="an-badge">Insights</span></h2>
            <div class="an-range"><i class="far fa-calendar-alt mr-1"></i>{{ $an['range'] ?? '' }} · period applies to sales, customer, doctor & appointment widgets</div>
        </div>
        <nav class="an-periods" aria-label="Analytics period">
            @foreach (($an['periods'] ?? []) as $pk => $pl)
                <a href="{{ request()->fullUrlWithQuery(['period' => $pk]) }}#analytics" class="{{ ($an['period'] ?? '') === $pk ? 'active' : '' }}" id="period-{{ $pk }}">{{ $pl }}</a>
            @endforeach
        </nav>
    </div>

    {{-- ─────────────── 1. CUSTOMER INTELLIGENCE ─────────────── --}}
    @if ($anyGroup('customers'))
    <div class="an-group-title"><i class="fas fa-users" style="background:#ede9fe;color:#6d28d9;"></i>Customer Intelligence</div>
    <div class="an-grid">

        @if ($on('top_customers')) @php $w = $an['top_customers']; @endphp
        <div class="crm-table-card an-card" id="w-top_customers">
            <div class="crm-table-header">
                <div class="table-title-group"><h2><i class="fas fa-crown" style="color:#d97706;"></i> Top Customers <small>{{ $pLabel }}</small></h2></div>
                @if (empty($w['error']))
                <div class="an-tabs" data-tabs><button type="button" class="active" data-pane="tc-spend">By Spend</button><button type="button" data-pane="tc-visits">By Visits</button></div>
                @endif
            </div>
            @if (!empty($w['error'])) {!! $err !!} @else
            <div class="an-body flush">
                @foreach (['tc-spend' => $w['by_spend'] ?? [], 'tc-visits' => $w['by_visits'] ?? []] as $pane => $rows)
                <div class="an-pane {{ $loop->first ? 'active' : '' }}" id="{{ $pane }}">
                    @forelse ($rows as $r)
                        <div class="an-rank">
                            <span class="no">{{ $loop->iteration }}</span>
                            <a class="main" href="{{ url('admin/customers/' . $r['id']) }}" title="Open customer">
                                <div class="t">{{ $r['name'] }}@if ($r['tier'])<span class="an-tier">{{ $r['tier'] }}</span>@endif</div>
                                <div class="s">{{ $r['orders'] }} purchase{{ $r['orders'] > 1 ? 's' : '' }} · last {{ $r['last'] }}</div>
                            </a>
                            <div class="val">{{ $tk($r['spend']) }}<small>{{ $r['orders'] }} visit{{ $r['orders'] > 1 ? 's' : '' }}</small></div>
                            @if ($r['phone'])<a href="tel:{{ $r['phone'] }}" class="lk-btn call" title="Call {{ $r['phone'] }}"><i class="fas fa-phone"></i></a>@endif
                        </div>
                    @empty
                        <div class="lk-empty"><i class="fas fa-users" style="color:var(--page-text-muted);"></i>No purchases in this period</div>
                    @endforelse
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @endif

        @if ($on('customer_rfm')) @php $w = $an['customer_rfm']; @endphp
        <div class="crm-table-card an-card" id="w-customer_rfm">
            <div class="crm-table-header">
                <div class="table-title-group"><h2><i class="fas fa-layer-group" style="color:#7c3aed;"></i> Customer Value Segments <small>all time</small></h2></div>
                @if (empty($w['error']))<span class="an-link" style="color:var(--page-text-muted);">{{ $num($w['customers'] ?? 0) }} buyers</span>@endif
            </div>
            @if (!empty($w['error'])) {!! $err !!} @else
            <div class="an-body">
                <div class="an-seg-grid">
                    @foreach ($w['segments'] ?? [] as $s)
                        <div class="an-seg" style="--c:{{ $s['color'] }};" title="{{ $s['hint'] }}">
                            @if ($canAud && $s['count'] > 0)<a href="{{ route('dashboard.audience.export', $s['key']) }}" class="dl" title="Export {{ $s['label'] }} list (CSV)"><i class="fas fa-download"></i></a>@endif
                            <div class="h"><i class="{{ $s['icon'] }}"></i>{{ $s['label'] }}</div>
                            <div class="n">{{ $num($s['count']) }}<small>{{ $s['share'] }}%</small></div>
                            <div class="r">{{ $tk($s['revenue']) }} lifetime</div>
                            <div class="meter"><span style="width:{{ min($s['share'], 100) }}%;"></span></div>
                        </div>
                    @endforeach
                </div>
                <div class="an-note"><i class="fas fa-info-circle"></i>Segments use Recency (last purchase), Frequency (purchases) and Monetary value. Hover a tile for the rule{{ $canAud ? '; use ⤓ to export the list' : '' }}.</div>
            </div>
            @endif
        </div>
        @endif

        @if ($on('new_vs_returning')) @php $w = $an['new_vs_returning']; @endphp
        <div class="crm-table-card an-card" id="w-new_vs_returning">
            <div class="crm-table-header"><div class="table-title-group"><h2><i class="fas fa-user-friends" style="color:#0d9488;"></i> New vs Returning Buyers <small>{{ $pLabel }}</small></h2></div></div>
            @if (!empty($w['error'])) {!! $err !!} @else
            @php $tot = max(($w['new_count'] ?? 0) + ($w['ret_count'] ?? 0), 1); $np = round(($w['new_count'] ?? 0) / $tot * 100); @endphp
            <div class="an-body">
                <div class="an-stats">
                    <div class="an-stat c-teal"><div class="v">{{ $num($w['new_count']) }}</div><div class="l">New buyers</div><div class="s">{{ $tk($w['new_revenue']) }}</div></div>
                    <div class="an-stat c-blue"><div class="v">{{ $num($w['ret_count']) }}</div><div class="l">Returning</div><div class="s">{{ $tk($w['ret_revenue']) }}</div></div>
                    <div class="an-stat c-violet"><div class="v">{{ $w['repeat_rate'] }}%</div><div class="l">Repeat rate</div><div class="s">of {{ $num($w['total']) }} buyers</div></div>
                </div>
                @if ($w['total'] > 0)
                <div class="an-split">
                    <span style="width:{{ $np }}%;background:#14b8a6;">{{ $np >= 12 ? $np . '% new' : '' }}</span>
                    <span style="width:{{ 100 - $np }}%;background:#3b82f6;">{{ 100 - $np >= 12 ? (100 - $np) . '% returning' : '' }}</span>
                </div>
                @else
                    <div class="lk-empty" style="padding:12px;">No buyers in this period</div>
                @endif
                <div class="an-note"><i class="fas fa-lightbulb"></i>A healthy hearing-care business grows returning revenue through batteries, service and upgrades.</div>
            </div>
            @endif
        </div>
        @endif

        @if ($on('dormant_customers')) @php $w = $an['dormant_customers']; @endphp
        <div class="crm-table-card an-card" id="w-dormant_customers">
            <div class="crm-table-header">
                <div class="table-title-group"><h2><i class="fas fa-bed" style="color:#64748b;"></i> Dormant Customers</h2></div>
                @if ($canAud && empty($w['error']) && ($w['count_6m'] ?? 0) > 0)<a href="{{ route('dashboard.audience.export', 'dormant') }}" class="an-link"><i class="fas fa-download mr-1"></i>Export list</a>@endif
            </div>
            @if (!empty($w['error'])) {!! $err !!} @else
            <div class="an-body flush">
                <div class="an-stats" style="padding:10px 8px 0;">
                    <div class="an-stat c-amber"><div class="v">{{ $num($w['count_6m']) }}</div><div class="l">No buy 6+ months</div></div>
                    <div class="an-stat c-red"><div class="v">{{ $num($w['count_12m']) }}</div><div class="l">No buy 12+ months</div></div>
                    <div class="an-stat c-slate"><div class="v">{{ $tk($w['value']) }}</div><div class="l">Their lifetime value</div></div>
                </div>
                @forelse ($w['list'] ?? [] as $r)
                    <div class="an-rank">
                        <a class="main" href="{{ url('admin/customers/' . $r['id']) }}"><div class="t">{{ $r['name'] }}</div><div class="s">Last purchase {{ $r['last'] }} · {{ $r['orders'] }} order(s)</div></a>
                        <div class="val">{{ $tk($r['spend']) }}<small>{{ $r['days'] }} days idle</small></div>
                        @if ($r['phone'])<a href="tel:{{ $r['phone'] }}" class="lk-btn call" title="Call {{ $r['phone'] }}"><i class="fas fa-phone"></i></a>@endif
                    </div>
                @empty
                    <div class="lk-empty"><i class="fas fa-check-circle" style="color:var(--success-500);"></i>No dormant customers — everyone bought in the last 6 months</div>
                @endforelse
            </div>
            @endif
        </div>
        @endif

        @if ($on('membership_breakdown')) @php $w = $an['membership_breakdown']; @endphp
        <div class="crm-table-card an-card" id="w-membership_breakdown">
            <div class="crm-table-header"><div class="table-title-group"><h2><i class="fas fa-id-card" style="color:#ca8a04;"></i> Membership Breakdown</h2></div></div>
            @if (!empty($w['error'])) {!! $err !!} @else
            @php $maxC = max(collect($w['tiers'] ?? [])->max('customers') ?? 0, 1); @endphp
            <div class="an-body">
                <div class="an-stats">
                    <div class="an-stat c-amber"><div class="v">{{ $num($w['members']) }}</div><div class="l">Customers</div></div>
                    <div class="an-stat c-green"><div class="v">{{ $num($w['upgrades_month']) }}</div><div class="l">Tier upgrades</div><div class="s">this month</div></div>
                </div>
                @foreach ($w['tiers'] ?? [] as $t)
                    <div class="an-bar-row">
                        <span class="lbl">{{ $t['name'] }}</span>
                        <div class="trk"><div class="fil" style="width:{{ $t['customers'] ? max(round($t['customers'] / $maxC * 100), 4) : 0 }}%;background:{{ $t['color'] }};"></div></div>
                        <span class="num">{{ $num($t['customers']) }}<small>{{ $t['share'] }}% rev</small></span>
                    </div>
                @endforeach
            </div>
            @endif
        </div>
        @endif

        @if ($on('points_liability')) @php $w = $an['points_liability']; @endphp
        <div class="crm-table-card an-card" id="w-points_liability">
            <div class="crm-table-header"><div class="table-title-group"><h2><i class="fas fa-coins" style="color:#d97706;"></i> Loyalty Points</h2></div></div>
            @if (!empty($w['error'])) {!! $err !!} @else
            <div class="an-body flush">
                <div class="an-stats" style="padding:10px 8px 0;">
                    <div class="an-stat c-violet"><div class="v">{{ $num($w['outstanding']) }}</div><div class="l">Outstanding pts</div></div>
                    <div class="an-stat c-green"><div class="v">{{ $num($w['earned']) }}</div><div class="l">Earned</div></div>
                    <div class="an-stat c-blue"><div class="v">{{ $num($w['redeemed']) }}</div><div class="l">Redeemed</div><div class="s">{{ $w['redeem_rate'] }}% rate</div></div>
                    <div class="an-stat c-red"><div class="v">{{ $num($w['expiring_30']) }}</div><div class="l">Expiring 30d</div></div>
                </div>
                @foreach ($w['top_holders'] ?? [] as $h)
                    <div class="an-rank">
                        <span class="no">{{ $loop->iteration }}</span>
                        <a class="main" href="{{ url('admin/customers/' . $h['id']) }}"><div class="t">{{ $h['name'] }}</div><div class="s">{{ $h['phone'] ?: 'No phone' }}</div></a>
                        <div class="val">{{ $num($h['points']) }}<small>points</small></div>
                    </div>
                @endforeach
                <div class="an-note" style="padding:0 8px;"><i class="fas fa-lightbulb"></i>A high outstanding balance with a low redeem rate is a ready-made "redeem your points" campaign.</div>
            </div>
            @endif
        </div>
        @endif

        @if ($on('birthdays')) @php $w = $an['birthdays']; @endphp
        <div class="crm-table-card an-card" id="w-birthdays">
            <div class="crm-table-header">
                <div class="table-title-group"><h2><i class="fas fa-birthday-cake" style="color:#db2777;"></i> Birthdays This Week</h2></div>
                @if (empty($w['error']) && count($w['phones'] ?? []))
                    <button type="button" class="an-link js-copy-phones" style="border:0;background:none;cursor:pointer;" data-phones="{{ implode(',', $w['phones']) }}" id="bd-copy-phones"><i class="far fa-copy mr-1"></i>Copy {{ count($w['phones']) }} no.</button>
                @endif
            </div>
            @if (!empty($w['error'])) {!! $err !!} @else
            <div class="an-body flush">
                <div class="an-stats" style="padding:10px 8px 0;">
                    <div class="an-stat c-red"><div class="v">{{ $num($w['today']) }}</div><div class="l">Today</div></div>
                    <div class="an-stat c-violet"><div class="v">{{ $num($w['week_count']) }}</div><div class="l">Next 7 days</div></div>
                    <div class="an-stat c-slate"><div class="v">{{ $num($w['month']) }}</div><div class="l">Next 30 days</div></div>
                </div>
                @forelse ($w['list'] ?? [] as $b)
                    <div class="an-rank">
                        <a class="main" href="{{ $b['url'] }}"><div class="t">{{ $b['name'] }}@if ($b['customer_id'])<span class="an-tier">Customer</span>@endif</div><div class="s">{{ $b['date_label'] }} · turns {{ $b['turning'] }}</div></a>
                        <span class="an-chip {{ $b['in_days'] === 0 ? 'down' : 'flat' }}">{{ $b['in_days'] === 0 ? 'Today' : ($b['in_days'] === 1 ? 'Tomorrow' : 'in ' . $b['in_days'] . 'd') }}</span>
                        <div class="lk-actions">
                            @if ($b['phone'])
                                <a href="tel:{{ $b['phone'] }}" class="lk-btn call" title="Call {{ $b['phone'] }}"><i class="fas fa-phone"></i></a>
                                <a href="sms:{{ $b['phone'] }}?body={{ rawurlencode('Happy Birthday ' . $b['name'] . '! Wishing you a wonderful year ahead.') }}" class="lk-btn" title="Send birthday SMS"><i class="fas fa-sms"></i></a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="lk-empty"><i class="fas fa-birthday-cake" style="color:var(--page-text-muted);"></i>No birthdays in the next 7 days</div>
                @endforelse
                <div class="an-note" style="padding:0 8px;"><i class="fas fa-info-circle"></i>{{ $w['coverage'] }}% of leads have a Date of Birth. Add it on the lead form to grow this list; use <a href="{{ url('admin/sms') }}">SMS module</a> for bulk wishes.</div>
            </div>
            @endif
        </div>
        @endif

        @if ($on('customer_geography')) @php $w = $an['customer_geography']; @endphp
        <div class="crm-table-card an-card" id="w-customer_geography">
            <div class="crm-table-header">
                <div class="table-title-group"><h2><i class="fas fa-map-marked-alt" style="color:#0d9488;"></i> Customer Geography <small>revenue {{ $pLabel }}</small></h2></div>
                @if (empty($w['error']))<span class="an-link" style="color:var(--page-text-muted);">{{ $w['districts'] }} district{{ $w['districts'] == 1 ? '' : 's' }}</span>@endif
            </div>
            @if (!empty($w['error'])) {!! $err !!} @else
            <div class="an-body">
                @if (count($w['rows'] ?? []))
                <table class="an-table">
                    <thead><tr><th>District</th><th class="c">Customers</th><th class="c">Leads</th><th class="r">Revenue</th></tr></thead>
                    <tbody>
                        @foreach ($w['rows'] as $g)
                            <tr>
                                <td style="{{ $g['unset'] ? 'color:var(--page-text-muted);font-style:italic;' : 'font-weight:600;' }}">{{ $g['name'] }}
                                    <div class="an-split" style="height:5px;margin:4px 0 0;"><span style="width:{{ $g['customers'] ? max(round($g['customers'] / $w['max_c'] * 100), 4) : 0 }}%;background:#14b8a6;"></span></div>
                                </td>
                                <td class="c">{{ $num($g['customers']) }}</td>
                                <td class="c">{{ $num($g['leads']) }}</td>
                                <td class="r"><b>{{ $tk($g['revenue']) }}</b></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                    <div class="lk-empty" style="padding:12px;"><i class="fas fa-map" style="color:var(--page-text-muted);"></i>No location data yet</div>
                @endif
                <div class="an-note"><i class="fas fa-lightbulb"></i>@if ($w['top'])<b>{{ $w['top'] }}</b> is your strongest area — target nearby districts for camps & ads.@endif @if ($w['unset'] > 0){{ $w['unset'] }} customer(s) have no district set.@endif</div>
            </div>
            @endif
        </div>
        @endif
    </div>
    @endif

    {{-- ─────────────── 2. HEARING CARE OPPORTUNITIES ─────────────── --}}
    @if ($anyGroup('hearing'))
    <div class="an-group-title"><i class="fas fa-assistive-listening-systems" style="background:#ccfbf1;color:#0f766e;"></i>Hearing Care Opportunities</div>
    <div class="an-grid">

        @if ($on('upgrade_due')) @php $w = $an['upgrade_due']; @endphp
        <div class="crm-table-card an-card" id="w-upgrade_due">
            <div class="crm-table-header">
                <div class="table-title-group"><h2><i class="fas fa-sync-alt" style="color:#7c3aed;"></i> Device Upgrade Due</h2></div>
                @if ($canAud && empty($w['error']) && ($w['due_count'] ?? 0) > 0)<a href="{{ route('dashboard.audience.export', 'upgrade_due') }}" class="an-link"><i class="fas fa-download mr-1"></i>Export list</a>@endif
            </div>
            @if (!empty($w['error'])) {!! $err !!} @else
            <div class="an-body flush">
                <div class="an-stats" style="padding:10px 8px 0;">
                    <div class="an-stat c-violet"><div class="v">{{ $num($w['due_count']) }}</div><div class="l">Due now</div><div class="s">device 3+ years old</div></div>
                    <div class="an-stat c-amber"><div class="v">{{ $num($w['upcoming_count']) }}</div><div class="l">Coming up</div><div class="s">2½–3 years</div></div>
                    <div class="an-stat c-slate"><div class="v">{{ $num($w['owners']) }}</div><div class="l">Device owners</div></div>
                </div>
                @forelse ($w['list'] ?? [] as $d)
                    <div class="an-rank">
                        <a class="main" href="{{ url('admin/customers/' . $d['customer_id']) }}"><div class="t">{{ $d['name'] }}</div><div class="s">{{ $d['model'] }} · bought {{ $d['date_label'] }}</div></a>
                        <span class="an-chip {{ $d['stage'] === 'due' ? 'down' : 'flat' }}">{{ $d['age_label'] }}</span>
                        <div class="lk-actions">
                            @if ($d['phone'])<a href="tel:{{ $d['phone'] }}" class="lk-btn call" title="Call {{ $d['phone'] }}"><i class="fas fa-phone"></i></a>@endif
                            <a href="{{ url('admin/sale/' . $d['sale_id']) }}" class="lk-btn" title="Open invoice"><i class="fas fa-file-invoice"></i></a>
                        </div>
                    </div>
                @empty
                    <div class="lk-empty"><i class="fas fa-check-circle" style="color:var(--success-500);"></i>No devices older than 2½ years yet</div>
                @endforelse
                <div class="an-note" style="padding:0 8px;"><i class="fas fa-info-circle"></i>Based on each customer's latest hearing-aid purchase (BTE / ITE / CIC / RIC / Hearing Aid categories).</div>
            </div>
            @endif
        </div>
        @endif

        @if ($on('binaural_opportunity')) @php $w = $an['binaural_opportunity']; @endphp
        <div class="crm-table-card an-card" id="w-binaural_opportunity">
            <div class="crm-table-header">
                <div class="table-title-group"><h2><i class="fas fa-assistive-listening-systems" style="color:#0d9488;"></i> Second-Ear Opportunity</h2></div>
                @if ($canAud && empty($w['error']) && ($w['count'] ?? 0) > 0)<a href="{{ route('dashboard.audience.export', 'binaural') }}" class="an-link"><i class="fas fa-download mr-1"></i>Export list</a>@endif
            </div>
            @if (!empty($w['error'])) {!! $err !!} @else
            <div class="an-body flush">
                <div class="an-stats" style="padding:10px 8px 0;">
                    <div class="an-stat c-teal"><div class="v">{{ $num($w['count']) }}</div><div class="l">Single-device owners</div></div>
                    <div class="an-stat c-blue"><div class="v">{{ $w['share'] }}%</div><div class="l">Of device owners</div></div>
                </div>
                @forelse ($w['list'] ?? [] as $d)
                    <div class="an-rank">
                        <a class="main" href="{{ url('admin/customers/' . $d['customer_id']) }}"><div class="t">{{ $d['name'] }}</div><div class="s">{{ $d['model'] }} · bought {{ $d['date_label'] }}</div></a>
                        <div class="lk-actions">
                            @if ($d['phone'])<a href="tel:{{ $d['phone'] }}" class="lk-btn call" title="Call {{ $d['phone'] }}"><i class="fas fa-phone"></i></a>@endif
                            <a href="{{ url('admin/sale/' . $d['sale_id']) }}" class="lk-btn" title="Open invoice"><i class="fas fa-file-invoice"></i></a>
                        </div>
                    </div>
                @empty
                    <div class="lk-empty"><i class="fas fa-check-circle" style="color:var(--success-500);"></i>Every device customer already has a pair</div>
                @endforelse
            </div>
            @endif
        </div>
        @endif

        @if ($on('service_due')) @php $w = $an['service_due']; @endphp
        <div class="crm-table-card an-card" id="w-service_due">
            <div class="crm-table-header">
                <div class="table-title-group"><h2><i class="fas fa-tools" style="color:#ea580c;"></i> Service / Cleaning Due</h2></div>
                @if (empty($w['error']) && count($w['phones'] ?? []))
                    <button type="button" class="an-link js-copy-phones" style="border:0;background:none;cursor:pointer;" data-phones="{{ implode(',', $w['phones']) }}" id="svc-copy-phones"><i class="far fa-copy mr-1"></i>Copy {{ count($w['phones']) }} no.</button>
                @endif
            </div>
            @if (!empty($w['error'])) {!! $err !!} @else
            <div class="an-body flush">
                <div class="an-stats" style="padding:10px 8px 0;">
                    <div class="an-stat c-red"><div class="v">{{ $num($w['overdue']) }}</div><div class="l">Overdue</div><div class="s">missed last cycle</div></div>
                    <div class="an-stat c-amber"><div class="v">{{ $num($w['due']) }}</div><div class="l">Due in 30 days</div></div>
                </div>
                @forelse ($w['list'] ?? [] as $s)
                    <div class="an-rank">
                        <a class="main" href="{{ url('admin/customers/' . $s['customer_id']) }}"><div class="t">{{ $s['name'] }}</div><div class="s">{{ $s['model'] }} · every {{ $s['interval'] }} mo{{ $s['last_service'] ? ' · last service ' . $s['last_service'] : '' }}</div></a>
                        <span class="an-chip {{ $s['stage'] === 'overdue' ? 'down' : 'flat' }}" title="{{ $s['due_label'] }}">{{ $s['days'] < 0 ? abs($s['days']) . 'd late' : ($s['days'] === 0 ? 'Today' : 'in ' . $s['days'] . 'd') }}</span>
                        <div class="lk-actions">
                            @if ($s['phone'])<a href="tel:{{ $s['phone'] }}" class="lk-btn call" title="Call {{ $s['phone'] }}"><i class="fas fa-phone"></i></a>@endif
                            <a href="{{ url('admin/appointments/calendar') }}" class="lk-btn" title="Book service appointment"><i class="far fa-calendar-plus"></i></a>
                        </div>
                    </div>
                @empty
                    <div class="lk-empty"><i class="fas fa-check-circle" style="color:var(--success-500);"></i>No services due in the next 30 days</div>
                @endforelse
                <div class="an-note" style="padding:0 8px;"><i class="fas fa-info-circle"></i>Cycle = product's Service Interval (default 6 months for hearing aids). A completed appointment or service invoice resets it.</div>
            </div>
            @endif
        </div>
        @endif

        @if ($on('test_to_sale_funnel')) @php $w = $an['test_to_sale_funnel']; @endphp
        <div class="crm-table-card an-card" id="w-test_to_sale_funnel">
            <div class="crm-table-header">
                <div class="table-title-group"><h2><i class="fas fa-filter" style="color:#0284c7;"></i> Hearing Test → Sale <small>{{ $pLabel }}</small></h2></div>
                @if (empty($w['error']))<span class="an-link" style="color:var(--page-text-muted);">{{ $w['conversion'] }}% convert</span>@endif
            </div>
            @if (!empty($w['error'])) {!! $err !!} @else
            <div class="an-body">
                @foreach ($w['stages'] as $st)
                    <div class="an-bar-row">
                        <span class="lbl"><i class="{{ $st['icon'] }} mr-1" style="color:{{ $st['color'] }};"></i>{{ $st['label'] }}</span>
                        <div class="trk"><div class="fil" style="width:{{ $st['count'] ? max($st['pct'], 4) : 0 }}%;background:{{ $st['color'] }};"></div></div>
                        <span class="num">{{ $num($st['count']) }}<small>{{ $st['pct'] }}%</small></span>
                    </div>
                @endforeach
                <div class="an-stats" style="margin-top:10px;">
                    <div class="an-stat c-green"><div class="v">{{ $w['conversion'] }}%</div><div class="l">Test → purchase</div></div>
                    <div class="an-stat c-blue"><div class="v">{{ $w['avg_days'] !== null ? $w['avg_days'] . 'd' : '—' }}</div><div class="l">Avg days to buy</div></div>
                </div>
                @if (count($w['by_service'] ?? []))
                    <div class="an-legend">@foreach ($w['by_service'] as $bs)<span><i style="background:#6366f1;"></i>{{ $bs['name'] }}<b>{{ $bs['count'] }}</b></span>@endforeach</div>
                @endif
                <div class="an-note"><i class="fas fa-info-circle"></i>Stages come from appointment services (diagnostic / consultation → fitting) and device invoices after the first test.</div>
            </div>
            @endif
        </div>
        @endif
    </div>
    @endif

    {{-- ─────────────── 3. SALES & REVENUE ─────────────── --}}
    @if ($anyGroup('sales'))
    <div class="an-group-title"><i class="fas fa-chart-line" style="background:#dcfce7;color:#15803d;"></i>Sales &amp; Revenue</div>
    <div class="an-grid">

        @if ($on('top_products')) @php $w = $an['top_products']; @endphp
        <div class="crm-table-card an-card" id="w-top_products">
            <div class="crm-table-header">
                <div class="table-title-group"><h2><i class="fas fa-trophy" style="color:#d97706;"></i> Top Sellers <small>{{ $pLabel }}</small></h2></div>
                @if (empty($w['error']))
                <div class="an-tabs" data-tabs><button type="button" class="active" data-pane="tp-products">Products</button><button type="button" data-pane="tp-brands">Brands</button></div>
                @endif
            </div>
            @if (!empty($w['error'])) {!! $err !!} @else
            <div class="an-body">
                @foreach (['tp-products' => $w['products'] ?? [], 'tp-brands' => $w['brands'] ?? []] as $pane => $rows)
                    @php $mx = max(collect($rows)->max('revenue') ?? 0, 1); $pal = ['#6366f1','#0d9488','#d97706','#e11d48','#7c3aed','#2563eb','#059669','#ea580c']; @endphp
                    <div class="an-pane {{ $loop->first ? 'active' : '' }}" id="{{ $pane }}">
                        @forelse ($rows as $i => $r)
                            <div class="an-bar-row" title="{{ $r['name'] }}{{ $r['category'] ? ' · ' . $r['category'] : '' }}">
                                <span class="lbl">{{ $r['name'] }}</span>
                                <div class="trk"><div class="fil" style="width:{{ max(round($r['revenue'] / $mx * 100), 4) }}%;background:{{ $pal[$i % 8] }};"></div></div>
                                <span class="num">{{ $tk($r['revenue']) }}<small>{{ $num($r['qty']) }} pcs</small></span>
                            </div>
                        @empty
                            <div class="lk-empty"><i class="fas fa-box" style="color:var(--page-text-muted);"></i>No product sales in this period</div>
                        @endforelse
                    </div>
                @endforeach
            </div>
            @endif
        </div>
        @endif

        @if ($on('gross_profit')) @php $w = $an['gross_profit']; @endphp
        <div class="crm-table-card an-card" id="w-gross_profit">
            <div class="crm-table-header"><div class="table-title-group"><h2><i class="fas fa-percentage" style="color:#059669;"></i> Gross Profit &amp; Margin <small>{{ $pLabel }}</small></h2></div></div>
            @if (!empty($w['error'])) {!! $err !!} @else
            <div class="an-body">
                <div class="an-stats">
                    <div class="an-stat c-blue"><div class="v">{{ $tk($w['revenue']) }}</div><div class="l">Product revenue</div></div>
                    <div class="an-stat c-slate"><div class="v">{{ $tk($w['cost']) }}</div><div class="l">Cost of goods</div></div>
                    <div class="an-stat {{ $w['profit'] >= 0 ? 'c-green' : 'c-red' }}"><div class="v">{{ $tk($w['profit']) }}</div><div class="l">Gross profit</div></div>
                    <div class="an-stat c-violet"><div class="v">{{ $w['margin'] }}%</div><div class="l">Margin</div></div>
                </div>
                @if (count($w['brands'] ?? []))
                <table class="an-table">
                    <thead><tr><th>Brand</th><th class="r">Revenue</th><th class="r">Profit</th><th class="r">Margin</th></tr></thead>
                    <tbody>
                        @foreach ($w['brands'] as $b)
                            <tr><td>{{ $b['name'] }}</td><td class="r">{{ $tk($b['revenue']) }}</td><td class="r">{{ $tk($b['profit']) }}</td>
                                <td class="r"><span class="an-chip {{ $b['margin'] >= 20 ? 'up' : ($b['margin'] > 0 ? 'flat' : 'down') }}">{{ $b['margin'] }}%</span></td></tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
                <div class="an-note">
                    <i class="fas fa-concierge-bell"></i>Service revenue in period: <b>{{ $tk($w['service_revenue']) }}</b> (not included above).
                    @if ($w['missing_cost'] > 0)<br><i class="fas fa-exclamation-triangle" style="color:var(--warning-500);"></i>{{ $w['missing_cost'] }} sold line(s) have no purchase cost recorded, so profit may be overstated.@endif
                </div>
            </div>
            @endif
        </div>
        @endif

        @if ($on('aov_trend')) @php $w = $an['aov_trend']; @endphp
        <div class="crm-table-card an-card" id="w-aov_trend">
            <div class="crm-table-header"><div class="table-title-group"><h2><i class="fas fa-receipt" style="color:#2563eb;"></i> Average Order Value <small>last 12 months</small></h2></div></div>
            @if (!empty($w['error'])) {!! $err !!} @else
            <div class="an-body">
                <div class="an-stats">
                    <div class="an-stat c-blue"><div class="v">{{ $tk($w['current']) }}</div><div class="l">This month</div></div>
                    <div class="an-stat c-slate"><div class="v">{{ $tk($w['previous']) }}</div><div class="l">Last month</div></div>
                    <div class="an-stat {{ $w['change'] >= 0 ? 'c-green' : 'c-red' }}"><div class="v">{{ $w['change'] >= 0 ? '+' : '' }}{{ $w['change'] }}%</div><div class="l">Change</div></div>
                    <div class="an-stat c-violet"><div class="v">{{ $tk($w['lifetime']) }}</div><div class="l">Lifetime AOV</div></div>
                </div>
                <div style="height:190px;"><canvas id="aovChart"></canvas></div>
            </div>
            @endif
        </div>
        @endif

        @if ($on('payment_mix')) @php $w = $an['payment_mix']; @endphp
        <div class="crm-table-card an-card" id="w-payment_mix">
            <div class="crm-table-header"><div class="table-title-group"><h2><i class="fas fa-wallet" style="color:#0d9488;"></i> Payment Method Mix <small>{{ $pLabel }}</small></h2></div></div>
            @if (!empty($w['error'])) {!! $err !!} @else
            <div class="an-body">
                @if (count($w['methods'] ?? []))
                <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
                    <div style="width:150px;height:150px;"><canvas id="payMixChart"></canvas></div>
                    <table class="an-table" style="flex:1;min-width:220px;">
                        <thead><tr><th>Method</th><th class="c">Invoices</th><th class="r">Amount</th><th class="r">Share</th></tr></thead>
                        <tbody>
                            @foreach ($w['methods'] as $i => $m)
                                <tr><td><i class="pm-dot" data-i="{{ $i }}" style="display:inline-block;width:9px;height:9px;border-radius:3px;margin-right:6px;"></i>{{ $m['label'] }}</td>
                                    <td class="c">{{ $num($m['count']) }}</td><td class="r">{{ $tk($m['total']) }}</td><td class="r">{{ $m['share'] }}%</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                    <div class="lk-empty"><i class="fas fa-wallet" style="color:var(--page-text-muted);"></i>No sales in this period</div>
                @endif
            </div>
            @endif
        </div>
        @endif

        @if ($on('receivables_aging')) @php $w = $an['receivables_aging']; @endphp
        <div class="crm-table-card an-card" id="w-receivables_aging">
            <div class="crm-table-header">
                <div class="table-title-group"><h2><i class="fas fa-hand-holding-usd" style="color:#e11d48;"></i> Customer Dues Aging</h2></div>
                @if ($canAud && empty($w['error']) && ($w['count'] ?? 0) > 0)<a href="{{ route('dashboard.audience.export', 'debtors') }}" class="an-link"><i class="fas fa-download mr-1"></i>Export debtors</a>@endif
            </div>
            @if (!empty($w['error'])) {!! $err !!} @else
            @include('admin.dashboard.partials.aging-body', ['w' => $w, 'who' => 'customer', 'linkBase' => 'admin/customers/'])
            @endif
        </div>
        @endif

        @if ($on('discount_leakage')) @php $w = $an['discount_leakage']; @endphp
        <div class="crm-table-card an-card" id="w-discount_leakage">
            <div class="crm-table-header"><div class="table-title-group"><h2><i class="fas fa-tags" style="color:#d97706;"></i> Discount Leakage <small>{{ $pLabel }}</small></h2></div></div>
            @if (!empty($w['error'])) {!! $err !!} @else
            <div class="an-body">
                <div class="an-stats">
                    <div class="an-stat c-amber"><div class="v">{{ $tk($w['discount']) }}</div><div class="l">Discount given</div></div>
                    <div class="an-stat {{ $w['pct'] > 10 ? 'c-red' : 'c-slate' }}"><div class="v">{{ $w['pct'] }}%</div><div class="l">Of gross sales</div></div>
                    <div class="an-stat c-blue"><div class="v">{{ $num($w['discounted']) }}<small style="font-size:12px;color:var(--page-text-muted);">/{{ $num($w['invoices']) }}</small></div><div class="l">Invoices discounted</div></div>
                    <div class="an-stat c-red"><div class="v">{{ $num($w['heavy']) }}</div><div class="l">Over 10% off</div></div>
                </div>
                @if (count($w['staff'] ?? []))
                <table class="an-table">
                    <thead><tr><th>Staff</th><th class="c">Invoices</th><th class="r">Discount</th><th class="r">Avg %</th></tr></thead>
                    <tbody>
                        @foreach ($w['staff'] as $s)
                            <tr><td>{{ $s['name'] }}</td><td class="c">{{ $s['invoices'] }}</td><td class="r">{{ $tk($s['discount']) }}</td>
                                <td class="r"><span class="an-chip {{ $s['pct'] > 10 ? 'down' : 'flat' }}">{{ $s['pct'] }}%</span></td></tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                    <div class="lk-empty" style="padding:12px;"><i class="fas fa-check-circle" style="color:var(--success-500);"></i>No discounts given in this period</div>
                @endif
            </div>
            @endif
        </div>
        @endif

        @if ($on('branch_leaderboard')) @php $w = $an['branch_leaderboard']; @endphp
        <div class="crm-table-card an-card an-wide" id="w-branch_leaderboard">
            <div class="crm-table-header"><div class="table-title-group"><h2><i class="fas fa-store" style="color:#6366f1;"></i> Branch Leaderboard <small>all branches · this month</small></h2></div></div>
            @if (!empty($w['error'])) {!! $err !!} @else
            <div class="an-body" style="overflow-x:auto;">
                <table class="an-table">
                    <thead><tr><th style="width:36px;">#</th><th>Branch</th><th style="min-width:200px;">This month</th><th class="r">Last month</th><th class="c">Growth</th><th class="c">Invoices</th><th class="r">All time</th><th class="c">Leads</th><th class="c">Conversion</th></tr></thead>
                    <tbody>
                        @foreach ($w['rows'] ?? [] as $b)
                            <tr>
                                <td><span class="an-rank" style="padding:0;border:none;"><span class="no">{{ $loop->iteration }}</span></span></td>
                                <td style="font-weight:600;">{{ $b['name'] }}</td>
                                <td><b>{{ $tk($b['this_month']) }}</b><div class="an-inline-bar"><span style="width:{{ $b['this_month'] ? max(round($b['this_month'] / $w['max'] * 100), 3) : 0 }}%;background:#6366f1;"></span></div></td>
                                <td class="r">{{ $tk($b['last_month']) }}</td>
                                <td class="c">@if ($b['growth'] === null)<span class="an-chip flat">—</span>@else<span class="an-chip {{ $b['growth'] >= 0 ? 'up' : 'down' }}">{{ $b['growth'] >= 0 ? '+' : '' }}{{ $b['growth'] }}%</span>@endif</td>
                                <td class="c">{{ $num($b['invoices']) }}</td>
                                <td class="r">{{ $tk($b['all_time']) }}</td>
                                <td class="c">{{ $num($b['leads']) }}</td>
                                <td class="c">{{ $b['conversion'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
        @endif
    </div>
    @endif

    {{-- ─────────────── 4. MARKETING & REFERRALS ─────────────── --}}
    @if ($anyGroup('marketing'))
    <div class="an-group-title"><i class="fas fa-bullhorn" style="background:#fee2e2;color:#be123c;"></i>Marketing &amp; Referrals</div>
    <div class="an-grid">

        @if ($on('audience_builder')) @php $w = $an['audience_builder']; @endphp
        <div class="crm-table-card an-card an-wide" id="w-audience_builder">
            <div class="crm-table-header">
                <div class="table-title-group"><h2><i class="fas fa-bullseye" style="color:#e11d48;"></i> Marketing Audience Builder</h2></div>
                <a href="{{ url('admin/sms') }}" class="an-link"><i class="fas fa-sms mr-1"></i>Open SMS module →</a>
            </div>
            @if (!empty($w['error'])) {!! $err !!} @else
            <div class="an-body">
                <div class="an-aud-grid">
                    @foreach ($w['segments'] ?? [] as $s)
                        <div class="an-aud {{ $s['count'] ? '' : 'empty' }}">
                            <div class="top">
                                <span class="ic"><i class="{{ $s['icon'] }}"></i></span>
                                <div style="min-width:0;"><div class="nm">{{ $s['label'] }}</div><div class="ht">{{ $s['hint'] }}</div></div>
                                <span class="ct">{{ $num($s['count']) }}</span>
                            </div>
                            <div class="act">
                                <a href="{{ route('dashboard.audience.export', $s['key']) }}" id="aud-export-{{ $s['key'] }}"><i class="fas fa-file-csv mr-1"></i>Export CSV</a>
                                <button type="button" class="js-copy-phones" data-phones="{{ implode(',', $s['phones']) }}" id="aud-copy-{{ $s['key'] }}"><i class="far fa-copy mr-1"></i>Copy {{ count($s['phones']) }} no.</button>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="an-note"><i class="fas fa-info-circle"></i>Export a segment to CSV, or copy its phone numbers and paste them into the SMS module to run a campaign.</div>
            </div>
            @endif
        </div>
        @endif

        @if ($on('top_doctors')) @php $w = $an['top_doctors']; @endphp
        <div class="crm-table-card an-card" id="w-top_doctors">
            <div class="crm-table-header"><div class="table-title-group"><h2><i class="fas fa-user-md" style="color:#0284c7;"></i> Top Referring Doctors <small>{{ $pLabel }}</small></h2></div></div>
            @if (!empty($w['error'])) {!! $err !!} @else
            <div class="an-body" style="overflow-x:auto;">
                @if (count($w['list'] ?? []))
                <table class="an-table">
                    <thead><tr><th>Doctor</th><th class="c">Referrals</th><th class="c">Patients</th><th class="r">Revenue</th><th class="r">Commission due</th></tr></thead>
                    <tbody>
                        @foreach ($w['list'] as $d)
                            <tr>
                                <td><a href="{{ url('admin/doctor/' . $d['id']) }}">{{ $d['name'] }}</a><div style="font-size:11px;color:var(--page-text-muted);">last {{ $d['last'] }}</div></td>
                                <td class="c">{{ $d['referrals'] }}</td><td class="c">{{ $d['patients'] }}</td>
                                <td class="r"><b>{{ $tk($d['revenue']) }}</b></td>
                                <td class="r">{{ $d['due'] > 0 ? $tk($d['due']) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                    <div class="lk-empty"><i class="fas fa-user-md" style="color:var(--page-text-muted);"></i>No doctor-referred sales in this period</div>
                @endif
            </div>
            @endif
        </div>
        @endif

        @if ($on('inactive_doctors')) @php $w = $an['inactive_doctors']; @endphp
        <div class="crm-table-card an-card" id="w-inactive_doctors">
            <div class="crm-table-header"><div class="table-title-group"><h2><i class="fas fa-user-clock" style="color:#d97706;"></i> Inactive Doctors <small>no referral in 90 days</small></h2></div></div>
            @if (!empty($w['error'])) {!! $err !!} @else
            <div class="an-body flush">
                <div class="an-stats" style="padding:10px 8px 0;">
                    <div class="an-stat c-green"><div class="v">{{ $w['active'] }}</div><div class="l">Active (90d)</div></div>
                    <div class="an-stat c-amber"><div class="v">{{ $w['inactive'] }}</div><div class="l">Gone quiet</div></div>
                    <div class="an-stat c-slate"><div class="v">{{ $w['never'] }}</div><div class="l">Never referred</div><div class="s">of {{ $w['total'] }} doctors</div></div>
                </div>
                @forelse ($w['list'] ?? [] as $d)
                    <div class="an-rank">
                        <a class="main" href="{{ url('admin/doctor/' . $d['id']) }}"><div class="t">{{ $d['name'] }}</div><div class="s">{{ $d['referrals'] }} past referral(s) · last {{ $d['last'] }}</div></a>
                        <span class="an-chip down">{{ $d['days'] }}d</span>
                        @if ($d['phone'])<a href="tel:{{ $d['phone'] }}" class="lk-btn call" title="Call {{ $d['phone'] }}"><i class="fas fa-phone"></i></a>@endif
                    </div>
                @empty
                    <div class="lk-empty"><i class="fas fa-check-circle" style="color:var(--success-500);"></i>All referring doctors are active</div>
                @endforelse
            </div>
            @endif
        </div>
        @endif

        @if ($on('referral_trend')) @php $w = $an['referral_trend']; @endphp
        <div class="crm-table-card an-card" id="w-referral_trend">
            <div class="crm-table-header"><div class="table-title-group"><h2><i class="fas fa-wave-square" style="color:#6366f1;"></i> Referral Trend <small>last 6 months</small></h2></div></div>
            @if (!empty($w['error'])) {!! $err !!} @else
            <div class="an-body">
                @if (count($w['doctors'] ?? []))
                <table class="an-table">
                    <thead><tr><th>Doctor</th><th style="width:45%;">{{ implode(' · ', $w['months']) }}</th><th class="r">Total</th></tr></thead>
                    <tbody>
                        @foreach ($w['doctors'] as $d)
                            <tr>
                                <td style="font-weight:600;">{{ $d['name'] }}</td>
                                <td><div class="an-spark" title="{{ implode(' / ', $d['counts']) }}">@foreach ($d['counts'] as $c)<span class="{{ $c ? '' : 'z' }}" style="height:{{ $c ? max(round($c / $w['max'] * 100), 12) : 8 }}%;"></span>@endforeach</div></td>
                                <td class="r"><b>{{ $d['total'] }}</b></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                    <div class="lk-empty"><i class="fas fa-wave-square" style="color:var(--page-text-muted);"></i>No referrals in the last 6 months</div>
                @endif
            </div>
            @endif
        </div>
        @endif

        @if ($on('lead_response_time')) @php $w = $an['lead_response_time']; $bk = $w['buckets'] ?? []; $bt = max(array_sum($bk), 1); @endphp
        <div class="crm-table-card an-card" id="w-lead_response_time">
            <div class="crm-table-header"><div class="table-title-group"><h2><i class="fas fa-stopwatch" style="color:#2563eb;"></i> Lead Response Time <small>{{ $pLabel }}</small></h2></div></div>
            @if (!empty($w['error'])) {!! $err !!} @else
            @php $segs = [['< 1 hour', $bk['lt1h'] ?? 0, '#10b981'], ['1–24 h', $bk['lt24h'] ?? 0, '#14b8a6'], ['1–3 days', $bk['lt3d'] ?? 0, '#f59e0b'], ['3+ days', $bk['gt3d'] ?? 0, '#f97316'], ['Not contacted', $bk['none'] ?? 0, '#e11d48']]; @endphp
            <div class="an-body">
                <div class="an-stats">
                    <div class="an-stat c-blue"><div class="v">{{ $w['avg_hours'] !== null ? $w['avg_hours'] . 'h' : '—' }}</div><div class="l">Avg first contact</div><div class="s">median {{ $w['median'] !== null ? $w['median'] . 'h' : '—' }}</div></div>
                    <div class="an-stat c-green"><div class="v">{{ $w['within_24h'] }}%</div><div class="l">Within 24 h</div></div>
                    <div class="an-stat c-red"><div class="v">{{ $num($bk['none'] ?? 0) }}</div><div class="l">Not contacted</div><div class="s">of {{ $num($w['leads']) }} leads</div></div>
                </div>
                @if ($w['leads'] > 0)
                <div class="an-split">@foreach ($segs as [$l, $v, $c])@if ($v)<span style="width:{{ round($v / $bt * 100, 1) }}%;background:{{ $c }};" title="{{ $l }}: {{ $v }}">{{ $v / $bt >= .12 ? $v : '' }}</span>@endif @endforeach</div>
                <div class="an-legend">@foreach ($segs as [$l, $v, $c])<span><i style="background:{{ $c }};"></i>{{ $l }}<b>{{ $v }}</b></span>@endforeach</div>
                @endif
                @if (count($w['staff'] ?? []))
                <table class="an-table" style="margin-top:8px;">
                    <thead><tr><th>Staff</th><th class="c">Leads</th><th class="c">Contacted</th><th class="r">Avg</th></tr></thead>
                    <tbody>
                        @foreach ($w['staff'] as $s)
                            <tr><td style="font-weight:600;">{{ $s['name'] }}</td><td class="c">{{ $s['leads'] }}</td><td class="c">{{ $s['contacted'] }}</td>
                                <td class="r"><span class="an-chip {{ $s['avg_hours'] === null ? 'flat' : ($s['avg_hours'] <= 24 ? 'up' : 'down') }}">{{ $s['avg_hours'] !== null ? $s['avg_hours'] . 'h' : '—' }}</span></td></tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
                <div class="an-note"><i class="fas fa-lightbulb"></i>First contact = first completed follow-up or first status change. Leads called within an hour convert far better.</div>
            </div>
            @endif
        </div>
        @endif

        @if ($on('lost_reasons')) @php $w = $an['lost_reasons']; @endphp
        <div class="crm-table-card an-card" id="w-lost_reasons">
            <div class="crm-table-header">
                <div class="table-title-group"><h2><i class="fas fa-heart-broken" style="color:#e11d48;"></i> Lost Lead Reasons <small>{{ $pLabel }}</small></h2></div>
                <a href="{{ url('admin/leads?lead_status=lost') }}" class="an-link">Lost leads →</a>
            </div>
            @if (!empty($w['error'])) {!! $err !!} @else
            <div class="an-body">
                <div class="an-stats">
                    <div class="an-stat c-red"><div class="v">{{ $num($w['total']) }}</div><div class="l">Leads lost</div></div>
                    <div class="an-stat c-amber"><div class="v">{{ $w['lost_rate'] }}%</div><div class="l">Lost rate</div><div class="s">of new leads</div></div>
                </div>
                @forelse ($w['reasons'] ?? [] as $r)
                    <div class="an-bar-row">
                        <span class="lbl"><i class="{{ $r['icon'] }} mr-1" style="color:{{ $r['color'] }};"></i>{{ $r['label'] }}</span>
                        <div class="trk"><div class="fil" style="width:{{ max(round($r['count'] / $w['max'] * 100), 4) }}%;background:{{ $r['color'] }};"></div></div>
                        <span class="num">{{ $r['count'] }}<small>{{ $r['share'] }}%</small></span>
                    </div>
                @empty
                    <div class="lk-empty" style="padding:12px;"><i class="fas fa-check-circle" style="color:var(--success-500);"></i>No lost leads in this period</div>
                @endforelse
                @if (count($w['competitors'] ?? []))
                    <div class="an-legend" style="margin-top:6px;"><span style="font-weight:600;">Lost to:</span>@foreach ($w['competitors'] as $c)<span><i style="background:#7c3aed;"></i>{{ $c['name'] }}<b>{{ $c['count'] }}</b></span>@endforeach</div>
                @endif
                <div class="an-note"><i class="fas fa-info-circle"></i>Pick a reason chip when marking a lead Lost (lead edit form) for cleaner grouping.</div>
            </div>
            @endif
        </div>
        @endif

        @if ($on('commission_due')) @php $w = $an['commission_due']; @endphp
        <div class="crm-table-card an-card" id="w-commission_due">
            <div class="crm-table-header">
                <div class="table-title-group"><h2><i class="fas fa-hand-holding-medical" style="color:#0284c7;"></i> Referral Commission Due</h2></div>
                <a href="{{ url('admin/doctor') }}" class="an-link">Doctors →</a>
            </div>
            @if (!empty($w['error'])) {!! $err !!} @else
            <div class="an-body" style="overflow-x:auto;">
                <div class="an-stats">
                    <div class="an-stat c-red"><div class="v">{{ $tk($w['due']) }}</div><div class="l">Total due</div><div class="s">{{ $w['doctors_due'] }} doctor(s)</div></div>
                    <div class="an-stat c-violet"><div class="v">{{ $tk($w['earned_month']) }}</div><div class="l">Earned this month</div></div>
                    <div class="an-stat c-green"><div class="v">{{ $tk($w['paid_month']) }}</div><div class="l">Paid this month</div></div>
                </div>
                @if (count($w['list'] ?? []))
                <table class="an-table">
                    <thead><tr><th>Doctor</th><th class="r">Earned</th><th class="r">Paid</th><th class="r">Due</th></tr></thead>
                    <tbody>
                        @foreach ($w['list'] as $d)
                            <tr>
                                <td><a href="{{ url('admin/doctor/' . $d['id']) }}">{{ $d['name'] }}</a><div style="font-size:11px;color:var(--page-text-muted);">{{ $d['rule'] }} · last paid {{ $d['last_paid'] }}</div></td>
                                <td class="r">{{ $tk($d['earned']) }}</td>
                                <td class="r">{{ $tk($d['paid']) }}</td>
                                <td class="r"><b style="color:var(--danger-600);">{{ $tk($d['due']) }}</b></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                    <div class="lk-empty" style="padding:12px;"><i class="fas fa-check-circle" style="color:var(--success-500);"></i>No commission outstanding</div>
                @endif
            </div>
            @endif
        </div>
        @endif
    </div>
    @endif

    {{-- ─────────────── 5. OPERATIONS & FINANCE ─────────────── --}}
    @if ($anyGroup('ops'))
    <div class="an-group-title"><i class="fas fa-cogs" style="background:#e0f2fe;color:#0369a1;"></i>Operations &amp; Finance</div>
    <div class="an-grid">

        @if ($on('cash_today')) @php $w = $an['cash_today']; @endphp
        <div class="crm-table-card an-card" id="w-cash_today">
            <div class="crm-table-header"><div class="table-title-group"><h2><i class="fas fa-money-bill-wave" style="color:#059669;"></i> Cash Flow Today</h2></div></div>
            @if (!empty($w['error'])) {!! $err !!} @else
            @php $t = $w['today']; $m = $w['month']; @endphp
            <div class="an-body">
                <div class="an-stats">
                    <div class="an-stat c-green"><div class="v">{{ $tk($t['in']) }}</div><div class="l">Cash in</div><div class="s">customers {{ $tk($t['customers']) }}</div></div>
                    <div class="an-stat c-red"><div class="v">{{ $tk($t['out']) }}</div><div class="l">Cash out</div><div class="s">suppliers {{ $tk($t['suppliers']) }}</div></div>
                    <div class="an-stat {{ $t['net'] >= 0 ? 'c-blue' : 'c-amber' }}"><div class="v">{{ $tk($t['net']) }}</div><div class="l">Net today</div></div>
                </div>
                <div class="an-week">
                    @foreach ($w['week'] as $d)
                        <div class="d" title="{{ $d['day'] }}: in {{ $tk($d['in']) }} / out {{ $tk($d['out']) }}">
                            <div class="bars">
                                <span style="height:{{ $d['in'] ? max(round($d['in'] / $w['max'] * 100), 3) : 2 }}%;background:#10b981;"></span>
                                <span style="height:{{ $d['out'] ? max(round($d['out'] / $w['max'] * 100), 3) : 2 }}%;background:#f43f5e;"></span>
                            </div>
                            <span class="lb">{{ $d['day'] }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="an-legend" style="margin-top:8px;"><span><i style="background:#10b981;"></i>In</span><span><i style="background:#f43f5e;"></i>Out</span>
                    <span style="margin-left:auto;">Month to date: in <b>{{ $tk($m['in']) }}</b> · out <b>{{ $tk($m['out']) }}</b> · net <b style="color:{{ $m['net'] >= 0 ? 'var(--success-600)' : 'var(--danger-600)' }};">{{ $tk($m['net']) }}</b></span></div>
                <div class="an-note"><i class="fas fa-info-circle"></i>Out = supplier payments {{ $tk($t['suppliers']) }} · doctor payments {{ $tk($t['doctors']) }} · expenses {{ $tk($t['expenses']) }}.</div>
            </div>
            @endif
        </div>
        @endif

        @if ($on('supplier_aging')) @php $w = $an['supplier_aging']; @endphp
        <div class="crm-table-card an-card" id="w-supplier_aging">
            <div class="crm-table-header">
                <div class="table-title-group"><h2><i class="fas fa-truck-loading" style="color:#ea580c;"></i> Supplier Payables Aging</h2></div>
                <a href="{{ url('admin/report/supplier-statement') }}" class="an-link">Statement →</a>
            </div>
            @if (!empty($w['error'])) {!! $err !!} @else
            @include('admin.dashboard.partials.aging-body', ['w' => $w, 'who' => 'supplier', 'linkBase' => 'admin/report/supplier-statement?supplier_id='])
            @endif
        </div>
        @endif

        @if ($on('dead_stock')) @php $w = $an['dead_stock']; @endphp
        <div class="crm-table-card an-card" id="w-dead_stock">
            <div class="crm-table-header"><div class="table-title-group"><h2><i class="fas fa-box" style="color:#64748b;"></i> Dead / Slow Stock <small>no sale in 90+ days</small></h2></div></div>
            @if (!empty($w['error'])) {!! $err !!} @else
            <div class="an-body">
                <div class="an-stats">
                    <div class="an-stat c-slate"><div class="v">{{ $num($w['count']) }}</div><div class="l">Products</div></div>
                    <div class="an-stat c-amber"><div class="v">{{ $num($w['units']) }}</div><div class="l">Units idle</div></div>
                    <div class="an-stat c-red"><div class="v">{{ $tk($w['value']) }}</div><div class="l">Cash tied up</div></div>
                </div>
                @if (count($w['list'] ?? []))
                <table class="an-table">
                    <thead><tr><th>Product</th><th class="c">Qty</th><th class="r">Value</th><th class="r">Last sold</th></tr></thead>
                    <tbody>
                        @foreach ($w['list'] as $p)
                            <tr><td><b>{{ $p['name'] }}</b><div style="font-size:11px;color:var(--page-text-muted);">{{ $p['category'] }}</div></td>
                                <td class="c">{{ $p['qty'] }}</td><td class="r">{{ $tk($p['value']) }}</td>
                                <td class="r">{{ $p['last'] }}<div style="font-size:11px;color:var(--danger-600);">{{ $p['days'] }}d idle</div></td></tr>
                        @endforeach
                    </tbody>
                </table>
                @else
                    <div class="lk-empty" style="padding:12px;"><i class="fas fa-check-circle" style="color:var(--success-500);"></i>All stock moved in the last 90 days</div>
                @endif
            </div>
            @endif
        </div>
        @endif

        @if ($on('branch_stock')) @php $w = $an['branch_stock']; @endphp
        <div class="crm-table-card an-card" id="w-branch_stock">
            <div class="crm-table-header"><div class="table-title-group"><h2><i class="fas fa-warehouse" style="color:#7c3aed;"></i> Stock Value by Branch</h2></div><span class="an-link" style="color:var(--page-text-muted);">Total {{ $tk($w['total'] ?? 0) }}</span></div>
            @if (!empty($w['error'])) {!! $err !!} @else
            <div class="an-body">
                @foreach ($w['rows'] ?? [] as $i => $b)
                    <div class="an-bar-row" title="{{ $b['products'] }} products · {{ $num($b['units']) }} units">
                        <span class="lbl">{{ $b['name'] }}</span>
                        <div class="trk"><div class="fil" style="width:{{ $b['value'] ? max(round($b['value'] / $w['max'] * 100), 4) : 0 }}%;background:{{ ['#7c3aed','#0d9488','#2563eb','#d97706','#e11d48'][$i % 5] }};"></div></div>
                        <span class="num">{{ $tk($b['value']) }}<small>{{ $num($b['units']) }} pcs</small></span>
                    </div>
                @endforeach
                <div class="an-note"><i class="fas fa-info-circle"></i>Valued at average purchase cost.</div>
            </div>
            @endif
        </div>
        @endif

        @if ($on('appointment_utilisation')) @php $w = $an['appointment_utilisation']; @endphp
        <div class="crm-table-card an-card" id="w-appointment_utilisation">
            <div class="crm-table-header">
                <div class="table-title-group"><h2><i class="far fa-calendar-check" style="color:#d97706;"></i> Appointment Utilisation <small>{{ $pLabel }}</small></h2></div>
                <a href="{{ url('admin/appointments/calendar') }}" class="an-link">Calendar →</a>
            </div>
            @if (!empty($w['error'])) {!! $err !!} @else
            @php $tot = max($w['total'], 1); $segs = [['Completed', $w['completed'], '#10b981'], ['Missed', $w['missed'], '#f97316'], ['Cancelled', $w['cancelled'], '#e11d48'], ['Upcoming', $w['scheduled'], '#6366f1']]; @endphp
            <div class="an-body">
                <div class="an-stats">
                    <div class="an-stat c-blue"><div class="v">{{ $num($w['total']) }}</div><div class="l">Booked</div></div>
                    <div class="an-stat c-green"><div class="v">{{ $w['show_rate'] }}%</div><div class="l">Show rate</div></div>
                    <div class="an-stat c-amber"><div class="v">{{ $num($w['missed']) }}</div><div class="l">Missed / not updated</div></div>
                    <div class="an-stat c-violet"><div class="v">{{ $num($w['upcoming_7']) }}</div><div class="l">Next 7 days</div></div>
                </div>
                @if ($w['total'] > 0)
                <div class="an-split">@foreach ($segs as [$l, $v, $c])@if ($v)<span style="width:{{ round($v / $tot * 100, 1) }}%;background:{{ $c }};">{{ $v / $tot >= .12 ? $v : '' }}</span>@endif @endforeach</div>
                @endif
                <div class="an-legend">@foreach ($segs as [$l, $v, $c])<span><i style="background:{{ $c }};"></i>{{ $l }}<b>{{ $v }}</b></span>@endforeach</div>
            </div>
            @endif
        </div>
        @endif

        @if ($on('ticket_sla')) @php $w = $an['ticket_sla']; @endphp
        <div class="crm-table-card an-card" id="w-ticket_sla">
            <div class="crm-table-header">
                <div class="table-title-group"><h2><i class="fas fa-stopwatch" style="color:#e11d48;"></i> Ticket SLA <small>target {{ $w['sla_hours'] ?? 72 }}h</small></h2></div>
                <a href="{{ url('admin/ticket') }}" class="an-link">All tickets →</a>
            </div>
            @if (!empty($w['error'])) {!! $err !!} @else
            <div class="an-body flush">
                <div class="an-stats" style="padding:10px 8px 0;">
                    <div class="an-stat c-blue"><div class="v">{{ $w['open'] }}</div><div class="l">Open</div></div>
                    <div class="an-stat c-red"><div class="v">{{ $w['overdue'] }}</div><div class="l">Over SLA</div></div>
                    <div class="an-stat c-teal"><div class="v">{{ $w['avg_hours'] !== null ? $w['avg_hours'] . 'h' : '—' }}</div><div class="l">Avg resolution</div></div>
                    <div class="an-stat c-green"><div class="v">{{ $w['resolved_month'] }}</div><div class="l">Resolved (month)</div></div>
                </div>
                @forelse ($w['oldest'] ?? [] as $t)
                    <div class="an-rank">
                        <a class="main" href="{{ url('admin/ticket/' . $t['id'] . '/edit') }}"><div class="t">{{ $t['subject'] }}</div><div class="s">{{ $t['priority'] }} · {{ $t['status'] }} · open {{ $t['age'] }}</div></a>
                        <span class="an-chip {{ $t['overdue'] ? 'down' : 'flat' }}">{{ $t['overdue'] ? 'Over SLA' : 'Within SLA' }}</span>
                    </div>
                @empty
                    <div class="lk-empty"><i class="fas fa-check-circle" style="color:var(--success-500);"></i>No open tickets</div>
                @endforelse
            </div>
            @endif
        </div>
        @endif

        @if ($on('expense_revenue')) @php $w = $an['expense_revenue']; @endphp
        <div class="crm-table-card an-card an-wide" id="w-expense_revenue">
            <div class="crm-table-header"><div class="table-title-group"><h2><i class="fas fa-balance-scale-right" style="color:#059669;"></i> Expense vs Revenue <small>last 12 months · cash basis</small></h2></div></div>
            @if (!empty($w['error'])) {!! $err !!} @else
            <div class="an-body">
                <div class="an-stats">
                    <div class="an-stat c-blue"><div class="v">{{ $tk($w['revenue']) }}</div><div class="l">Revenue</div></div>
                    <div class="an-stat c-amber"><div class="v">{{ $tk($w['purchases']) }}</div><div class="l">Purchases</div></div>
                    <div class="an-stat c-red"><div class="v">{{ $tk($w['expenses']) }}</div><div class="l">Other expenses</div></div>
                    <div class="an-stat {{ $w['net'] >= 0 ? 'c-green' : 'c-red' }}"><div class="v">{{ $tk($w['net']) }}</div><div class="l">Net</div></div>
                </div>
                <div style="height:260px;"><canvas id="expRevChart"></canvas></div>
            </div>
            @endif
        </div>
        @endif
    </div>
    @endif
</section>
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    /* In-card tabs */
    document.querySelectorAll('[data-tabs] button').forEach(btn => btn.addEventListener('click', function () {
        const card = this.closest('.an-card');
        this.parentElement.querySelectorAll('button').forEach(b => b.classList.toggle('active', b === this));
        card.querySelectorAll('.an-pane').forEach(p => p.classList.toggle('active', p.id === this.dataset.pane));
    }));

    /* Copy phone numbers (audience builder) */
    document.querySelectorAll('.js-copy-phones').forEach(btn => btn.addEventListener('click', async function () {
        const list = (this.dataset.phones || '').split(',').filter(Boolean);
        if (!list.length) return;
        const text = list.join('\n');
        try { await navigator.clipboard.writeText(text); }
        catch (e) { const ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove(); }
        const old = this.innerHTML; this.innerHTML = '<i class="fas fa-check mr-1"></i>Copied ' + list.length;
        setTimeout(() => this.innerHTML = old, 1800);
    }));

    if (typeof Chart === 'undefined') return;
    const css = n => getComputedStyle(document.documentElement).getPropertyValue(n).trim();
    const tip = { backgroundColor: css('--card-bg') || '#fff', titleColor: css('--page-text') || '#1e293b', bodyColor: css('--page-text-secondary') || '#64748b', borderColor: css('--card-border') || '#e2e8f0', borderWidth: 1, padding: 10 };
    const money = v => '৳' + Number(v).toLocaleString();
    const axisY = { beginAtZero: true, grid: { color: 'rgba(148,163,184,0.08)' }, ticks: { color: '#94a3b8', font: { size: 11 }, callback: v => Math.abs(v) >= 1000 ? '৳' + (v / 1000) + 'k' : '৳' + v } };
    const axisX = { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 11 } } };

    /* AOV trend */
    const aovEl = document.getElementById('aovChart');
    if (aovEl) {
        const d = @json($an['aov_trend']['series'] ?? []);
        new Chart(aovEl, { type: 'line', data: { labels: d.map(i => i.month), datasets: [{ label: 'AOV', data: d.map(i => i.aov), borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,.08)', fill: true, tension: .35, borderWidth: 2.5, pointRadius: 3, pointBackgroundColor: '#fff', pointBorderColor: '#2563eb' }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: Object.assign({}, tip, { callbacks: { label: c => 'AOV ' + money(c.raw) + ' · ' + d[c.dataIndex].orders + ' orders' } }) }, scales: { y: axisY, x: axisX } } });
    }

    /* Payment mix */
    const pmEl = document.getElementById('payMixChart');
    if (pmEl) {
        const d = @json($an['payment_mix']['methods'] ?? []);
        const pal = ['#0d9488', '#6366f1', '#d97706', '#e11d48', '#2563eb'];
        document.querySelectorAll('.pm-dot').forEach(el => el.style.background = pal[el.dataset.i % pal.length]);
        new Chart(pmEl, { type: 'doughnut', data: { labels: d.map(i => i.label), datasets: [{ data: d.map(i => i.total), backgroundColor: pal, borderWidth: 0, hoverOffset: 4 }] },
            options: { responsive: true, maintainAspectRatio: false, cutout: '66%', plugins: { legend: { display: false }, tooltip: Object.assign({}, tip, { callbacks: { label: c => c.label + ': ' + money(c.raw) } }) } } });
    }

    /* Expense vs revenue */
    const erEl = document.getElementById('expRevChart');
    if (erEl) {
        const d = @json($an['expense_revenue']['series'] ?? []);
        new Chart(erEl, { data: { labels: d.map(i => i.month), datasets: [
                { type: 'bar', label: 'Revenue', data: d.map(i => i.revenue), backgroundColor: 'rgba(99,102,241,.75)', borderRadius: 5, maxBarThickness: 22 },
                { type: 'bar', label: 'Purchases', data: d.map(i => i.purchases), backgroundColor: 'rgba(245,158,11,.7)', borderRadius: 5, maxBarThickness: 22 },
                { type: 'bar', label: 'Expenses', data: d.map(i => i.expenses), backgroundColor: 'rgba(244,63,94,.7)', borderRadius: 5, maxBarThickness: 22 },
                { type: 'line', label: 'Net', data: d.map(i => i.net), borderColor: '#059669', backgroundColor: '#059669', tension: .35, borderWidth: 2.5, pointRadius: 3 }
            ] },
            options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } }, tooltip: Object.assign({}, tip, { callbacks: { label: c => c.dataset.label + ': ' + money(c.raw) } }) }, scales: { y: axisY, x: axisX } } });
    }

    /* A1 — Revenue chart: 6M / 12M + last-year comparison */
    const ext = @json($an['revenue_extended'] ?? null);
    const ctrl = document.getElementById('revRange');
    if (ext && !ext.error && ctrl && window.dashRevenueChart) {
        const ch = window.dashRevenueChart;
        const purchasesShown = ch.data.datasets.some(ds => ds.label === 'Purchases');
        let range = 6;
        const ly = document.getElementById('revLy');
        const render = () => {
            const s = ext.labels.length - range;
            ch.data.labels = ext.labels.slice(s);
            ch.data.datasets = ch.data.datasets.filter(ds => ds.label !== 'Last year');
            ch.data.datasets.forEach(ds => {
                if (ds.label === 'Sales') ds.data = ext.sales.slice(s);
                if (ds.label === 'Purchases' && purchasesShown) ds.data = ext.purchases.slice(s);
            });
            if (ly && ly.checked) {
                ch.data.datasets.push({ label: 'Last year', data: ext.sales_ly.slice(s), borderColor: '#94a3b8', borderWidth: 2, borderDash: [3, 4], tension: .4, fill: false, pointRadius: 2 });
            }
            ch.update();
        };
        ctrl.querySelectorAll('button').forEach(b => b.addEventListener('click', function () {
            ctrl.querySelectorAll('button').forEach(x => x.classList.toggle('active', x === this));
            range = parseInt(this.dataset.range, 10);
            const lbl = document.getElementById('revRangeLabel'); if (lbl) lbl.textContent = 'last ' + range + ' months';
            render();
        }));
        if (ly) ly.addEventListener('change', render);
    }
});
</script>
@endpush
