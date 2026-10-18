@extends('admin.layouts.master')
@section('page_title', 'Time Slots')
@section('breadcrumb_parent', 'Appointments')
@section('breadcrumb_parent_url', url('admin/time-slots'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-clock" style="color: var(--accent-500); margin-right: 8px;"></i>Time Slots</h1>
            <p>Configure appointment scheduling time slots</p>
        </div>
        <div class="header-actions">
            @can('permission', 'time_slot.create')
                <a href="{{ url('admin/time-slots/create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>Add Time Slot</a>
            @endcan
        </div>
    </div>

    @include('admin.alert_message')

    <div class="alert alert-info d-flex align-items-center mb-3" style="border-radius: 8px; border-left: 4px solid #0284c7; background: #f0f9ff; color: #0369a1; padding: 12px 16px;">
        <i class="fas fa-info-circle mr-3" style="font-size: 22px; color: #0284c7;"></i>
        <div style="font-size: 13px; line-height: 1.5;">
            <strong>Modern Dynamic Scheduling Active:</strong>
            Appointments now automatically calculate continuous start times based on your specialists' working hours in 
            <a href="{{ url('admin/roster') }}" style="font-weight: 600; color: #0369a1; text-decoration: underline;">Staff Rosters & Leave</a> 
            and customized service durations in 
            <a href="{{ url('admin/appointment-services') }}" style="font-weight: 600; color: #0369a1; text-decoration: underline;">Service List</a>. 
            The static 1-hour slots below are preserved for legacy compatibility.
        </div>
    </div>

    <div class="crm-table-card crm-animate-in delay-1">
        <div class="crm-table-header">
            <div class="table-title"><i class="fas fa-list" style="color: var(--page-text-muted);"></i> All Time Slots <span class="table-count">{{ count($timeSlots) }}</span></div>
        </div>
        <div style="overflow-x: auto;">
            <table class="crm-table">
                <thead><tr><th style="width: 50px;">#</th><th>Start Time</th><th>End Time</th><th>Status</th><th style="width: 110px; text-align: center;">Actions</th></tr></thead>
                <tbody>
                    @forelse ($timeSlots as $key => $slot)
                    <tr>
                        <td style="color: var(--page-text-muted); font-size: 12px;">{{ ++$key }}</td>
                        <td>
                            <span style="font-weight: 500; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="far fa-clock" style="color: var(--accent-400); font-size: 13px;"></i>
                                {{ $slot->start_time->format('g:i A') }}
                            </span>
                        </td>
                        <td>
                            <span style="font-weight: 500; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="far fa-clock" style="color: var(--gray-400); font-size: 13px;"></i>
                                {{ $slot->end_time->format('g:i A') }}
                            </span>
                        </td>
                        <td>@if($slot->status)<span class="crm-status-badge badge-status-won"><span class="dot"></span>Active</span>@else<span class="crm-status-badge badge-status-lost"><span class="dot"></span>Inactive</span>@endif</td>
                        <td><div class="crm-actions">@can('permission', 'time_slot.edit')<a href="{{ route('time-slots.edit', $slot->id) }}" class="action-btn btn-edit" title="Edit"><i class="fas fa-pen"></i></a>@endcan @can('permission', 'time_slot.delete')<form action="{{ route('time-slots.destroy', $slot->id) }}" method="POST" style="display:inline;">@csrf @method('DELETE')<button type="submit" class="action-btn btn-delete" title="Delete" onclick="confirmDelete(event)"><i class="far fa-trash-alt"></i></button></form>@endcan</div></td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="text-align: center; padding: 40px !important; color: var(--page-text-muted);"><i class="fas fa-clock" style="font-size: 32px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>No time slots found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
