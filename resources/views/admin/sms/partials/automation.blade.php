<!-- Automation Tab -->
<div class="tab-pane fade p-3" id="automation-tab">

    {{-- ── Page header + summary + filters ── --}}
    @php
        $autoAll    = collect($automations);
        $autoTotal  = $autoAll->count();
        $autoActive = $autoAll->where('is_active', true)->count();
        $autoSent   = $autoAll->sum('sent_count');
    @endphp

    <style>
        .sma-head { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:14px; margin-bottom:18px; }
        .sma-head-l { display:flex; align-items:center; gap:14px; min-width:0; }
        .sma-head-ico { flex:0 0 auto; width:44px; height:44px; border-radius:14px; display:inline-flex; align-items:center; justify-content:center; font-size:18px; color:#fff; background: linear-gradient(135deg, var(--accent-500, #6366f1), var(--accent-700, #4338ca)); box-shadow: 0 8px 18px -8px rgba(99,102,241,.7); }
        .sma-head h1 { margin:0; font-size:18px; font-weight:800; letter-spacing:-.01em; color: var(--page-text, #1f2937); }
        .sma-head p { margin:2px 0 0; font-size:12.5px; color: var(--page-text-muted, #6b7280); }

        .sma-toolbar { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; margin: 0 0 14px; }
        .sma-summary { display:flex; flex-wrap:wrap; gap:8px; }
        .sma-pill { display:inline-flex; align-items:center; gap:7px; padding:6px 12px; border-radius:999px; background: var(--gray-50, #f8fafc); border:1px solid var(--gray-100, #eef0f4); font-size:12px; color: var(--page-text-secondary, #4b5563); }
        .sma-pill b { color: var(--page-text, #1f2937); font-weight:700; }
        .sma-dot { width:7px; height:7px; border-radius:50%; background: var(--success-500, #22c55e); }
        .sma-filters { display:inline-flex; padding:3px; gap:2px; background: var(--gray-100, #eef0f4); border-radius:11px; }
        .sma-filter { border:0; background:transparent; padding:6px 13px; border-radius:8px; font-size:12.5px; font-weight:600; color: var(--page-text-muted, #6b7280); cursor:pointer; transition: background .15s, color .15s, box-shadow .15s; }
        .sma-filter:hover { color: var(--page-text, #1f2937); }
        .sma-filter.is-on { background:#fff; color: var(--page-text, #1f2937); box-shadow: 0 1px 3px rgba(16,24,40,.12); }
        .sma-filter:focus-visible { outline:2px solid var(--accent-500, #6366f1); outline-offset:1px; }

        .sma-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap:16px; }
        .sma-card { display:flex; flex-direction:column; background: var(--page-surface, #fff); border:1px solid var(--gray-100, #eef0f4); border-radius:16px; box-shadow: 0 1px 2px rgba(16,24,40,.04); overflow:hidden; transition: box-shadow .2s, transform .2s, border-color .2s; }
        .sma-card:hover { box-shadow: 0 12px 28px -14px rgba(16,24,40,.25); transform: translateY(-2px); border-color: var(--gray-200, #e5e7eb); }
        .sma-card.is-off { background: var(--gray-50, #fafbfc); }
        .sma-card.is-off .sma-ico, .sma-card.is-off .sma-name, .sma-card.is-off .sma-bubble { opacity:.6; }
        .sma-top { display:flex; align-items:flex-start; gap:12px; padding:16px 16px 12px; }
        .sma-ico { flex:0 0 auto; width:40px; height:40px; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; font-size:15px; }
        .sma-id { flex:1; min-width:0; }
        .sma-name { font-size:14.5px; font-weight:700; line-height:1.3; color: var(--page-text, #1f2937); word-break:break-word; }
        .sma-trig { margin-top:2px; font-size:11.5px; font-weight:600; }
        .sma-tags { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
        .sma-tag { display:inline-flex; align-items:center; gap:5px; font-size:10.5px; font-weight:700; padding:3px 8px; border-radius:999px; }
        .sma-tag.on { background: var(--success-50, #ecfdf5); color: var(--success-700, #047857); }
        .sma-tag.off { background: var(--gray-100, #f3f4f6); color: var(--page-text-muted, #6b7280); }
        .sma-tag.ess { background: var(--info-50, #eff6ff); color: var(--info-700, #1d4ed8); }
        .sma-switch { flex:0 0 auto; padding-top:2px; }
        .sma-switch .custom-control { margin:0; }

        .sma-desc { margin:0; padding:0 16px 10px; font-size:12px; color: var(--page-text-muted, #6b7280); line-height:1.5; }
        .sma-msg { padding:0 16px 14px; }
        .sma-bubble { position:relative; padding:11px 13px; border-radius:4px 14px 14px 14px; background: var(--gray-50, #f4f6fa); border:1px solid var(--gray-100, #eef0f4); font-size:12.5px; line-height:1.55; color: var(--page-text-secondary, #374151); word-break:break-word; white-space:pre-line; display:-webkit-box; -webkit-line-clamp:4; -webkit-box-orient:vertical; overflow:hidden; }
        .sma-card.is-off .sma-bubble { background:#fff; }

        .sma-foot { margin-top:auto; display:flex; align-items:center; justify-content:space-between; gap:10px; padding:11px 16px; border-top:1px solid var(--gray-100, #eef0f4); }
        .sma-sent { font-size:12px; color: var(--page-text-muted, #6b7280); display:inline-flex; align-items:center; gap:6px; }
        .sma-sent b { color: var(--page-text, #1f2937); }
        .sma-acts { display:inline-flex; align-items:center; gap:6px; }
        .sma-acts form { display:inline; margin:0; }

        .sma-empty { text-align:center; padding:56px 20px; background: var(--page-surface, #fff); border:1.5px dashed var(--gray-200, #e5e7eb); border-radius:16px; }
        .sma-empty-ico { width:64px; height:64px; margin:0 auto 14px; border-radius:20px; display:flex; align-items:center; justify-content:center; font-size:26px; background: var(--accent-50, #eef2ff); color: var(--accent-500, #6366f1); }
        .sma-empty h6 { margin:0 0 6px; font-size:15px; font-weight:700; color: var(--page-text, #1f2937); }
        .sma-empty p { margin:0 auto; max-width:380px; font-size:13px; color: var(--page-text-muted, #6b7280); }
        .sma-nomatch { display:none; text-align:center; padding:36px 16px; font-size:13px; color: var(--page-text-muted, #6b7280); }

        @media (max-width: 480px) { .sma-grid { grid-template-columns: 1fr; } .sma-filters { width:100%; overflow-x:auto; } }
    </style>

    <div class="sma-head">
        <div class="sma-head-l">
            <span class="sma-head-ico"><i class="fas fa-robot" aria-hidden="true"></i></span>
            <div>
                <h1>Automated Messages</h1>
                <p>Triggers fire automatically based on customer and lead activity</p>
            </div>
        </div>
        @can('permission', 'sms_automation.create')
        <a href="{{ url('admin/sms-automations/create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus mr-1"></i> New Automation
        </a>
        @endcan
    </div>

    {{-- ── Cost control: mode switch, usage and cost ── --}}
    @php
        $modeInfo = [
            'all'       => ['All automations', 'Every active automation sends.', 'fa-bolt'],
            'essential' => ['Essential only', 'Appointments, tickets, repair and pickup messages. Welcome, marketing, warranty and points messages stop.', 'fa-star'],
            'off'       => ['Paused', 'No automatic SMS at all. Manual and campaign SMS still work.', 'fa-pause-circle'],
        ];
        $modeShort = [
            'all'       => 'Every active automation sends',
            'essential' => 'Only important messages',
            'off'       => 'No automatic SMS',
        ];
        $capPct = $usage['cap'] > 0 ? min(100, round($usage['cap_used'] / $usage['cap'] * 100)) : 0;
        $capTone = $capPct >= 90 ? 'danger' : ($capPct >= 70 ? 'warn' : 'ok');
    @endphp

    <style>
        .smc { background: var(--page-surface, #fff); border: 1px solid var(--gray-100, #eef0f4); border-radius: 16px; box-shadow: 0 1px 2px rgba(16,24,40,.04); margin-bottom: 18px; overflow: hidden; }
        .smc-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:16px 22px; border-bottom:1px solid var(--gray-100, #eef0f4); }
        .smc-title { margin:0; font-size:13px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color: var(--page-text-muted, #6b7280); display:flex; align-items:center; gap:8px; }
        .smc-title i { color: var(--accent-500, #6366f1); }
        .smc-status { font-size:12px; font-weight:600; padding:4px 10px; border-radius:999px; background: var(--success-50, #ecfdf5); color: var(--success-700, #047857); }
        .smc-status.is-essential { background: var(--warning-50, #fffbeb); color: var(--warning-700, #b45309); }
        .smc-status.is-off { background: var(--danger-50, #fef2f2); color: var(--danger-700, #b91c1c); }

        .smc-body { display:grid; grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr); gap:0; }
        .smc-col { padding: 20px 22px; }
        .smc-col + .smc-col { border-left:1px solid var(--gray-100, #eef0f4); background: var(--gray-50, #fafbfc); }
        .smc-label { display:block; font-size:12px; font-weight:600; color: var(--page-text, #1f2937); margin: 0 0 8px; }

        /* mode picker */
        .smc-modes { display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:10px; }
        .smc-mode { position:relative; display:flex; flex-direction:column; align-items:flex-start; gap:6px; text-align:left; padding:12px 14px; border:1.5px solid var(--gray-200, #e5e7eb); border-radius:12px; background:#fff; color: var(--page-text, #1f2937); cursor:pointer; transition: border-color .15s, box-shadow .15s, background .15s, transform .15s; }
        .smc-mode:hover { border-color: var(--accent-300, #a5b4fc); transform: translateY(-1px); }
        .smc-mode:focus-visible { outline: 2px solid var(--accent-500, #6366f1); outline-offset: 2px; }
        .smc-mode-ico { width:28px; height:28px; border-radius:8px; display:inline-flex; align-items:center; justify-content:center; font-size:13px; background: var(--gray-100, #f3f4f6); color: var(--page-text-muted, #6b7280); }
        .smc-mode-name { font-size:13.5px; font-weight:700; line-height:1.2; }
        .smc-mode-sub { font-size:11.5px; color: var(--page-text-muted, #6b7280); line-height:1.35; }
        .smc-mode.is-active { border-color: var(--accent-500, #6366f1); background: var(--accent-50, #eef2ff); box-shadow: 0 0 0 3px rgba(99,102,241,.12); }
        .smc-mode.is-active .smc-mode-ico { background: var(--accent-500, #6366f1); color:#fff; }
        .smc-mode.is-active.is-off { border-color: var(--danger-500, #ef4444); background: var(--danger-50, #fef2f2); box-shadow: 0 0 0 3px rgba(239,68,68,.12); }
        .smc-mode.is-active.is-off .smc-mode-ico { background: var(--danger-500, #ef4444); }
        .smc-mode-check { position:absolute; top:10px; right:10px; font-size:13px; color: var(--accent-500, #6366f1); }
        .smc-mode.is-off .smc-mode-check { color: var(--danger-500, #ef4444); }
        .smc-hint { margin: 10px 2px 0; font-size:12px; color: var(--page-text-muted, #6b7280); line-height:1.5; }

        /* settings fields */
        .smc-fields { display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:14px; margin-top:20px; padding-top:18px; border-top:1px dashed var(--gray-200, #e5e7eb); }
        .smc-field small { display:block; margin-top:5px; font-size:11.5px; color: var(--page-text-muted, #6b7280); line-height:1.4; }
        .smc-input { position:relative; }
        .smc-input .form-control { height:38px; padding-left:30px; border-radius:10px; font-size:13.5px; }
        .smc-input .smc-unit { position:absolute; left:11px; top:50%; transform:translateY(-50%); font-size:13px; color: var(--page-text-muted, #6b7280); pointer-events:none; }
        .smc-input .smc-unit.is-icon { font-size:12px; }
        .smc-save-row { grid-column: 1 / -1; display:flex; justify-content:flex-end; }

        /* usage tiles */
        .smc-stat-grid { display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:10px; }
        .smc-stat { background:#fff; border:1px solid var(--gray-100, #eef0f4); border-radius:12px; padding:12px 14px; }
        .smc-stat-k { font-size:11.5px; font-weight:600; color: var(--page-text-muted, #6b7280); }
        .smc-stat-v { margin-top:4px; font-size:24px; font-weight:800; line-height:1; letter-spacing:-.02em; color: var(--page-text, #1f2937); }
        .smc-stat-v small { font-size:11px; font-weight:600; color: var(--page-text-muted, #6b7280); letter-spacing:0; margin-left:2px; }
        .smc-stat-c { margin-top:6px; font-size:12px; color: var(--page-text-secondary, #4b5563); }
        .smc-cap { margin-top:10px; background:#fff; border:1px solid var(--gray-100, #eef0f4); border-radius:12px; padding:12px 14px; }
        .smc-cap-top { display:flex; justify-content:space-between; align-items:baseline; font-size:11.5px; font-weight:600; color: var(--page-text-muted, #6b7280); }
        .smc-cap-top b { font-size:13px; color: var(--page-text, #1f2937); }
        .smc-bar { height:8px; margin-top:9px; background: var(--gray-100, #eef0f4); border-radius:999px; overflow:hidden; }
        .smc-bar > span { display:block; height:100%; border-radius:999px; background: var(--accent-500, #6366f1); transition: width .5s ease; }
        .smc-bar > span.warn { background: var(--warning-500, #f59e0b); }
        .smc-bar > span.danger { background: var(--danger-500, #ef4444); }

        /* breakdown */
        .smc-more { border-top:1px solid var(--gray-100, #eef0f4); }
        .smc-more > summary { list-style:none; cursor:pointer; padding:13px 22px; font-size:13px; font-weight:600; color: var(--page-text, #1f2937); display:flex; align-items:center; gap:8px; user-select:none; }
        .smc-more > summary::-webkit-details-marker { display:none; }
        .smc-more > summary:hover { background: var(--gray-50, #fafbfc); }
        .smc-more > summary .smc-chev { transition: transform .2s; font-size:11px; color: var(--page-text-muted, #6b7280); }
        .smc-more[open] > summary .smc-chev { transform: rotate(90deg); }
        .smc-more-body { padding: 0 22px 16px; }

        @media (max-width: 900px) {
            .smc-body { grid-template-columns: 1fr; }
            .smc-col + .smc-col { border-left:0; border-top:1px solid var(--gray-100, #eef0f4); }
        }
        @media (max-width: 560px) {
            .smc-modes, .smc-fields { grid-template-columns: 1fr; }
            .smc-col, .smc-head { padding-left:16px; padding-right:16px; }
        }
    </style>

    <div class="smc" id="smsCostPanel">
        <div class="smc-head">
            <h2 class="smc-title"><i class="fas fa-sliders-h"></i> Automatic SMS control</h2>
            <span class="smc-status {{ $usage['mode'] === 'off' ? 'is-off' : ($usage['mode'] === 'essential' ? 'is-essential' : '') }}">
                {{ $modeInfo[$usage['mode']][0] }}
            </span>
        </div>

        <div class="smc-body">
            {{-- Left: mode + settings --}}
            <div class="smc-col">
                @can('permission', 'sms_automation.edit')
                <form action="{{ route('sms-automations.mode') }}" method="POST" id="smsModeForm">
                    @csrf
                    <span class="smc-label">Mode</span>
                    <div class="smc-modes" role="group" aria-label="Automatic SMS mode">
                        @foreach($modeInfo as $key => [$label, $hint, $icon])
                            <button type="submit" name="mode" value="{{ $key }}" title="{{ $hint }}"
                                    class="smc-mode {{ $key === 'off' ? 'is-off' : '' }} {{ $usage['mode'] === $key ? 'is-active' : '' }}"
                                    aria-pressed="{{ $usage['mode'] === $key ? 'true' : 'false' }}">
                                @if($usage['mode'] === $key)<i class="fas fa-check-circle smc-mode-check" aria-hidden="true"></i>@endif
                                <span class="smc-mode-ico"><i class="fas {{ $icon }}" aria-hidden="true"></i></span>
                                <span class="smc-mode-name">{{ $label }}</span>
                                <span class="smc-mode-sub">{{ $modeShort[$key] }}</span>
                            </button>
                        @endforeach
                    </div>
                    <p class="smc-hint"><i class="fas fa-info-circle mr-1"></i>{{ $modeInfo[$usage['mode']][1] }}</p>

                    <div class="smc-fields">
                        <div class="smc-field">
                            <label for="costPerSms" class="smc-label">Cost per SMS</label>
                            <div class="smc-input">
                                <span class="smc-unit">৳</span>
                                <input type="number" step="0.001" min="0" max="100" name="cost_per_sms" id="costPerSms" value="{{ $usage['rate'] }}" class="form-control" placeholder="0.35">
                            </div>
                            <small>Used only for the cost figures on this page.</small>
                        </div>
                        <div class="smc-field">
                            <label for="lowBalance" class="smc-label">Low balance warning</label>
                            <div class="smc-input">
                                <span class="smc-unit is-icon"><i class="fas fa-bell"></i></span>
                                <input type="number" step="0.01" min="0" name="low_balance" id="lowBalance" value="{{ $gateway['threshold'] ?? '' }}" class="form-control" placeholder="e.g. 500">
                            </div>
                            <small>Warn when the gateway balance is at or below this. Same unit as the gateway; empty = no warning.</small>
                        </div>
                        <div class="smc-save-row">
                            {{-- Keeps the current mode, saves cost and warning values --}}
                            <button type="submit" name="mode" value="{{ $usage['mode'] }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-save mr-1"></i> Save settings
                            </button>
                        </div>
                    </div>
                </form>
                @else
                    <span class="smc-label">Mode</span>
                    <strong>{{ $modeInfo[$usage['mode']][0] }}</strong>
                    <p class="smc-hint">{{ $modeInfo[$usage['mode']][1] }}</p>
                @endcan
            </div>

            {{-- Right: usage --}}
            <div class="smc-col">
                <span class="smc-label">Usage</span>
                <div class="smc-stat-grid">
                    <div class="smc-stat">
                        <div class="smc-stat-k">Today</div>
                        <div class="smc-stat-v">{{ number_format($usage['today_parts']) }}<small>SMS</small></div>
                        <div class="smc-stat-c">≈ ৳{{ number_format($usage['today_cost'], 2) }}</div>
                    </div>
                    <div class="smc-stat">
                        <div class="smc-stat-k">This month</div>
                        <div class="smc-stat-v">{{ number_format($usage['month_parts']) }}<small>SMS</small></div>
                        <div class="smc-stat-c">≈ ৳{{ number_format($usage['month_cost'], 2) }}</div>
                    </div>
                </div>
                <div class="smc-cap">
                    <div class="smc-cap-top">
                        <span>Automatic SMS today</span>
                        <span><b>{{ $usage['cap_used'] }}</b> / {{ $usage['cap'] }}</span>
                    </div>
                    <div class="smc-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $capPct }}">
                        <span class="{{ $capTone === 'ok' ? '' : $capTone }}" style="width:{{ $capPct }}%;"></span>
                    </div>
                </div>
            </div>
        </div>

        <details class="smc-more">
            <summary><i class="fas fa-chevron-right smc-chev"></i> Where this month's SMS went</summary>
            <div class="smc-more-body">
                <div class="table-responsive">
                    <table class="table table-sm" style="font-size:12.5px; margin-bottom:0;">
                        <thead><tr><th>Automation</th><th>Type</th><th class="text-right">Messages</th><th class="text-right">SMS parts</th><th class="text-right">Cost ≈</th></tr></thead>
                        <tbody>
                            @foreach($usage['automations'] as $row)
                                <tr>
                                    <td>{{ $row['name'] }} @unless($row['active'])<span class="badge badge-light">off</span>@endunless</td>
                                    <td>{!! $row['essential'] ? '<span class="badge badge-info">Essential</span>' : '<span class="badge badge-secondary">Optional</span>' !!}</td>
                                    <td class="text-right">{{ number_format($row['messages']) }}</td>
                                    <td class="text-right">{{ number_format($row['parts']) }}</td>
                                    <td class="text-right">৳{{ number_format($row['cost'], 2) }}</td>
                                </tr>
                            @endforeach
                            @foreach($usage['other'] as $row)
                                <tr class="text-muted">
                                    <td>{{ $row['type'] }} (campaigns &amp; one-off)</td><td></td>
                                    <td class="text-right">{{ number_format($row['messages']) }}</td>
                                    <td class="text-right">{{ number_format($row['parts']) }}</td>
                                    <td class="text-right">৳{{ number_format($row['cost'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </details>
    </div>

    {{-- ── Automation cards ── --}}
    @if($autoTotal > 0)
        <div class="sma-toolbar">
            <div class="sma-summary">
                <span class="sma-pill"><span class="sma-dot"></span><b>{{ $autoActive }}</b> of <b>{{ $autoTotal }}</b> active</span>
                <span class="sma-pill"><i class="fas fa-paper-plane" style="font-size:10px;"></i><b>{{ number_format($autoSent) }}</b> sent in total</span>
            </div>
            <div class="sma-filters" role="tablist" aria-label="Filter automations">
                <button type="button" class="sma-filter is-on" data-filter="all">All</button>
                <button type="button" class="sma-filter" data-filter="active">Active</button>
                <button type="button" class="sma-filter" data-filter="inactive">Inactive</button>
                <button type="button" class="sma-filter" data-filter="essential">Essential</button>
            </div>
        </div>
    @endif

    <div class="sma-grid" id="smaGrid">
    @forelse($automations as $automation)
        @php
            $trigMap = [
                'lead_created'        => ['color' => 'var(--success-600)', 'bg' => 'var(--success-50)',  'icon' => 'fas fa-user-plus'],
                'customer_first_sale' => ['color' => 'var(--accent-600)',  'bg' => 'var(--accent-50)',   'icon' => 'fas fa-shopping-bag'],
                'appointment_reminder'=> ['color' => 'var(--warning-600)', 'bg' => 'var(--warning-50)',  'icon' => 'fas fa-calendar-alt'],
                'warranty_expiring'   => ['color' => '#6B4EBB',            'bg' => '#EEE9FF',            'icon' => 'fas fa-shield-alt'],
                'points_milestone'    => ['color' => 'var(--success-600)', 'bg' => 'var(--success-50)',  'icon' => 'fas fa-coins'],
                'membership_upgrade'  => ['color' => '#7C3AAF',            'bg' => '#FDF0FF',            'icon' => 'fas fa-award'],
                'points_expiring'     => ['color' => 'var(--danger-600)',  'bg' => 'var(--danger-50)',   'icon' => 'fas fa-hourglass-half'],
                'customer_created'    => ['color' => 'var(--primary-600)', 'bg' => 'var(--primary-50)',  'icon' => 'fas fa-id-card'],
                'appointment_booked'  => ['color' => 'var(--warning-600)', 'bg' => 'var(--warning-50)',  'icon' => 'fas fa-calendar-check'],
                'ticket_created'      => ['color' => 'var(--info-600)',    'bg' => 'var(--info-50)',     'icon' => 'fas fa-tools'],
                'ticket_solved'       => ['color' => 'var(--success-600)', 'bg' => 'var(--success-50)',  'icon' => 'fas fa-check-circle'],
            ];
            $t = $trigMap[$automation->trigger_type] ?? ['color' => 'var(--gray-600)', 'bg' => 'var(--gray-100)', 'icon' => 'fas fa-bolt'];
            $trigEnum = \App\Enums\SmsAutomationTrigger::tryFrom($automation->trigger_type);
            $isEssential = (bool) $trigEnum?->isEssential();
        @endphp
        <article class="sma-card {{ $automation->is_active ? '' : 'is-off' }}"
                 data-active="{{ $automation->is_active ? 1 : 0 }}" data-essential="{{ $isEssential ? 1 : 0 }}">
            <div class="sma-top">
                <span class="sma-ico" style="background: {{ $t['bg'] }}; color: {{ $t['color'] }};"><i class="{{ $t['icon'] }}" aria-hidden="true"></i></span>
                <div class="sma-id">
                    <div class="sma-name">{{ $automation->name }}</div>
                    <div class="sma-trig" style="color: {{ $t['color'] }};">
                        {{ $trigEnum?->label() ?? ucwords(str_replace('_', ' ', $automation->trigger_type)) }}
                    </div>
                    <div class="sma-tags">
                        @if($automation->is_active)
                            <span class="sma-tag on"><i class="fas fa-circle" style="font-size:5px;"></i> Active</span>
                        @else
                            <span class="sma-tag off"><i class="fas fa-circle" style="font-size:5px;"></i> Inactive</span>
                        @endif
                        @if($isEssential)
                            <span class="sma-tag ess" title="Kept when the mode is Essential only"><i class="fas fa-star" style="font-size:8px;"></i> Essential</span>
                        @endif
                    </div>
                </div>
                @can('permission', 'sms_automation.edit')
                <div class="sma-switch">
                    <div class="custom-control custom-switch" title="Switch this automation on or off">
                        <input type="checkbox" class="custom-control-input sms-auto-toggle" id="autoToggle{{ $automation->id }}" data-id="{{ $automation->id }}" {{ $automation->is_active ? 'checked' : '' }}>
                        <label class="custom-control-label" for="autoToggle{{ $automation->id }}"><span class="sr-only">Toggle {{ $automation->name }}</span></label>
                    </div>
                </div>
                @endcan
            </div>

            @if($automation->description)
                <p class="sma-desc">{{ $automation->description }}</p>
            @endif

            <div class="sma-msg">
                <div class="sma-bubble" title="{{ Str::limit($automation->message_body, 400) }}">{{ Str::limit($automation->message_body, 200) }}</div>
            </div>

            <div class="sma-foot">
                <span class="sma-sent"><i class="fas fa-paper-plane" style="font-size:10px;" aria-hidden="true"></i> <b>{{ number_format($automation->sent_count) }}</b> sent</span>
                <span class="sma-acts">
                    @can('permission', 'sms_automation.edit')
                    <a href="{{ url('admin/sms-automations/' . $automation->id . '/edit') }}" class="action-btn btn-edit" title="Edit" aria-label="Edit {{ $automation->name }}">
                        <i class="fas fa-pen"></i>
                    </a>
                    @endcan
                    @can('permission', 'sms_automation.delete')
                    <form action="{{ url('admin/sms-automations/' . $automation->id) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="action-btn btn-delete" title="Delete" aria-label="Delete {{ $automation->name }}" onclick="confirmDelete(event)">
                            <i class="far fa-trash-alt"></i>
                        </button>
                    </form>
                    @endcan
                </span>
            </div>
        </article>
    @empty
        <div class="sma-empty" style="grid-column: 1 / -1;">
            <div class="sma-empty-ico"><i class="fas fa-robot" aria-hidden="true"></i></div>
            <h6>No Automation Rules Yet</h6>
            <p>Create your first automation to send messages automatically when events occur.</p>
            @can('permission', 'sms_automation.create')
            <a href="{{ url('admin/sms-automations/create') }}" class="btn btn-primary btn-sm mt-3">
                <i class="fas fa-plus mr-1"></i> Create First Automation
            </a>
            @endcan
        </div>
    @endforelse
    </div>
    <div class="sma-nomatch" id="smaNoMatch"><i class="far fa-folder-open mr-1"></i> No automations match this filter.</div>

</div>

@push('scripts')
<script>
$(document).on('change', '.sms-auto-toggle', function () {
    var box = $(this).prop('disabled', true);
    $.ajax({
        url: '{{ url('admin/sms-automations') }}/' + box.data('id') + '/toggle', method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    }).done(function () { window.location.hash = 'automation-tab'; window.location.reload(); })
      .fail(function () { box.prop('checked', !box.prop('checked')).prop('disabled', false); });
});

// Filter automation cards (All / Active / Inactive / Essential)
$(document).on('click', '.sma-filter', function () {
    var f = $(this).data('filter'), shown = 0;
    $('.sma-filter').removeClass('is-on');
    $(this).addClass('is-on');
    $('#smaGrid .sma-card').each(function () {
        var $c = $(this), on = Number($c.data('active')) === 1, ess = Number($c.data('essential')) === 1;
        var show = f === 'all' || (f === 'active' && on) || (f === 'inactive' && !on) || (f === 'essential' && ess);
        $c.toggle(show);
        if (show) shown++;
    });
    $('#smaNoMatch').toggle(shown === 0);
});
</script>
@endpush
