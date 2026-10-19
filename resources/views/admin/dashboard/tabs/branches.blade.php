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
        <div class="td-card" style="grid-column:1/-1">
            <h3>
                <span class="t-halo" style="background:#fef3c7;color:#b45309"><i class="fas fa-trophy"></i></span>
                Branch league table
                <a class="td-more" href="{{ $report('admin/report/vendor-monthly') }}" id="td-b-report-monthly">Branch monthly</a>
            </h3>
            <div class="td-note">This month against last month</div>
            <div style="overflow-x:auto">
                <table class="td-table">
                    <thead>
                        <tr>
                            <th style="width:40px;text-align:center">#</th>
                            <th>Branch</th>
                            <th>This month</th>
                            <th style="min-width:140px">Contribution</th>
                            <th>Last month</th>
                            <th>Growth</th>
                            <th style="text-align:right">Invoices</th>
                            <th style="text-align:right">Leads</th>
                            <th style="text-align:right">Won</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($b['rows'] as $r)
                            @php
                                $rank = $loop->iteration;
                                $rankClass = $rank === 1 ? 'rank-1' : ($rank === 2 ? 'rank-2' : ($rank === 3 ? 'rank-3' : 'rank-n'));
                                $growthVal = $r['growth'] ?? null;
                                $growthUp = $growthVal !== null && $growthVal >= 0;
                            @endphp
                            <tr>
                                <td style="text-align:center">
                                    <span class="rank-badge {{ $rankClass }}">
                                        @if ($rank === 1) 🥇 @elseif ($rank === 2) 🥈 @elseif ($rank === 3) 🥉 @else {{ $rank }} @endif
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight:700;color:#0f172a">{{ $r['name'] }}</span>
                                </td>
                                <td>
                                    <b style="font-size:13.5px;color:#0f172a">{{ $m($r['this_month']) }}</b>
                                </td>
                                <td>
                                    <span class="td-meter" style="height:7px;border-radius:999px;background:#f1f5f9;display:block;overflow:hidden">
                                        <i style="width:{{ $b['max'] > 0 ? min(100, round(($r['this_month'] / $b['max']) * 100)) : 0 }}%;background:linear-gradient(90deg, #6366f1, #818cf8);border-radius:999px;display:block;height:100%"></i>
                                    </span>
                                </td>
                                <td style="color:#64748b">{{ $m($r['last_month']) }}</td>
                                <td>
                                    <span class="t-chg {{ $growthUp ? 'up' : 'down' }}">{{ $r['growth'] === null ? '—' : (($r['growth'] >= 0 ? '▲ ' : '▼ ') . abs($r['growth']) . '%') }}</span>
                                </td>
                                <td style="text-align:right;font-weight:600;color:#334155">{{ number_format($r['invoices']) }}</td>
                                <td style="text-align:right;color:#64748b">{{ number_format($r['leads']) }}</td>
                                <td style="text-align:right">
                                    <span style="font-weight:700;color:{{ $r['conversion'] >= 30 ? '#047857' : ($r['conversion'] >= 15 ? '#b45309' : '#64748b') }}">
                                        {{ $r['conversion'] }}%
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($ok('branch_stock'))
        @php $s = $w['branch_stock']; @endphp
        <div class="td-card">
            <h3>
                <span class="t-halo" style="background:#fef3c7;color:#d97706"><i class="fas fa-boxes"></i></span>
                Stock value by branch
                <a class="td-more" href="{{ $report('admin/report/stock') }}" id="td-b-report-stock">Stock report</a>
            </h3>
            <div class="td-note">{{ $m($s['total']) }} in stock at cost</div>
            @foreach ($s['rows'] as $r)
                <div class="td-row">
                    <span class="td-name">{{ $r['name'] }}
                        <span class="td-sub">{{ number_format($r['units']) }} units · {{ $r['products'] }} products</span>
                    </span>
                    <span class="td-meter" style="height:6px;border-radius:999px;background:#fef3c7;overflow:hidden;flex:1;max-width:140px;margin:0 12px">
                        <i style="width:{{ $s['max'] > 0 ? min(100, round(($r['value'] / $s['max']) * 100)) : 0 }}%;background:linear-gradient(90deg, #f59e0b, #fbbf24);height:100%;border-radius:999px;display:block"></i>
                    </span>
                    <b style="font-weight:700;color:#0f172a">{{ $m($r['value']) }}</b>
                </div>
            @endforeach
        </div>
    @endif

    @if ($ok('dead_stock'))
        @php $d = $w['dead_stock']; @endphp
        <div class="td-card">
            <h3>
                <span class="t-halo" style="background:#fee2e2;color:#ef4444"><i class="fas fa-box-open"></i></span>
                Dead and slow stock
                <a class="td-more" href="{{ $report('admin/report/stock') }}" id="td-b-report-dead">Stock report</a>
            </h3>
            <div class="td-note">{{ $d['count'] }} product(s), {{ number_format($d['units']) }} units, {{ $m($d['value']) }} with no sale for 90+ days (head office scope)</div>
            @forelse (array_slice($d['list'], 0, 8) as $x)
                <div class="td-row">
                    <span class="td-name">{{ $x['name'] }}
                        <span class="td-sub">{{ $x['category'] }} · last sold {{ $x['last'] }}</span>
                    </span>
                    <small style="background:#f1f5f9;padding:2px 8px;border-radius:6px;font-weight:600;color:#475569;margin-right:8px">{{ $x['qty'] }} pcs</small>
                    <b style="color:#ef4444;font-weight:700">{{ $m($x['value']) }}</b>
                </div>
            @empty
                <div class="td-empty">No dead stock.</div>
            @endforelse
        </div>
    @endif

    @if ($ok('appointment_utilisation'))
        @php $a = $w['appointment_utilisation']; @endphp
        <div class="td-card">
            <h3>
                <span class="t-halo" style="background:#ecfdf5;color:#059669"><i class="far fa-calendar-check"></i></span>
                Appointment use
                <a class="td-more" href="{{ url('admin/appointments/calendar') }}" id="td-b-calendar">Calendar</a>
            </h3>
            <div class="td-note">{{ $label }} · show rate {{ $a['show_rate'] }}% · {{ $a['upcoming_7'] }} booked in the next 7 days</div>
            @if (!$a['total'])
                <div class="td-empty">No appointments in this period.</div>
            @else
                <div class="td-chart-box" style="height:200px">
                    <canvas class="td-cv" data-cv="{{ json_encode(['kind' => 'doughnut', 'fmt' => 'num', 'labels' => ['Completed', 'Scheduled', 'Missed', 'Cancelled'], 'sets' => [['label' => 'Appointments', 'data' => [$a['completed'], $a['scheduled'], $a['missed'], $a['cancelled']], 'colors' => ['#10b981', '#6366f1', '#f59e0b', '#e11d48']]]]) }}"></canvas>
                </div>
            @endif
        </div>
    @endif
</div>
