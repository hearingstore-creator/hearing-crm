@extends('admin.layouts.master')
@section('page_title', 'Support')
@section('breadcrumb_parent', 'Support')
@section('breadcrumb_parent_url', url('admin/ticket'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <style>
        .sx-layout { display:grid; grid-template-columns:210px minmax(0, 1fr); gap:18px; align-items:start; }
        @media (max-width:900px) { .sx-layout { grid-template-columns:1fr; } }
        .sx-views { background:var(--card-bg); border:1px solid var(--card-border); border-radius:14px; padding:10px; position:sticky; top:12px; }
        .sx-views h4 { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--page-text-muted); margin:6px 8px 6px; }
        .sx-view { display:flex; justify-content:space-between; align-items:center; padding:8px 10px; border-radius:9px; color:var(--page-text); text-decoration:none !important; font-size:13.5px; }
        .sx-view:hover { background:var(--accent-50); color:var(--page-text); }
        .sx-view.on { background:var(--accent-50); color:var(--accent-700); font-weight:700; }
        .sx-view .n { font-size:12px; font-weight:700; min-width:22px; text-align:center; padding:1px 7px; border-radius:99px; background:var(--card-border); color:var(--page-text-muted); }
        .sx-view.on .n { background:var(--accent-500); color:#fff; }
        .sx-view.hot .n { background:rgba(225,29,72,.12); color:#be123c; }
        .sx-switch { display:flex; gap:6px; margin:6px 8px 4px; }
        .sx-switch a { flex:1; text-align:center; font-size:12.5px; padding:5px 0; border-radius:8px; border:1px solid var(--card-border); color:var(--page-text-muted); text-decoration:none !important; }
        .sx-switch a.on { background:var(--accent-500); border-color:var(--accent-500); color:#fff; font-weight:600; }
        .sx-bar { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
        .sx-bar input[type=search] { flex:1 1 240px; }
        .sx-bar select, .sx-bar input { height:36px; font-size:13px; }
        .sx-list { background:var(--card-bg); border:1px solid var(--card-border); border-radius:14px; overflow:hidden; }
        .sx-row { display:grid; grid-template-columns:minmax(0, 1fr) auto; gap:12px; align-items:center; padding:12px 16px; border-bottom:1px solid var(--card-border); color:var(--page-text); text-decoration:none !important; }
        .sx-row:last-child { border-bottom:0; }
        .sx-row:hover { background:var(--accent-50); color:var(--page-text); }
        .sx-row .top { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .sx-row .no { font-size:11.5px; font-weight:700; color:var(--page-text-muted); }
        .sx-row .subj { font-weight:600; font-size:14px; }
        .sx-row .meta { font-size:12.5px; color:var(--page-text-muted); margin-top:2px; }
        .sx-chip { display:inline-block; font-size:11px; font-weight:700; color:#fff; padding:2px 9px; border-radius:99px; }
        .sx-due { font-size:12px; font-weight:700; padding:3px 10px; border-radius:8px; white-space:nowrap; }
        .sx-due.danger { background:rgba(225,29,72,.12); color:#be123c; }
        .sx-due.warning { background:rgba(217,119,6,.14); color:#b45309; }
        .sx-due.muted { background:var(--card-border); color:var(--page-text-muted); }
        .sx-empty { padding:36px 16px; text-align:center; color:var(--page-text-muted); }
    </style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-headset" style="color: var(--accent-500); margin-right: 8px;"></i>Support</h1>
            <p>Tickets that need you first. Time left follows the priority: {{ collect(config('crm.ticket_sla_hours'))->map(fn ($h, $p) => $p . ' ' . $h . 'h')->implode(' · ') }}.</p>
        </div>
        <div class="header-actions">
            @can('permission','add_ticket.create')
                <a href="{{ url('admin/ticket/create') }}" class="btn btn-primary btn-sm" id="btn-new-ticket"><i class="fas fa-plus mr-1"></i>New ticket</a>
            @endcan
        </div>
    </div>

    @include('admin.alert_message')

    <div class="sx-layout">
        <nav class="sx-views" aria-label="Ticket views">
            <div class="sx-switch">
                <a href="{{ url('admin/ticket') }}" class="on" id="switch-tickets">Tickets</a>
                @can('permission','task_list.index')<a href="{{ url('admin/task') }}" id="switch-tasks">Tasks</a>@endcan
            </div>
            <h4>Views</h4>
            @foreach ($views as $key => $label)
                <a href="{{ url('admin/ticket?view=' . $key) }}" class="sx-view {{ $view === $key ? 'on' : '' }} {{ in_array($key, ['overdue', 'unassigned'], true) && $counts[$key] > 0 ? 'hot' : '' }}" id="view-{{ $key }}">
                    <span>{{ $label }}</span><span class="n">{{ $counts[$key] }}</span>
                </a>
            @endforeach
        </nav>

        <div>
            <form method="GET" class="sx-bar" id="inbox-filters">
                <input type="hidden" name="view" value="{{ $view }}">
                <input type="search" name="q" value="{{ $q }}" class="form-control" id="inbox-search" placeholder="Search ticket number, subject, patient name or phone" autocomplete="off">
                <select name="priority" class="form-control" style="width:auto;" id="inbox-priority" onchange="this.form.submit()">
                    <option value="">Any priority</option>
                    @foreach ($priorities as $p)<option value="{{ $p->value }}" @selected(request('priority') === $p->value)>{{ $p->value }}</option>@endforeach
                </select>
                <select name="assigned_to" class="form-control" style="width:auto;" id="inbox-owner" onchange="this.form.submit()">
                    <option value="">Any owner</option>
                    <option value="{{ auth()->id() }}" @selected((string) request('assigned_to') === (string) auth()->id())>Me</option>
                    <option value="none" @selected(request('assigned_to') === 'none')>Unassigned</option>
                    @foreach ($users as $u)@if ($u->id !== auth()->id())<option value="{{ $u->id }}" @selected((string) request('assigned_to') === (string) $u->id)>{{ $u->name }}</option>@endif @endforeach
                </select>
                <button type="submit" class="btn btn-primary btn-sm" id="btn-inbox-search"><i class="fas fa-search mr-1"></i>Search</button>
                @if ($q !== '' || request()->hasAny(['priority', 'assigned_to']))
                    <a href="{{ url('admin/ticket?view=' . $view) }}" class="btn btn-secondary btn-sm" id="btn-inbox-clear">Clear</a>
                @endif
            </form>

            <div class="sx-list crm-animate-in delay-1">
                @forelse ($tickets as $t)
                    @php $due = $t->dueState(); @endphp
                    <a href="{{ url('admin/ticket/' . $t->id) }}" class="sx-row" id="ticket-row-{{ $t->id }}">
                        <div style="min-width:0;">
                            <div class="top">
                                <span class="no">{{ $t->number }}</span>
                                <span class="subj">{{ $t->subject }}</span>
                            </div>
                            <div class="meta">
                                {{ $t->customer->lead->full_name ?? 'Unknown patient' }}
                                @if ($t->customer?->lead?->phone_number) · {{ $t->customer->lead->phone_number }}@endif
                                · {{ $t->assignedTo->name ?? 'Unassigned' }}
                                · opened {{ $t->created_at->diffForHumans() }}
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; justify-content:flex-end;">
                            <span class="sx-chip" style="background:{{ $t->priority->color() }};">{{ $t->priority->value }}</span>
                            <span class="sx-chip" style="background:{{ $t->status->color() }};">{{ $t->status->value }}</span>
                            <span class="sx-due {{ $due['tone'] }}" title="{{ $due['state'] === 'done' ? 'Finished' : 'Due ' . $t->due_at->format('d M, h:i A') }}">{{ $due['label'] }}</span>
                        </div>
                    </a>
                @empty
                    <div class="sx-empty">
                        <i class="fas fa-check-circle" style="font-size:28px; display:block; margin-bottom:8px; color:#10b981;"></i>
                        @if ($q !== '' || request()->hasAny(['priority', 'assigned_to']))
                            No tickets match. Try a different search or clear the filters.
                        @else
                            Nothing in {{ strtolower($views[$view]) }}. You're all caught up.
                        @endif
                    </div>
                @endforelse
            </div>
            <div style="margin-top:12px;">{{ $tickets->links() }}</div>
        </div>
    </div>
</div>
@endsection
