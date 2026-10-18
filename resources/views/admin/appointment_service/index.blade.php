@extends('admin.layouts.master')
@section('page_title', 'Appointment Services')
@section('breadcrumb_parent', 'Appointments')
@section('breadcrumb_parent_url', url('admin/appointment-services'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-concierge-bell" style="color: var(--accent-500); margin-right: 8px;"></i>Appointment Services</h1>
            <p>Manage available services for appointments</p>
        </div>
        <div class="header-actions">
            @can('permission', 'add_service.create')
                <a href="{{ url('admin/appointment-services/create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>Add Service</a>
            @endcan
        </div>
    </div>

    @include('admin.alert_message')

    <div class="crm-table-card crm-animate-in delay-1">
        <div class="crm-table-header">
            <div class="table-title"><i class="fas fa-list" style="color: var(--page-text-muted);"></i> All Services <span class="table-count">{{ count($services) }}</span></div>
        </div>
        <div style="overflow-x: auto;">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Service Name</th>
                        <th>Category</th>
                        <th>Duration & Buffer</th>
                        <th>Equipment / Room</th>
                        <th>Online Booking</th>
                        <th>Max/Day</th>
                        <th>Status</th>
                        <th style="width: 110px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($services as $key => $service)
                    <tr>
                        <td style="color: var(--page-text-muted); font-size: 12px;">{{ ++$key }}</td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                @if($service->color_hex)
                                    <span style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: {{ $service->color_hex }}; flex-shrink: 0;" title="Calendar Color: {{ $service->color_hex }}"></span>
                                @endif
                                <div>
                                    <span style="font-weight: 600; color: var(--gray-800);">{{ $service->name }}</span>
                                    @if($service->description)
                                        <div style="font-size: 11px; color: var(--page-text-muted); line-height: 1.3;">{{ Str::limit($service->description, 50) }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>@if($service->category)<span class="crm-status-badge badge-status-verified"><span class="dot"></span>{{ $service->category->label() }}</span>@endif</td>
                        <td>
                            <div style="font-size: 13px; font-weight: 600; color: #1e293b;">
                                <i class="far fa-clock text-primary mr-1"></i>{{ $service->duration_minutes ?? 45 }} mins
                            </div>
                            <div style="font-size: 11px; color: var(--page-text-muted);">
                                +{{ $service->buffer_after_minutes ?? 10 }}m clean buffer
                            </div>
                        </td>
                        <td>
                            @if($service->requires_resource)
                                <span class="badge badge-warning" style="font-size: 11px; padding: 4px 8px; border-radius: 6px; font-weight: 600; background: #fef3c7; color: #92400e; border: 1px solid #fde68a;">
                                    <i class="fas fa-door-closed mr-1"></i>Sound Booth Req.
                                </span>
                            @else
                                <span class="text-muted" style="font-size: 12px;">Standard Room</span>
                            @endif
                        </td>
                        <td>
                            @if($service->is_online_booking_enabled)
                                <span class="badge badge-success" style="font-size: 11px; padding: 4px 8px; border-radius: 6px; font-weight: 600; background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0;">
                                    <i class="fas fa-globe mr-1"></i>Website Enabled
                                </span>
                            @else
                                <span class="badge badge-secondary" style="font-size: 11px; padding: 4px 8px; border-radius: 6px; font-weight: 500; background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0;">
                                    Internal Only
                                </span>
                            @endif
                        </td>
                        <td style="font-size: 13px;">{{ $service->max_per_day }}</td>
                        <td>@if($service->status)<span class="crm-status-badge badge-status-won"><span class="dot"></span>Active</span>@else<span class="crm-status-badge badge-status-lost"><span class="dot"></span>Inactive</span>@endif</td>
                        <td><div class="crm-actions">@can('permission', 'service_list.edit')<a href="{{ route('appointment-services.edit', $service->id) }}" class="action-btn btn-edit" title="Edit"><i class="fas fa-pen"></i></a>@endcan @can('permission', 'service_list.delete')<form action="{{ route('appointment-services.destroy', $service->id) }}" method="POST" style="display:inline;">@csrf @method('DELETE')<button type="submit" class="action-btn btn-delete" title="Delete" onclick="confirmDelete(event)"><i class="far fa-trash-alt"></i></button></form>@endcan</div></td>
                    </tr>
                    @empty
                    <tr><td colspan="9" style="text-align: center; padding: 40px !important; color: var(--page-text-muted);"><i class="fas fa-inbox" style="font-size: 32px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>No services found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
