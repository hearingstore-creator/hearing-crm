{{-- Sales & Money tab. $w = widget results, $label = period label, $legacy = old dashboard URL --}}
@php
    $ok = fn ($k) => isset($w[$k]) && empty($w[$k]['error']);
    $failed = collect($w)->filter(fn ($v) => is_array($v) && !empty($v['error']))->count();
    $m = fn ($v) => '৳' . number_format((float) $v);
    $C = ['sales' => '#6366f1', 'last' => '#cbd5e1', 'purchases' => '#94a3b8', 'profit' => '#10b981', 'dues' => '#f59e0b', 'expense' => '#e11d48'];
    $gp = $w['gross_profit'] ?? null; $aov = $w['aov_trend'] ?? null; $dl = $w['discount_leakage'] ?? null; $cash = $w['cash_today'] ?? null;
@endphp

@if ($failed)<div class="td-err">{{ $failed }} block(s) could not be loaded and are left out.</div>@endif

<div class="td-stats">
    @if ($ok('gross_profit'))
        <div class="td-stat"><span>Revenue · {{ $label }}</span><b>{{ $m($gp['revenue']) }}</b><small>services {{ $m($gp['service_revenue']) }} on top</small></div>
        <div class="td-stat"><span>Gross profit</span><b>{{ $m($gp['profit']) }}</b><small>{{ $gp['margin'] }}% margin{{ $gp['missing_cost'] ? ' · ' . $gp['missing_cost'] . ' lines without cost' : '' }}</small></div>
    @endif
    @if ($ok('aov_trend'))
        <div class="td-stat"><span>Average order value</span><b>{{ $m($aov['current']) }}</b><small class="{{ $aov['change'] >= 0 ? 'up' : 'down' }}">{{ $aov['change'] >= 0 ? '▲' : '▼' }} {{ abs($aov['change']) }}% vs last month</small></div>
    @endif
    @if ($ok('discount_leakage'))
        <div class="td-stat"><span>Discount given</span><b>{{ $m($dl['discount']) }}</b><small>{{ $dl['pct'] }}% of gross · {{ $dl['heavy'] }} heavy (over 10%)</small></div>
    @endif
    @if ($ok('cash_today'))
        <div class="td-stat"><span>Cash net · this month</span><b>{{ $m($cash['month']['net']) }}</b><small>in {{ $m($cash['month']['in']) }} · out {{ $m($cash['month']['out']) }}</small></div>
    @endif
</div>

<div class="td-charts">
    @if ($ok('revenue_extended'))
        @php $r = $w['revenue_extended']; @endphp
        <div class="td-card">
            <h3>
                <span class="t-halo" style="background:#e0e7ff;color:#4f46e5"><i class="fas fa-chart-line"></i></span>
                Revenue by month
                <a class="td-more" href="{{ $report('admin/report/sales-performance') }}" id="td-s-report-sales">Sales performance</a>
            </h3>
            <div class="td-note">Last 12 months, against the same months a year earlier and purchases</div>
            <div class="td-chart-box"><canvas class="td-cv" data-cv="{{ json_encode(['kind' => 'line', 'fmt' => 'money', 'labels' => $r['labels'], 'sets' => [['label' => 'Sales', 'data' => $r['sales'], 'color' => $C['sales'], 'fill' => true], ['label' => 'A year earlier', 'data' => $r['sales_ly'], 'color' => $C['last'], 'dash' => true], ['label' => 'Purchases', 'data' => $r['purchases'], 'color' => $C['purchases']]]]) }}"></canvas></div>
        </div>
    @endif
    @if ($ok('aov_trend'))
        <div class="td-card">
            <h3>
                <span class="t-halo" style="background:#e0f2fe;color:#0284c7"><i class="fas fa-receipt"></i></span>
                Average order value
                <a class="td-more" href="{{ $report('admin/report/sales-performance') }}" id="td-s-report-aov">Sales performance</a>
            </h3>
            <div class="td-note">Per invoice, last 12 months · lifetime {{ $m($aov['lifetime']) }}</div>
            <div class="td-chart-box"><canvas class="td-cv" data-cv="{{ json_encode(['kind' => 'line', 'fmt' => 'money', 'labels' => array_column($aov['series'], 'month'), 'sets' => [['label' => 'Average order', 'data' => array_column($aov['series'], 'aov'), 'color' => $C['sales'], 'fill' => true]]]) }}"></canvas></div>
        </div>
    @endif
    @if ($ok('payment_mix'))
        <div class="td-card">
            <h3>
                <span class="t-halo" style="background:#fef3c7;color:#d97706"><i class="fas fa-credit-card"></i></span>
                Payment mix
            </h3>
            <div class="td-note">{{ $label }}, by invoice value</div>
            @if (empty($w['payment_mix']['methods']))
                <div class="td-empty">No sales in this period.</div>
            @else
                <div class="td-chart-box"><canvas class="td-cv" data-cv="{{ json_encode(['kind' => 'doughnut', 'fmt' => 'money', 'labels' => array_column($w['payment_mix']['methods'], 'label'), 'sets' => [['label' => 'Sales', 'data' => array_column($w['payment_mix']['methods'], 'total')]]]) }}"></canvas></div>
            @endif
        </div>
    @endif
    @if ($ok('discount_leakage'))
        <div class="td-card">
            <h3>
                <span class="t-halo" style="background:#fce7f3;color:#db2777"><i class="fas fa-tags"></i></span>
                Discounts by staff
            </h3>
            <div class="td-note">{{ $label }} · {{ $dl['discounted'] }} of {{ $dl['invoices'] }} invoices discounted</div>
            @forelse ($dl['staff'] as $s)
                <div class="td-row">
                    <span class="td-name">{{ $s['name'] }}</span>
                    <span class="td-meter" style="height:6px;border-radius:999px;background:#fee2e2;flex:1;max-width:140px;margin:0 12px;overflow:hidden">
                        <i style="width:{{ min($s['pct'] * 4, 100) }}%;background:{{ $C['expense'] }};border-radius:999px;display:block;height:100%"></i>
                    </span>
                    <b>{{ $m($s['discount']) }}</b>
                    <small style="background:#f1f5f9;padding:2px 8px;border-radius:6px;font-weight:600;color:#475569;margin-left:6px">{{ $s['pct'] }}%</small>
                </div>
            @empty
                <div class="td-empty">No discounts in this period.</div>
            @endforelse
        </div>
    @endif
    @if ($ok('receivables_aging'))
        @php $a = $w['receivables_aging']; @endphp
        <div class="td-card">
            <h3>
                <span class="t-halo" style="background:#fee2e2;color:#dc2626"><i class="fas fa-hand-holding-usd"></i></span>
                Patient dues by age
                <a class="td-more" href="{{ url('admin/customers') }}" id="td-s-report-dues">Customers</a>
            </h3>
            <div class="td-note">{{ $m($a['total']) }} owed by {{ $a['count'] }} patient(s)</div>
            <div class="td-chart-box" style="height:170px"><canvas class="td-cv" data-cv="{{ json_encode(['kind' => 'bar', 'fmt' => 'money', 'labels' => ['0–30 days', '31–60', '61–90', '90+'], 'sets' => [['label' => 'Owed', 'data' => array_values($a['buckets']), 'color' => $C['dues']]]]) }}"></canvas></div>
            @foreach (array_slice($a['top'] ?? [], 0, 5) as $p)
                <div class="td-row"><a class="td-name" href="{{ url('admin/customers/' . $p['id']) }}" style="font-weight:600;color:#0f172a">{{ $p['name'] }}</a><b style="color:#ef4444">{{ $m($p['balance']) }}</b><small style="background:#fee2e2;color:#b91c1c;padding:2px 8px;border-radius:6px;font-weight:700">{{ $p['oldest_days'] }}d</small></div>
            @endforeach
        </div>
    @endif
    @if ($ok('supplier_aging'))
        @php $a = $w['supplier_aging']; @endphp
        <div class="td-card">
            <h3>
                <span class="t-halo" style="background:#ffedd5;color:#ea580c"><i class="fas fa-truck"></i></span>
                Supplier dues by age
                <a class="td-more" href="{{ $report('admin/report/supplier-statement') }}" id="td-s-report-sup">Supplier statement</a>
            </h3>
            <div class="td-note">{{ $m($a['total']) }} owed to {{ $a['count'] }} supplier(s)</div>
            <div class="td-chart-box" style="height:170px"><canvas class="td-cv" data-cv="{{ json_encode(['kind' => 'bar', 'fmt' => 'money', 'labels' => ['0–30 days', '31–60', '61–90', '90+'], 'sets' => [['label' => 'Owed', 'data' => array_values($a['buckets']), 'color' => $C['dues']]]]) }}"></canvas></div>
            @foreach (array_slice($a['top'] ?? [], 0, 5) as $p)
                <div class="td-row"><span class="td-name" style="font-weight:600;color:#0f172a">{{ $p['name'] }}</span><b style="color:#ea580c">{{ $m($p['balance']) }}</b><small style="background:#ffedd5;color:#9a3412;padding:2px 8px;border-radius:6px;font-weight:700">{{ $p['oldest_days'] }}d</small></div>
            @endforeach
        </div>
    @endif
    @if ($ok('expense_revenue'))
        @php $e = $w['expense_revenue']; @endphp
        <div class="td-card">
            <h3>
                <span class="t-halo" style="background:#ecfdf5;color:#059669"><i class="fas fa-balance-scale"></i></span>
                Income and expense
                <a class="td-more" href="{{ $report('admin/report/ledger') }}" id="td-s-report-ledger">Ledger</a>
            </h3>
            <div class="td-note">Last 12 months · net {{ $m($e['net']) }} (revenue − purchases − expenses)</div>
            <div class="td-chart-box"><canvas class="td-cv" data-cv="{{ json_encode(['kind' => 'bar', 'fmt' => 'money', 'labels' => array_column($e['series'], 'month'), 'sets' => [['label' => 'Revenue', 'data' => array_column($e['series'], 'revenue'), 'color' => $C['sales']], ['label' => 'Purchases', 'data' => array_column($e['series'], 'purchases'), 'color' => $C['purchases']], ['label' => 'Expenses', 'data' => array_column($e['series'], 'expenses'), 'color' => $C['expense']]]]) }}"></canvas></div>
        </div>
    @endif
    @if ($ok('cash_today'))
        <div class="td-card">
            <h3>
                <span class="t-halo" style="background:#f3e8ff;color:#7c3aed"><i class="fas fa-wallet"></i></span>
                Cash in and out, last 7 days
                <a class="td-more" href="{{ $report('admin/report/ledger') }}" id="td-s-report-cash">Ledger</a>
            </h3>
            <div class="td-note">Today: in {{ $m($cash['today']['in']) }} · out {{ $m($cash['today']['out']) }}</div>
            <div class="td-chart-box"><canvas class="td-cv" data-cv="{{ json_encode(['kind' => 'bar', 'fmt' => 'money', 'labels' => array_column($cash['week'], 'day'), 'sets' => [['label' => 'Cash in', 'data' => array_column($cash['week'], 'in'), 'color' => $C['profit']], ['label' => 'Cash out', 'data' => array_column($cash['week'], 'out'), 'color' => $C['expense']]]]) }}"></canvas></div>
        </div>
    @endif
</div>
