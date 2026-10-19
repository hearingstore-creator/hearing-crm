{{-- Compact My Day list for the Today dashboard. $myDay = HomeController::myDay() --}}
@php
    $rows = collect();

    foreach ($myDay['activities'] ?? [] as $a) {
        $at = \Illuminate\Support\Carbon::parse($a->follow_up_date);
        $over = $at->lt(today());
        $phone = $a->lead?->phone_number;
        $name = $a->lead?->full_name ?? ('Lead #' . $a->lead_id);
        $waMsg = "Assalamu Alaikum {$name}. This is Zenhear Hearing Care Centre following up regarding your enquiry. How may we assist you with your hearing health today?";
        $sub = $a->type?->label() ?? 'Follow-up';
        if ($a->deal?->stage) {
            $sub .= ' · ' . $a->deal->stage->name;
        }
        if ($a->follow_up_notes) {
            $sub .= ' · ' . \Illuminate\Support\Str::limit($a->follow_up_notes, 55);
        }

        $rows->push([
            'id'          => $a->id,
            'deal_id'     => $a->deal_id,
            'cat'         => 'deal',
            'icon'        => $a->type?->icon() ?? 'fas fa-phone-alt',
            'title'       => $name,
            'sub'         => $sub,
            'badge'       => $over ? ($at->diffForHumans(today(), true) . ' late') : $at->format('h:i A'),
            'tone'        => $over ? 'danger' : 'deal',
            'over'        => $over,
            'url'         => $a->deal_id && ($myDay['canBoard'] ?? false) ? url('admin/deals?deal=' . $a->deal_id) : url('admin/leads/' . $a->lead_id),
            'phone'       => $phone,
            'wa'          => $phone ? 'https://wa.me/' . \App\Classes\Helper::normalizePhone($phone) . '?text=' . rawurlencode($waMsg) : null,
            'can_done'    => (bool) ($myDay['canDone'] ?? true),
            'action_type' => 'deal_activity',
            'customer_id' => null,
            'after_sales' => null,
        ]);
    }

    foreach ($myDay['appointments'] ?? [] as $ap) {
        $slot = $ap->professionalTimeSlot?->timeSlot;
        $timeStr = $slot ? \Illuminate\Support\Carbon::parse($slot->start_time)->format('h:i A') : 'Today';
        $phone = $ap->lead?->phone_number;
        $name = $ap->lead?->full_name ?? 'Appointment';
        $waMsg = "Assalamu Alaikum {$name}. Gentle reminder from Zenhear Hearing Care Centre regarding your appointment today at {$timeStr}. Please let us know if you need directions.";

        $rows->push([
            'id'          => $ap->id,
            'deal_id'     => null,
            'cat'         => 'appointment',
            'icon'        => 'far fa-calendar-check',
            'title'       => $name,
            'sub'         => ($ap->service?->name ?? 'Clinical Session') . ' · ' . ($ap->status?->label() ?? 'Scheduled'),
            'badge'       => $timeStr,
            'tone'        => 'appointment',
            'over'        => false,
            'url'         => url('admin/appointments/' . $ap->id . '/edit'),
            'phone'       => $phone,
            'wa'          => $phone ? 'https://wa.me/' . \App\Classes\Helper::normalizePhone($phone) . '?text=' . rawurlencode($waMsg) : null,
            'can_done'    => false,
            'action_type' => 'appointment',
            'customer_id' => null,
            'after_sales' => null,
        ]);
    }

    $careIcons = [
        'service_due' => ['icon' => 'fas fa-tools', 'cat' => 'care', 'default_tone' => 'amber'],
        'warranty'    => ['icon' => 'fas fa-shield-alt', 'cat' => 'care', 'default_tone' => 'cyan'],
        'birthdays'   => ['icon' => 'fas fa-birthday-cake', 'cat' => 'care', 'default_tone' => 'purple'],
        'battery'     => ['icon' => 'fas fa-battery-half', 'cat' => 'care', 'default_tone' => 'amber'],
    ];

    foreach ($careIcons as $key => $meta) {
        foreach ($myDay[$key] ?? [] as $it) {
            $isOver = (bool) ($it['is_overdue'] ?? false);
            $rows->push([
                'id'          => $it['customer_id'] ?? null,
                'customer_id' => $it['customer_id'] ?? null,
                'deal_id'     => null,
                'cat'         => 'care',
                'icon'        => $meta['icon'],
                'title'       => $it['title'] ?? '',
                'sub'         => $it['subtitle'] ?? '',
                'badge'       => $it['badge'] ?? '',
                'tone'        => $isOver ? 'danger' : ($it['badge_tone'] ?? $meta['default_tone']),
                'over'        => $isOver,
                'url'         => $it['url'] ?? '#',
                'phone'       => $it['phone'] ?? null,
                'wa'          => $it['whatsapp_url'] ?? null,
                'can_done'    => (bool) ($it['can_done'] ?? false),
                'action_type' => 'after_sales',
                'after_sales' => $it['after_sales'] ?? null,
            ]);
        }
    }

    // My support tickets and tasks
    foreach ($myDay['tickets'] ?? [] as $t) {
        $state = $t->dueState();
        $lead = $t->customer?->lead;
        $rows->push([
            'id' => $t->id, 'deal_id' => null, 'cat' => 'support', 'icon' => 'fas fa-ticket-alt',
            'title' => $t->number . ' · ' . $t->subject,
            'sub' => ($lead->full_name ?? 'Unknown patient') . ' · ' . $t->priority->value . ' · ' . $t->status->value,
            'badge' => $state['label'], 'tone' => $state['tone'] === 'muted' ? '' : $state['tone'], 'over' => $state['state'] === 'overdue',
            'url' => url('admin/ticket/' . $t->id), 'phone' => $lead?->phone_number, 'wa' => null,
            'can_done' => false, 'action_type' => 'ticket', 'customer_id' => null, 'after_sales' => null,
        ]);
    }
    foreach ($myDay['tasks'] ?? [] as $tk) {
        $days = (int) today()->diffInDays($tk->due_date, false);
        $lead = $tk->customer?->lead;
        $rows->push([
            'id' => $tk->id, 'deal_id' => null, 'cat' => 'support', 'icon' => 'fas fa-tasks',
            'title' => $tk->subject,
            'sub' => 'Task · ' . ($lead->full_name ?? 'No patient') . ' · due ' . $tk->due_date->format('d M'),
            'badge' => $days < 0 ? 'Overdue ' . abs($days) . 'd' : ($days === 0 ? 'Due today' : 'Due tomorrow'), 'tone' => $days < 0 ? 'danger' : ($days === 0 ? 'warning' : ''), 'over' => $days < 0,
            'url' => url('admin/task/' . $tk->id . '/edit'), 'phone' => $lead?->phone_number, 'wa' => null,
            'can_done' => false, 'action_type' => 'task', 'customer_id' => null, 'after_sales' => null,
        ]);
    }

    $rows = $rows->sortByDesc('over')->values();
    $shown = $rows->take(16);

    $totalCount = $myDay['totalPersonal'] ?? $rows->count();
    $overdueCount = $myDay['overdue'] ?? $rows->where('over', true)->count();
    $tomorrowCount = $myDay['tomorrow'] ?? 0;

    $dealCount = $rows->where('cat', 'deal')->count();
    $apptCount = $rows->where('cat', 'appointment')->count();
    $careCount = $rows->where('cat', 'care')->count();
    $supportCount = $rows->where('cat', 'support')->count();
@endphp

<div class="md-sum">
    <span class="md-pill-todo"><i class="fas fa-check-circle" style="color:#4f46e5;"></i> <b id="md-cnt-todo">{{ $totalCount }}</b> to do</span>
    @if ($overdueCount > 0)
        <span class="md-pill-danger" id="md-pill-overdue"><span class="md-dot-danger"></span><b id="md-cnt-overdue">{{ $overdueCount }}</b> overdue</span>
    @endif
    @if ($tomorrowCount > 0)
        <span class="md-pill-muted"><i class="far fa-clock"></i> <b>{{ $tomorrowCount }}</b> due tomorrow</span>
    @endif
</div>

@if ($rows->count() > 0)
    <div class="md-filter-bar" id="mdFilterBar">
        <button type="button" class="md-chip active" data-cat="all">All <span class="chip-n">{{ $rows->count() }}</span></button>
        @if ($dealCount > 0)
            <button type="button" class="md-chip" data-cat="deal"><i class="fas fa-phone-alt"></i>Calls & Deals <span class="chip-n">{{ $dealCount }}</span></button>
        @endif
        @if ($apptCount > 0)
            <button type="button" class="md-chip" data-cat="appointment"><i class="far fa-calendar-check"></i>Appointments <span class="chip-n">{{ $apptCount }}</span></button>
        @endif
        @if ($supportCount > 0)
            <button type="button" class="md-chip" data-cat="support"><i class="fas fa-headset"></i>Tickets &amp; tasks <span class="chip-n">{{ $supportCount }}</span></button>
        @endif
        @if ($careCount > 0)
            <button type="button" class="md-chip" data-cat="care"><i class="fas fa-heart"></i>Care & Service <span class="chip-n">{{ $careCount }}</span></button>
        @endif
    </div>
@endif

@if ($shown->isEmpty())
    <div class="td-empty"><i class="fas fa-check-circle mr-2" style="color:#059669; font-size:16px;"></i>Nothing waiting for you today. All caught up!</div>
@else
    <div class="md-list" id="mdItemList">
        @foreach ($shown as $i => $r)
            <div class="md-item {{ $r['over'] ? 'is-over' : '' }}" id="td-md-{{ $i }}" data-cat="{{ $r['cat'] }}" data-id="{{ $r['id'] }}">
                <span class="md-i ic-{{ $r['cat'] }}"><i class="{{ $r['icon'] }}"></i></span>
                
                <a class="md-t" href="{{ $r['url'] }}" title="{{ $r['title'] }}">
                    <div class="md-t-row">
                        <b class="md-t-name">{{ $r['title'] }}</b>
                        @if ($r['badge'])
                            <span class="md-badge {{ $r['tone'] }}">{{ $r['badge'] }}</span>
                        @endif
                    </div>
                    <span class="md-t-sub">{{ $r['sub'] }}</span>
                </a>

                <div class="md-actions">
                    @if (!empty($r['phone']))
                        <a href="tel:{{ $r['phone'] }}" class="lk-btn call js-contact" @if($r['deal_id']) data-deal="{{ $r['deal_id'] }}" data-channel="call" @endif title="Call {{ $r['phone'] }}"><i class="fas fa-phone"></i></a>
                    @endif
                    @if (!empty($r['wa']))
                        <a href="{{ $r['wa'] }}" target="_blank" rel="noopener" class="lk-btn wa js-contact" @if($r['deal_id']) data-deal="{{ $r['deal_id'] }}" data-channel="whatsapp" @endif title="Open WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    @endif
                    @if ($r['cat'] === 'appointment')
                        <a href="{{ url('admin/appointments/calendar') }}" class="lk-btn cal" title="Open Calendar"><i class="far fa-calendar-alt"></i></a>
                    @endif
                    @if ($r['can_done'] && $r['action_type'] === 'deal_activity')
                        <button type="button" class="lk-btn ok js-md-done" data-id="{{ $r['id'] }}" title="Mark done"><i class="fas fa-check"></i></button>
                    @elseif ($r['can_done'] && $r['action_type'] === 'after-sales')
                        <button type="button" class="lk-btn ok js-after-sales-done" data-customer-id="{{ $r['customer_id'] }}" data-type="{{ $r['after_sales'] }}" title="Record care completed"><i class="fas fa-check"></i></button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    @if ($rows->count() > $shown->count())
        <div class="td-note" style="margin:10px 0 0; text-align:center;">
            <a href="{{ route('dashboard.legacy') }}#my-day" style="color:var(--accent-600); font-weight:600; text-decoration:none;">
                +{{ $rows->count() - $shown->count() }} more items on the full list &rarr;
            </a>
        </div>
    @endif
@endif
