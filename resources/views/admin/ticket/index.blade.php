@extends('admin.layouts.master')
@section('page_title', 'Tickets')
@section('breadcrumb_parent', 'Support')
@section('breadcrumb_parent_url', url('admin/ticket'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/kpi-cards.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-ticket-alt" style="color: var(--accent-500); margin-right: 8px;"></i>Tickets</h1>
            <p>Manage support tickets and customer issues</p>
        </div>
        <div class="header-actions">
            @can('permission','add_ticket.create')
                <a href="{{ url('admin/ticket/create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>Add Ticket</a>
            @endcan
        </div>
    </div>

    {{-- Summary Cards --}}
    @php $ts = $ticketStats; $noCardFilter = !request()->hasAny(['status', 'open_only', 'done_only', 'assigned_to']); @endphp
    <div class="kpi-row crm-animate-in">
        @include('admin.partials.kpi-card', [
            'href' => url('admin/ticket'), 'color' => 'indigo', 'icon' => 'fas fa-ticket-alt', 'id' => 'kpi-ticket-total',
            'label' => 'Total Tickets', 'value' => number_format($ts['total']),
            'sub' => '<b>+' . $ts['today'] . '</b> opened today', 'active' => $noCardFilter,
        ])
        @include('admin.partials.kpi-card', [
            'href' => url('admin/ticket?open_only=1'), 'color' => 'rose', 'icon' => 'fas fa-hourglass-half', 'id' => 'kpi-ticket-due',
            'label' => 'Due / Unresolved', 'value' => number_format($ts['due']),
            'sub' => '<b>' . $ts['open'] . '</b> still open', 'active' => request()->boolean('open_only'),
        ])
        @include('admin.partials.kpi-card', [
            'href' => url('admin/ticket?status=' . urlencode('In Progress')), 'color' => 'amber', 'icon' => 'fas fa-spinner', 'id' => 'kpi-ticket-progress',
            'label' => 'In Progress', 'value' => number_format($ts['in_progress']),
            'active' => request('status') === 'In Progress',
        ])
        @include('admin.partials.kpi-card', [
            'href' => url('admin/ticket?done_only=1'), 'color' => 'green', 'icon' => 'fas fa-check-double', 'id' => 'kpi-ticket-completed',
            'label' => 'Completed', 'value' => number_format($ts['completed']),
            'sub' => '<b>' . ($ts['total'] ? round($ts['completed'] / $ts['total'] * 100) : 0) . '%</b> resolution rate',
            'active' => request()->boolean('done_only') || in_array(request('status'), ['Solved', 'Closed'], true),
        ])
        @include('admin.partials.kpi-card', [
            'href' => url('admin/ticket?open_only=1&assigned_to=' . auth()->id()), 'color' => 'violet', 'icon' => 'fas fa-user-check', 'id' => 'kpi-ticket-mine',
            'label' => 'Assigned to Me', 'value' => number_format($ts['mine_open']),
            'sub' => 'open tickets', 'active' => (string) request('assigned_to') === (string) auth()->id(),
        ])
    </div>

    <div class="crm-filter-panel crm-animate-in delay-1">
        <button class="crm-filter-toggle {{ request()->hasAny(['status','priority','customer_id','assigned_to','start_date','end_date']) ? 'active' : '' }}" 
                onclick="this.classList.toggle('active'); document.getElementById('filterBody').style.display = this.classList.contains('active') ? 'block' : 'none';">
            <i class="fas fa-sliders-h"></i><span>Filters</span><i class="fas fa-chevron-down" style="margin-left: auto;"></i>
        </button>
        <div id="filterBody" class="crm-filter-body" style="{{ request()->hasAny(['status','priority','customer_id','assigned_to','start_date','end_date']) ? '' : 'display:none;' }}">
            <form method="GET">
                <div class="filter-row">
                    <div><label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Status</label>
                        <select class="form-control select" name="status"><option value="">All Statuses</option>@foreach ($statuses ?? [] as $status)<option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>{{ $status->value }}</option>@endforeach</select></div>
                    <div><label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Priority</label>
                        <select class="form-control select" name="priority"><option value="">All Priorities</option>@foreach ($priorities ?? [] as $priority)<option value="{{ $priority->value }}" {{ request('priority') == $priority->value ? 'selected' : '' }}>{{ $priority->value }}</option>@endforeach</select></div>
                    <div><label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Customer</label>
                        <select class="form-control select" name="customer_id"><option value="">All Customers</option>@foreach ($customers ?? [] as $customer)<option value="{{ $customer->id }}" {{ request('customer_id') == $customer->id ? 'selected' : '' }}>{{ $customer->lead->full_name }}</option>@endforeach</select></div>
                    <div><label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Assigned To</label>
                        <select class="form-control select" name="assigned_to"><option value="">All Users</option>@foreach ($users ?? [] as $user)<option value="{{ $user->id }}" {{ request('assigned_to') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>@endforeach</select></div>
                    <div><label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">From</label>
                        <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}"></div>
                    <div><label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">To</label>
                        <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}"></div>
                    <div class="filter-actions">
                        <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-search mr-1"></i>Search</button>
                        <a href="{{ url('admin/ticket') }}" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i></a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @include('admin.alert_message')

    <div class="crm-table-card crm-animate-in delay-2">
        <div class="crm-table-header">
            <div class="table-title"><i class="fas fa-list" style="color: var(--page-text-muted);"></i> All Tickets</div>
        </div>
        <div style="overflow-x: auto;">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Subject</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Assigned To</th>
                        <th>Created</th>
                        <th style="width: 110px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tickets ?? [] as $key => $ticket)
                    <tr>
                        <td style="color: var(--page-text-muted); font-size: 12px;">{{ ++$key }}</td>
                        <td><span style="font-weight: 500;">{{ $ticket->subject }}</span></td>
                        <td style="font-size: 13px;">{{ $ticket->customer->lead->full_name ?? '' }}</td>
                        <td><span class="badge" style="background-color: {{ $ticket->status->color() }}; color: #fff; font-size: 11px; padding: 4px 10px; border-radius: 20px;">{{ $ticket->status->value }}</span></td>
                        <td><span class="badge" style="background-color: {{ $ticket->priority->color() }}; color: #fff; font-size: 11px; padding: 4px 10px; border-radius: 20px;">{{ $ticket->priority->value }}</span></td>
                        <td style="font-size: 13px;">{{ $ticket->assignedTo->name ?? 'Unassigned' }}</td>
                        <td><span style="font-size: 12px; color: var(--page-text-muted);">{{ $ticket->created_at->format('d M Y') }}</span></td>
                        <td>
                            <div class="crm-actions">
                                @can('permission','ticket_list.edit')<a href="{{ url('admin/ticket/'.$ticket->id.'/edit') }}" class="action-btn btn-edit" title="Edit"><i class="fas fa-pen"></i></a>@endcan
                                @can('permission','ticket_list.delete')<form action="{{ url('admin/ticket/'.$ticket->id) }}" method="POST" style="display:inline;">@csrf @method('DELETE')<button type="submit" class="action-btn btn-delete" title="Delete" onclick="confirmDelete(event)"><i class="far fa-trash-alt"></i></button></form>@endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" style="text-align: center; padding: 40px !important; color: var(--page-text-muted);"><i class="fas fa-ticket-alt" style="font-size: 32px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>No tickets found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="crm-table-footer">{{ $tickets->appends(request()->input())->links() }}</div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>$(document).ready(function() { $('.select').select2(); });</script>
@endpush
