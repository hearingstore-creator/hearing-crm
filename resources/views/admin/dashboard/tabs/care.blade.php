{{-- Patients & Care tab. $w = widget results, $label = period label, $legacy = old dashboard URL --}}
@php
    $ok = fn ($k) => isset($w[$k]) && empty($w[$k]['error']);
    $failed = collect($w)->filter(fn ($v) => is_array($v) && !empty($v['error']))->count();
    $m = fn ($v) => '৳' . number_format((float) $v);
    $call = fn ($phone) => $phone ? '<a class="md-wa" style="color:#0284c7" href="tel:' . e($phone) . '" title="Call ' . e($phone) . '"><i class="fas fa-phone"></i></a>' : '';
@endphp

@if ($failed)<div class="td-err">{{ $failed }} block(s) could not be loaded and are left out.</div>@endif

<div class="td-charts">
    @if ($ok('new_vs_returning'))
        @php $n = $w['new_vs_returning']; @endphp
        <div class="td-card"><h3><i class="fas fa-user-friends"></i>New and returning buyers<a class="td-more" href="{{ url('admin/customers') }}" id="td-c-report-customers">Customers</a></h3>
            <div class="td-note">{{ $label }} · repeat rate {{ $n['repeat_rate'] }}% of {{ $n['total'] }} buyer(s)</div>
            @if ($n['total'] === 0)<div class="td-empty">No buyers in this period.</div>@else
            <div class="td-chart-box" style="height:200px"><canvas class="td-cv" data-cv="{{ json_encode(['kind' => 'doughnut', 'fmt' => 'num', 'labels' => ['New', 'Returning'], 'sets' => [['label' => 'Buyers', 'data' => [$n['new_count'], $n['ret_count']]]]]) }}"></canvas></div>
            <div class="td-row"><span class="td-name">New</span><b>{{ $n['new_count'] }}</b><small>{{ $m($n['new_revenue']) }}</small></div>
            <div class="td-row"><span class="td-name">Returning</span><b>{{ $n['ret_count'] }}</b><small>{{ $m($n['ret_revenue']) }}</small></div>@endif</div>
    @endif

    @if ($ok('customer_rfm'))
        @php $r = $w['customer_rfm']; @endphp
        <div class="td-card"><h3><i class="fas fa-layer-group"></i>Patient segments<a class="td-more" href="{{ $legacy }}#w-audience_builder" id="td-c-audience">Audience builder</a></h3>
            <div class="td-note">{{ number_format($r['customers']) }} patient(s) by value and recency, all time</div>
            @foreach ($r['segments'] as $s)
                <div class="td-row" title="{{ $s['hint'] }}"><span class="td-name"><i class="{{ $s['icon'] }}" style="color:{{ $s['color'] }};width:16px"></i> {{ $s['label'] }}</span><span class="td-meter"><i style="width:{{ $s['share'] }}%;background:{{ $s['color'] }}"></i></span><b>{{ number_format($s['count']) }}</b><small>{{ $m($s['revenue']) }}</small></div>
            @endforeach</div>
    @endif

    @if ($ok('dormant_customers'))
        @php $d = $w['dormant_customers']; @endphp
        <div class="td-card"><h3><i class="fas fa-bed"></i>Dormant patients<a class="td-more" href="{{ $legacy }}#w-audience_builder" id="td-c-dormant">Send an offer</a></h3>
            <div class="td-note">{{ $d['count_6m'] }} quiet for 6+ months ({{ $d['count_12m'] }} for 12+) · {{ $m($d['value']) }} lifetime spend</div>
            @forelse (array_slice($d['list'], 0, 8) as $p)
                <div class="td-row"><a class="td-name" href="{{ url('admin/customers/' . $p['id']) }}">{{ $p['name'] }}</a><small>{{ $p['days'] }}d ago</small><b>{{ $m($p['spend']) }}</b>{!! $call($p['phone']) !!}</div>
            @empty<div class="td-empty">Nobody has gone quiet.</div>@endforelse</div>
    @endif

    @if ($ok('upgrade_due'))
        @php $u = $w['upgrade_due']; @endphp
        <div class="td-card"><h3><i class="fas fa-sync-alt"></i>Device upgrade due</h3>
            <div class="td-note">{{ $u['due_count'] }} due now · {{ $u['upcoming_count'] }} coming up · {{ $u['owners'] }} device owners</div>
            @forelse (array_slice($u['list'], 0, 8) as $p)
                <div class="td-row"><a class="td-name" href="{{ url('admin/customers/' . $p['customer_id']) }}">{{ $p['name'] }}<span class="td-sub">{{ $p['model'] }}</span></a><small>{{ $p['age_label'] }}</small>{!! $call($p['phone']) !!}</div>
            @empty<div class="td-empty">No upgrades due.</div>@endforelse</div>
    @endif

    @if ($ok('binaural_opportunity'))
        @php $b = $w['binaural_opportunity']; @endphp
        <div class="td-card"><h3><i class="fas fa-assistive-listening-systems"></i>Second-ear chances</h3>
            <div class="td-note">{{ $b['count'] }} single-device owners of {{ $b['owners'] }} ({{ $b['share'] }}%)</div>
            @forelse (array_slice($b['list'], 0, 8) as $p)
                <div class="td-row"><a class="td-name" href="{{ url('admin/customers/' . $p['customer_id']) }}">{{ $p['name'] }}<span class="td-sub">{{ $p['model'] }}</span></a><small>{{ $p['date_label'] ?? '' }}</small>{!! $call($p['phone']) !!}</div>
            @empty<div class="td-empty">No single-device owners.</div>@endforelse</div>
    @endif

    @if ($ok('membership_breakdown'))
        @php $mb = $w['membership_breakdown']; @endphp
        <div class="td-card"><h3><i class="fas fa-id-card"></i>Membership tiers<a class="td-more" href="{{ $report('admin/report/membership-loyalty') }}" id="td-c-report-members">Membership report</a></h3>
            <div class="td-note">{{ number_format($mb['members']) }} patient(s) · {{ $mb['upgrades_month'] }} upgrade(s) this month</div>
            @if (!$mb['members'])<div class="td-empty">No members yet.</div>@else
            <div class="td-chart-box" style="height:200px"><canvas class="td-cv" data-cv="{{ json_encode(['kind' => 'doughnut', 'fmt' => 'num', 'labels' => array_column($mb['tiers'], 'name'), 'sets' => [['label' => 'Patients', 'data' => array_column($mb['tiers'], 'customers'), 'colors' => array_column($mb['tiers'], 'color')]]]) }}"></canvas></div>@endif</div>
    @endif

    @if ($ok('points_liability'))
        @php $p = $w['points_liability']; @endphp
        <div class="td-card"><h3><i class="fas fa-coins"></i>Loyalty points<a class="td-more" href="{{ $report('admin/report/membership-loyalty') }}" id="td-c-report-points">Membership report</a></h3>
            <div class="td-note">{{ number_format($p['outstanding']) }} outstanding · {{ number_format($p['expiring_30']) }} expire within 30 days · {{ $p['redeem_rate'] }}% redeemed</div>
            @forelse ($p['top_holders'] as $h)
                <div class="td-row"><a class="td-name" href="{{ url('admin/customers/' . $h['id']) }}">{{ $h['name'] }}</a><b>{{ number_format($h['points']) }} pts</b>{!! $call($h['phone']) !!}</div>
            @empty<div class="td-empty">No points held.</div>@endforelse</div>
    @endif

    @if ($ok('service_due'))
        @php $s = $w['service_due']; @endphp
        <div class="td-card"><h3><i class="fas fa-tools"></i>Service and cleaning due<a class="td-more" href="{{ $report('admin/report/warranty-expiry') }}" id="td-c-report-warranty">Warranty expiry</a></h3>
            <div class="td-note">{{ $s['overdue'] }} overdue · {{ $s['due'] }} due in the next 30 days</div>
            @forelse (array_slice($s['list'], 0, 8) as $p)
                <div class="td-row"><a class="td-name" href="{{ url('admin/customers/' . $p['customer_id']) }}">{{ $p['name'] }}<span class="td-sub">{{ $p['model'] }} · {{ $p['due_label'] }}</span></a><span class="md-badge {{ $p['stage'] === 'overdue' ? 'danger' : 'warning' }}">{{ $p['stage'] === 'overdue' ? abs($p['days']) . 'd late' : 'in ' . $p['days'] . 'd' }}</span>{!! $call($p['phone']) !!}</div>
            @empty<div class="td-empty">No service due.</div>@endforelse</div>
    @endif
</div>
