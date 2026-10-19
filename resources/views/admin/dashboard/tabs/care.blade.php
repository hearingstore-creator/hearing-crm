{{-- Patients & Care tab. $w = widget results, $label = period label, $legacy = old dashboard URL --}}
@php
    $ok = fn ($k) => isset($w[$k]) && empty($w[$k]['error']);
    $failed = collect($w)->filter(fn ($v) => is_array($v) && !empty($v['error']))->count();
    $m = fn ($v) => '৳' . number_format((float) $v);
    $actionCluster = function ($phone, $name, $contextMsg) {
        if (!$phone) return '';
        $norm = \App\Classes\Helper::normalizePhone($phone);
        $wa = 'https://wa.me/' . $norm . '?text=' . rawurlencode($contextMsg);
        return '<div class="td-row-acts">' .
            '<a class="lk-btn call" href="tel:' . e($phone) . '" title="Call ' . e($phone) . '"><i class="fas fa-phone"></i></a>' .
            '<a class="lk-btn wa" href="' . e($wa) . '" target="_blank" rel="noopener" title="WhatsApp ' . e($name) . '"><i class="fab fa-whatsapp"></i></a>' .
        '</div>';
    };
@endphp

@if ($failed)<div class="td-err">{{ $failed }} block(s) could not be loaded and are left out.</div>@endif

<div class="td-charts">
    @if ($ok('new_vs_returning'))
        @php $n = $w['new_vs_returning']; @endphp
        <div class="td-card">
            <h3>
                <span class="t-halo" style="width:28px; height:28px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; background:#eef2ff; color:#4f46e5; font-size:13px;"><i class="fas fa-user-friends"></i></span>
                New and returning buyers
                <a class="td-more" href="{{ url('admin/customers') }}" id="td-c-report-customers">Customers</a>
            </h3>
            <div class="td-note">{{ $label }} · repeat rate {{ $n['repeat_rate'] }}% of {{ $n['total'] }} buyer(s)</div>
            @if ($n['total'] === 0)
                <div class="td-empty">No buyers in this period.</div>
            @else
                <div class="td-chart-box" style="height:200px"><canvas class="td-cv" data-cv="{{ json_encode(['kind' => 'doughnut', 'fmt' => 'num', 'labels' => ['New', 'Returning'], 'sets' => [['label' => 'Buyers', 'data' => [$n['new_count'], $n['ret_count']], 'colors' => ['#4f46e5', '#059669']]]]) }}"></canvas></div>
                <div class="td-row"><span class="td-name">New buyers</span><b>{{ $n['new_count'] }}</b><small>{{ $m($n['new_revenue']) }}</small></div>
                <div class="td-row"><span class="td-name">Returning patients</span><b>{{ $n['ret_count'] }}</b><small>{{ $m($n['ret_revenue']) }}</small></div>
            @endif
        </div>
    @endif

    @if ($ok('customer_rfm'))
        @php $r = $w['customer_rfm']; @endphp
        <div class="td-card">
            <h3>
                <span class="t-halo" style="width:28px; height:28px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; background:#f5f3ff; color:#7c3aed; font-size:13px;"><i class="fas fa-layer-group"></i></span>
                Patient segments
                <a class="td-more" href="{{ $legacy }}#w-audience_builder" id="td-c-audience">Audience builder</a>
            </h3>
            <div class="td-note">{{ number_format($r['customers']) }} patient(s) by value and recency, all time</div>
            @foreach ($r['segments'] as $s)
                <div class="td-row" title="{{ $s['hint'] }}">
                    <span class="td-name"><i class="{{ $s['icon'] }}" style="color:{{ $s['color'] }};width:16px"></i> {{ $s['label'] }}</span>
                    <span class="td-meter"><i style="width:{{ $s['share'] }}%;background:{{ $s['color'] }}"></i></span>
                    <b>{{ number_format($s['count']) }}</b>
                    <small>{{ $m($s['revenue']) }}</small>
                </div>
            @endforeach
        </div>
    @endif

    @if ($ok('dormant_customers'))
        @php $d = $w['dormant_customers']; @endphp
        <div class="td-card">
            <h3>
                <span class="t-halo" style="width:28px; height:28px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; background:#fff1f2; color:#e11d48; font-size:13px;"><i class="fas fa-user-clock"></i></span>
                Dormant patients
                <a class="td-more" href="{{ $legacy }}#w-audience_builder" id="td-c-dormant">Send an offer</a>
            </h3>
            <div class="td-note">{{ $d['count_6m'] }} quiet for 6+ months ({{ $d['count_12m'] }} for 12+) · {{ $m($d['value']) }} lifetime spend</div>
            @forelse (array_slice($d['list'], 0, 8) as $p)
                @php
                    $waMsg = "Assalamu Alaikum " . ($p['name'] ?? 'Sir/Madam') . ". Zenhear Hearing Care Centre misses you! We invite you for a complimentary hearing checkup & cleaning this month. Let us know when you can visit.";
                @endphp
                <div class="td-row">
                    <a class="td-name" href="{{ url('admin/customers/' . $p['id']) }}">{{ $p['name'] }}</a>
                    <small>{{ $p['days'] }}d ago</small>
                    <b>{{ $m($p['spend']) }}</b>
                    {!! $actionCluster($p['phone'] ?? null, $p['name'], $waMsg) !!}
                </div>
            @empty
                <div class="td-empty">Nobody has gone quiet.</div>
            @endforelse
        </div>
    @endif

    @if ($ok('upgrade_due'))
        @php $u = $w['upgrade_due']; @endphp
        <div class="td-card">
            <h3>
                <span class="t-halo" style="width:28px; height:28px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; background:#fffbeb; color:#d97706; font-size:13px;"><i class="fas fa-sync-alt"></i></span>
                Device upgrade due
            </h3>
            <div class="td-note">{{ $u['due_count'] }} due now · {{ $u['upcoming_count'] }} coming up · {{ $u['owners'] }} device owners</div>
            @forelse (array_slice($u['list'], 0, 8) as $p)
                @php
                    $waMsg = "Assalamu Alaikum " . ($p['name'] ?? 'Sir/Madam') . ". This is Zenhear Hearing Care Centre. Your hearing aid (" . ($p['model'] ?? '') . ") may be eligible for our latest technology upgrade benefits. Let us know if you would like details.";
                @endphp
                <div class="td-row">
                    <a class="td-name" href="{{ url('admin/customers/' . $p['customer_id']) }}">
                        {{ $p['name'] }}
                        <span class="td-sub">{{ $p['model'] }}</span>
                    </a>
                    <small>{{ $p['age_label'] }}</small>
                    {!! $actionCluster($p['phone'] ?? null, $p['name'], $waMsg) !!}
                </div>
            @empty
                <div class="td-empty">No upgrades due.</div>
            @endforelse
        </div>
    @endif

    @if ($ok('binaural_opportunity'))
        @php $b = $w['binaural_opportunity']; @endphp
        <div class="td-card">
            <h3>
                <span class="t-halo" style="width:28px; height:28px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; background:#ecfeff; color:#0891b2; font-size:13px;"><i class="fas fa-assistive-listening-systems"></i></span>
                Second-ear chances
            </h3>
            <div class="td-note">{{ $b['count'] }} single-device owners of {{ $b['owners'] }} ({{ $b['share'] }}%)</div>
            @forelse (array_slice($b['list'], 0, 8) as $p)
                @php
                    $waMsg = "Assalamu Alaikum " . ($p['name'] ?? 'Sir/Madam') . ". Zenhear Hearing Care Centre is currently offering a special binaural second-ear discount to ensure balanced hearing. Let us know if you'd like a trial.";
                @endphp
                <div class="td-row">
                    <a class="td-name" href="{{ url('admin/customers/' . $p['customer_id']) }}">
                        {{ $p['name'] }}
                        <span class="td-sub">{{ $p['model'] }}</span>
                    </a>
                    <small>{{ $p['date_label'] ?? '' }}</small>
                    {!! $actionCluster($p['phone'] ?? null, $p['name'], $waMsg) !!}
                </div>
            @empty
                <div class="td-empty">No single-device owners.</div>
            @endforelse
        </div>
    @endif

    @if ($ok('membership_breakdown'))
        @php $mb = $w['membership_breakdown']; @endphp
        <div class="td-card">
            <h3>
                <span class="t-halo" style="width:28px; height:28px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; background:#faf5ff; color:#9333ea; font-size:13px;"><i class="fas fa-id-card"></i></span>
                Membership tiers
                <a class="td-more" href="{{ $report('admin/report/membership-loyalty') }}" id="td-c-report-members">Membership report</a>
            </h3>
            <div class="td-note">{{ number_format($mb['members']) }} patient(s) · {{ $mb['upgrades_month'] }} upgrade(s) this month</div>
            @if (!$mb['members'])
                <div class="td-empty">No members yet.</div>
            @else
                <div class="td-chart-box" style="height:200px"><canvas class="td-cv" data-cv="{{ json_encode(['kind' => 'doughnut', 'fmt' => 'num', 'labels' => array_column($mb['tiers'], 'name'), 'sets' => [['label' => 'Patients', 'data' => array_column($mb['tiers'], 'customers'), 'colors' => array_column($mb['tiers'], 'color')]]]) }}"></canvas></div>
            @endif
        </div>
    @endif

    @if ($ok('points_liability'))
        @php $p = $w['points_liability']; @endphp
        <div class="td-card">
            <h3>
                <span class="t-halo" style="width:28px; height:28px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; background:#fffbeb; color:#d97706; font-size:13px;"><i class="fas fa-coins"></i></span>
                Loyalty points
                <a class="td-more" href="{{ $report('admin/report/membership-loyalty') }}" id="td-c-report-points">Membership report</a>
            </h3>
            <div class="td-note">{{ number_format($p['outstanding']) }} outstanding · {{ number_format($p['expiring_30']) }} expire within 30 days · {{ $p['redeem_rate'] }}% redeemed</div>
            @forelse ($p['top_holders'] as $h)
                @php
                    $waMsg = "Assalamu Alaikum " . ($h['name'] ?? 'Sir/Madam') . ". You have reward points at Zenhear Hearing Care Centre available to redeem on hearing aid batteries or accessories.";
                @endphp
                <div class="td-row">
                    <a class="td-name" href="{{ url('admin/customers/' . $h['id']) }}">{{ $h['name'] }}</a>
                    <b>{{ number_format($h['points']) }} pts</b>
                    {!! $actionCluster($h['phone'] ?? null, $h['name'], $waMsg) !!}
                </div>
            @empty
                <div class="td-empty">No points held.</div>
            @endforelse
        </div>
    @endif

    @if ($ok('service_due'))
        @php $s = $w['service_due']; @endphp
        <div class="td-card">
            <h3>
                <span class="t-halo" style="width:28px; height:28px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; background:#ecfdf5; color:#059669; font-size:13px;"><i class="fas fa-tools"></i></span>
                Service and cleaning due
                <a class="td-more" href="{{ $report('admin/report/warranty-expiry') }}" id="td-c-report-warranty">Warranty expiry</a>
            </h3>
            <div class="td-note">{{ $s['overdue'] }} overdue · {{ $s['due'] }} due in the next 30 days</div>
            @forelse (array_slice($s['list'], 0, 8) as $p)
                @php
                    $waMsg = "Assalamu Alaikum " . ($p['name'] ?? 'Sir/Madam') . ". Your hearing device (" . ($p['model'] ?? '') . ") is due for regular maintenance & cleaning checkup at Zenhear Hearing Care Centre. Please contact us to schedule.";
                @endphp
                <div class="td-row">
                    <a class="td-name" href="{{ url('admin/customers/' . $p['customer_id']) }}">
                        {{ $p['name'] }}
                        <span class="td-sub">{{ $p['model'] }} · {{ $p['due_label'] }}</span>
                    </a>
                    <span class="md-badge {{ $p['stage'] === 'overdue' ? 'danger' : 'warning' }}">{{ $p['stage'] === 'overdue' ? abs($p['days']) . 'd late' : 'in ' . $p['days'] . 'd' }}</span>
                    {!! $actionCluster($p['phone'] ?? null, $p['name'], $waMsg) !!}
                </div>
            @empty
                <div class="td-empty">No service due.</div>
            @endforelse
        </div>
    @endif
</div>
