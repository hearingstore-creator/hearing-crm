@extends('admin.layouts.master')
@section('page_title', 'Tasks')
@section('breadcrumb_parent', 'CRM')
@section('breadcrumb_parent_url', url('admin/task'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/kpi-cards.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-tasks" style="color: var(--accent-500); margin-right: 8px;"></i>Tasks</h1>
            <p>Manage and track team tasks and assignments</p>
        </div>
        <div class="header-actions">
            @can('permission', 'add_task.create')
                <a href="{{ url('admin/task/create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>Add Task</a>
            @endcan
        </div>
    </div>

    {{-- Summary Cards --}}
    @php $ks = $taskStats; $noCardFilter = !request()->hasAny(['status', 'open_only', 'overdue', 'assigned_to']); @endphp
    <div class="kpi-row crm-animate-in">
        @include('admin.partials.kpi-card', [
            'href' => url('admin/task'), 'color' => 'indigo', 'icon' => 'fas fa-tasks', 'id' => 'kpi-task-total',
            'label' => 'Total Tasks', 'value' => number_format($ks['total']),
            'sub' => '<b>' . $ks['cancelled'] . '</b> cancelled', 'active' => $noCardFilter,
        ])
        @include('admin.partials.kpi-card', [
            'href' => url('admin/task?open_only=1'), 'color' => 'amber', 'icon' => 'fas fa-hourglass-half', 'id' => 'kpi-task-pending',
            'label' => 'Due / Pending', 'value' => number_format($ks['pending']),
            'sub' => '<b>' . $ks['in_progress'] . '</b> in progress · <b>' . $ks['due_today'] . '</b> due today', 'active' => request()->boolean('open_only'),
        ])
        @include('admin.partials.kpi-card', [
            'href' => url('admin/task?overdue=1'), 'color' => 'rose', 'icon' => 'fas fa-exclamation-triangle', 'id' => 'kpi-task-overdue',
            'label' => 'Overdue', 'value' => number_format($ks['overdue']),
            'sub' => 'past due date', 'active' => request()->boolean('overdue'),
        ])
        @include('admin.partials.kpi-card', [
            'href' => url('admin/task?status=completed'), 'color' => 'green', 'icon' => 'fas fa-check-double', 'id' => 'kpi-task-completed',
            'label' => 'Completed', 'value' => number_format($ks['completed']),
            'sub' => '<b>' . ($ks['total'] ? round($ks['completed'] / $ks['total'] * 100) : 0) . '%</b> completion rate',
            'active' => request('status') === 'completed',
        ])
        @include('admin.partials.kpi-card', [
            'href' => url('admin/task?open_only=1&assigned_to=' . auth()->id()), 'color' => 'violet', 'icon' => 'fas fa-user-check', 'id' => 'kpi-task-mine',
            'label' => 'Assigned to Me', 'value' => number_format($ks['mine_open']),
            'sub' => 'open tasks', 'active' => (string) request('assigned_to') === (string) auth()->id(),
        ])
    </div>

    <div class="crm-filter-panel crm-animate-in delay-1">
        <button class="crm-filter-toggle {{ request()->hasAny(['status','priority','customer_id','assigned_to','search']) ? 'active' : '' }}" 
                onclick="this.classList.toggle('active'); document.getElementById('filterBody').style.display = this.classList.contains('active') ? 'block' : 'none';">
            <i class="fas fa-sliders-h"></i><span>Filters</span><i class="fas fa-chevron-down" style="margin-left: auto;"></i>
        </button>
        <div id="filterBody" class="crm-filter-body" style="{{ request()->hasAny(['status','priority','customer_id','assigned_to','search']) ? '' : 'display:none;' }}">
            <form method="GET">
                <div class="filter-row">
                    <div><label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Status</label>
                        <select class="form-control select2" name="status"><option value="">All Statuses</option>@foreach ($statuses as $status)<option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>{{ $status->label() }}</option>@endforeach</select></div>
                    <div><label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Priority</label>
                        <select class="form-control select2" name="priority"><option value="">All Priorities</option>@foreach ($priorities as $priority)<option value="{{ $priority->value }}" {{ request('priority') == $priority->value ? 'selected' : '' }}>{{ $priority->label() }}</option>@endforeach</select></div>
                    <div><label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Customer</label>
                        <select class="form-control select2" name="customer_id"><option value="">All Customers</option>@foreach ($customers as $customer)<option value="{{ $customer->id }}" {{ request('customer_id') == $customer->id ? 'selected' : '' }}>{{ $customer->lead->full_name ?? '-' }}</option>@endforeach</select></div>
                    <div><label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Assigned To</label>
                        <select class="form-control select2" name="assigned_to"><option value="">All Users</option>@foreach ($users as $user)<option value="{{ $user->id }}" {{ request('assigned_to') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>@endforeach</select></div>
                    <div><label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search..." value="{{ request('search') }}"></div>
                    <div class="filter-actions">
                        <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-search mr-1"></i>Search</button>
                        <a href="{{ url('admin/task') }}" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i></a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @include('admin.alert_message')

    <div class="crm-table-card crm-animate-in delay-2">
        <div class="crm-table-header">
            <div class="table-title"><i class="fas fa-list" style="color: var(--page-text-muted);"></i> All Tasks</div>
        </div>
        <div style="overflow-x: auto;">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Subject</th>
                        <th>Customer</th>
                        <th>Assigned To</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Due Date</th>
                        <th style="width: 110px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tasks ?? [] as $key => $task)
                    <tr>
                        <td style="color: var(--page-text-muted); font-size: 12px;">{{ ($tasks->currentPage() - 1) * $tasks->perPage() + $key + 1 }}</td>
                        <td><span style="font-weight: 500;">{{ $task->subject }}</span></td>
                        <td style="font-size: 13px;">{{ $task->customer->lead->full_name ?? '—' }}</td>
                        <td style="font-size: 13px;">{{ $task->assignedTo->name ?? '—' }}</td>
                        <td><span class="crm-status-badge badge-status-{{ $task->status->value }}"><span class="dot"></span>{{ $task->status->label() }}</span></td>
                        <td><span class="crm-status-badge badge-status-{{ $task->priority->value }}"><span class="dot"></span>{{ $task->priority->label() }}</span></td>
                        <td>
                            @if($task->due_date)
                                <span style="font-size: 12px;">{{ $task->due_date->format('d M Y') }}</span>
                                @if($task->isOverdue())<i class="fas fa-exclamation-triangle" style="color: var(--danger-500); margin-left: 4px;" title="Overdue"></i>@endif
                            @else <span style="color: var(--page-text-muted);">—</span> @endif
                        </td>
                        <td>
                            <div class="crm-actions">
                                @can('permission', 'task_list.edit')<a href="{{ url('admin/task/'.$task->id.'/edit') }}" class="action-btn btn-edit" title="Edit"><i class="fas fa-pen"></i></a>@endcan
                                @can('permission', 'task_list.delete')<form action="{{ url('admin/task', $task->id) }}" method="POST" style="display:inline;">@csrf @method('DELETE')<button type="submit" class="action-btn btn-delete" title="Delete" onclick="confirmDelete(event)"><i class="far fa-trash-alt"></i></button></form>@endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" style="text-align: center; padding: 40px !important; color: var(--page-text-muted);"><i class="fas fa-tasks" style="font-size: 32px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>No tasks found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($tasks ?? null)
        <div class="crm-table-footer">
            <span>Showing {{ $tasks->firstItem() }}–{{ $tasks->lastItem() }} of {{ $tasks->total() }}</span>
            {{ $tasks->appends(request()->all())->links() }}
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>$(document).ready(function() { $('.select2').select2(); });</script>
@endpush
