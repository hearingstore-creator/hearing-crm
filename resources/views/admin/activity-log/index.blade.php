@extends('admin.layouts.master')
@section('page_title', 'Activity Log')
@section('breadcrumb_parent', 'System')
@section('breadcrumb_parent_url', url('admin/activity-log'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-history" style="color: var(--accent-500); margin-right: 8px;"></i>Activity Log</h1>
            <p>Track all system actions and changes</p>
        </div>
        <div class="header-actions">
            <a href="{{ route('activity-log.export', request()->query()) }}" class="btn btn-secondary btn-sm" id="btn-export"><i class="fas fa-file-csv mr-1"></i>Export CSV</a>
        </div>
    </div>

    @include('admin.activity-log.tabs', ['active' => 'changes'])

    <div class="crm-filter-panel crm-animate-in delay-1">
        <button class="crm-filter-toggle {{ request()->hasAny(['user_id','subject_type','action','start_date','end_date']) ? 'active' : '' }}" 
                onclick="this.classList.toggle('active'); document.getElementById('filterBody').style.display = this.classList.contains('active') ? 'block' : 'none';">
            <i class="fas fa-sliders-h"></i><span>Filters</span><i class="fas fa-chevron-down" style="margin-left: auto;"></i>
        </button>
        <div id="filterBody" class="crm-filter-body" style="{{ request()->hasAny(['user_id','subject_type','action','start_date','end_date']) ? '' : 'display:none;' }}">
            <form method="GET" action="{{ url('admin/activity-log') }}">
                <div class="filter-row">
                    <div><label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">User</label>
                        <select class="form-control select2" name="user_id"><option value="">All Users</option>@foreach ($users ?? [] as $user)<option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>@endforeach</select></div>
                    <div><label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Subject Type</label>
                        <select class="form-control select2" name="subject_type"><option value="">All Types</option>@foreach ($subjectTypes ?? [] as $subjectType)<option value="{{ $subjectType }}" {{ request('subject_type') == $subjectType ? 'selected' : '' }}>{{ class_basename($subjectType) }}</option>@endforeach</select></div>
                    <div><label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Action</label>
                        <select class="form-control select2" name="action"><option value="">All Actions</option><option value="created" {{ request('action') == 'created' ? 'selected' : '' }}>Created</option><option value="updated" {{ request('action') == 'updated' ? 'selected' : '' }}>Updated</option><option value="deleted" {{ request('action') == 'deleted' ? 'selected' : '' }}>Deleted</option></select></div>
                    <div><label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">From</label>
                        <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}"></div>
                    <div><label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">To</label>
                        <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}"></div>
                    <div class="filter-actions">
                        <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-search mr-1"></i>Search</button>
                        <a href="{{ url('admin/activity-log') }}" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i></a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @include('admin.alert_message')

    <div class="crm-table-card crm-animate-in delay-2">
        <div class="crm-table-header">
            <div class="table-title"><i class="fas fa-list" style="color: var(--page-text-muted);"></i> Activity Records</div>
        </div>
        <div style="overflow-x: auto;">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>User</th>
                        <th>Subject</th>
                        <th>Action</th>
                        <th>Changes</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($activityLogs ?? [] as $key => $log)
                    <tr>
                        <td style="color: var(--page-text-muted); font-size: 12px;">{{ ++$key + ($activityLogs->firstItem() - 1) }}</td>
                        <td>
                            <div class="crm-name-cell">
                                <div class="crm-avatar">{{ strtoupper(substr($log->user->name ?? 'U', 0, 1)) }}</div>
                                <div class="crm-name-text">
                                    <span class="name">{{ $log->user->name ?? 'N/A' }}</span>
                                    <span class="uid">{{ $log->user->email ?? '' }}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($log->subject_type)
                                <span style="font-weight: 500; font-size: 13px;">{{ class_basename($log->subject_type) }}</span>
                                <br><span style="font-size: 11px; color: var(--page-text-muted);">ID: {{ $log->subject_id ?? 'N/A' }}</span>
                            @else <span style="color: var(--page-text-muted);">N/A</span> @endif
                        </td>
                        <td>
                            <span class="crm-status-badge badge-status-{{ $log->action ?? 'default' }}"><span class="dot"></span>{{ $log->action ?? 'N/A' }}</span>
                        </td>
                        <td>
                            @if($log->old_values || $log->new_values)
                                @if($log->old_values)<strong style="font-size: 10px; color: var(--danger-500);">OLD:</strong><pre style="font-size: 10px; max-height: 80px; overflow-y: auto; background: var(--gray-50); border-radius: 4px; padding: 4px 8px; margin: 2px 0;">{{ json_encode($log->old_values, JSON_PRETTY_PRINT) }}</pre>@endif
                                @if($log->new_values)<strong style="font-size: 10px; color: var(--success-500);">NEW:</strong><pre style="font-size: 10px; max-height: 80px; overflow-y: auto; background: var(--gray-50); border-radius: 4px; padding: 4px 8px; margin: 2px 0;">{{ json_encode($log->new_values, JSON_PRETTY_PRINT) }}</pre>@endif
                            @else <span style="color: var(--page-text-muted); font-size: 12px;">No changes</span> @endif
                        </td>
                        <td><span style="font-size: 12px; color: var(--page-text-muted);">{{ $log->created_at ? $log->created_at->format('d M Y, h:i A') : 'N/A' }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="text-align: center; padding: 40px !important; color: var(--page-text-muted);"><i class="fas fa-history" style="font-size: 32px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>No activity logs found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($activityLogs ?? null)
        <div class="crm-table-footer">{{ $activityLogs->appends(request()->query())->links() }}</div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>$(document).ready(function() { $('.select2').select2({ width: '100%', placeholder: 'Select option' }); });</script>
@endpush
