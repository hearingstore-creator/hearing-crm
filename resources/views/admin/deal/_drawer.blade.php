{{-- Deal drawer (loaded by AJAX into #deal-drawer). --}}
@php
    $lead = $deal->lead;
    $money = fn ($v) => '৳' . number_format((float) $v);
    $pending = $deal->activities->where('status', 'pending')->sortBy('follow_up_date')->values();
    $temp = \App\Enums\LeadTemperature::tryFrom((string) $deal->temperature);
    $closed = !$deal->isOpen();
@endphp
<div class="dd-head" style="--stage-color: {{ $deal->stage->color ?? '#6366f1' }};">
    <div class="dd-head-top">
        <div class="dd-avatar">{{ strtoupper(mb_substr($lead->full_name ?? 'D', 0, 1)) }}</div>
        <div class="dd-ident">
            <h2 id="dd-title">{{ $lead->full_name ?? '—' }}</h2>
            <div class="dd-sub">
                <a href="tel:{{ $lead->phone_number }}" id="dd-call" @if ($can['move'] && !$closed) class="js-contact" data-deal="{{ $deal->id }}" data-channel="call" @endif><i class="fas fa-phone-alt"></i> {{ $lead->phone_number }}</a>
                @if ($lead->phone_number)
                    <a href="https://wa.me/{{ \App\Classes\Helper::normalizePhone($lead->phone_number) }}" target="_blank" rel="noopener" id="dd-wa" class="dd-wa{{ $can['move'] && !$closed ? ' js-contact' : '' }}" data-deal="{{ $deal->id }}" data-channel="whatsapp" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                @endif
                <span>· {{ $lead->lead_uid }}</span>
                @if ($temp)<span class="dc-temp t-{{ $temp->value }}" title="{{ $temp->label() }}"><i class="{{ $temp->icon() }}"></i></span>@endif
            </div>
        </div>
        <button type="button" class="dd-close" id="dd-close" aria-label="Close"><i class="fas fa-times"></i></button>
    </div>

    <div class="dd-facts">
        <div><span>Deal</span><b>{{ $deal->display_title }}</b></div>
        <div><span>Value</span><b>{{ $deal->value ? $money($deal->value) : '—' }}</b></div>
        <div><span>Branch · Owner</span><b>{{ $deal->vendor->name ?? 'Head Office' }} · {{ $deal->owner->name ?? 'Unassigned' }}</b></div>
        <div>
            <span>Membership</span>
            @if ($customer)
                <b><i class="fas fa-crown" style="color: #f59e0b;"></i> {{ $customer->membershipTier->name ?? 'Customer' }}
                    · {{ number_format((float) $points) }} pts</b>
            @else
                <b style="color: var(--page-text-muted);">Not a customer yet</b>
            @endif
        </div>
        @php $ears = $lead?->hearingEars() ?? []; @endphp
        <div class="dd-hearing" id="dd-hearing">
            <span><i class="fas fa-assistive-listening-systems"></i> Hearing loss</span>
            @forelse ($ears as $e)
                <b class="dd-ear"><em>{{ $e['ear'] }}</em> {{ implode(' · ', array_filter([$e['degree'], $e['type']])) ?: 'degree not recorded' }}</b>
            @empty
                <b style="color: var(--page-text-muted);">Not recorded</b>
                <a href="{{ url('admin/leads/' . $deal->lead_id . '/edit') }}" id="dd-hearing-add" class="dd-hearing-add"><i class="fas fa-plus"></i> Add</a>
            @endforelse
        </div>
    </div>

    {{-- Stage bar --}}
    <div class="dd-stagebar" aria-label="Stage">
        @foreach ($stages as $s)
            @php $reached = $deal->stage && $s->sort_order <= $deal->stage->sort_order; @endphp
            <span class="{{ $reached ? 'is-reached' : '' }} {{ $s->id === $deal->stage_id ? 'is-current' : '' }}"
                  style="{{ $reached ? '--seg:' . $s->color : '' }}" title="{{ $s->name }}">{{ $s->name }}</span>
        @endforeach
    </div>

    <div class="dd-status">
        @if ($deal->status === 'won')
            <span class="crm-status-badge badge-status-won"><span class="dot"></span>Won {{ $deal->won_at?->format('d M Y') }}</span>
        @elseif ($deal->status === 'lost')
            <span class="crm-status-badge badge-status-lost"><span class="dot"></span>Lost {{ $deal->lost_at?->format('d M Y') }} · {{ $deal->lostReason->name ?? '' }}</span>
        @else
            <span class="dd-days{{ $deal->isRotting() ? ' is-rotting' : '' }}"><i class="far fa-clock"></i> {{ $deal->daysInStage() }} days in {{ $deal->stage->name ?? '' }}</span>
            <span style="margin-left: auto;"></span>
            @if ($can['won'])<button type="button" class="btn btn-success btn-sm" id="dd-won" data-act="won"><i class="fas fa-trophy mr-1"></i>Won</button>@endif
            @if ($can['lost'])<button type="button" class="btn btn-danger btn-sm" id="dd-lost" data-act="lost"><i class="fas fa-times mr-1"></i>Lost</button>@endif
        @endif
        @if ($can['lead'])
            <a href="{{ url('admin/leads/' . $deal->lead_id) }}" class="btn btn-secondary btn-sm" id="dd-profile"><i class="fas fa-user mr-1"></i>Profile</a>
        @endif
    </div>

    @php $otherPipelines = $pipelines->where('id', '!=', $deal->pipeline_id); @endphp
    @if ($deal->status === 'open' && $can['move'] && $otherPipelines->isNotEmpty())
        <div class="dd-pipeline" id="dd-pipeline">
            <span class="dd-meta"><i class="fas fa-project-diagram mr-1"></i>{{ $deal->pipeline->name ?? '' }}</span>
            <label for="dd-pipeline-to" class="sr-only">Move to pipeline</label>
            <select id="dd-pipeline-to" class="form-control form-control-sm" style="width: auto; display: inline-block;">
                @foreach ($otherPipelines as $pl)
                    <option value="{{ $pl->id }}">{{ $pl->name }}</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-secondary btn-sm js-dd-pipeline" data-id="{{ $deal->id }}" id="dd-pipeline-move">Move to pipeline</button>
        </div>
    @elseif ($pipelines->count() > 1)
        <div class="dd-pipeline"><span class="dd-meta"><i class="fas fa-project-diagram mr-1"></i>{{ $deal->pipeline->name ?? '' }}</span></div>
    @endif
</div>

<ul class="dd-tabs" role="tablist">
    <li><button type="button" class="active" data-tab="activities" id="dd-tab-activities">Activities <span class="ps-badge">{{ $pending->count() }}</span></button></li>
    <li><button type="button" data-tab="notes" id="dd-tab-notes">Notes <span class="ps-badge">{{ $notes->count() }}</span></button></li>
    <li><button type="button" data-tab="appointments" id="dd-tab-appointments">Appointments <span class="ps-badge">{{ $appointments->count() }}</span></button></li>
    <li><button type="button" data-tab="timeline" id="dd-tab-timeline">Timeline</button></li>
    <li><button type="button" data-tab="deals" id="dd-tab-deals">Other deals <span class="ps-badge">{{ $otherDeals->count() }}</span></button></li>
    <li><button type="button" data-tab="purchases" id="dd-tab-purchases">Purchases <span class="ps-badge">{{ $purchases->count() }}</span></button></li>
</ul>

<div class="dd-body">
    {{-- Activities --}}
    <section class="dd-pane active" data-pane="activities">
        @if ($can['move'] && !$closed)
            <form class="dd-activity-form" id="dd-activity-form" data-deal="{{ $deal->id }}" novalidate>
                <div class="dd-types" role="radiogroup" aria-label="Activity type">
                    @foreach ($types as $t)
                        <label title="{{ $t->label() }}"><input type="radio" name="activity_type" value="{{ $t->value }}" @checked($t->value === 'call')><span><i class="{{ $t->icon() }}"></i><em>{{ $t->label() }}</em></span></label>
                    @endforeach
                </div>
                <textarea name="note" class="form-control" rows="2" maxlength="1000" placeholder="What is it about? (e.g. confirm trial fitting)" id="dd-act-note"></textarea>
                <div class="dd-act-row" data-when>
                    <input type="datetime-local" name="due_at" class="form-control" id="dd-act-due" value="{{ now()->addDay()->setTime(11, 0)->format('Y-m-d\TH:i') }}">
                    <select name="assigned_user" class="form-control" id="dd-act-assignee" aria-label="Assigned to">
                        @foreach ($assignees as $a)
                            <option value="{{ $a['id'] }}" @selected($a['id'] == $deal->owner_id)>{{ $a['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="dd-act-row">
                    <label class="dd-check" data-when><input type="checkbox" name="done" value="1" id="dd-act-done"> Already done (log it)</label>
                    <button type="submit" class="btn btn-primary btn-sm" id="dd-act-submit" style="margin-left: auto;"><i class="fas fa-plus mr-1"></i>Save</button>
                </div>
                <div class="deal-errors text-danger" style="font-size: 12.5px;"></div>
            </form>
        @endif

        @include('admin.deal.partials.sequence-box')
        @include('admin.deal.partials.trial-box')

        <h3 class="dd-h">Planned</h3>
        @forelse ($pending as $a)
            @php
                $at = \Illuminate\Support\Carbon::parse($a->follow_up_date);
                $state = $at->isToday() ? 'today' : ($at->isPast() ? 'overdue' : 'upcoming');
            @endphp
            <div class="dd-activity is-{{ $state }}" id="dd-activity-{{ $a->id }}">
                <span class="dd-act-ic"><i class="{{ $a->type->icon() }}"></i></span>
                <div class="dd-act-main">
                    <b>{{ $a->type->label() }}</b>
                    <span class="dc-chip chip-{{ $state }}">{{ $state === 'today' ? 'Today ' . $at->format('h:i A') : $at->format('d M Y, h:i A') }}</span>
                    @if ($a->follow_up_notes)<div class="dd-act-note">{{ $a->follow_up_notes }}</div>@endif
                    <div class="dd-meta">{{ $a->assignedUser->name ?? 'Unassigned' }}</div>
                    @if ($can['move'] && in_array($a->activity_type, ['call', 'whatsapp'], true))
                        <div class="dd-results" id="dd-results-{{ $a->id }}" hidden>
                            @foreach (\App\Enums\CallOutcome::cases() as $o)
                                <button type="button" class="md-chip md-chip-{{ $o->value }} js-dd-result" data-id="{{ $a->id }}" data-result="{{ $o->value }}" id="dd-r-{{ $a->id }}-{{ $o->value }}"><i class="{{ $o->icon() }}"></i>{{ $o->label() }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>
                @if ($can['move'])
                    <button type="button" class="btn btn-sm btn-soft-done js-act-done" data-id="{{ $a->id }}" data-contact="{{ in_array($a->activity_type, ['call', 'whatsapp'], true) ? 1 : 0 }}" id="dd-done-{{ $a->id }}" title="Mark done"><i class="fas fa-check"></i></button>
                @endif
            </div>
        @empty
            <div class="dd-empty"><i class="fas fa-exclamation-triangle" style="color: var(--warning-500);"></i> Nothing planned. Every open deal should have a next step.</div>
        @endforelse
    </section>

    {{-- Notes about the person (shared across their deals) --}}
    <section class="dd-pane" data-pane="notes">
        @if ($can['move'])
            <form class="dd-note-form" id="dd-note-form" data-deal="{{ $deal->id }}" novalidate>
                <textarea name="body" class="form-control" rows="3" maxlength="2000" placeholder="Hearing details, family decision-maker, budget, preferences…" id="dd-note-body"></textarea>
                <div class="dd-act-row">
                    <label class="dd-check"><input type="checkbox" name="is_pinned" value="1" id="dd-note-pin"> <i class="fas fa-thumbtack"></i> Pin to top</label>
                    <button type="submit" class="btn btn-primary btn-sm" id="dd-note-submit" style="margin-left: auto;"><i class="fas fa-plus mr-1"></i>Save note</button>
                </div>
                <div class="deal-errors text-danger" style="font-size: 12.5px;"></div>
            </form>
        @endif
        @forelse ($notes as $n)
            <div class="dd-note{{ $n->is_pinned ? ' is-pinned' : '' }}" id="dd-note-{{ $n->id }}">
                <div class="dd-note-body">{{ $n->body }}</div>
                <div class="dd-note-foot">
                    <span class="dd-meta">
                        @if ($n->is_pinned)<i class="fas fa-thumbtack" title="Pinned"></i>@endif
                        {{ $n->creator->name ?? 'System' }} · {{ $n->created_at?->format('d M Y, h:i A') }}{{ $n->deal_id != $deal->id ? ' · other deal' : '' }}
                    </span>
                    @if ($can['move'])
                        <button type="button" class="dd-icon-btn js-note-pin" data-id="{{ $n->id }}" id="dd-note-pin-{{ $n->id }}" title="{{ $n->is_pinned ? 'Unpin' : 'Pin' }}"><i class="fas fa-thumbtack"></i></button>
                        @if ($n->created_by == auth()->id() || auth()->user()->isSuperAdmin())
                            <button type="button" class="dd-icon-btn is-danger js-note-del" data-id="{{ $n->id }}" id="dd-note-del-{{ $n->id }}" title="Delete"><i class="fas fa-trash-alt"></i></button>
                        @endif
                    @endif
                </div>
            </div>
        @empty
            <div class="dd-empty">No notes yet. Keep lasting facts about the person here.</div>
        @endforelse
    </section>

    {{-- Appointments (real bookings in the Appointments module) --}}
    <section class="dd-pane" data-pane="appointments">
        @if ($can['appointment'] && !$closed)
            <form class="dd-appt-form" id="dd-appt-form" data-deal="{{ $deal->id }}" novalidate>
                <div class="dd-appt-grid">
                    <select name="service_id" class="form-control" id="dd-appt-service" aria-label="Service">
                        <option value="">Service…</option>
                        @foreach ($services as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                    </select>
                    <select name="user_id" class="form-control" id="dd-appt-pro" aria-label="Professional" disabled><option value="">Professional…</option></select>
                    <input type="date" name="appointment_date" class="form-control" id="dd-appt-date" min="{{ today()->format('Y-m-d') }}" value="{{ today()->addDay()->format('Y-m-d') }}" aria-label="Date">
                </div>
                <div class="dd-slots" id="dd-appt-slots"><span class="dd-meta">Choose a service and professional to see free slots.</span></div>
                <input type="hidden" name="professional_time_slot_id" id="dd-appt-slot">
                <textarea name="notes" class="form-control" rows="2" maxlength="1000" placeholder="Note for the appointment (optional)" id="dd-appt-notes"></textarea>
                <div class="dd-act-row">
                    <span class="dd-meta"><i class="fas fa-sms"></i> Sends the "Appointment Booked" SMS</span>
                    <button type="submit" class="btn btn-primary btn-sm" id="dd-appt-submit" style="margin-left: auto;"><i class="far fa-calendar-check mr-1"></i>Book</button>
                </div>
                <div class="deal-errors text-danger" style="font-size: 12.5px;"></div>
            </form>
        @endif
        @forelse ($appointments as $ap)
            @php 
                $timeDisplay = $ap->start_time 
                    ? date('g:i A', strtotime($ap->start_time)) 
                    : ($ap->professionalTimeSlot?->timeSlot ? $ap->professionalTimeSlot->timeSlot->start_time->format('g:i A') : '');
            @endphp
            <div class="dd-row" id="dd-appt-{{ $ap->id }}">
                <span class="dd-act-ic" style="color: {{ $ap->status?->colorHex() }};"><i class="{{ $ap->status?->icon() ?? 'far fa-calendar' }}"></i></span>
                <div style="flex: 1;">
                    <b>{{ $ap->appointment_date?->format('d M Y') }}{{ $timeDisplay ? ', ' . $timeDisplay : '' }}</b>
                    <div class="dd-meta">{{ $ap->service->name ?? '—' }} · {{ $ap->user->name ?? '—' }}{{ $ap->deal_id == $deal->id ? '' : ' · other deal' }}</div>
                </div>
                <span class="dd-appt-status" style="color: {{ $ap->status?->colorHex() }};">{{ $ap->status?->label() }}</span>
            </div>
        @empty
            <div class="dd-empty">No appointments in the last 30 days or upcoming.</div>
        @endforelse
    </section>

    {{-- Timeline --}}
    <section class="dd-pane" data-pane="timeline">
        <div class="dd-timeline">
            @forelse ($timeline as $item)
                <div class="dd-tl-item">
                    <span class="dd-tl-dot" style="background: {{ $item['color'] }};"><i class="{{ $item['icon'] }}"></i></span>
                    <div>
                        <b>{{ $item['title'] }}</b>
                        @if (!empty($item['text']))<div class="dd-act-note">{{ $item['text'] }}</div>@endif
                        <div class="dd-meta">{{ $item['at']?->format('d M Y, h:i A') }}{{ $item['by'] ? ' · ' . $item['by'] : '' }}</div>
                    </div>
                </div>
            @empty
                <div class="dd-empty">No history yet.</div>
            @endforelse
        </div>
    </section>

    {{-- Other deals of the same person --}}
    <section class="dd-pane" data-pane="deals">
        @forelse ($otherDeals as $o)
            <div class="dd-row">
                <span class="m-dot" style="width: 10px; height: 10px; border-radius: 50%; background: {{ $o->stage->color ?? '#94a3b8' }};"></span>
                <div style="flex: 1;">
                    <b>{{ $o->display_title }}</b>
                    <div class="dd-meta">{{ $o->created_at?->format('d M Y') }} · {{ $o->vendor->name ?? 'Head Office' }}{{ $o->value ? ' · ' . $money($o->value) : '' }}</div>
                </div>
                <span class="crm-status-badge badge-status-{{ $o->status === 'open' ? 'new' : $o->status }}"><span class="dot"></span>{{ $o->status === 'open' ? ($o->stage->name ?? 'Open') : ucfirst($o->status) }}</span>
            </div>
        @empty
            <div class="dd-empty">This is the person's only deal.</div>
        @endforelse
    </section>

    {{-- Purchase history (read-only) --}}
    <section class="dd-pane" data-pane="purchases">
        @forelse ($purchases as $p)
            <div class="dd-row">
                <span class="dd-act-ic"><i class="fas fa-file-invoice"></i></span>
                <div style="flex: 1;">
                    <b>{{ $p->sale_invoice_no }}</b>
                    <div class="dd-meta">{{ $p->date?->format('d M Y') }} · {{ $p->vendor->name ?? 'Head Office' }}</div>
                </div>
                <b>{{ $money($p->total_amount) }}</b>
                @can('permission', 'sale_list.view')
                    <a href="{{ url('admin/sale/' . $p->id) }}" class="btn btn-secondary btn-sm" id="dd-sale-{{ $p->id }}" title="Open invoice"><i class="fas fa-external-link-alt"></i></a>
                @endcan
            </div>
        @empty
            <div class="dd-empty">{{ $customer ? 'No purchases yet.' : 'Purchases appear here once the person buys.' }}</div>
        @endforelse
        @if ($customer && $can['sale'] && $deal->status === 'won' && !$deal->sale_master_id)
            <a href="{{ url('admin/sale/create?customer_id=' . $customer->id) }}" class="btn btn-primary btn-sm mt-2" id="dd-create-sale"><i class="fas fa-plus mr-1"></i>Create sale</a>
        @endif
    </section>
</div>
