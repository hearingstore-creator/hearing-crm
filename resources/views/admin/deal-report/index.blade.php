@extends('admin.layouts.master')
@section('page_title', 'Deal Reports')
@section('breadcrumb_parent', 'Leads')
@section('breadcrumb_parent_url', url('admin/deals'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/kpi-cards.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/deal-board.css') }}?v={{ filemtime(public_path('assets/css/deal-board.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/deal-reports.css') }}">
@endpush

@php
    $money = fn ($v) => '৳' . number_format((float) $v);
    $groups = ['campaign' => 'Campaign', 'source' => 'Source', 'branch' => 'Branch', 'owner' => 'Owner'];
    $groupUrl = fn ($g) => url('admin/deal-reports') . '?' . http_build_query(array_filter(array_merge(request()->only(['from', 'to', 'branch']), ['group' => $g])));
    $maxDays = max(1, collect($timeInStage)->max('days') ?? 1);
    $maxLost = max(1, $lostReasons->max() ?? 1);
    $totalLost = max(1, $lostReasons->sum());
    $forecastTotal = collect($forecast)->sum('weighted');
@endphp

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-chart-line" style="color: var(--accent-500); margin-right: 8px;"></i>Deal Reports</h1>
            <p>Deals created {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</p>
        </div>
        <form method="GET" class="dr-filters" id="dr-filters">
            <input type="hidden" name="group" value="{{ $group }}">
            <input type="date" name="from" id="dr-from" class="form-control" value="{{ $from->toDateString() }}" aria-label="From">
            <input type="date" name="to" id="dr-to" class="form-control" value="{{ $to->toDateString() }}" aria-label="To">
            @if ($isHq)
                <select name="branch" id="dr-branch" class="form-control" aria-label="Branch">
                    <option value="">All branches</option>
                    <option value="hq" @selected(request('branch') === 'hq')>Head Office</option>
                    @foreach ($branches as $bid => $bname)<option value="{{ $bid }}" @selected(request('branch') == $bid)>{{ $bname }}</option>@endforeach
                </select>
            @endif
            <button type="submit" class="btn btn-primary" id="dr-apply"><i class="fas fa-filter mr-1"></i>Apply</button>
        </form>
    </div>

    <div class="kpi-row crm-animate-in delay-1">
        @include('admin.partials.kpi-card', ['id' => 'dr-kpi-deals', 'color' => 'indigo', 'icon' => 'fas fa-layer-group',
            'label' => 'New deals', 'value' => number_format($summary['deals']), 'sub' => 'created in the period'])
        @include('admin.partials.kpi-card', ['id' => 'dr-kpi-won', 'color' => 'green', 'icon' => 'fas fa-trophy',
            'label' => 'Won', 'value' => number_format($summary['won']), 'sub' => $money($summary['wonValue']) . ' value'])
        @include('admin.partials.kpi-card', ['id' => 'dr-kpi-rate', 'color' => 'violet', 'icon' => 'fas fa-percentage',
            'label' => 'Win rate', 'value' => $summary['winRate'] !== null ? $summary['winRate'] . '%' : '—',
            'sub' => $summary['daysToWin'] !== null ? 'avg ' . $summary['daysToWin'] . ' days to win' : 'of closed deals'])
        @include('admin.partials.kpi-card', ['id' => 'dr-kpi-speed', 'color' => $summary['speed'] && $summary['speed']['onTime'] < 70 ? 'rose' : 'slate', 'icon' => 'fas fa-stopwatch',
            'label' => 'Called on time', 'value' => $summary['speed'] ? $summary['speed']['onTime'] . '%' : '—',
            'sub' => $summary['speed'] ? 'by the office-hours deadline · median ' . $summary['speed']['median'] . ' min' : 'first contact vs deadline'])
    </div>

    <div class="dr-grid crm-animate-in delay-2">
        <section class="crm-table-card dr-card" aria-labelledby="dr-funnel-h">
            <h2 class="dr-h" id="dr-funnel-h"><i class="fas fa-filter"></i> Funnel</h2>
            <p class="dr-sub">Deals that reached each stage</p>
            @foreach ($funnel as $i => $row)
                <div class="dr-bar-row">
                    <span class="dr-bar-label">{{ $row['name'] }}</span>
                    <div class="dr-bar"><span style="width: {{ max(2, $row['pct']) }}%; background: {{ $row['color'] }};"></span></div>
                    <b class="dr-bar-val">{{ $row['count'] }}</b>
                    <span class="dr-bar-pct">{{ $row['pct'] }}%</span>
                </div>
            @endforeach
        </section>

        <section class="crm-table-card dr-card" aria-labelledby="dr-time-h">
            <h2 class="dr-h" id="dr-time-h"><i class="fas fa-hourglass-half"></i> Time in stage</h2>
            <p class="dr-sub">Average days before moving on</p>
            @foreach ($timeInStage as $row)
                <div class="dr-bar-row">
                    <span class="dr-bar-label">{{ $row['name'] }}</span>
                    <div class="dr-bar"><span style="width: {{ $row['days'] !== null ? max(2, $row['days'] / $maxDays * 100) : 0 }}%; background: {{ $row['color'] }};"></span></div>
                    <b class="dr-bar-val">{{ $row['days'] !== null ? $row['days'] . 'd' : '—' }}</b>
                    <span class="dr-bar-pct">{{ $row['n'] }} moves</span>
                </div>
            @endforeach
        </section>

        <section class="crm-table-card dr-card" aria-labelledby="dr-lost-h">
            <h2 class="dr-h" id="dr-lost-h"><i class="fas fa-times-circle"></i> Why deals are lost</h2>
            <p class="dr-sub">{{ $lostReasons->sum() }} lost deals</p>
            @forelse ($lostReasons as $name => $n)
                <div class="dr-bar-row">
                    <span class="dr-bar-label">{{ $name }}</span>
                    <div class="dr-bar"><span style="width: {{ max(2, $n / $maxLost * 100) }}%; background: var(--danger-500);"></span></div>
                    <b class="dr-bar-val">{{ $n }}</b>
                    <span class="dr-bar-pct">{{ round($n / $totalLost * 100) }}%</span>
                </div>
            @empty
                <div class="dr-empty">No lost deals in this period.</div>
            @endforelse
        </section>

        <section class="crm-table-card dr-card dr-wide" aria-labelledby="dr-forecast-h">
            <div class="dr-forecast-head">
                <div>
                    <h2 class="dr-h" id="dr-forecast-h"><i class="fas fa-balance-scale"></i> Weighted forecast</h2>
                    <p class="dr-sub">All open deals right now · deal value × stage win probability</p>
                </div>
                <div class="dr-forecast-total">
                    <span>Expected revenue</span>
                    <b>{{ $money($forecastTotal) }}</b>
                    <small>of {{ $money(collect($forecast)->sum('value')) }} in {{ collect($forecast)->sum('count') }} open deals</small>
                </div>
            </div>
            <div class="dr-forecast">
                @foreach ($forecast as $i => $row)
                    <div class="dr-fc" id="dr-fc-{{ $i }}" style="--c: {{ $row['color'] }};">
                        <div class="dr-fc-top"><span class="dr-dot" style="background: {{ $row['color'] }};"></span>{{ $row['name'] }}<em>{{ $row['probability'] }}%</em></div>
                        <b class="dr-fc-weighted">{{ $money($row['weighted']) }}</b>
                        <div class="dr-fc-bar"><span style="width: {{ $forecastTotal > 0 ? max(2, $row['weighted'] / $forecastTotal * 100) : 0 }}%;"></span></div>
                        <div class="dr-fc-meta"><span>{{ $row['count'] }} deal{{ $row['count'] === 1 ? '' : 's' }}</span><span>{{ $money($row['value']) }} value</span></div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="crm-table-card dr-card" aria-labelledby="dr-members-h">
            <h2 class="dr-h" id="dr-members-h"><i class="fas fa-crown"></i> Members vs non-members</h2>
            <p class="dr-sub">Member = already a customer when the deal started</p>
            <div class="dr-compare">
                @foreach (['Members', 'Non-members'] as $label)
                    @php $m = $members[$label] ?? null; @endphp
                    <div class="dr-compare-col">
                        <span>{{ $label }}</span>
                        <b>{{ $m && $m['winRate'] !== null ? $m['winRate'] . '%' : '—' }}</b>
                        <em>win rate</em>
                        <small>{{ $m['deals'] ?? 0 }} deals · avg won {{ $m && $m['avgWon'] ? $money($m['avgWon']) : '—' }}</small>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="crm-table-card dr-card" aria-labelledby="dr-response-h" id="dr-response">
            <h2 class="dr-h" id="dr-response-h"><i class="fas fa-stopwatch"></i> Response time by staff</h2>
            <p class="dr-sub">First contact vs the call deadline (office hours, holidays skipped)</p>
            @if ($summary['speed'])
                <table class="table table-sm dr-table">
                    <thead><tr><th>Staff</th><th class="text-right">Deals</th><th class="text-right">Median</th><th class="text-right">On time</th></tr></thead>
                    <tbody>
                        @foreach ($summary['speed']['byOwner'] as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td class="text-right">{{ $row['n'] }}</td>
                                <td class="text-right">{{ $row['median'] < 120 ? $row['median'] . ' min' : round($row['median'] / 60, 1) . ' h' }}</td>
                                <td class="text-right"><b style="color: {{ $row['onTime'] >= 80 ? '#059669' : ($row['onTime'] >= 50 ? '#d97706' : '#dc2626') }};">{{ $row['onTime'] }}%</b></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="dr-sub">No contacted deals in this period yet.</div>
            @endif
        </section>
    </div>

    <div class="crm-table-card crm-animate-in delay-3">
        <div class="crm-table-header">
            <div class="table-title"><i class="fas fa-bullhorn"></i> Performance by {{ strtolower($groups[$group]) }}</div>
            <div class="deal-views" role="tablist" aria-label="Group by">
                @foreach ($groups as $g => $label)
                    <a href="{{ $groupUrl($g) }}" id="dr-group-{{ $g }}" class="{{ $group === $g ? 'active' : '' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
        <div style="overflow-x: auto;">
            <table class="crm-table">
                <thead><tr><th>{{ $groups[$group] }}</th><th>Deals</th><th>Open</th><th>Won</th><th>Lost</th><th>Win rate</th><th>Won value</th><th>Open value</th></tr></thead>
                <tbody>
                    @forelse ($breakdown as $row)
                        <tr>
                            <td><b>{{ $row['name'] }}</b></td><td>{{ $row['deals'] }}</td><td>{{ $row['open'] }}</td><td>{{ $row['won'] }}</td><td>{{ $row['lost'] }}</td>
                            <td>@if ($row['winRate'] !== null)<span class="dr-rate" style="--r: {{ $row['winRate'] }}%;">{{ $row['winRate'] }}%</span>@else — @endif</td>
                            <td><b>{{ $money($row['wonValue']) }}</b></td><td>{{ $money($row['openValue']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="dr-empty">No deals in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="dr-sub" style="padding: 10px 18px 14px;">Spend, cost per lead and return per campaign: Marketing → Marketing Return.</p>
    </div>

    <div class="crm-table-card crm-animate-in delay-3" style="margin-top: 16px;" id="dr-feedback">
        <div class="crm-table-header">
            <div class="table-title"><i class="fas fa-smile"></i> Patient feedback (NPS) · {{ $feedback['answers'] }} answers of {{ $feedback['sent'] }} surveys sent</div>
        </div>
        <div style="display:grid; grid-template-columns: minmax(0, 1fr) minmax(0, 2fr); gap: 16px; padding: 0 18px 14px;">
            <div>
                <div style="font-size: 30px; font-weight: 800; color: {{ ($feedback['nps'] ?? 0) >= 30 ? '#059669' : (($feedback['nps'] ?? 0) >= 0 ? '#d97706' : '#dc2626') }};">{{ $feedback['nps'] !== null ? $feedback['nps'] : '—' }}</div>
                <div class="dr-sub">NPS = % who give 9–10 minus % who give 0–6 (from −100 to 100)</div>
                <div style="font-size:13px; margin-top:6px;">{{ $feedback['promoters'] }} happy · {{ $feedback['passives'] }} neutral · {{ $feedback['detractors'] }} unhappy</div>
            </div>
            <div>
                @forelse ($feedback['comments'] as $c)
                    <div style="font-size:13px; padding:4px 0; border-bottom:1px solid var(--gray-100);"><b>{{ $c['score'] }}/10</b> · {{ $c['comment'] }} <span class="dr-sub">{{ $c['date'] }}</span></div>
                @empty
                    <div class="dr-sub">No comments yet. Switch on "Satisfaction Survey" in SMS Center → Automation to start asking.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="crm-table-card crm-animate-in delay-3" style="margin-top: 16px;" id="dr-trials">
        <div class="crm-table-header">
            <div class="table-title"><i class="fas fa-assistive-listening-systems"></i> Trials · {{ $trials['total'] }} patients tried a device, {{ $trials['won'] }} bought ({{ $trials['total'] ? round($trials['won'] / $trials['total'] * 100) : 0 }}%)</div>
        </div>
        <div style="display:grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: 16px; padding: 0 18px 14px;">
            <table class="crm-table">
                <thead><tr><th>Model tried</th><th class="text-right">Patients</th><th class="text-right">Bought</th><th class="text-right">Trial → sale</th><th class="text-right">Take-home</th></tr></thead>
                <tbody>
                    @forelse ($trials['byModel'] as $m)
                        <tr><td>{{ $m['model'] }}</td><td class="text-right">{{ $m['tried'] }}</td><td class="text-right">{{ $m['won'] }}</td><td class="text-right">{{ $m['rate'] !== null ? $m['rate'] . '%' : '—' }}</td><td class="text-right">{{ $m['takeHome'] ?: '' }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="dr-empty">No trials recorded in this period. Pick the model and say how it went when a deal moves to Trial.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div>
                <div class="dr-sub" style="margin: 8px 0;">How the trials went</div>
                @forelse ($trials['results'] as $key => $n)
                    <div style="display:flex; justify-content:space-between; font-size:13px; padding:3px 0; border-bottom:1px solid var(--gray-100);"><span>{{ \App\Models\DealTrial::RESULTS[$key] ?? $key }}</span><b>{{ $n }}</b></div>
                @empty
                    <div class="dr-sub">No results recorded yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
