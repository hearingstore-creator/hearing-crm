{{-- Pipeline tab. $w = widget results, $label = period label, $legacy = old dashboard URL --}}
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
<div class="td-note" style="margin-bottom:14px">Leads, appointments and doctors follow your own data scope. The branch picker does not change them.</div>

<div class="td-charts">
    @if ($ok('test_to_sale_funnel'))
        @php $f = $w['test_to_sale_funnel']; @endphp
        <div class="td-card" style="grid-column:1/-1;">
            <h3>
                <span class="t-halo" style="width:28px; height:28px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; background:#eef2ff; color:#4f46e5; font-size:13px;"><i class="fas fa-route"></i></span>
                Patient Hearing Journey & Test-to-Sale Funnel
                <a class="td-more" href="{{ $report('admin/report/lead-funnel') }}" id="td-p-report-funnel">Lead funnel report &rarr;</a>
            </h3>
            <div class="td-note">{{ $label }} · {{ $f['conversion'] }}% of booked tests end in a hearing aid fitting & sale{{ $f['avg_days'] !== null ? ' · avg ' . $f['avg_days'] . ' days to decision' : '' }}</div>

            {{-- Visual Journey Step Cards --}}
            <div class="td-journey-strip">
                @foreach ($f['stages'] as $stepIdx => $s)
                    <div class="td-journey-step">
                        <span class="js-num">STEP {{ $stepIdx + 1 }}</span>
                        <div class="js-halo" style="background:{{ $s['color'] }}18; color:{{ $s['color'] }};">
                            <i class="{{ $s['icon'] }}"></i>
                        </div>
                        <div class="js-count">{{ number_format($s['count']) }}</div>
                        <div class="js-name">{{ $s['label'] }}</div>
                        <span class="js-pct">{{ $s['pct'] }}%</span>
                    </div>
                @endforeach
            </div>

            {{-- Detailed Progress Rows --}}
            @foreach ($f['stages'] as $s)
                <div class="td-row">
                    <span class="td-name"><i class="{{ $s['icon'] }}" style="color:{{ $s['color'] }};width:18px"></i> {{ $s['label'] }}</span>
                    <span class="td-meter"><i style="width:{{ $s['pct'] }}%;background:{{ $s['color'] }}"></i></span>
                    <b>{{ number_format($s['count']) }}</b>
                    <small>{{ $s['pct'] }}%</small>
                </div>
            @endforeach
        </div>
    @endif

    @if ($ok('lead_response_time'))
        @php $l = $w['lead_response_time']; $b = $l['buckets']; @endphp
        <div class="td-card">
            <h3>
                <span class="t-halo" style="width:28px; height:28px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; background:#ecfdf5; color:#059669; font-size:13px;"><i class="fas fa-stopwatch"></i></span>
                Lead response time
                <a class="td-more" href="{{ url('admin/deals') }}" id="td-p-deals">Deals board</a>
            </h3>
            <div class="td-note">{{ $label }} · {{ $l['leads'] }} lead(s) · {{ $l['within_24h'] }}% answered within 24h{{ $l['median'] !== null ? ' · median ' . $l['median'] . 'h' : '' }}</div>
            @if (!$l['leads'])
                <div class="td-empty">No leads in this period.</div>
            @else
                <div class="td-chart-box" style="height:170px"><canvas class="td-cv" data-cv="{{ json_encode(['kind' => 'bar', 'fmt' => 'num', 'labels' => ['Under 1h', '1–24h', '1–3 days', 'Over 3 days', 'Not yet'], 'sets' => [['label' => 'Leads', 'data' => [$b['lt1h'], $b['lt24h'], $b['lt3d'], $b['gt3d'], $b['none']], 'colors' => ['#059669', '#4f46e5', '#d97706', '#e11d48', '#cbd5e1']]]]) }}"></canvas></div>
                @foreach ($l['staff'] as $s)
                    <div class="td-row">
                        <span class="td-name">{{ $s['name'] }}</span>
                        <small>{{ $s['contacted'] }}/{{ $s['leads'] }} contacted</small>
                        <b>{{ $s['avg_hours'] !== null ? $s['avg_hours'] . 'h' : '—' }}</b>
                    </div>
                @endforeach
            @endif
        </div>
    @endif

    @if ($ok('lost_reasons'))
        @php $r = $w['lost_reasons']; @endphp
        <div class="td-card">
            <h3>
                <span class="t-halo" style="width:28px; height:28px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; background:#fff1f2; color:#e11d48; font-size:13px;"><i class="fas fa-times-circle"></i></span>
                Why leads are lost
                <a class="td-more" href="{{ $report('admin/report/lead-funnel') }}" id="td-p-report-lost">Lead funnel</a>
            </h3>
            <div class="td-note">{{ $label }} · {{ $r['total'] }} lost ({{ $r['lost_rate'] }}% of leads)</div>
            @forelse ($r['reasons'] as $x)
                <div class="td-row">
                    <span class="td-name"><i class="{{ $x['icon'] }}" style="color:{{ $x['color'] }};width:16px"></i> {{ $x['label'] }}</span>
                    <span class="td-meter"><i style="width:{{ $x['count'] / $r['max'] * 100 }}%;background:{{ $x['color'] }}"></i></span>
                    <b>{{ $x['count'] }}</b>
                    <small>{{ $x['share'] }}%</small>
                </div>
            @empty
                <div class="td-empty">No lost leads in this period.</div>
            @endforelse
            @if ($r['competitors'])
                <div class="td-note" style="margin-top:8px">Lost to: {{ collect($r['competitors'])->map(fn ($c) => $c['name'] . ' (' . $c['count'] . ')')->implode(', ') }}</div>
            @endif
        </div>
    @endif

    @if ($ok('source_roi'))
        @php $s = $w['source_roi']; @endphp
        <div class="td-card">
            <h3>
                <span class="t-halo" style="width:28px; height:28px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; background:#fffbeb; color:#d97706; font-size:13px;"><i class="fas fa-bullseye"></i></span>
                Revenue by lead source
                <a class="td-more" href="{{ url('admin/leads') }}" id="td-p-leads">Leads</a>
            </h3>
            <div class="td-note">All time: leads, conversion and what each source brought in</div>
            @forelse (array_slice($s['rows'], 0, 8) as $x)
                <div class="td-row">
                    <span class="td-name">
                        {{ $x['source'] }}
                        <span class="td-sub">{{ $x['leads'] }} leads · {{ $x['conversion'] }}% won</span>
                    </span>
                    <span class="td-meter"><i style="width:{{ $x['revenue'] / $s['max'] * 100 }}%;background:#4f46e5"></i></span>
                    <b>{{ $m($x['revenue']) }}</b>
                </div>
            @empty
                <div class="td-empty">No lead sources yet.</div>
            @endforelse
        </div>
    @endif

    @if ($ok('top_doctors'))
        @php $d = $w['top_doctors']; @endphp
        <div class="td-card">
            <h3>
                <span class="t-halo" style="width:28px; height:28px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; background:#ecfeff; color:#0891b2; font-size:13px;"><i class="fas fa-user-md"></i></span>
                Top referring doctors
                <a class="td-more" href="{{ $report('admin/report/doctor-statement') }}" id="td-p-report-doctor">Doctor statement</a>
            </h3>
            <div class="td-note">{{ $label }} · {{ $m($d['total_revenue']) }} referred revenue</div>
            @forelse (array_slice($d['list'], 0, 8) as $x)
                <div class="td-row">
                    <span class="td-name">
                        {{ $x['name'] }}
                        <span class="td-sub">{{ $x['referrals'] }} referral(s) · last {{ $x['last'] }}</span>
                    </span>
                    <b>{{ $m($x['revenue']) }}</b>
                    @if ($x['due'] > 0)
                        <span class="md-badge warning">due {{ $m($x['due']) }}</span>
                    @endif
                </div>
            @empty
                <div class="td-empty">No referrals in this period.</div>
            @endforelse
        </div>
    @endif

    @if ($ok('inactive_doctors'))
        @php $d = $w['inactive_doctors']; @endphp
        <div class="td-card">
            <h3>
                <span class="t-halo" style="width:28px; height:28px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; background:#f1f5f9; color:#475569; font-size:13px;"><i class="fas fa-user-clock"></i></span>
                Doctors gone quiet
            </h3>
            <div class="td-note">{{ $d['inactive'] }} silent for 90+ days · {{ $d['active'] }} active · {{ $d['never'] }} never referred</div>
            @forelse (array_slice($d['list'], 0, 8) as $x)
                @php
                    $waMsg = "Assalamu Alaikum Dr. " . ($x['name'] ?? '') . ". Warm greetings from Zenhear Hearing Care Centre. We would love to share our updated patient audiology reports and service catalog with your clinic.";
                @endphp
                <div class="td-row">
                    <span class="td-name">
                        {{ $x['name'] }}
                        <span class="td-sub">{{ $x['referrals'] }} referral(s) before · last {{ $x['last'] }}</span>
                    </span>
                    <small>{{ $x['days'] }}d ago</small>
                    {!! $actionCluster($x['phone'] ?? null, $x['name'], $waMsg) !!}
                </div>
            @empty
                <div class="td-empty">Every referring doctor is active.</div>
            @endforelse
        </div>
    @endif

    @if ($ok('referral_trend'))
        @php $t = $w['referral_trend']; $palette = ['#4f46e5', '#059669', '#d97706', '#0891b2', '#7c3aed']; @endphp
        <div class="td-card">
            <h3>
                <span class="t-halo" style="width:28px; height:28px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; background:#f5f3ff; color:#7c3aed; font-size:13px;"><i class="fas fa-chart-line"></i></span>
                Referral trend
            </h3>
            <div class="td-note">Referrals per month from the top doctors</div>
            @if (!$t['doctors'])
                <div class="td-empty">No referrals yet.</div>
            @else
                <div class="td-chart-box"><canvas class="td-cv" data-cv="{{ json_encode(['kind' => 'line', 'fmt' => 'num', 'labels' => $t['months'], 'sets' => collect($t['doctors'])->take(5)->values()->map(fn ($x, $i) => ['label' => $x['name'], 'data' => $x['counts'], 'color' => $palette[$i % 5]])->all()]) }}"></canvas></div>
            @endif
        </div>
    @endif

    @if ($ok('commission_due'))
        @php $c = $w['commission_due']; @endphp
        <div class="td-card">
            <h3>
                <span class="t-halo" style="width:28px; height:28px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; background:#ecfdf5; color:#059669; font-size:13px;"><i class="fas fa-percent"></i></span>
                Doctor commission due
                <a class="td-more" href="{{ $report('admin/report/doctor-statement') }}" id="td-p-report-commission">Doctor statement</a>
            </h3>
            <div class="td-note">{{ $m($c['due']) }} owed to {{ $c['doctors_due'] }} doctor(s) · earned {{ $m($c['earned']) }}, paid {{ $m($c['paid']) }}</div>
            @forelse ($c['list'] as $x)
                <div class="td-row">
                    <span class="td-name">
                        {{ $x['name'] }}
                        <span class="td-sub">{{ $x['rule'] }} · last paid {{ $x['last_paid'] }}</span>
                    </span>
                    <b>{{ $m($x['due']) }}</b>
                </div>
            @empty
                <div class="td-empty">No commission due.</div>
            @endforelse
        </div>
    @endif
</div>
