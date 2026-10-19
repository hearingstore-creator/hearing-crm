{{-- ═════════════════════════════════════════════════════════════════════ --}}
{{-- SUPER MY DAY & LOOKOUT HUB (Phase 3 Unified Command Center)          --}}
{{-- Combines personal actionable to-do queue with branch-wide monitoring. --}}
{{-- ═════════════════════════════════════════════════════════════════════ --}}
@php
    $acts     = $myDay['activities'] ?? collect();
    $appts    = $myDay['appointments'] ?? collect();
    $svcList  = $myDay['service_due'] ?? collect();
    $warrList = $myDay['warranty'] ?? collect();
    $bdayList = $myDay['birthdays'] ?? collect();
    $batList  = $myDay['battery'] ?? collect();
    $totalPersonal = $myDay['totalPersonal'] ?? ($acts->count() + $appts->count() + $svcList->count() + $warrList->count() + $bdayList->count() + $batList->count());
    $totalOverdue  = $myDay['overdue'] ?? 0;

    // Daily Lookout data for the Branch Lookout tab
    $dl       = $statistics['daily_lookout'] ?? [];
    $followUps= $dl['follow_ups'] ?? collect();
    $lookAppts= $dl['appointments'] ?? collect();
    $tasks    = $dl['tasks'] ?? collect();
    $tickets  = $dl['tickets'] ?? collect();
    $warranty = $dl['warranty_expiry'] ?? collect();
    $battery  = $dl['battery_expiry'] ?? collect();
    $anl      = $analytics ?? [];
    $okA      = fn ($k) => !empty($anl[$k]) && empty($anl[$k]['error']);
    $bdays    = $okA('birthdays') ? ($anl['birthdays']['list'] ?? []) : [];
    $upgList  = $okA('upgrade_due') ? ($anl['upgrade_due']['list'] ?? []) : [];
    $allSvc   = $okA('service_due') ? ($anl['service_due']['list'] ?? []) : [];

    $lookoutTabs = array_values(array_filter([
        $can('follow_ups')      ? ['followups', 'fas fa-phone-alt', 'Follow-ups', count($followUps), collect($followUps)->where('is_overdue', true)->count()] : null,
        $can('appointments')    ? ['appointments', 'far fa-calendar-check', 'Appointments', count($lookAppts), 0] : null,
        $can('total_tasks')     ? ['tasks', 'fas fa-tasks', 'Tasks', count($tasks), collect($tasks)->where('is_overdue', true)->count()] : null,
        $can('total_tickets')   ? ['tickets', 'fas fa-ticket-alt', 'Tickets', count($tickets), 0] : null,
        $can('warranty_alerts') ? ['warranty', 'fas fa-shield-alt', 'Warranty', count($warranty), 0] : null,
        $can('warranty_alerts') ? ['battery', 'fas fa-battery-half', 'Battery', count($battery), 0] : null,
        $okA('birthdays')       ? ['birthdays', 'fas fa-birthday-cake', 'Birthdays', count($bdays), collect($bdays)->where('in_days', 0)->count()] : null,
        $okA('upgrade_due')     ? ['upgrade', 'fas fa-sync-alt', 'Upgrade Due', count($upgList), 0] : null,
        $okA('service_due')     ? ['service', 'fas fa-tools', 'Service Due', count($allSvc), collect($allSvc)->where('stage', 'overdue')->count()] : null,
    ]));
    $lookoutTotalCount = array_sum(array_column($lookoutTabs, 3));

    $canTaskEdit   = $canP('task_list.edit');
    $canTicketEdit = $canP('ticket_list.edit');
    $canApptEdit   = $canP('appointment_list.edit');
@endphp

<section class="crm-table-card dash-fade myday" id="my-day" style="animation-delay:.06s; margin-bottom:24px;" aria-labelledby="my-day-title">
    {{-- Header Bar --}}
    <div class="myday-head">
        <div>
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <h2 id="my-day-title"><i class="fas fa-sun" style="color:var(--warning-500);"></i> My Day & Lookout</h2>
                <div class="myday-stat-pills">
                    <span class="md-pill" title="Personal tasks due today"><i class="fas fa-check-circle"></i> <b id="stat-todo-count">{{ $totalPersonal }}</b> to do</span>
                    @if ($totalOverdue > 0)
                        <span class="md-pill md-pill-danger" id="stat-overdue-pill" title="Items past due"><i class="fas fa-exclamation-triangle"></i> <b id="stat-overdue-count">{{ $totalOverdue }}</b> overdue</span>
                    @endif
                    @if ($myDay['tomorrow'] > 0)
                        <span class="md-pill md-pill-muted" title="Activities due tomorrow"><i class="far fa-clock"></i> {{ $myDay['tomorrow'] }} tomorrow</span>
                    @endif
                </div>
            </div>
            <p style="margin:4px 0 0; font-size:12px; color:var(--page-text-muted);">
                Your personal daily execution checklist & branch oversight for {{ now()->format('l, d M Y') }}
            </p>
        </div>

        {{-- Switcher: My Action List vs Branch Lookout --}}
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <div class="myday-segmented" id="myDaySwitcher">
                <button type="button" class="md-seg-btn active" data-view="personal" id="seg-btn-personal">
                    <i class="fas fa-user-check"></i> My Action List
                    <span class="md-seg-count" id="count-personal-badge">{{ $totalPersonal }}</span>
                </button>
                @if ($can('daily_lookout') && count($lookoutTabs))
                <button type="button" class="md-seg-btn" data-view="lookout" id="seg-btn-lookout">
                    <i class="fas fa-building"></i> Branch Lookout
                    <span class="md-seg-count">{{ $lookoutTotalCount }}</span>
                </button>
                @endif
            </div>

            @if ($myDay['canBoard'])
                <a href="{{ url('admin/deals') }}" class="btn btn-secondary btn-sm" id="myday-board" title="Open Deals Board">
                    <i class="fas fa-columns mr-1"></i>Deals board
                </a>
            @endif
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- VIEW 1: MY ACTION LIST (Active Personal Checklist)             --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    <div class="myday-view-pane active" id="pane-my-tasks">
        {{-- Category Filter Bar --}}
        <div class="md-filter-bar" id="mdCategoryChips">
            <button type="button" class="md-chip active" data-cat="all">
                All <span class="chip-n" id="chip-count-all">{{ $totalPersonal }}</span>
            </button>
            @if ($acts->count())
            <button type="button" class="md-chip" data-cat="deal">
                <i class="fas fa-phone-alt"></i> Calls & Deals <span class="chip-n">{{ $acts->count() }}</span>
            </button>
            @endif
            @if ($appts->count())
            <button type="button" class="md-chip" data-cat="appointment">
                <i class="far fa-calendar-check"></i> Appointments <span class="chip-n">{{ $appts->count() }}</span>
            </button>
            @endif
            @if ($svcList->count())
            <button type="button" class="md-chip chip-cyan" data-cat="service">
                <i class="fas fa-tools"></i> 6-Mo Service <span class="chip-n">{{ $svcList->count() }}</span>
            </button>
            @endif
            @if ($warrList->count())
            <button type="button" class="md-chip chip-amber" data-cat="warranty">
                <i class="fas fa-shield-alt"></i> Warranty Alert <span class="chip-n">{{ $warrList->count() }}</span>
            </button>
            @endif
            @if ($bdayList->count())
            <button type="button" class="md-chip chip-purple" data-cat="birthday">
                <i class="fas fa-birthday-cake"></i> Birthdays <span class="chip-n">{{ $bdayList->count() }}</span>
            </button>
            @endif
            @if ($batList->count())
            <button type="button" class="md-chip chip-amber" data-cat="battery">
                <i class="fas fa-battery-half"></i> Battery (15d) <span class="chip-n">{{ $batList->count() }}</span>
            </button>
            @endif
        </div>

        {{-- Actionable items list --}}
        <div class="myday-list" id="myday-list">
            {{-- 1. Appointments --}}
            @foreach ($appts as $ap)
                @php 
                    $slot = $ap->professionalTimeSlot?->timeSlot;
                    $phone = $ap->lead?->phone_number;
                    $waMsg = "Assalamu Alaikum " . ($ap->lead->full_name ?? 'Sir/Madam') . ". This is a reminder from Zenhear Hearing Care Centre regarding your appointment today with " . (Auth::user()->name ?? 'our specialist') . ". Please contact us if you need directions.";
                @endphp
                <div class="md-row is-appt" id="md-appt-{{ $ap->id }}" data-cat="appointment">
                    <span class="md-time">{{ $slot ? $slot->start_time->format('h:i A') : 'Today' }}</span>
                    <span class="md-ic"><i class="far fa-calendar-check"></i></span>
                    <a class="md-main" href="{{ url('admin/appointments/' . $ap->id . '/edit') }}" id="md-open-appt-{{ $ap->id }}">
                        <b>{{ $ap->lead->full_name ?? 'Appointment' }}</b>
                        <span><span class="md-type-tag tag-appt">Appointment</span> {{ $ap->service->name ?? 'Clinical Session' }}</span>
                    </a>
                    <div class="md-actions">
                        @if ($phone)
                            <a href="tel:{{ $phone }}" class="lk-btn call" title="Call {{ $phone }}" id="md-appt-call-{{ $ap->id }}"><i class="fas fa-phone"></i></a>
                            <a href="https://wa.me/{{ \App\Classes\Helper::normalizePhone($phone) }}?text={{ rawurlencode($waMsg) }}" target="_blank" rel="noopener" class="lk-btn wa" title="WhatsApp Reminder"><i class="fab fa-whatsapp"></i></a>
                        @endif
                        <a href="{{ url('admin/appointments/calendar') }}" class="lk-btn" title="Open calendar" id="md-appt-cal-{{ $ap->id }}"><i class="far fa-calendar-alt"></i></a>
                    </div>
                </div>
            @endforeach

            {{-- 2. Deal Activities --}}
            @foreach ($acts as $a)
                @php
                    $at = \Illuminate\Support\Carbon::parse($a->follow_up_date);
                    $over = $at->lt(today());
                    $phone = $a->lead?->phone_number;
                    $waMsg = "Assalamu Alaikum " . ($a->lead->full_name ?? 'Sir/Madam') . ". This is Zenhear Hearing Care Centre following up regarding your enquiry. How may we assist you with your hearing health today?";
                @endphp
                <div class="md-row {{ $over ? 'is-over' : '' }}" id="md-act-{{ $a->id }}" data-cat="deal" data-deal="{{ $a->deal_id }}">
                    <span class="md-time">{{ $over ? $at->format('d M') : $at->format('h:i A') }}</span>
                    <span class="md-ic"><i class="{{ $a->type->icon() }}"></i></span>
                    <a class="md-main" href="{{ $a->deal_id && $myDay['canBoard'] ? url('admin/deals?deal=' . $a->deal_id) : url('admin/leads/' . $a->lead_id) }}" id="md-open-{{ $a->id }}">
                        <b>{{ $a->lead->full_name ?? '—' }}</b>
                        <span>
                            <span class="md-type-tag tag-deal">{{ $a->type->label() }}</span>
                            @if ($a->deal?->stage)<i class="fas fa-circle" style="color: {{ $a->deal->stage->color }}; font-size: 7px; margin-left:4px;"></i> {{ $a->deal->stage->name }}@endif
                            {{ $a->follow_up_notes ? ' · ' . \Illuminate\Support\Str::limit($a->follow_up_notes, 65) : '' }}
                        </span>
                    </a>
                    @if ($over)<span class="li-badge md-badge-over">Overdue</span>@endif
                    <div class="md-actions">
                        @if ($phone)
                            <a href="tel:{{ $phone }}" class="lk-btn call js-contact" data-deal="{{ $a->deal_id }}" data-channel="call" title="Call {{ $phone }}" id="md-call-{{ $a->id }}"><i class="fas fa-phone"></i></a>
                            <a href="https://wa.me/{{ \App\Classes\Helper::normalizePhone($phone) }}?text={{ rawurlencode($waMsg) }}" target="_blank" rel="noopener" class="lk-btn wa js-contact" data-deal="{{ $a->deal_id }}" data-channel="whatsapp" title="WhatsApp {{ $phone }}" id="md-wa-{{ $a->id }}"><i class="fab fa-whatsapp"></i></a>
                        @endif
                        @if ($myDay['canDone'])
                            <button type="button" class="lk-btn ok js-md-done" data-id="{{ $a->id }}" title="Mark done" id="md-done-{{ $a->id }}"><i class="fas fa-check"></i></button>
                        @endif
                    </div>
                </div>
            @endforeach

            {{-- 3. Phase 3 After-Sales: 6-Month Service Due --}}
            @foreach ($svcList as $s)
                <div class="md-row {{ $s['is_overdue'] ? 'is-over' : '' }}" id="md-svc-{{ $s['customer_id'] }}" data-cat="service">
                    <span class="md-time {{ $s['is_overdue'] ? 'text-danger' : '' }}">{{ $s['time'] }}</span>
                    <span class="md-ic ic-service"><i class="fas fa-tools"></i></span>
                    <a class="md-main" href="{{ $s['url'] }}" title="Open Customer Profile">
                        <b>{{ $s['title'] }}</b>
                        <span><span class="md-type-tag tag-service">Service Due</span> {{ $s['subtitle'] }}</span>
                    </a>
                    @if ($s['is_overdue'])<span class="li-badge md-badge-over">Overdue</span>@endif
                    <div class="md-actions">
                        @if ($s['phone'])
                            <a href="tel:{{ $s['phone'] }}" class="lk-btn call" title="Call {{ $s['phone'] }}"><i class="fas fa-phone"></i></a>
                        @endif
                        @if ($s['whatsapp_url'])
                            <a href="{{ $s['whatsapp_url'] }}" target="_blank" rel="noopener" class="lk-btn wa" title="WhatsApp 6-Mo Invitation"><i class="fab fa-whatsapp"></i></a>
                        @endif
                        <button type="button" class="lk-btn ok js-after-sales-done" data-customer-id="{{ $s['customer_id'] }}" data-type="service_check" title="Mark service check completed"><i class="fas fa-check"></i></button>
                    </div>
                </div>
            @endforeach

            {{-- 4. Phase 3 After-Sales: Warranty Expiry --}}
            @foreach ($warrList as $w)
                <div class="md-row {{ $w['is_overdue'] ? 'is-over' : '' }}" id="md-warr-{{ $w['customer_id'] }}" data-cat="warranty">
                    <span class="md-time">{{ $w['time'] }}</span>
                    <span class="md-ic ic-warranty"><i class="fas fa-shield-alt"></i></span>
                    <a class="md-main" href="{{ $w['url'] }}" title="Open Customer Profile">
                        <b>{{ $w['title'] }}</b>
                        <span><span class="md-type-tag tag-warranty">Warranty Alert</span> {{ $w['subtitle'] }}</span>
                    </a>
                    @if ($w['is_overdue'])<span class="li-badge md-badge-over">Expired</span>@endif
                    <div class="md-actions">
                        @if ($w['phone'])
                            <a href="tel:{{ $w['phone'] }}" class="lk-btn call" title="Call {{ $w['phone'] }}"><i class="fas fa-phone"></i></a>
                        @endif
                        @if ($w['whatsapp_url'])
                            <a href="{{ $w['whatsapp_url'] }}" target="_blank" rel="noopener" class="lk-btn wa" title="WhatsApp Warranty Alert"><i class="fab fa-whatsapp"></i></a>
                        @endif
                        <button type="button" class="lk-btn ok js-after-sales-done" data-customer-id="{{ $w['customer_id'] }}" data-type="warranty_check" title="Mark warranty follow-up completed"><i class="fas fa-check"></i></button>
                    </div>
                </div>
            @endforeach

            {{-- 5. Phase 3 After-Sales: Birthdays Today --}}
            @foreach ($bdayList as $b)
                <div class="md-row" id="md-bday-{{ $b['customer_id'] ?? $b['lead_id'] }}" data-cat="birthday">
                    <span class="md-time" style="color:#9333ea; font-weight:800;">Today</span>
                    <span class="md-ic ic-birthday"><i class="fas fa-birthday-cake"></i></span>
                    <a class="md-main" href="{{ $b['url'] }}" title="Open Profile">
                        <b>{{ $b['title'] }}</b>
                        <span><span class="md-type-tag tag-birthday">Birthday</span> {{ $b['subtitle'] }}</span>
                    </a>
                    <div class="md-actions">
                        @if ($b['phone'])
                            <a href="tel:{{ $b['phone'] }}" class="lk-btn call" title="Call {{ $b['phone'] }}"><i class="fas fa-phone"></i></a>
                        @endif
                        @if ($b['whatsapp_url'])
                            <a href="{{ $b['whatsapp_url'] }}" target="_blank" rel="noopener" class="lk-btn wa" title="Send WhatsApp Birthday Greeting"><i class="fab fa-whatsapp"></i></a>
                        @endif
                        @if (!empty($b['customer_id']))
                            <button type="button" class="lk-btn ok js-after-sales-done" data-customer-id="{{ $b['customer_id'] }}" data-type="birthday_wish" title="Mark birthday greeting sent"><i class="fas fa-check"></i></button>
                        @endif
                    </div>
                </div>
            @endforeach

            {{-- 6. Phase 3 After-Sales: Battery Expiry (15 days) --}}
            @foreach ($batList as $bat)
                <div class="md-row {{ $bat['is_overdue'] ? 'is-over' : '' }}" id="md-bat-{{ $bat['customer_id'] ?? $loop->index }}" data-cat="battery">
                    <span class="md-time">{{ $bat['time'] }}</span>
                    <span class="md-ic ic-battery"><i class="fas fa-battery-half"></i></span>
                    <a class="md-main" href="{{ $bat['url'] }}" title="Open Customer Profile">
                        <b>{{ $bat['title'] }}</b>
                        <span><span class="md-type-tag tag-battery">Battery Expiry</span> {{ $bat['subtitle'] }}</span>
                    </a>
                    @if ($bat['is_overdue'])<span class="li-badge md-badge-over">Expired</span>@endif
                    <div class="md-actions">
                        @if ($bat['phone'])
                            <a href="tel:{{ $bat['phone'] }}" class="lk-btn call" title="Call {{ $bat['phone'] }}"><i class="fas fa-phone"></i></a>
                        @endif
                        @if ($bat['whatsapp_url'])
                            <a href="{{ $bat['whatsapp_url'] }}" target="_blank" rel="noopener" class="lk-btn wa" title="WhatsApp Battery Refill Reminder"><i class="fab fa-whatsapp"></i></a>
                        @endif
                        @if (!empty($bat['customer_id']))
                            <button type="button" class="lk-btn ok js-after-sales-done" data-customer-id="{{ $bat['customer_id'] }}" data-type="battery_reminder" title="Mark battery refill follow-up completed"><i class="fas fa-check"></i></button>
                        @endif
                    </div>
                </div>
            @endforeach

            @if ($totalPersonal === 0)
                <div class="lk-empty" id="myday-empty"><i class="fas fa-check-circle" style="color: var(--success-500);"></i>You're all caught up — no urgent tasks due today!</div>
            @endif
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════ --}}
    {{-- VIEW 2: BRANCH LOOKOUT (Team & Monitoring Overview)           --}}
    {{-- ═══════════════════════════════════════════════════════════════ --}}
    @if ($can('daily_lookout') && count($lookoutTabs))
    <div class="myday-view-pane" id="pane-branch-lookout" style="display:none;">
        <div style="padding:0 18px;">
            <div class="lookout-tabs" id="lookoutTabs">
                @foreach ($lookoutTabs as $i => [$key, $icon, $title, $count, $hot])
                    <button type="button" class="lookout-tab {{ $i === 0 ? 'active' : '' }}" data-tab="{{ $key }}" id="tab-{{ $key }}">
                        <i class="{{ $icon }}" style="font-size:11px;"></i> {{ $title }}
                        <span class="tab-count {{ $hot ? 'hot' : '' }}" data-count-for="{{ $key }}">{{ $count }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Follow-ups --}}
        @if ($can('follow_ups'))
        <div class="lookout-panel {{ $lookoutTabs[0][0] === 'followups' ? 'active' : '' }}" id="panel-followups">
            @forelse ($followUps as $f)
                @php $tone = $f['is_overdue'] ? 'danger' : ($f['is_today'] ? 'warning' : 'accent'); @endphp
                <div class="lk" data-row="followups">
                    <div class="li-icon" style="background:var(--{{ $tone }}-50);color:var(--{{ $tone }}-600);"><i class="fas fa-phone-alt"></i></div>
                    <a class="li-main" href="{{ url('admin/leads/' . $f['lead_id']) }}" title="Open lead profile">
                        <div class="li-title">{{ $f['lead'] }}</div>
                        <div class="li-sub">{{ $f['mode'] }} · {{ $f['time'] }} · {{ $f['assigned_to'] }}{{ $f['notes'] ? ' · ' . \Illuminate\Support\Str::limit($f['notes'], 50) : '' }}</div>
                    </a>
                    <span class="li-badge" style="background:var(--{{ $tone }}-50);color:var(--{{ $tone }}-{{ $tone === 'accent' ? '700' : '600' }});">{{ $f['is_overdue'] ? 'Overdue · ' . $f['date'] : ($f['is_today'] ? 'Today' : $f['date']) }}</span>
                    <div class="lk-actions">
                        @if ($f['phone'])<a href="tel:{{ $f['phone'] }}" class="lk-btn call" title="Call {{ $f['phone'] }}"><i class="fas fa-phone"></i></a>@endif
                        <button type="button" class="lk-btn ok js-complete-followup" data-id="{{ $f['id'] }}" data-name="{{ $f['lead'] }}" title="Mark as complete"><i class="fas fa-check"></i></button>
                    </div>
                </div>
            @empty
                <div class="lk-empty"><i class="fas fa-check-circle" style="color:var(--success-500);"></i>No follow-ups due — branch is all caught up</div>
            @endforelse
            @if (count($followUps))<a href="{{ url('admin/leads') }}" class="lk-more">Go to leads →</a>@endif
        </div>
        @endif

        {{-- Appointments --}}
        @if ($can('appointments'))
        <div class="lookout-panel {{ $lookoutTabs[0][0] === 'appointments' ? 'active' : '' }}" id="panel-appointments">
            @forelse ($lookAppts as $a)
                <div class="lk" data-row="appointments">
                    <div class="li-icon" style="background:var(--warning-50);color:var(--warning-600);"><i class="far fa-calendar-check"></i></div>
                    <a class="li-main" href="{{ $canApptEdit ? url('admin/appointments/' . $a['id'] . '/edit') : url('admin/appointments') }}">
                        <div class="li-title">{{ $a['customer'] }}</div>
                        <div class="li-sub">{{ $a['date'] }}{{ $a['phone'] ? ' · ' . $a['phone'] : '' }}</div>
                    </a>
                    <div class="lk-actions">
                        @if ($a['phone'])<a href="tel:{{ $a['phone'] }}" class="lk-btn call" title="Call"><i class="fas fa-phone"></i></a>@endif
                        @if ($canApptEdit)
                            <select class="lk-select js-status" data-url="{{ url('admin/appointments/' . $a['id'] . '/status') }}" data-method="POST" data-done="completed,cancelled" title="Update status">
                                @foreach (\App\Enums\AppointmentStatus::cases() as $st)
                                    <option value="{{ $st->value }}" {{ $a['status'] === $st->value ? 'selected' : '' }}>{{ ucfirst($st->value) }}</option>
                                @endforeach
                            </select>
                        @else
                            <span class="li-badge" style="background:var(--accent-50);color:var(--accent-700);">{{ ucfirst($a['status']) }}</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="lk-empty"><i class="far fa-calendar" style="color:var(--page-text-muted);"></i>No branch appointments today</div>
            @endforelse
            <a href="{{ url('admin/appointments/calendar') }}" class="lk-more">Open calendar →</a>
        </div>
        @endif

        {{-- Tasks --}}
        @if ($can('total_tasks'))
        <div class="lookout-panel {{ $lookoutTabs[0][0] === 'tasks' ? 'active' : '' }}" id="panel-tasks">
            @forelse ($tasks as $t)
                @php $tone = $t['is_overdue'] ? 'danger' : 'accent'; @endphp
                <div class="lk" data-row="tasks">
                    <div class="li-icon" style="background:var(--{{ $tone }}-50);color:var(--{{ $tone }}-600);"><i class="fas fa-tasks"></i></div>
                    <a class="li-main" href="{{ $canTaskEdit ? url('admin/task/' . $t['id'] . '/edit') : url('admin/task') }}">
                        <div class="li-title">{{ $t['subject'] }}</div>
                        <div class="li-sub">{{ $t['assigned_to'] }} · Due {{ $t['due_date'] }} · {{ ucfirst($t['priority']) }} priority</div>
                    </a>
                    @if ($t['is_overdue'])<span class="li-badge" style="background:var(--danger-50);color:var(--danger-600);">Overdue</span>@endif
                    <div class="lk-actions">
                        @if ($canTaskEdit)
                            <select class="lk-select js-status" data-url="{{ url('admin/task/' . $t['id'] . '/status') }}" data-method="PATCH" data-done="completed,cancelled" title="Update status">
                                @foreach (\App\Enums\TaskStatus::cases() as $st)
                                    <option value="{{ $st->value }}" {{ $t['status'] === $st->value ? 'selected' : '' }}>{{ $label($st->value) }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="lk-btn ok js-quick" data-url="{{ url('admin/task/' . $t['id'] . '/status') }}" data-method="PATCH" data-status="completed" title="Mark task complete"><i class="fas fa-check"></i></button>
                        @else
                            <span class="li-badge" style="background:var(--warning-50);color:var(--warning-600);">{{ $label($t['status']) }}</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="lk-empty"><i class="fas fa-check-circle" style="color:var(--success-500);"></i>No pending branch tasks</div>
            @endforelse
            <a href="{{ url('admin/task') }}" class="lk-more">All tasks →</a>
        </div>
        @endif

        {{-- Tickets --}}
        @if ($can('total_tickets'))
        <div class="lookout-panel {{ $lookoutTabs[0][0] === 'tickets' ? 'active' : '' }}" id="panel-tickets">
            @forelse ($tickets as $tk)
                <div class="lk" data-row="tickets">
                    <div class="li-icon" style="background:var(--danger-50);color:var(--danger-600);"><i class="fas fa-ticket-alt"></i></div>
                    <a class="li-main" href="{{ $canTicketEdit ? url('admin/ticket/' . $tk['id'] . '/edit') : url('admin/ticket') }}">
                        <div class="li-title">{{ $tk['subject'] }}</div>
                        <div class="li-sub">{{ $tk['customer'] ? $tk['customer'] . ' · ' : '' }}{{ $tk['assigned_to'] }} · {{ $tk['created'] }} · {{ ucfirst($tk['priority']) }}</div>
                    </a>
                    <div class="lk-actions">
                        @if ($canTicketEdit)
                            <select class="lk-select js-status" data-url="{{ url('admin/ticket/' . $tk['id'] . '/status') }}" data-method="PATCH" data-done="Solved,Closed" title="Update status">
                                @foreach (\App\Enums\TicketStatus::cases() as $st)
                                    <option value="{{ $st->value }}" {{ $tk['status'] === $st->value ? 'selected' : '' }}>{{ $st->value }}</option>
                                @endforeach
                            </select>
                        @else
                            <span class="li-badge" style="background:var(--warning-50);color:var(--warning-600);">{{ $tk['status'] }}</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="lk-empty"><i class="fas fa-check-circle" style="color:var(--success-500);"></i>No active tickets</div>
            @endforelse
            <a href="{{ url('admin/ticket') }}" class="lk-more">All tickets →</a>
        </div>
        @endif

        {{-- Warranty / Battery --}}
        @if ($can('warranty_alerts'))
            @foreach (['warranty' => [$warranty, 'fas fa-shield-alt', 'Warranty expires'], 'battery' => [$battery, 'fas fa-battery-half', 'Battery usage expires']] as $key => [$rows, $icon, $txt])
            <div class="lookout-panel {{ $lookoutTabs[0][0] === $key ? 'active' : '' }}" id="panel-{{ $key }}">
                @forelse ($rows as $w)
                    @php $tone = $w['days_left'] <= 7 ? 'danger' : 'warning'; @endphp
                    <div class="lk">
                        <div class="li-icon" style="background:var(--{{ $tone }}-50);color:var(--{{ $tone }}-600);"><i class="{{ $icon }}"></i></div>
                        <a class="li-main" href="{{ $w['customer_id'] ? url('admin/customers/' . $w['customer_id']) : '#' }}" title="Open customer">
                            <div class="li-title">{{ $w['customer'] }} — {{ $w['product'] }}</div>
                            <div class="li-sub">{{ $key === 'battery' ? 'Battery ' . $w['battery_size'] . ' · ' : '' }}{{ $txt }} {{ $w['expiry_date'] }}</div>
                        </a>
                        <span class="li-badge" style="background:var(--{{ $tone }}-50);color:var(--{{ $tone }}-600);">{{ $w['days_left'] }}d left</span>
                        <div class="lk-actions">
                            @if ($w['phone'])<a href="tel:{{ $w['phone'] }}" class="lk-btn call" title="Call"><i class="fas fa-phone"></i></a>@endif
                            <a href="{{ url('admin/sale/' . $w['sale_id']) }}" class="lk-btn" title="Open invoice"><i class="fas fa-file-invoice"></i></a>
                        </div>
                    </div>
                @empty
                    <div class="lk-empty"><i class="{{ $icon }}" style="color:var(--success-500);"></i>No {{ $key }} alerts in the next {{ $key === 'battery' ? '15' : '30' }} days</div>
                @endforelse
                <a href="{{ url($key === 'warranty' ? 'admin/report/warranty-expiry' : 'admin/report/battery-usage-expiry') }}" class="lk-more">Full report →</a>
            </div>
            @endforeach
        @endif

        {{-- Birthdays --}}
        @if ($okA('birthdays'))
        <div class="lookout-panel {{ $lookoutTabs[0][0] === 'birthdays' ? 'active' : '' }}" id="panel-birthdays">
            @forelse ($bdays as $b)
                @php $tone = $b['in_days'] === 0 ? 'danger' : 'accent'; @endphp
                <div class="lk">
                    <div class="li-icon" style="background:var(--{{ $tone }}-50);color:var(--{{ $tone }}-600);"><i class="fas fa-birthday-cake"></i></div>
                    <a class="li-main" href="{{ $b['url'] }}" title="Open profile">
                        <div class="li-title">{{ $b['name'] }}</div>
                        <div class="li-sub">{{ $b['date_label'] }} · turns {{ $b['turning'] }}{{ $b['customer_id'] ? ' · customer' : ' · lead' }}</div>
                    </a>
                    <span class="li-badge" style="background:var(--{{ $tone }}-50);color:var(--{{ $tone }}-{{ $tone === 'accent' ? '700' : '600' }});">{{ $b['in_days'] === 0 ? 'Today' : ($b['in_days'] === 1 ? 'Tomorrow' : 'in ' . $b['in_days'] . 'd') }}</span>
                    <div class="lk-actions">
                        @if ($b['phone'])
                            <a href="tel:{{ $b['phone'] }}" class="lk-btn call" title="Call"><i class="fas fa-phone"></i></a>
                            <a href="sms:{{ $b['phone'] }}?body={{ rawurlencode('Happy Birthday ' . $b['name'] . '! Wishing you a wonderful year ahead.') }}" class="lk-btn" title="Send birthday SMS"><i class="fas fa-sms"></i></a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="lk-empty"><i class="fas fa-birthday-cake" style="color:var(--page-text-muted);"></i>No birthdays in the next 7 days</div>
            @endforelse
            <a href="#w-birthdays" class="lk-more">Birthday widget →</a>
        </div>
        @endif

        {{-- Upgrade Due --}}
        @if ($okA('upgrade_due'))
        <div class="lookout-panel {{ $lookoutTabs[0][0] === 'upgrade' ? 'active' : '' }}" id="panel-upgrade">
            @forelse ($upgList as $d)
                @php $tone = $d['stage'] === 'due' ? 'danger' : 'warning'; @endphp
                <div class="lk">
                    <div class="li-icon" style="background:var(--{{ $tone }}-50);color:var(--{{ $tone }}-600);"><i class="fas fa-sync-alt"></i></div>
                    <a class="li-main" href="{{ url('admin/customers/' . $d['customer_id']) }}" title="Open customer">
                        <div class="li-title">{{ $d['name'] }} — {{ $d['model'] }}</div>
                        <div class="li-sub">Bought {{ $d['date_label'] }}{{ $d['phone'] ? ' · ' . $d['phone'] : '' }}</div>
                    </a>
                    <span class="li-badge" style="background:var(--{{ $tone }}-50);color:var(--{{ $tone }}-600);">{{ $d['age_label'] }}</span>
                    <div class="lk-actions">
                        @if ($d['phone'])<a href="tel:{{ $d['phone'] }}" class="lk-btn call" title="Call"><i class="fas fa-phone"></i></a>@endif
                        <a href="{{ url('admin/sale/' . $d['sale_id']) }}" class="lk-btn" title="Open invoice"><i class="fas fa-file-invoice"></i></a>
                    </div>
                </div>
            @empty
                <div class="lk-empty"><i class="fas fa-check-circle" style="color:var(--success-500);"></i>No devices due for upgrade</div>
            @endforelse
            <a href="#w-upgrade_due" class="lk-more">Upgrade widget →</a>
        </div>
        @endif

        {{-- Service Due --}}
        @if ($okA('service_due'))
        <div class="lookout-panel {{ $lookoutTabs[0][0] === 'service' ? 'active' : '' }}" id="panel-service">
            @forelse ($allSvc as $s)
                @php $tone = $s['stage'] === 'overdue' ? 'danger' : 'warning'; @endphp
                <div class="lk">
                    <div class="li-icon" style="background:var(--{{ $tone }}-50);color:var(--{{ $tone }}-600);"><i class="fas fa-tools"></i></div>
                    <a class="li-main" href="{{ url('admin/customers/' . $s['customer_id']) }}" title="Open customer">
                        <div class="li-title">{{ $s['name'] }} — {{ $s['model'] }}</div>
                        <div class="li-sub">Service due {{ $s['due_label'] }} · every {{ $s['interval'] }} months</div>
                    </a>
                    <span class="li-badge" style="background:var(--{{ $tone }}-50);color:var(--{{ $tone }}-600);">{{ $s['days'] < 0 ? abs($s['days']) . 'd late' : ($s['days'] === 0 ? 'Today' : 'in ' . $s['days'] . 'd') }}</span>
                    <div class="lk-actions">
                        @if ($s['phone'])<a href="tel:{{ $s['phone'] }}" class="lk-btn call" title="Call"><i class="fas fa-phone"></i></a>@endif
                        <a href="{{ url('admin/appointments/calendar') }}" class="lk-btn" title="Book appointment"><i class="far fa-calendar-plus"></i></a>
                    </div>
                </div>
            @empty
                <div class="lk-empty"><i class="fas fa-check-circle" style="color:var(--success-500);"></i>No devices due for service</div>
            @endforelse
            <a href="#w-service_due" class="lk-more">Service widget →</a>
        </div>
        @endif
    </div>
    @endif
</section>
