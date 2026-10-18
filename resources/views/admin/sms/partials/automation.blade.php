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
