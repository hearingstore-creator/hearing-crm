@extends('admin.layouts.master')
@section('page_title', 'Lead Details')
@section('breadcrumb_parent', 'Leads')
@section('breadcrumb_parent_url', url('admin/leads'))

@push('css')
  <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-id-card" style="color: var(--accent-500); margin-right: 8px;"></i>Lead Details</h1>
            <p>Viewing complete profile and journey for this lead</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/leads') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i>Back to Leads
            </a>
            @can('permission', 'proposals.create')
                <a href="{{ route('proposal.create', ['lead_id' => $lead->id]) }}" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-file-invoice mr-1"></i>Create Quotation
                </a>
            @endcan
            @if (auth()->user()->isSuperAdmin() && !$lead->erased_at)
                <a href="{{ route('leads.data-export', $lead->id) }}" class="btn btn-outline-secondary btn-sm" title="Everything held about this person, as a file"><i class="fas fa-file-export mr-1"></i>Data export</a>
                <button type="button" class="btn btn-outline-danger btn-sm" data-toggle="collapse" data-target="#lead-erase"><i class="fas fa-user-slash mr-1"></i>Erase</button>
            @endif
            @if ($lead->lead_status != 'won')
                @can('permission', 'lead_list.edit')
                    <a href="{{ route('leads.edit', $lead->id) }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-pen mr-1"></i>Edit Lead
                    </a>
                @endcan
            @endif
        </div>
    </div>

    @include('admin.alert_message')
    @if ($lead->erased_at)
        <div class="alert alert-secondary" style="font-size:13px;">Personal details were erased on {{ \Illuminate\Support\Carbon::parse($lead->erased_at)->format('d M Y') }} at the person's request.</div>
    @elseif (auth()->user()->isSuperAdmin())
        <div class="collapse" id="lead-erase">
            <form method="POST" action="{{ route('leads.erase', $lead->id) }}" class="alert alert-danger" style="font-size:13px;">
                @csrf
                <b>Erase this person's personal details</b> – name, phone, email, address, notes and messages. Invoices and payments stay, without the name. This cannot be undone.
                <input type="text" name="reason" class="form-control form-control-sm my-2" maxlength="500" placeholder="Reason, e.g. asked by email on 3 Nov to be removed" required>
                <label class="mb-2" style="font-weight:400;"><input type="checkbox" name="confirm" value="1" required> I understand this cannot be undone</label><br>
                <button type="submit" class="btn btn-danger btn-sm">Erase personal details</button>
            </form>
        </div>
    @endif

    {{-- Hero Card --}}
    <div class="crm-hero-card crm-animate-in delay-1">
        <div class="crm-hero-top">
            <div class="crm-hero-identity">
                <div class="crm-hero-avatar">{{ strtoupper(substr($lead->full_name, 0, 1)) }}</div>
                <div class="crm-hero-info">
                    <h2>{{ $lead->full_name }}</h2>
                    <div class="crm-hero-meta">
                        <span><i class="fas fa-hashtag"></i> {{ $lead->lead_uid ?? 'â€”' }}</span>
                        <span><i class="fas fa-phone"></i> {{ $lead->phone_number }}</span>
                        @if($lead->email)
                            <span><i class="fas fa-envelope"></i> {{ $lead->email }}</span>
                        @endif
                        <span class="crm-status-badge lead-stage-badge" id="lead-stage-badge" style="--stage: {{ $lead->status_color }};">
                            <span class="dot"></span>
                            {{ $lead->status_label }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Profile Completion Ring --}}
            @php $completion = $lead->profileCompletion(); @endphp
            <div style="display: flex; align-items: center; gap: var(--space-4);">
                <div class="completion-ring">
                    <svg width="60" height="60" viewBox="0 0 60 60">
                        <circle class="ring-bg" cx="30" cy="30" r="25" />
                        <circle class="ring-progress" cx="30" cy="30" r="25"
                            stroke-dasharray="{{ 2 * 3.14159 * 25 }}"
                            stroke-dashoffset="{{ 2 * 3.14159 * 25 * (1 - $completion / 100) }}" />
                    </svg>
                    <span class="ring-text">{{ $completion }}%</span>
                </div>
                <div>
                    <div style="font-size: var(--font-size-xs); color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Profile</div>
                    <div style="font-size: var(--font-size-sm); color: var(--page-text); font-weight: 500;">Completion</div>
                </div>
            </div>
        </div>

        {{-- Quick Meta Row --}}
        <div style="display: flex; gap: var(--space-6); flex-wrap: wrap; padding-top: var(--space-4); border-top: 1px solid var(--card-border);">
            <div>
                <div style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Created</div>
                <div style="font-size: var(--font-size-sm); font-weight: 500; color: var(--page-text);">{{ $lead->created_at->format('d M Y, h:i A') }}</div>
            </div>
            <div>
                <div style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Last Updated</div>
                <div style="font-size: var(--font-size-sm); font-weight: 500; color: var(--page-text);">{{ $lead->updated_at->diffForHumans() }}</div>
            </div>
            @if($lead->source_of_entry_label)
            <div>
                <div style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Source</div>
                <div style="font-size: var(--font-size-sm); font-weight: 500; color: var(--page-text);">{{ $lead->source_of_entry_label }}</div>
            </div>
            @endif
            @if($lead->lead_type_label)
            <div>
                <div style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Lead Type</div>
                <div style="font-size: var(--font-size-sm); font-weight: 500; color: var(--page-text);">{{ $lead->lead_type_label }}</div>
            </div>
            @endif
            @if($lead->vendor)
            <div>
                <div style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Branch</div>
                <div style="font-size: var(--font-size-sm); font-weight: 500; color: var(--page-text);">{{ $lead->vendor->name ?? 'â€”' }}</div>
            </div>
            @endif
            @if($lead->created_by)
            <div>
                <div style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Created By</div>
                <div style="font-size: var(--font-size-sm); font-weight: 500; color: var(--page-text);">{{ optional($lead->creator)->name ?? 'â€”' }}</div>
            </div>
            @endif
        </div>
    </div>

    {{-- Lead Journey Pipeline --}}
    <div class="crm-journey-card crm-animate-in delay-2">
        <h5><i class="fas fa-route" style="color: var(--accent-500);"></i> Lead Journey</h5>
        <div class="journey-pipeline">
            @foreach($leadJourney as $index => $step)
                @php
                    $stepClass = $step['is_current'] ? 'current' : ($step['is_reached'] ? 'reached' : '');
                @endphp
                <div class="journey-step {{ $stepClass }}" style="--stage: {{ $step['color'] }};">
                    <div class="journey-step-node">
                        <i class="{{ $step['icon'] }}"></i>
                    </div>
                    <span class="journey-step-label">{{ $step['label'] }}</span>
                </div>
                @if($index < $leadJourney->count() - 1)
                    <div class="journey-connector {{ $step['is_reached'] ? 'active' : '' }}"></div>
                @endif
            @endforeach
        </div>

        {{-- Journey History --}}
        @if($lead->statusHistories->count() > 0)
            <div style="margin-top: var(--space-5); padding-top: var(--space-4); border-top: 1px solid var(--card-border);">
                <h6 style="font-size: var(--font-size-xs); font-weight: 600; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: var(--space-3);">
                    <i class="fas fa-history mr-1"></i> Status History
                </h6>
                <div style="overflow-x: auto;">
                    <table class="crm-table" style="min-width: 100%;">
                        <thead>
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th>Date & Time</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Changed By</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lead->statusHistories as $index => $history)
                            <tr>
                                <td style="color: var(--page-text-muted);">{{ $index + 1 }}</td>
                                <td style="font-weight: 500;">{{ $history->created_at->format('d M Y, h:i A') }}</td>
                                <td>
                                    @if($history->from_status)
                                        <span class="crm-status-badge badge-status-{{ $history->from_status }}">
                                            <span class="dot"></span>{{ ucfirst(str_replace('_', ' ', $history->from_status)) }}
                                        </span>
                                    @else
                                        <span style="color: var(--page-text-muted);">â€”</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="crm-status-badge badge-status-{{ $history->to_status }}">
                                        <span class="dot"></span>{{ ucfirst(str_replace('_', ' ', $history->to_status)) }}
                                    </span>
                                </td>
                                <td style="font-size: 12px;">{{ optional($history->creator)->name ?? 'â€”' }}</td>
                                <td style="font-size: 12px; color: var(--page-text-secondary);">{{ $history->notes ?? 'â€”' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    {{-- Info Grid --}}
    <div class="crm-info-grid crm-animate-in delay-3">

        {{-- Personal Information --}}
        <div class="crm-info-card">
            <div class="crm-info-card-header">
                <div class="info-icon" style="background: var(--accent-500);"><i class="fas fa-user"></i></div>
                <h6>Personal Information</h6>
            </div>
            <div class="crm-info-card-body">
                <div class="info-row">
                    <span class="info-label">Full Name</span>
                    <span class="info-value">{{ $lead->full_name }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone</span>
                    <span class="info-value">{{ $lead->phone_number }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value">{{ $lead->email ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Age</span>
                    <span class="info-value">{{ $lead->age ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Gender</span>
                    <span class="info-value">{{ $lead->gender ? ucfirst($lead->gender) : 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">District</span>
                    <span class="info-value">{{ $lead->district->name ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Address</span>
                    <span class="info-value">{{ $lead->address ?? 'N/A' }}</span>
                </div>
            </div>
        </div>

        {{-- Contact Person --}}
        <div class="crm-info-card">
            <div class="crm-info-card-header">
                <div class="info-icon" style="background: var(--gray-500);"><i class="fas fa-users"></i></div>
                <h6>Contact Person</h6>
            </div>
            <div class="crm-info-card-body">
                <div class="info-row">
                    <span class="info-label">Name</span>
                    <span class="info-value">{{ $lead->contact_person_name ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Relation</span>
                    <span class="info-value">{{ $lead->relation ? ucfirst($lead->relation) : 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone</span>
                    <span class="info-value">{{ $lead->contact_person_phone ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Referred By</span>
                    <span class="info-value">{{ $lead->ref_by ?? 'N/A' }}</span>
                </div>
            </div>
        </div>

        {{-- Lead Details --}}
        <div class="crm-info-card">
            <div class="crm-info-card-header">
                <div class="info-icon" style="background: #7C3AED;"><i class="fas fa-clipboard-list"></i></div>
                <h6>Lead Details</h6>
            </div>
            <div class="crm-info-card-body">
                <div class="info-row">
                    <span class="info-label">Inquiry For</span>
                    <span class="info-value">{{ $lead->inquiry_for_label ?: 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Interested Product</span>
                    <span class="info-value">{{ $lead->interest_brand_product ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Approx Budget</span>
                    <span class="info-value">{{ $lead->approx_budget ? 'à§³ ' . number_format($lead->approx_budget, 2) : 'N/A' }}</span>
                </div>

                {{-- Hearing Loss Visual --}}
                @if($hearingLoss)
                    <div style="margin-top: var(--space-4); padding-top: var(--space-3); border-top: 1px solid var(--table-border);">
                        <div style="font-size: 11px; font-weight: 600; color: #7C3AED; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: var(--space-3);">
                            <i class="fas fa-ear-listen mr-1"></i> Hearing Loss
                        </div>
                        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                            @foreach($hearingLoss as $item)
                                <div style="flex: 1; min-width: 160px; background: var(--table-header-bg); border: 1px solid var(--card-border); border-radius: 10px; overflow: hidden;">
                                    <div style="background: #7C3AED; color: #fff; padding: 8px 12px; font-weight: 600; font-size: 12px; display: flex; align-items: center; gap: 6px;">
                                        <i class="fas fa-ear-listen"></i> {{ $item['ear'] }} Ear
                                    </div>
                                    <div style="padding: 10px 12px;">
                                        <div style="font-size: 12px; margin-bottom: 4px;">
                                            <span style="color: var(--page-text-muted);">Degree:</span>
                                            <strong style="color: var(--page-text);">{{ $item['degree'] }}</strong>
                                        </div>
                                        <div style="font-size: 12px;">
                                            <span style="color: var(--page-text-muted);">Type:</span>
                                            <strong style="color: var(--page-text);">{{ $item['type'] }}</strong>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Notes & Loss Details --}}
        <div class="crm-info-card">
            <div class="crm-info-card-header">
                <div class="info-icon" style="background: var(--warning-500);"><i class="fas fa-sticky-note"></i></div>
                <h6>Notes & Additional Info</h6>
            </div>
            <div class="crm-info-card-body">
                @if($lead->notes)
                    <div style="background: var(--table-header-bg); border-radius: 8px; padding: var(--space-4); margin-bottom: var(--space-3); font-size: var(--font-size-sm); color: var(--page-text-secondary); line-height: 1.6;">
                        {{ $lead->notes }}
                    </div>
                @else
                    <div style="color: var(--page-text-muted); font-size: var(--font-size-sm); font-style: italic;">No notes added</div>
                @endif

                @if($lead->lead_status === 'lost' && ($lead->loss_reason || $lead->loss_to_whom))
                    <div style="background: var(--danger-50); border: 1px solid rgba(239,68,68,0.2); border-radius: 10px; padding: var(--space-4); margin-top: var(--space-4);">
                        <div style="font-size: 11px; font-weight: 600; color: var(--danger-600); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: var(--space-3);">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Loss Details
                        </div>
                        <div class="info-row">
                            <span class="info-label" style="color: var(--danger-500);">Loss Reason</span>
                            <span class="info-value">{{ $lead->loss_reason ?? 'N/A' }}</span>
                        </div>
                        <div class="info-row" style="border-bottom: none;">
                            <span class="info-label" style="color: var(--danger-500);">Lost to Whom</span>
                            <span class="info-value">{{ $lead->loss_to_whom ?? 'N/A' }}</span>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Communication Timeline (SMS + Follow-ups + Appointments) --}}
    @include('admin.partials.communication-timeline', ['timelineItems' => $timelineItems])

    {{-- Activities (same engine as the Deals board: schedule, one-tap call results, auto next step) --}}
    <div class="crm-info-card crm-animate-in delay-4 lp-acts" id="lead-activities" style="margin-bottom: var(--space-6);">
        <div class="crm-info-card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
            <div style="display: flex; align-items: center; gap: var(--space-3);">
                <div class="info-icon" style="background: var(--success-500);"><i class="fas fa-bolt"></i></div>
                <h6>Activities</h6>
                <span class="lp-acts-count">{{ $pendingActivities->count() }} pending</span>
                @if($openDeal && $openDeal->stage)
                    <span class="lp-acts-stage" style="--stage: {{ $openDeal->stage->color ?? '#6366f1' }};">{{ $openDeal->stage->name }}</span>
                @endif
            </div>
            @can('permission', 'deal_board.index')
                <a href="{{ url('admin/deals') }}" class="btn btn-secondary btn-sm" id="lp-open-board"><i class="fas fa-columns mr-1"></i>Deals Board</a>
            @endcan
        </div>
        <div class="crm-info-card-body">
            @if(!$openDeal)
                <div class="lp-acts-empty">
                    <i class="fas fa-handshake"></i>
                    This lead has no open deal. Activities are planned on deals, so open or reopen a deal from the Deals Board.
                </div>
            @else
                @forelse($pendingActivities as $act)
                    @php
                        $type = $act->activity_type instanceof \App\Enums\ActivityType ? $act->activity_type : \App\Enums\ActivityType::tryFrom((string) $act->activity_type) ?? \App\Enums\ActivityType::TASK;
                        $due = \Carbon\Carbon::parse($act->follow_up_date);
                        $isCall = in_array($type, [\App\Enums\ActivityType::CALL, \App\Enums\ActivityType::WHATSAPP], true);
                    @endphp
                    <div class="lp-act {{ $due->isPast() ? 'is-overdue' : ($due->isToday() ? 'is-today' : '') }}" id="lp-act-{{ $act->id }}">
                        <div class="lp-act-icon"><i class="{{ $type->icon() }}"></i></div>
                        <div class="lp-act-main">
                            <div class="lp-act-title">
                                {{ $type->label() }}
                                <span class="lp-act-due"><i class="far fa-clock mr-1"></i>{{ $due->format('d M, h:i A') }}{{ $due->isPast() ? ' Â· overdue' : '' }}</span>
                                @if($act->assignedUser)<span class="lp-act-who"><i class="fas fa-user mr-1"></i>{{ $act->assignedUser->name }}</span>@endif
                            </div>
                            @if($act->follow_up_notes)<div class="lp-act-note">{{ $act->follow_up_notes }}</div>@endif
                            @if($canWorkDeal)
                                <div class="lp-act-actions">
                                    @if($isCall)
                                        @foreach(\App\Enums\CallOutcome::cases() as $res)
                                            <button type="button" class="lp-chip js-lp-result" data-id="{{ $act->id }}" data-result="{{ $res->value }}" id="lp-res-{{ $act->id }}-{{ $res->value }}">
                                                <i class="{{ $res->icon() }}"></i>{{ $res->label() }}
                                            </button>
                                        @endforeach
                                        <input type="datetime-local" class="lp-callback d-none" id="lp-cb-{{ $act->id }}">
                                    @else
                                        <button type="button" class="lp-chip lp-chip-done js-lp-done" data-id="{{ $act->id }}" id="lp-done-{{ $act->id }}"><i class="fas fa-check"></i>Done</button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="lp-acts-empty"><i class="fas fa-calendar-plus"></i>Nothing planned. Add the next step below so this lead is not forgotten.</div>
                @endforelse

                @if($canWorkDeal)
                    <form class="lp-act-form" id="lp-act-form">
                        <select name="activity_type" class="form-control" id="lp-act-type">
                            @foreach(\App\Enums\ActivityType::cases() as $t)
                                <option value="{{ $t->value }}">{{ $t->label() }}</option>
                            @endforeach
                        </select>
                        <input type="datetime-local" name="due_at" class="form-control" id="lp-act-due" value="{{ now()->addHour()->format('Y-m-d\TH:i') }}">
                        <select name="assigned_user" class="form-control" id="lp-act-user">
                            <option value="">Deal owner</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="note" class="form-control lp-act-note-input" id="lp-act-note" maxlength="1000" placeholder="Note (optional)">
                        <label class="lp-act-donechk"><input type="checkbox" name="done" value="1" id="lp-act-done"> Already done</label>
                        <button type="submit" class="btn btn-primary btn-sm" id="lp-act-save"><i class="fas fa-plus mr-1"></i>Add</button>
                    </form>
                @endif
            @endif
        </div>
    </div>

</div>

@endsection

@push('css')
<style>
  .lp-acts-count { background: var(--success-50); color: var(--success-600); font-size: 11px; font-weight: 600; padding: 2px 10px; border-radius: 20px; }
  .lp-acts-stage { font-size: 11px; font-weight: 600; padding: 2px 10px; border-radius: 20px; color: var(--stage); border: 1px solid var(--stage); }
  .lp-acts-empty { text-align: center; padding: 22px; color: var(--page-text-muted); font-size: 13px; }
  .lp-acts-empty i { display: block; font-size: 26px; margin-bottom: 8px; opacity: .5; }
  .lp-act { display: flex; gap: 12px; padding: 12px; border: 1px solid var(--card-border); border-left: 3px solid var(--accent-500, #6366f1); border-radius: 10px; margin-bottom: 10px; background: var(--card-bg); transition: transform .15s, box-shadow .15s; }
  .lp-act:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(0,0,0,.06); }
  .lp-act.is-overdue { border-left-color: var(--danger-500, #ef4444); }
  .lp-act.is-today { border-left-color: var(--warning-500, #f59e0b); }
  .lp-act-icon { width: 34px; height: 34px; flex: 0 0 34px; border-radius: 50%; display: grid; place-items: center; background: var(--table-header-bg); color: var(--accent-500, #6366f1); }
  .lp-act-main { flex: 1; min-width: 0; }
  .lp-act-title { font-weight: 600; font-size: 13px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; color: var(--page-text); }
  .lp-act-due, .lp-act-who { font-weight: 500; font-size: 11px; color: var(--page-text-muted); }
  .lp-act.is-overdue .lp-act-due { color: var(--danger-500, #ef4444); }
  .lp-act-note { font-size: 12px; color: var(--page-text-muted); margin-top: 4px; }
  .lp-act-actions { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; align-items: center; }
  .lp-chip { border: 1px solid var(--card-border); background: var(--card-bg); color: var(--page-text); font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px; cursor: pointer; display: inline-flex; gap: 5px; align-items: center; transition: all .15s; }
  .lp-chip:hover { border-color: var(--accent-500, #6366f1); color: var(--accent-500, #6366f1); }
  .lp-chip-done:hover { border-color: var(--success-500); color: var(--success-600); }
  .lp-chip:disabled { opacity: .5; cursor: wait; }
  .lp-callback { font-size: 12px; padding: 3px 8px; border-radius: 8px; border: 1px solid var(--card-border); background: var(--card-bg); color: var(--page-text); }
  .lp-act-form { display: grid; grid-template-columns: 150px 190px 160px 1fr auto auto; gap: 8px; align-items: center; margin-top: 14px; padding-top: 14px; border-top: 1px dashed var(--card-border); }
  .lp-act-form .form-control { height: 34px; font-size: 13px; }
  .lp-act-donechk { margin: 0; font-size: 12px; white-space: nowrap; color: var(--page-text-muted); }
  @media (max-width: 992px) { .lp-act-form { grid-template-columns: 1fr 1fr; } }
</style>
@endpush

@push('scripts')
  <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
  <script>
    $(document).ready(function() {
      $('.select').select2({
        width: '100%'
      });

      var csrf = '{{ csrf_token() }}';
      var base = '{{ url('admin/deals') }}';
      function notify(msg, ok) {
        if (window.toastr) { ok ? toastr.success(msg) : toastr.error(msg); } else { alert(msg); }
      }
      function post(url, data, $btn) {
        $btn && $btn.prop('disabled', true);
        return $.ajax({ url: url, method: 'POST', data: data, headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } })
          .done(function (r) {
            notify(r.message || 'Saved.', true);
            if (r.suggest_lost) { notify('This looks lost. Mark the deal Lost from the Deals Board.', false); }
            setTimeout(function () { location.reload(); }, 700);
          })
          .fail(function (x) {
            var e = x.responseJSON || {};
            notify(e.errors ? Object.values(e.errors)[0][0] : (e.message || 'Something went wrong.'), false);
            $btn && $btn.prop('disabled', false);
          });
      }

      // One-tap call results ("Call back" asks for a time first)
      $(document).on('click', '.js-lp-result', function () {
        var $b = $(this), id = $b.data('id'), result = $b.data('result'), data = { result: result };
        if (result === 'call_back') {
          var $cb = $('#lp-cb-' + id);
          if ($cb.hasClass('d-none') || !$cb.val()) { $cb.removeClass('d-none').focus(); notify('Pick the call-back time, then tap Call back again.', false); return; }
          data.callback_at = $cb.val();
        }
        post(base + '/activities/' + id + '/complete', data, $b);
      });
      $(document).on('click', '.js-lp-done', function () {
        post(base + '/activities/' + $(this).data('id') + '/complete', {}, $(this));
      });

      // Add activity / log note
      $('#lp-act-type').on('change', function () { $('#lp-act-due').toggle(this.value !== 'note'); }).trigger('change');
      $('#lp-act-form').on('submit', function (e) {
        e.preventDefault();
        post(base + '/{{ $openDeal->id ?? 0 }}/activities', $(this).serialize(), $('#lp-act-save'));
      });
    });
  </script>
@endpush
