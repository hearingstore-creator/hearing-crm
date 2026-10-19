{{-- Branches tab (owner view). $w = widget results, $label = period label, $legacy = old dashboard URL --}}
@php
    $ok = fn ($k) => isset($w[$k]) && empty($w[$k]['error']) && empty($w[$k]['restricted']);
    $restricted = collect($w)->contains(fn ($v) => is_array($v) && !empty($v['restricted']));
    $failed = collect($w)->filter(fn ($v) => is_array($v) && !empty($v['error']))->count();
    $m = fn ($v) => '৳' . number_format((float) $v);
@endphp

@if ($failed)<div class="td-err">{{ $failed }} block(s) could not be loaded and are left out.</div>@endif
@if ($restricted)<div class="td-note" style="margin-bottom:12px">Some branch comparisons are only for company-wide administrators.</div>@endif
<div class="td-note" style="margin-bottom:12px">This tab compares all branches and ignores the branch picker.</div>

<div class="td-charts">
    @if ($ok('branch_leaderboard'))
        @php $b = $w['branch_leaderboard']; @endphp
        <div class="td-card" style="grid-column:1/-1"><h3><i class="fas fa-trophy"></i>Branch league table<a class="td-more" href="{{ url('admin/report/vendor-monthly') }}" id="td-b-report-monthly">Branch monthly</a></h3>
            <div class="td-note">This month against last month</div>
            <div style="overflow-x:auto"><table class="td-table">
                <thead><tr><th>Branch</th><th>This month</th><th></th><th>Last month</th><th>Growth</th><th>Invoices</th><th>Leads</th><th>Won</th></tr></thead>
                <tbody>@foreach ($b['rows'] as $r)
                    <tr><td>{{ $r['name'] }}</td><td><b>{{ $m($r['this_month']) }}</b></td>
                        <td style="min-width:120px"><span class="td-meter"><i style="width:{{ $r['this_month'] / $b['max'] * 100 }}%;background:#6366f1"></i></span></td>
                        <td>{{ $m($r['last_month']) }}</td>
                        <td class="{{ ($r['growth'] ?? 0) >= 0 ? 'up' : 'down' }}">{{ $r['growth'] === null ? '—' : (($r['growth'] >= 0 ? '▲ ' : '▼ ') . abs($r['growth']) . '%') }}</td>
                        <td>{{ $r['invoices'] }}</td><td>{{ $r['leads'] }}</td><td>{{ $r['conversion'] }}%</td></tr>
                @endforeach</tbody></table></div></div>
    @endif

    @if ($ok('branch_stock'))
        @php $s = $w['branch_stock']; @endphp
        <div class="td-card"><h3><i class="fas fa-boxes"></i>Stock value by branch<a class="td-more" href="{{ url('admin/report/stock') }}" id="td-b-report-stock">Stock report</a></h3>
            <div class="td-note">{{ $m($s['total']) }} in stock at cost</div>
            @foreach ($s['rows'] as $r)
                <div class="td-row"><span class="td-name">{{ $r['name'] }}<span class="td-sub">{{ number_format($r['units']) }} units · {{ $r['products'] }} products</span></span><span class="td-meter"><i style="width:{{ $r['value'] / $s['max'] * 100 }}%;background:#f59e0b"></i></span><b>{{ $m($r['value']) }}</b></div>
            @endforeach</div>
    @endif

    @if ($ok('dead_stock'))
        @php $d = $w['dead_stock']; @endphp
        <div class="td-card"><h3><i class="fas fa-box-open"></i>Dead and slow stock<a class="td-more" href="{{ url('admin/report/stock') }}" id="td-b-report-dead">Stock report</a></h3>
            <div class="td-note">{{ $d['count'] }} product(s), {{ number_format($d['units']) }} units, {{ $m($d['value']) }} with no sale for 90+ days (head office scope)</div>
            @forelse (array_slice($d['list'], 0, 8) as $x)
                <div class="td-row"><span class="td-name">{{ $x['name'] }}<span class="td-sub">{{ $x['category'] }} · last sold {{ $x['last'] }}</span></span><small>{{ $x['qty'] }} pcs</small><b>{{ $m($x['value']) }}</b></div>
            @empty<div class="td-empty">No dead stock.</div>@endforelse</div>
    @endif

    @if ($ok('appointment_utilisation'))
        @php $a = $w['appointment_utilisation']; @endphp
        <div class="td-card"><h3><i class="far fa-calendar-check"></i>Appointment use<a class="td-more" href="{{ url('admin/appointments/calendar') }}" id="td-b-calendar">Calendar</a></h3>
            <div class="td-note">{{ $label }} · show rate {{ $a['show_rate'] }}% · {{ $a['upcoming_7'] }} booked in the next 7 days</div>
            @if (!$a['total'])<div class="td-empty">No appointments in this period.</div>@else
            <div class="td-chart-box" style="height:200px"><canvas class="td-cv" data-cv="{{ json_encode(['kind' => 'doughnut', 'fmt' => 'num', 'labels' => ['Completed', 'Scheduled', 'Missed', 'Cancelled'], 'sets' => [['label' => 'Appointments', 'data' => [$a['completed'], $a['scheduled'], $a['missed'], $a['cancelled']], 'colors' => ['#10b981', '#6366f1', '#f59e0b', '#e11d48']]]]) }}"></canvas></div>@endif</div>
    @endif
</div>
