<!-- Automation Tab -->
<div class="tab-pane fade p-3" id="automation-tab">

    <div class="crm-page-header" style="margin-bottom: 16px;">
        <div class="page-title-group">
            <h1 style="font-size: 16px;"><i class="fas fa-robot" style="color: var(--accent-500); margin-right: 6px;"></i>Automated Messages</h1>
            <p>Triggers fire automatically based on customer and lead activity</p>
        </div>
        <div class="header-actions">
            @can('permission', 'sms_automation.create')
            <a href="{{ url('admin/sms-automations/create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus mr-1"></i> New Automation
            </a>
            @endcan
        </div>
    </div>

    {{-- ── Cost control: mode switch, usage and cost ── --}}
    @php
        $modeInfo = [
            'all'       => ['All automations', 'Every active automation sends.', 'fa-bolt'],
            'essential' => ['Essential only', 'Appointments, tickets, repair and pickup messages. Welcome, marketing, warranty and points messages stop.', 'fa-star'],
            'off'       => ['Paused', 'No automatic SMS at all. Manual and campaign SMS still work.', 'fa-pause-circle'],
        ];
        $capPct = $usage['cap'] > 0 ? min(100, round($usage['cap_used'] / $usage['cap'] * 100)) : 0;
    @endphp
    <div class="crm-table-card" style="margin-bottom: 16px; padding: 16px 20px;" id="smsCostPanel">
        <div style="display:flex; flex-wrap:wrap; gap:24px; align-items:flex-start; justify-content:space-between;">
            <div style="min-width:260px; flex:1;">
                <div style="font-size:12px; font-weight:700; text-transform:uppercase; color:var(--page-text-muted); margin-bottom:8px;"><i class="fas fa-sliders-h mr-1"></i>Automatic SMS mode</div>
                @can('permission', 'sms_automation.edit')
                <form action="{{ route('sms-automations.mode') }}" method="POST" id="smsModeForm">
                    @csrf
                    <div class="btn-group btn-group-sm" role="group" style="flex-wrap:wrap;">
                        @foreach($modeInfo as $key => [$label, $hint, $icon])
                            <button type="submit" name="mode" value="{{ $key }}" title="{{ $hint }}"
                                    class="btn {{ $usage['mode'] === $key ? ($key === 'off' ? 'btn-danger' : 'btn-primary') : 'btn-outline-secondary' }}">
                                <i class="fas {{ $icon }} mr-1"></i>{{ $label }}
                            </button>
                        @endforeach
                    </div>
                    <div style="font-size:12px; color:var(--page-text-muted); margin-top:8px;">{{ $modeInfo[$usage['mode']][1] }}</div>
                    <div style="font-size:12px; margin-top:10px; display:flex; align-items:center; gap:6px;">
                        <label for="costPerSms" class="mb-0">Cost per SMS (৳):</label>
                        <input type="number" step="0.001" min="0" max="100" name="cost_per_sms" id="costPerSms" value="{{ $usage['rate'] }}" class="form-control form-control-sm" style="width:90px;">
                        <small class="text-muted">used only for the cost figures below</small>
                    </div>
                    <div style="font-size:12px; margin-top:8px; display:flex; align-items:center; gap:6px;">
                        <label for="lowBalance" class="mb-0">Warn me when the gateway balance is at or below:</label>
                        <input type="number" step="0.01" min="0" name="low_balance" id="lowBalance" value="{{ $gateway['threshold'] ?? '' }}" class="form-control form-control-sm" style="width:110px;" placeholder="e.g. 500">
                        <small class="text-muted">same unit as the gateway shows; empty = no warning</small>
                    </div>
                </form>
                @else
                    <strong>{{ $modeInfo[$usage['mode']][0] }}</strong>
                    <div style="font-size:12px; color:var(--page-text-muted);">{{ $modeInfo[$usage['mode']][1] }}</div>
                @endcan
            </div>

            <div style="display:flex; gap:24px; flex-wrap:wrap;">
                <div>
                    <div style="font-size:11px; color:var(--page-text-muted);">Today</div>
                    <div style="font-size:20px; font-weight:700;">{{ number_format($usage['today_parts']) }} <small style="font-size:11px; font-weight:500;">SMS</small></div>
                    <div style="font-size:12px;">≈ ৳{{ number_format($usage['today_cost'], 2) }}</div>
                </div>
                <div>
                    <div style="font-size:11px; color:var(--page-text-muted);">This month</div>
                    <div style="font-size:20px; font-weight:700;">{{ number_format($usage['month_parts']) }} <small style="font-size:11px; font-weight:500;">SMS</small></div>
                    <div style="font-size:12px;">≈ ৳{{ number_format($usage['month_cost'], 2) }}</div>
                </div>
                <div style="min-width:150px;">
                    <div style="font-size:11px; color:var(--page-text-muted);">Automatic SMS today (daily limit {{ $usage['cap'] }})</div>
                    <div style="height:8px; background:var(--gray-100); border-radius:6px; margin-top:8px; overflow:hidden;">
                        <div style="height:100%; width:{{ $capPct }}%; background:{{ $capPct >= 90 ? 'var(--danger-500)' : 'var(--accent-500)' }};"></div>
                    </div>
                    <div style="font-size:12px; margin-top:4px;">{{ $usage['cap_used'] }} used</div>
                </div>
            </div>
        </div>

        <details style="margin-top:14px;">
            <summary style="cursor:pointer; font-size:13px; font-weight:600;">Where this month's SMS went</summary>
            <div class="table-responsive mt-2">
                <table class="table table-sm" style="font-size:12.5px;">
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
        </details>
    </div>
    @forelse($automations as $automation)
        @php
            $trigMap = [
                'lead_created'        => ['color' => 'var(--success-500)', 'bg' => 'var(--success-50)',  'icon' => 'fas fa-user-plus',     'label_color' => 'var(--success-700)'],
                'customer_first_sale' => ['color' => 'var(--accent-500)',  'bg' => 'var(--accent-50)',   'icon' => 'fas fa-shopping-bag',  'label_color' => 'var(--accent-700)'],
                'appointment_reminder'=> ['color' => 'var(--warning-500)', 'bg' => 'var(--warning-50)',  'icon' => 'fas fa-calendar-alt',  'label_color' => 'var(--warning-700)'],
                'warranty_expiring'   => ['color' => '#6B4EBB',           'bg' => '#EEE9FF',            'icon' => 'fas fa-shield-alt',    'label_color' => '#6B4EBB'],
                'points_milestone'    => ['color' => 'var(--success-600)','bg' => 'var(--success-50)',   'icon' => 'fas fa-coins',         'label_color' => 'var(--success-700)'],
                'membership_upgrade'  => ['color' => '#7C3AAF',          'bg' => '#FDF0FF',             'icon' => 'fas fa-award',         'label_color' => '#7C3AAF'],
                'points_expiring'     => ['color' => 'var(--danger-500)', 'bg' => 'var(--danger-50)',   'icon' => 'fas fa-hourglass-half','label_color' => 'var(--danger-700)'],
                'customer_created'    => ['color' => 'var(--primary-500)','bg' => 'var(--primary-50)',  'icon' => 'fas fa-id-card',       'label_color' => 'var(--primary-700)'],
                'appointment_booked'  => ['color' => 'var(--warning-600)','bg' => 'var(--warning-50)',  'icon' => 'fas fa-calendar-check','label_color' => 'var(--warning-700)'],
                'ticket_created'      => ['color' => 'var(--info-500)',   'bg' => 'var(--info-50)',     'icon' => 'fas fa-tools',         'label_color' => 'var(--info-700)'],
                'ticket_solved'       => ['color' => 'var(--success-500)', 'bg' => 'var(--success-50)',  'icon' => 'fas fa-check-circle',  'label_color' => 'var(--success-700)'],
            ];
            $t = $trigMap[$automation->trigger_type] ?? ['color' => 'var(--gray-500)', 'bg' => 'var(--gray-50)', 'icon' => 'fas fa-bolt', 'label_color' => 'var(--gray-700)'];
        @endphp
        <div class="crm-table-card" style="margin-bottom: 12px; padding: 0;">
            <div style="display: grid; grid-template-columns: auto 1fr auto; gap: 16px; padding: 16px 20px; align-items: start;">
                {{-- Trigger badge --}}
                <div style="padding-top: 2px;">
                    <span style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 14px; border-radius: 20px; font-size: 11px; font-weight: 600; background: {{ $t['bg'] }}; color: {{ $t['label_color'] }}; white-space: nowrap;">
                        <i class="{{ $t['icon'] }}" style="font-size: 10px;"></i>
                        {{ \App\Enums\SmsAutomationTrigger::tryFrom($automation->trigger_type)?->label() ?? ucwords(str_replace('_', ' ', $automation->trigger_type)) }}
                    </span>
                </div>

                {{-- Info --}}
                <div style="min-width: 0;">
                    <div style="font-size: 14px; font-weight: 600; color: var(--page-text); margin-bottom: 2px;">
                        {{ $automation->name }}
                        @if(\App\Enums\SmsAutomationTrigger::tryFrom($automation->trigger_type)?->isEssential())
                            <span class="badge badge-info" style="font-weight:600; font-size:10px; margin-left:6px;" title="Kept when the mode is Essential only">Essential</span>
                        @endif
                        @if(!$automation->is_active)
                            <span class="crm-status-badge badge-status-lost" style="margin-left: 8px;"><span class="dot"></span>Inactive</span>
                        @else
                            <span class="crm-status-badge badge-status-won" style="margin-left: 8px;"><span class="dot"></span>Active</span>
                        @endif
                    </div>
                    @if($automation->description)
                    <div style="font-size: 12px; color: var(--page-text-muted); margin-bottom: 6px;">{{ $automation->description }}</div>
                    @endif
                    <div style="font-size: 12px; color: var(--page-text-secondary); background: var(--gray-50); padding: 8px 12px; border-radius: 8px; font-family: monospace; border: 1px solid var(--gray-100); line-height: 1.5; word-break: break-word;">
                        "{{ Str::limit($automation->message_body, 200) }}"
                    </div>
                </div>

                {{-- Actions --}}
                <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0; padding-top: 2px;">
                    <span style="font-size: 11px; color: var(--page-text-muted); white-space: nowrap; margin-right: 4px;">
                        <i class="fas fa-paper-plane" style="font-size: 9px;"></i> {{ $automation->sent_count }} sent
                    </span>
                    @can('permission', 'sms_automation.edit')
                    <div class="custom-control custom-switch" title="Switch this automation on or off">
                        <input type="checkbox" class="custom-control-input sms-auto-toggle" id="autoToggle{{ $automation->id }}" data-id="{{ $automation->id }}" {{ $automation->is_active ? 'checked' : '' }}>
                        <label class="custom-control-label" for="autoToggle{{ $automation->id }}"></label>
                    </div>
                    <a href="{{ url('admin/sms-automations/' . $automation->id . '/edit') }}" class="action-btn btn-edit" title="Edit">
                        <i class="fas fa-pen"></i>
                    </a>
                    @endcan
                    @can('permission', 'sms_automation.delete')
                    <form action="{{ url('admin/sms-automations/' . $automation->id) }}" method="POST" style="display:inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="action-btn btn-delete" title="Delete" onclick="confirmDelete(event)">
                            <i class="far fa-trash-alt"></i>
                        </button>
                    </form>
                    @endcan
                </div>
            </div>
        </div>
    @empty
        <div style="text-align: center; padding: 48px 20px;">
            <i class="fas fa-robot" style="font-size: 44px; display: block; margin-bottom: 14px; color: var(--gray-300);"></i>
            <h6 style="font-weight: 600; color: var(--page-text); margin-bottom: 6px;">No Automation Rules Yet</h6>
            <p style="font-size: 13px; color: var(--page-text-muted);">Create your first automation to send messages automatically when events occur.</p>
            @can('permission', 'sms_automation.create')
            <a href="{{ url('admin/sms-automations/create') }}" class="btn btn-primary btn-sm mt-2">
                <i class="fas fa-plus mr-1"></i> Create First Automation
            </a>
            @endcan
        </div>
    @endforelse

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
</script>
@endpush
