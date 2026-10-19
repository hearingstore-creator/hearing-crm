{{-- Pipeline tab. $w = widget results, $label = period label, $legacy = old dashboard URL --}}
@php
    $ok = fn ($k) => isset($w[$k]) && empty($w[$k]['error']);
    $failed = collect($w)->filter(fn ($v) => is_array($v) && !empty($v['error']))->count();
    $m = fn ($v) => '৳' . number_format((float) $v);
@endphp

@if ($failed)<div class="td-err">{{ $failed }} block(s) could not be loaded and are left out.</div>@endif
<div class="td-note" style="margin-bottom:12px">Leads, appointments and doctors follow your own data scope. The branch picker does not change them.</div>

<div class="td-charts">
    @if ($ok('test_to_sale_funnel'))
        @php $f = $w['test_to_sale_funnel']; @endphp
        <div class="td-card"><h3><i class="fas fa-filter"></i>Test to sale funnel<a class="td-more" href="{{ $report('admin/report/lead-funnel') }}" id="td-p-report-funnel">Lead funnel</a></h3>
            <div class="td-note">{{ $label }} · {{ $f['conversion'] }}% of booked tests end in a device sale{{ $f['avg_days'] !== null ? ' · about ' . $f['avg_days'] . ' days to buy' : '' }}</div>
            @foreach ($f['stages'] as $s)
                <div class="td-row"><span class="td-name"><i class="{{ $s['icon'] }}" style="color:{{ $s['color'] }};width:18px"></i> {{ $s['label'] }}</span><span class="td-meter"><i style="width:{{ $s['pct'] }}%;background:{{ $s['color'] }}"></i></span><b>{{ $s['count'] }}</b><small>{{ $s['pct'] }}%</small></div>
            @endforeach</div>
    @endif

    @if ($ok('lead_response_time'))
        @php $l = $w['lead_response_time']; $b = $l['buckets']; @endphp
        <div class="td-card"><h3><i class="fas fa-stopwatch"></i>Lead response time<a class="td-more" href="{{ url('admin/deals') }}" id="td-p-deals">Deals board</a></h3>
            <div class="td-note">{{ $label }} · {{ $l['leads'] }} lead(s) · {{ $l['within_24h'] }}% answered within 24h{{ $l['median'] !== null ? ' · median ' . $l['median'] . 'h' : '' }}</div>
            @if (!$l['leads'])<div class="td-empty">No leads in this period.</div>@else
            <div class="td-chart-box" style="height:170px"><canvas class="td-cv" data-cv="{{ json_encode(['kind' => 'bar', 'fmt' => 'num', 'labels' => ['Under 1h', '1–24h', '1–3 days', 'Over 3 days', 'Not yet'], 'sets' => [['label' => 'Leads', 'data' => [$b['lt1h'], $b['lt24h'], $b['lt3d'], $b['gt3d'], $b['none']], 'colors' => ['#10b981', '#6366f1', '#f59e0b', '#e11d48', '#cbd5e1']]]]) }}"></canvas></div>
            @foreach ($l['staff'] as $s)
                <div class="td-row"><span class="td-name">{{ $s['name'] }}</span><small>{{ $s['contacted'] }}/{{ $s['leads'] }} contacted</small><b>{{ $s['avg_hours'] !== null ? $s['avg_hours'] . 'h' : '—' }}</b></div>
            @endforeach @endif</div>
    @endif

    @if ($ok('lost_reasons'))
        @php $r = $w['lost_reasons']; @endphp
        <div class="td-card"><h3><i class="fas fa-times-circle"></i>Why leads are lost<a class="td-more" href="{{ $report('admin/report/lead-funnel') }}" id="td-p-report-lost">Lead funnel</a></h3>
            <div class="td-note">{{ $label }} · {{ $r['total'] }} lost ({{ $r['lost_rate'] }}% of leads)</div>
            @forelse ($r['reasons'] as $x)
                <div class="td-row"><span class="td-name"><i class="{{ $x['icon'] }}" style="color:{{ $x['color'] }};width:16px"></i> {{ $x['label'] }}</span><span class="td-meter"><i style="width:{{ $x['count'] / $r['max'] * 100 }}%;background:{{ $x['color'] }}"></i></span><b>{{ $x['count'] }}</b><small>{{ $x['share'] }}%</small></div>
            @empty<div class="td-empty">No lost leads in this period.</div>@endforelse
            @if ($r['competitors'])<div class="td-note" style="margin-top:8px">Lost to: {{ collect($r['competitors'])->map(fn ($c) => $c['name'] . ' (' . $c['count'] . ')')->implode(', ') }}</div>@endif</div>
    @endif

    @if ($ok('source_roi'))
        @php $s = $w['source_roi']; @endphp
        <div class="td-card"><h3><i class="fas fa-bullseye"></i>Revenue by lead source<a class="td-more" href="{{ url('admin/leads') }}" id="td-p-leads">Leads</a></h3>
            <div class="td-note">All time: leads, conversion and what each source brought in</div>
            @forelse (array_slice($s['rows'], 0, 8) as $x)
                <div class="td-row"><span class="td-name">{{ $x['source'] }}<span class="td-sub">{{ $x['leads'] }} leads · {{ $x['conversion'] }}% won</span></span><span class="td-meter"><i style="width:{{ $x['revenue'] / $s['max'] * 100 }}%;background:#6366f1"></i></span><b>{{ $m($x['revenue']) }}</b></div>
            @empty<div class="td-empty">No lead sources yet.</div>@endforelse</div>
    @endif

    @if ($ok('top_doctors'))
        @php $d = $w['top_doctors']; @endphp
        <div class="td-card"><h3><i class="fas fa-user-md"></i>Top referring doctors<a class="td-more" href="{{ $report('admin/report/doctor-statement') }}" id="td-p-report-doctor">Doctor statement</a></h3>
            <div class="td-note">{{ $label }} · {{ $m($d['total_revenue']) }} referred revenue</div>
            @forelse (array_slice($d['list'], 0, 8) as $x)
                <div class="td-row"><span class="td-name">{{ $x['name'] }}<span class="td-sub">{{ $x['referrals'] }} referral(s) · last {{ $x['last'] }}</span></span><b>{{ $m($x['revenue']) }}</b>@if ($x['due'] > 0)<span class="md-badge warning">due {{ $m($x['due']) }}</span>@endif</div>
            @empty<div class="td-empty">No referrals in this period.</div>@endforelse</div>
    @endif

    @if ($ok('inactive_doctors'))
        @php $d = $w['inactive_doctors']; @endphp
        <div class="td-card"><h3><i class="fas fa-user-clock"></i>Doctors gone quiet</h3>
            <div class="td-note">{{ $d['inactive'] }} silent for 90+ days · {{ $d['active'] }} active · {{ $d['never'] }} never referred</div>
            @forelse (array_slice($d['list'], 0, 8) as $x)
                <div class="td-row"><span class="td-name">{{ $x['name'] }}<span class="td-sub">{{ $x['referrals'] }} referral(s) before · last {{ $x['last'] }}</span></span><small>{{ $x['days'] }}d</small>@if ($x['phone'])<a class="md-wa" style="color:#0284c7" href="tel:{{ $x['phone'] }}" title="Call"><i class="fas fa-phone"></i></a>@endif</div>
            @empty<div class="td-empty">Every referring doctor is active.</div>@endforelse</div>
    @endif

    @if ($ok('referral_trend'))
        @php $t = $w['referral_trend']; $palette = ['#6366f1', '#10b981', '#f59e0b', '#0ea5e9', '#e11d48']; @endphp
        <div class="td-card"><h3><i class="fas fa-chart-line"></i>Referral trend</h3>
            <div class="td-note">Referrals per month from the top doctors</div>
            @if (!$t['doctors'])<div class="td-empty">No referrals yet.</div>@else
            <div class="td-chart-box"><canvas class="td-cv" data-cv="{{ json_encode(['kind' => 'line', 'fmt' => 'num', 'labels' => $t['months'], 'sets' => collect($t['doctors'])->take(5)->values()->map(fn ($x, $i) => ['label' => $x['name'], 'data' => $x['counts'], 'color' => $palette[$i % 5]])->all()]) }}"></canvas></div>@endif</div>
    @endif

    @if ($ok('commission_due'))
        @php $c = $w['commission_due']; @endphp
        <div class="td-card"><h3><i class="fas fa-percent"></i>Doctor commission due<a class="td-more" href="{{ $report('admin/report/doctor-statement') }}" id="td-p-report-commission">Doctor statement</a></h3>
            <div class="td-note">{{ $m($c['due']) }} owed to {{ $c['doctors_due'] }} doctor(s) · earned {{ $m($c['earned']) }}, paid {{ $m($c['paid']) }}</div>
            @forelse ($c['list'] as $x)
                <div class="td-row"><span class="td-name">{{ $x['name'] }}<span class="td-sub">{{ $x['rule'] }} · last paid {{ $x['last_paid'] }}</span></span><b>{{ $m($x['due']) }}</b></div>
            @empty<div class="td-empty">No commission due.</div>@endforelse</div>
    @endif
</div>
