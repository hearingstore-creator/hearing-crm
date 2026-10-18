@extends('admin.layouts.master')
@section('page_title', 'Appointments')
@section('breadcrumb_parent', 'Appointments')
@section('breadcrumb_parent_url', url('admin/appointments'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-calendar-alt" style="color: var(--accent-500); margin-right: 8px;"></i>Appointments</h1>
            <p>Schedule, manage, and track patient appointments</p>
        </div>
        <div class="header-actions">
            <a href="{{ route('appointments.today') }}" class="btn btn-warning btn-sm" style="font-weight: 600;">
                <i class="fas fa-user-clock mr-1"></i>Today's Queue
            </a>
            @can('permission', 'calendar_view.index')
                <a href="{{ url('admin/appointments/calendar') }}" class="btn btn-secondary btn-sm">
                    <i class="fas fa-calendar mr-1"></i>Calendar View
                </a>
            @endcan
            @can('permission', 'add_appointment.create')
                <a href="{{ route('appointments.create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus mr-1"></i>Book Appointment
                </a>
            @endcan
        </div>
    </div>

    {{-- Filter Panel --}}
    <div class="crm-filter-panel crm-animate-in delay-1">
        <button class="crm-filter-toggle {{ request()->hasAny(['branch_id','user_id','service_id','status','start_date','end_date']) ? 'active' : '' }}" 
                onclick="this.classList.toggle('active'); document.getElementById('filterBody').style.display = this.classList.contains('active') ? 'block' : 'none';">
            <i class="fas fa-sliders-h"></i>
            <span>Filters</span>
            <i class="fas fa-chevron-down" style="margin-left: auto;"></i>
        </button>
        <div id="filterBody" class="crm-filter-body" style="{{ request()->hasAny(['branch_id','user_id','service_id','status','start_date','end_date']) ? '' : 'display:none;' }}">
            <form method="GET">
                <div class="filter-row">
                    @if(!empty($canViewAllBranches))
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Branch</label>
                        <select class="form-control select" name="branch_id">
                            <option value="">All Branches</option>
                            @foreach ($branches as $bId => $bName)
                                <option value="{{ $bId }}" {{ request('branch_id') == $bId ? 'selected' : '' }}>{{ $bName }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Professional</label>
                        <select class="form-control select" name="user_id">
                            <option value="">All Professionals</option>
                            @foreach ($professionals as $prof)
                                <option value="{{ $prof->id }}" {{ request('user_id') == $prof->id ? 'selected' : '' }}>{{ $prof->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Service</label>
                        <select class="form-control select" name="service_id">
                            <option value="">All Services</option>
                            @foreach ($appointmentServices as $service)
                                <option value="{{ $service->id }}" {{ request('service_id') == $service->id ? 'selected' : '' }}>{{ $service->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Status</label>
                        <select class="form-control" name="status">
                            <option value="">All Statuses</option>
                            @foreach ($appointmentStatuses as $status)
                                <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">From</label>
                        <input type="date" class="form-control" name="start_date" value="{{ request('start_date') }}">
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">To</label>
                        <input type="date" class="form-control" name="end_date" value="{{ request('end_date') }}">
                    </div>
                    <div class="filter-actions">
                        <button class="btn btn-primary btn-sm" type="submit" title="Search">
                            <i class="fas fa-search mr-1"></i>Search
                        </button>
                        <a href="{{ url('admin/appointments') }}" class="btn btn-secondary btn-sm" title="Clear">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @include('admin.alert_message')

    {{-- Data Table --}}
    <div class="crm-table-card crm-animate-in delay-2">
        <div class="crm-table-header">
            <div class="table-title">
                <i class="fas fa-list" style="color: var(--page-text-muted);"></i>
                All Appointments
                <span class="table-count">{{ $appointments->total() }}</span>
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Patient</th>
                        <th>Service & Resource</th>
                        <th>Branch</th>
                        <th>Specialist</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                        <th>Advance</th>
                        <th style="width: 110px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($appointments as $key => $appt)
                        <tr>
                            <td style="color: var(--page-text-muted); font-size: 12px;">{{ $appointments->firstItem() + $key }}</td>
                            <td>
                                <div class="crm-name-cell">
                                    @php
                                        $patientName = '—';
                                        $patientPhone = '';
                                        $patientLink = '#';
                                        if ($appt->customer) {
                                            $patientName = $appt->customer->lead?->full_name ?? 'Customer #'.$appt->customer_id;
                                            $patientPhone = $appt->customer->lead?->phone_number ?? '';
                                            $patientLink = url('admin/customers/'.$appt->customer->id.'/edit');
                                        } elseif ($appt->lead) {
                                            $patientName = $appt->lead->full_name;
                                            $patientPhone = $appt->lead->phone_number ?? '';
                                            $patientLink = url('admin/leads/'.$appt->lead->id.'/edit');
                                        }
                                    @endphp
                                    <div class="crm-avatar">{{ strtoupper(substr($patientName, 0, 1)) }}</div>
                                    <div class="crm-name-text">
                                        <a href="{{ $patientLink }}" class="name" style="color: var(--page-text); text-decoration: none; font-weight: 600;">{{ $patientName }}</a>
                                        @if($patientPhone)
                                            <div style="font-size: 11px; color: var(--page-text-muted);"><i class="fas fa-phone-alt mr-1" style="font-size: 9px;"></i>{{ $patientPhone }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if($appt->service)
                                    <span class="crm-status-badge badge-status-verified" style="display: inline-block;">
                                        <span class="dot"></span>
                                        {{ $appt->service->name }}
                                    </span>
                                @else
                                    <span style="color: var(--page-text-muted);">—</span>
                                @endif
                                @if($appt->resource)
                                    <div style="margin-top: 4px;">
                                        <span class="badge" style="background: #e0e7ff; color: #4338ca; font-size: 10px; font-weight: 600; padding: 2px 6px;">
                                            <i class="fas fa-door-open mr-1"></i>{{ $appt->resource->name }}
                                        </span>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <span style="font-size: 12px; font-weight: 500; color: var(--page-text);">
                                    <i class="fas fa-map-marker-alt mr-1" style="color: var(--accent-500); font-size: 11px;"></i>
                                    {{ $appt->branch?->company_name ?? $appt->branch?->name ?? 'Head Office' }}
                                </span>
                            </td>
                            <td>
                                @if($appt->user)
                                    <span style="font-size: 13px; font-weight: 500;">{{ $appt->user->name }}</span>
                                    @if($appt->user->designation)
                                        <br><span style="font-size: 11px; color: var(--page-text-muted);">{{ $appt->user->designation->label() }}</span>
                                    @endif
                                @else
                                    <span style="color: var(--page-text-muted);">—</span>
                                @endif
                            </td>
                            <td>
                                <div style="font-size: 13px; font-weight: 600; color: var(--page-text);">
                                    {{ $appt->appointment_date->format('d M Y') }}
                                </div>
                                @if(!empty($appt->is_time_unknown))
                                    <span class="badge badge-warning text-dark" style="font-size: 10px; font-weight: 700; padding: 2px 6px;">
                                        <i class="fas fa-question-circle mr-1"></i>Time Unknown
                                    </span>
                                @elseif(!empty($appt->start_time))
                                    <span style="font-size: 12px; color: var(--page-text-secondary);">
                                        <i class="far fa-clock mr-1" style="color: var(--accent-500);"></i>
                                        {{ date('g:i A', strtotime($appt->start_time)) }} - {{ date('g:i A', strtotime($appt->end_time)) }}
                                    </span>
                                @elseif($appt->professionalTimeSlot && $appt->professionalTimeSlot->timeSlot)
                                    <span style="font-size: 12px; color: var(--page-text-secondary);">
                                        <i class="far fa-clock mr-1" style="color: var(--accent-500);"></i>
                                        {{ $appt->professionalTimeSlot->timeSlot->start_time->format('g:i A') }} - {{ $appt->professionalTimeSlot->timeSlot->end_time->format('g:i A') }}
                                    </span>
                                @else
                                    <span style="color: var(--page-text-muted); font-size: 12px;">—</span>
                                @endif

                                @if(!empty($appt->needs_reschedule))
                                    <div style="margin-top: 3px;">
                                        <span class="badge badge-danger" style="font-size: 10px; font-weight: 700; padding: 2px 6px;">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>Needs Reschedule
                                        </span>
                                    </div>
                                @endif
                                @if(!empty($appt->is_overbooked))
                                    <div style="margin-top: 3px;">
                                        <span class="badge badge-warning text-dark" style="font-size: 10px; font-weight: 700; padding: 2px 6px;">
                                            <i class="fas fa-bolt mr-1"></i>Overbooked
                                        </span>
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($appt->status)
                                    <span class="crm-status-badge badge-status-{{ $appt->status->value }}">
                                        <span class="dot"></span>
                                        {{ $appt->status->label() }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if ($appt->customerAdvance)
                                    <div style="font-weight: 700; color: #16a34a; font-size: 13px;">৳{{ number_format($appt->customerAdvance->amount, 2) }}</div>
                                    <span class="{{ $appt->customerAdvance->statusBadgeClass() }}" style="font-size: 10px; padding: 2px 6px;">
                                        {{ $appt->customerAdvance->statusLabel() }}
                                    </span>
                                    <div style="margin-top: 3px;">
                                        <a href="{{ route('appointments.advance-receipt', $appt->id) }}" target="_blank" style="font-size: 11px; color: #0284c7; text-decoration: none;">
                                            <i class="fas fa-print mr-1"></i>Receipt
                                        </a>
                                    </div>
                                @else
                                    <span style="color: var(--page-text-muted); font-size: 12px;">—</span>
                                @endif
                            </td>
                            <td>
                                <div class="crm-actions">
                                    @can('permission', 'appointment_list.edit')
                                        <a href="{{ route('appointments.edit', $appt->id) }}" class="action-btn btn-edit" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                    @endcan
                                    @can('permission', 'appointment_list.delete')
                                        <form action="{{ route('appointments.destroy', $appt->id) }}" method="POST" style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="action-btn btn-delete" title="Delete"
                                                onclick="confirmDelete(event)">
                                                <i class="far fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px !important; color: var(--page-text-muted);">
                                <i class="fas fa-calendar-times" style="font-size: 32px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>
                                No appointments found. Try adjusting your filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($appointments->hasPages())
            <div class="crm-table-footer">
                <span>Showing {{ $appointments->firstItem() }}–{{ $appointments->lastItem() }} of {{ $appointments->total() }} appointments</span>
                {{ $appointments->appends(request()->query())->links() }}
            </div>
        @endif
    </div>

</div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            $('.select').select2({ width: '100%', allowClear: true });
        });
    </script>
@endpush
