@extends('admin.layouts.master')
@section('page_title', $ticket->number)
@section('breadcrumb_parent', 'Support')
@section('breadcrumb_parent_url', url('admin/ticket'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <style>
        .tk-grid { display:grid; grid-template-columns:minmax(0, 1.6fr) minmax(0, 1fr); gap:20px; }
        @media (max-width:1000px) { .tk-grid { grid-template-columns:1fr; } }
        .tk-card { background:var(--card-bg); border:1px solid var(--card-border); border-radius:14px; padding:16px; margin-bottom:16px; }
        .tk-card h3 { font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--page-text-muted); margin:0 0 10px; }
        .tk-badge { display:inline-block; font-size:12px; font-weight:600; color:#fff; padding:3px 11px; border-radius:20px; }
        .tk-desc { white-space:pre-wrap; word-break:break-word; font-size:14px; line-height:1.6; }
        .tk-status { display:flex; flex-wrap:wrap; gap:6px; }
        .tk-status button { border:1px solid var(--card-border); background:var(--card-bg); color:var(--page-text); border-radius:8px; padding:5px 11px; font-size:12.5px; cursor:pointer; }
        .tk-status button.on { background:var(--accent-50); border-color:var(--accent-500); color:var(--accent-700); font-weight:600; }
        .tk-time { border-left:2px solid var(--card-border); margin-left:6px; padding-left:14px; }
        .tk-ev { position:relative; padding:0 0 14px; font-size:13px; }
        .tk-ev::before { content:''; position:absolute; left:-20px; top:5px; width:10px; height:10px; border-radius:50%; background:var(--card-border); }
        .tk-ev.note::before { background:var(--accent-500); }
        .tk-ev.created::before { background:#10b981; }
        .tk-ev small { color:var(--page-text-muted); display:block; font-size:11.5px; }
        .tk-ev.note .tk-body { background:var(--accent-50); border-radius:10px; padding:8px 10px; margin-top:3px; white-space:pre-wrap; word-break:break-word; }
        .tk-row { display:flex; justify-content:space-between; gap:10px; padding:6px 0; border-bottom:1px solid var(--card-border); font-size:13px; }
        .tk-row:last-child { border-bottom:0; }
        .tk-row span:first-child { color:var(--page-text-muted); }
    </style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <div style="font-size:12px; color:var(--page-text-muted); font-weight:600;">{{ $ticket->number }}</div>
            <h1 style="margin-top:2px;">{{ $ticket->subject }}</h1>
            <p>
                <span class="tk-badge" style="background:{{ $ticket->status->color() }};">{{ $ticket->status->value }}</span>
                <span class="tk-badge" style="background:{{ $ticket->priority->color() }};">{{ $ticket->priority->value }}</span>
                <span style="margin-left:6px;">Opened {{ $ticket->created_at->format('d M Y, h:i A') }} · {{ $ticket->created_at->diffForHumans() }}</span>
            </p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/ticket') }}" class="btn btn-secondary btn-sm" id="btn-ticket-list"><i class="fas fa-list mr-1"></i>All tickets</a>
            @can('permission', 'ticket_list.edit')
                <a href="{{ url('admin/ticket/' . $ticket->id . '/edit') }}" class="btn btn-secondary btn-sm" id="btn-ticket-edit"><i class="fas fa-pen mr-1"></i>Edit</a>
            @endcan
            @can('permission', 'ticket_list.delete')
                <form method="POST" action="{{ url('admin/ticket/' . $ticket->id) }}" style="display:inline;" onsubmit="return confirm('Delete this ticket?');">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-secondary btn-sm" id="btn-ticket-delete" title="Delete"><i class="fas fa-trash"></i></button>
                </form>
            @endcan
        </div>
    </div>

    @include('admin.alert_message')
    @if ($errors->any())
        <div class="alert alert-danger m-2">@foreach ($errors->all() as $msg)<div>{{ $msg }}</div>@endforeach</div>
    @endif

    <div class="tk-grid">
        <div>
            <div class="tk-card">
                <h3>What happened</h3>
                <div class="tk-desc">{{ $ticket->description_text }}</div>
            </div>

            <div class="tk-card" id="timeline">
                <h3>Timeline</h3>
                @can('permission', 'ticket_list.edit')
                    <form method="POST" action="{{ route('ticket.notes', $ticket->id) }}" style="margin-bottom:16px;">
                        @csrf
                        <textarea name="body" id="ticket-note" class="form-control" rows="3" maxlength="2000" placeholder="Write a note for the team" required></textarea>
                        <div style="text-align:right; margin-top:8px;"><button type="submit" class="btn btn-primary btn-sm" id="btn-add-note"><i class="fas fa-paper-plane mr-1"></i>Add note</button></div>
                    </form>
                @endcan
                <div class="tk-time">
                    @forelse ($ticket->notes as $n)
                        <div class="tk-ev {{ $n->kind }}">
                            <small>{{ $n->user->name ?? 'System' }} · {{ $n->created_at->format('d M, h:i A') }} · {{ $n->created_at->diffForHumans() }}</small>
                            @if ($n->kind === 'note')<div class="tk-body">{{ $n->body }}</div>@else<div>{{ $n->body }}</div>@endif
                        </div>
                    @empty
                        <div style="color:var(--page-text-muted); font-size:13px;">No notes yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div>
            <div class="tk-card">
                <h3>Status</h3>
                @can('permission', 'ticket_list.edit')
                    <div class="tk-status">
                        @foreach ($statuses as $s)
                            <form method="POST" action="{{ url('admin/ticket/' . $ticket->id . '/status') }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $s->value }}">
                                <button type="submit" class="{{ $ticket->status === $s ? 'on' : '' }}" id="status-{{ \Illuminate\Support\Str::slug($s->value) }}">{{ $s->value }}</button>
                            </form>
                        @endforeach
                    </div>
                @else
                    <span class="tk-badge" style="background:{{ $ticket->status->color() }};">{{ $ticket->status->value }}</span>
                @endcan
            </div>

            <div class="tk-card">
                <h3>Owner</h3>
                @can('permission', 'ticket_list.edit')
                    <form method="POST" action="{{ route('ticket.assign', $ticket->id) }}" style="display:flex; gap:8px;">
                        @csrf
                        <select name="assigned_to" class="form-control" id="ticket-owner">
                            <option value="">Unassigned</option>
                            @foreach ($users as $u)<option value="{{ $u->id }}" @selected((int) $ticket->assigned_to === (int) $u->id)>{{ $u->name }}</option>@endforeach
                            @if ($ticket->assigned_to && !$users->contains('id', $ticket->assigned_to))
                                <option value="{{ $ticket->assigned_to }}" selected>{{ $ticket->assignedTo->name ?? 'User #' . $ticket->assigned_to }} (not a person)</option>
                            @endif
                        </select>
                        <button type="submit" class="btn btn-secondary btn-sm" id="btn-assign">Save</button>
                    </form>
                @else
                    {{ $ticket->assignedTo->name ?? 'Unassigned' }}
                @endcan
            </div>

            @if ($device || $repair || $canOpenRepair)
            <div class="tk-card" id="linked-card">
                <h3>Linked</h3>
                @if ($device)
                    <div class="tk-row"><span>Invoice</span><span>@can('permission', 'sale_list.view')<a href="{{ url('admin/sale/' . $device['sale_id']) }}" id="link-invoice">{{ $device['invoice'] }}</a>@else{{ $device['invoice'] }}@endcan{{ $device['sold_on'] ? ' · ' . $device['sold_on']->format('d M Y') : '' }}</span></div>
                    @if ($device['product'])<div class="tk-row"><span>Device</span><span>{{ $device['product'] }}</span></div>@endif
                    @if ($device['serial'])<div class="tk-row"><span>Serial</span><span>{{ $device['serial'] }}</span></div>@endif
                    <div class="tk-row"><span>Warranty</span><span>
                        @if ($device['warranty'] === 'valid')<b style="color:#059669">Valid to {{ $device['warranty_until']->format('d M Y') }}</b>
                        @elseif ($device['warranty'] === 'ended')<b style="color:#be123c">Ended {{ $device['warranty_until']->format('d M Y') }}</b>
                        @else No warranty on this product @endif
                    </span></div>
                @endif
                @if ($repair)
                    <div class="tk-row"><span>Repair job</span><span>@if ($canSeeRepair)<a href="{{ url('admin/repairs/' . $repair->id) }}" id="link-repair">{{ $repair->job_no }}</a>@else{{ $repair->job_no }}@endif · {{ ucwords(str_replace('_', ' ', $repair->current_status)) }}</span></div>
                @endif
                @if ($canOpenRepair)
                    <a href="{{ url('admin/repairs/create') . '?' . http_build_query(array_filter(['customer_id' => $ticket->customer_id, 'serial' => $ticket->serial_no, 'ticket_id' => $ticket->id])) }}" class="btn btn-secondary btn-sm" style="margin-top:10px;" id="btn-open-repair"><i class="fas fa-tools mr-1"></i>Open repair job</a>
                @endif
            </div>
            @endif

            <div class="tk-card">
                <h3>Details</h3>
                <div class="tk-row"><span>Patient</span><span>
                    @if ($ticket->customer)<a href="{{ url('admin/customers/' . $ticket->customer_id) }}" id="link-patient">{{ $ticket->customer->lead->full_name ?? 'Patient #' . $ticket->customer_id }}</a>@else—@endif
                </span></div>
                <div class="tk-row"><span>Phone</span><span>{{ $ticket->customer->lead->phone_number ?? '—' }}</span></div>
                <div class="tk-row"><span>Priority</span><span>{{ $ticket->priority->value }}</span></div>
                @php $due = $ticket->dueState(); @endphp
                <div class="tk-row"><span>Deadline</span><span title="{{ $ticket->priority->value }}: {{ \App\Models\Ticket::slaHoursFor($ticket->priority->value) }} hours from opening">{{ $ticket->due_at->format('d M, h:i A') }}@if ($due['state'] !== 'done') · <b style="color:{{ $due['tone'] === 'danger' ? '#be123c' : ($due['tone'] === 'warning' ? '#b45309' : 'inherit') }}">{{ $due['label'] }}</b>@endif</span></div>
                <div class="tk-row"><span>Opened</span><span>{{ $ticket->created_at->format('d M Y') }}</span></div>
                <div class="tk-row"><span>Resolved</span><span>{{ $ticket->resolved_at ? $ticket->resolved_at->format('d M Y, h:i A') : '—' }}</span></div>
                @if ($ticket->resolved_at)
                    <div class="tk-row"><span>Time to resolve</span><span>{{ $ticket->created_at->diffForHumans($ticket->resolved_at, true) }}</span></div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
