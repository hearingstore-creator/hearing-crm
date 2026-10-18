@extends('admin.layouts.master')
@section('page_title', 'Service Types')
@section('breadcrumb_parent', 'Settings')
@section('breadcrumb_parent_url', url('admin/service-type'))

@push('css')
@include('admin.partials.datatables-css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-concierge-bell" style="color: var(--accent-500); margin-right: 8px;"></i>Service Types</h1>
            <p>Manage service catalog, pricing, and booking requirements</p>
        </div>
        <div class="header-actions">
            @can('permission', 'service_type.create')
                <a href="{{ url('admin/service-type/create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>Add Service Type</a>
            @endcan
        </div>
    </div>

    @include('admin.alert_message')

    <div class="crm-table-card crm-animate-in delay-1">
        <div class="crm-table-header">
            <div class="table-title"><i class="fas fa-list" style="color: var(--page-text-muted);"></i> All Service Types <span class="table-count">{{ count($serviceTypes) }}</span></div>
        </div>
        <div style="overflow-x: auto;">
            <table class="crm-table" data-datatable="service-type-list">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Service Name</th>
                        <th>Booking Type</th>
                        <th>Linked Appt Service</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th style="width: 110px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($serviceTypes as $key => $val)
                    <tr>
                        <td style="color: var(--page-text-muted); font-size: 12px;">{{ ++$key }}</td>
                        <td><span style="font-weight: 600; color: var(--page-text);">{{ $val->service_type_name }}</span></td>
                        <td>
                            @if($val->booking_type === 'walk_in')
                                <span class="badge badge-success"><i class="fas fa-walking mr-1"></i>Walk-in</span>
                            @elseif($val->booking_type === 'appointment_required')
                                <span class="badge badge-warning text-dark"><i class="fas fa-calendar-alt mr-1"></i>Appointment required</span>
                            @else
                                <span class="badge badge-info"><i class="fas fa-exchange-alt mr-1"></i>Either</span>
                            @endif
                        </td>
                        <td>
                            @if($val->appointmentService)
                                <span class="text-sm font-weight-500 text-dark"><i class="far fa-calendar-check text-success mr-1"></i>{{ $val->appointmentService->name }}</span>
                            @else
                                <span class="text-muted" style="font-size: 12px;">—</span>
                            @endif
                        </td>
                        <td><span style="font-weight: 600;">{{ $val->price ? '৳'.number_format($val->price, 2) : '—' }}</span></td>
                        <td>
                            @if($val->status==1)
                                <span class="crm-status-badge badge-status-won"><span class="dot"></span>Active</span>
                            @else
                                <span class="crm-status-badge badge-status-lost"><span class="dot"></span>Inactive</span>
                            @endif
                        </td>
                        <td>
                            <div class="crm-actions">
                                @can('permission', 'service_type.edit')
                                    <a href="{{ route('service-type.edit', $val->id) }}" class="action-btn btn-edit" title="Edit"><i class="fas fa-pen"></i></a>
                                @endcan
                                @can('permission', 'service_type.delete')
                                    <form action="{{ route('service-type.destroy', $val->id) }}" method="POST" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="action-btn btn-delete" title="Delete" onclick="confirmDelete(event)"><i class="far fa-trash-alt"></i></button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection