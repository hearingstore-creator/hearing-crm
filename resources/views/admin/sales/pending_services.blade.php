@extends('admin.layouts.master')
@section('page_title', 'Services Sold – Not Yet Booked or Done')
@section('breadcrumb_parent', 'Sales')
@section('breadcrumb_parent_url', url('admin/sale'))

@push('css')
@include('admin.partials.datatables-css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<style>
.waiting-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.3px;
}
.waiting-fresh {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}
.waiting-warning {
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fde68a;
}
.waiting-urgent {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
    animation: pulseSlow 2s infinite;
}
@keyframes pulseSlow {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}
.filter-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.filter-tab-btn {
    padding: 7px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid var(--card-border, #e2e8f0);
    background: var(--table-header-bg, #f8fafc);
    color: var(--page-text-muted, #64748b);
    display: inline-flex;
    align-items: center;
    transition: all 0.2s;
}
.filter-tab-btn:hover {
    background: #e2e8f0;
    color: var(--page-text, #0f172a);
    text-decoration: none;
}
.filter-tab-btn.active {
    background: var(--accent-500, #4f46e5);
    color: #ffffff;
    border-color: var(--accent-500, #4f46e5);
}
.filter-tab-btn .badge {
    margin-left: 6px;
    font-size: 11px;
}
</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    {{-- Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1>
                <i class="fas fa-stethoscope" style="color: var(--accent-500); margin-right: 8px;"></i>
                Services Sold – Not Yet Booked or Done
            </h1>
            <p>Track sold services, follow up with patients by days waiting, and book or deliver their care.</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/appointments/calendar') }}" class="btn btn-outline-secondary btn-sm">
                <i class="far fa-calendar-alt mr-1"></i>Appointments Calendar
            </a>
            <a href="{{ url('admin/sale') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-shopping-cart mr-1"></i>All Sales
            </a>
        </div>
    </div>

    @include('admin.alert_message')

    {{-- Filter Tabs --}}
    <div class="filter-tabs">
        <a href="{{ route('sales.pending-services', array_merge(request()->query(), ['status' => 'not_booked'])) }}" 
           class="filter-tab-btn {{ $statusFilter === 'not_booked' ? 'active' : '' }}">
            <i class="fas fa-hourglass-start mr-1"></i>Not Booked Yet
            <span class="badge {{ $statusFilter === 'not_booked' ? 'badge-light text-dark' : 'badge-warning' }}">{{ $notBookedCount }}</span>
        </a>
        <a href="{{ route('sales.pending-services', array_merge(request()->query(), ['status' => 'booked'])) }}" 
           class="filter-tab-btn {{ $statusFilter === 'booked' ? 'active' : '' }}">
            <i class="fas fa-calendar-check mr-1"></i>Booked (Awaiting Visit)
            <span class="badge {{ $statusFilter === 'booked' ? 'badge-light text-dark' : 'badge-info' }}">{{ $bookedCount }}</span>
        </a>
        <a href="{{ route('sales.pending-services', array_merge(request()->query(), ['status' => 'pending'])) }}" 
           class="filter-tab-btn {{ $statusFilter === 'pending' ? 'active' : '' }}">
            <i class="fas fa-list-ul mr-1"></i>All Pending
            <span class="badge {{ $statusFilter === 'pending' ? 'badge-light text-dark' : 'badge-secondary' }}">{{ $notBookedCount + $bookedCount }}</span>
        </a>
        <a href="{{ route('sales.pending-services', array_merge(request()->query(), ['status' => 'done'])) }}" 
           class="filter-tab-btn {{ $statusFilter === 'done' ? 'active' : '' }}">
            <i class="fas fa-check-circle mr-1"></i>Delivered / Done
            <span class="badge {{ $statusFilter === 'done' ? 'badge-light text-dark' : 'badge-success' }}">{{ $doneCount }}</span>
        </a>
        <a href="{{ route('sales.pending-services', array_merge(request()->query(), ['status' => 'all'])) }}" 
           class="filter-tab-btn {{ $statusFilter === 'all' ? 'active' : '' }}">
            All Records
        </a>
    </div>

    {{-- Filters Card --}}
    <div class="crm-table-card crm-animate-in" style="margin-bottom: 20px; padding: 16px 20px;">
        <form method="GET" action="{{ route('sales.pending-services') }}" class="row align-items-end">
            <input type="hidden" name="status" value="{{ $statusFilter }}">

            <div class="col-md-3">
                <label style="font-size: 12px; font-weight: 600; color: var(--page-text-muted); margin-bottom: 4px;">Search Patient / Invoice</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Name, Phone, or Invoice #" value="{{ $search }}">
            </div>

            <div class="col-md-3">
                <label style="font-size: 12px; font-weight: 600; color: var(--page-text-muted); margin-bottom: 4px;">Booking Type</label>
                <select name="booking_type" class="form-control form-control-sm">
                    <option value="">All Booking Types</option>
                    <option value="walk_in" {{ $bookingTypeFilter === 'walk_in' ? 'selected' : '' }}>Walk-in</option>
                    <option value="appointment_required" {{ $bookingTypeFilter === 'appointment_required' ? 'selected' : '' }}>Appointment required</option>
                    <option value="either" {{ $bookingTypeFilter === 'either' ? 'selected' : '' }}>Either</option>
                </select>
            </div>

            @if(count($branches) > 1)
            <div class="col-md-3">
                <label style="font-size: 12px; font-weight: 600; color: var(--page-text-muted); margin-bottom: 4px;">Branch</label>
                <select name="vendor_id" class="form-control form-control-sm">
                    <option value="">All Branches</option>
                    @foreach($branches as $bId => $bName)
                        <option value="{{ $bId }}" {{ $branchId == $bId ? 'selected' : '' }}>{{ $bName }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <div class="col-md-3">
                <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-filter mr-1"></i>Filter</button>
                <a href="{{ route('sales.pending-services') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
        </form>
    </div>

    {{-- Main Table --}}
    <div class="crm-table-card crm-animate-in delay-1">
        <div class="crm-table-header">
            <div class="table-title">
                <i class="fas fa-stethoscope" style="color: var(--page-text-muted);"></i>
                Services List 
                <span class="table-count">{{ $services->total() }}</span>
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th style="width: 45px;">#</th>
                        <th style="width: 120px;">Days Waiting</th>
                        <th>Sale Date & Invoice</th>
                        <th>Patient Name & Phone</th>
                        <th>Branch</th>
                        <th>Service Item</th>
                        <th>Booking Type</th>
                        <th>Status</th>
                        <th style="width: 190px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($services as $idx => $sd)
                    @php
                        $daysWaiting = $sd->daysWaiting();
                        if ($daysWaiting <= 1) {
                            $waitClass = 'waiting-fresh';
                            $waitLabel = $daysWaiting === 0 ? 'Today' : '1 day';
                        } elseif ($daysWaiting <= 4) {
                            $waitClass = 'waiting-warning';
                            $waitLabel = $daysWaiting . ' days';
                        } else {
                            $waitClass = 'waiting-urgent';
                            $waitLabel = $daysWaiting . ' days (Call)';
                        }

                        $sale = $sd->saleMaster;
                        $patient = $sale?->customer?->lead;
                        $patientName = $patient?->full_name ?? ($sale?->customer?->name ?? 'Walk-in');
                        $phone = $patient?->phone_number ?? '';
                    @endphp
                    <tr id="service-row-{{ $sd->id }}">
                        <td style="color: var(--page-text-muted); font-size: 12px;">{{ $services->firstItem() + $idx }}</td>
                        <td>
                            @if($sd->fulfillment_status !== 'done')
                                <span class="waiting-badge {{ $waitClass }}">
                                    <i class="far fa-clock mr-1"></i>{{ $waitLabel }}
                                </span>
                            @else
                                <span class="badge badge-light text-muted">Completed</span>
                            @endif
                        </td>
                        <td>
                            @if($sale)
                                <a href="{{ route('sale.show', $sale->sale_invoice_no) }}" style="font-weight: 600; color: var(--accent-600);" target="_blank">
                                    #{{ $sale->sale_invoice_no }}
                                </a>
                                <div style="font-size: 11px; color: var(--page-text-muted);">{{ $sale->date?->format('d M Y') }}</div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <div style="font-weight: 600; color: var(--page-text);">{{ $patientName }}</div>
                            @if($phone)
                                <div style="font-size: 12px;">
                                    <a href="tel:{{ $phone }}" class="text-muted mr-2" title="Call patient">
                                        <i class="fas fa-phone-alt text-success mr-1"></i>{{ $phone }}
                                    </a>
                                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $phone) }}" target="_blank" class="text-success" title="WhatsApp">
                                        <i class="fab fa-whatsapp"></i>
                                    </a>
                                </div>
                            @endif
                        </td>
                        <td><span style="font-size: 12px; color: var(--page-text-muted);">{{ $sale?->vendor?->name ?? 'Main Branch' }}</span></td>
                        <td>
                            <div style="font-weight: 600;">{{ $sd->serviceType?->service_type_name ?? 'Service' }}</div>
                            <div style="font-size: 11px; color: var(--page-text-muted);">
                                ৳{{ number_format($sd->service_price, 2) }} &times; {{ $sd->qty }}
                            </div>
                        </td>
                        <td>
                            @php $bType = $sd->serviceType?->booking_type; @endphp
                            @if($bType === 'walk_in')
                                <span class="badge badge-success"><i class="fas fa-walking mr-1"></i>Walk-in</span>
                            @elseif($bType === 'appointment_required')
                                <span class="badge badge-warning text-dark"><i class="fas fa-calendar-alt mr-1"></i>Appointment req.</span>
                            @else
                                <span class="badge badge-info"><i class="fas fa-exchange-alt mr-1"></i>Either</span>
                            @endif
                        </td>
                        <td>
                            <span class="{{ $sd->statusBadgeClass() }}" id="badge-status-{{ $sd->id }}">
                                {{ $sd->statusLabel() }}
                            </span>
                            @if($sd->fulfillment_status === 'booked' && $sd->appointment)
                                <div style="font-size: 11px; margin-top: 3px;">
                                    <a href="{{ url('admin/appointments/' . $sd->appointment_id . '/edit') }}" class="text-primary font-weight-500" target="_blank">
                                        <i class="far fa-calendar-check mr-1"></i>{{ $sd->appointment->appointment_date?->format('d M Y') }}
                                        @if($sd->appointment->user)
                                            ({{ $sd->appointment->user->name }})
                                        @endif
                                    </a>
                                </div>
                            @elseif($sd->fulfillment_status === 'done')
                                <div style="font-size: 11px; color: var(--success-600); margin-top: 2px;">
                                    <i class="fas fa-check mr-1"></i>{{ $sd->done_at ? $sd->done_at->format('d M Y') : 'Delivered' }}
                                    @if($sd->doneByUser) ({{ $sd->doneByUser->name }}) @endif
                                </div>
                            @endif
                        </td>
                        <td style="text-align: center;">
                            <div class="d-flex justify-content-center align-items-center" style="gap: 5px;" id="actions-{{ $sd->id }}">
                                @if($sd->fulfillment_status === 'not_booked')
                                    {{-- Walk-in: Done now --}}
                                    @if($bType === 'walk_in')
                                        <button type="button" class="btn btn-success btn-xs btn-done-modal" data-id="{{ $sd->id }}" data-service="{{ $sd->serviceType?->service_type_name }}" data-patient="{{ $patientName }}">
                                            <i class="fas fa-check mr-1"></i>Done now
                                        </button>
                                    {{-- Appointment required: Book appointment --}}
                                    @elseif($bType === 'appointment_required')
                                        <a href="{{ url('admin/appointments/create?customer_id=' . ($sale?->customer_id ?? '') . '&service_detail_id=' . $sd->id . '&sale_master_id=' . ($sale?->id ?? '') . '&sale_invoice_no=' . ($sale?->sale_invoice_no ?? '') . '&service_id=' . ($sd->serviceType?->appointment_service_id ?? '')) }}" class="btn btn-primary btn-xs">
                                            <i class="fas fa-calendar-plus mr-1"></i>Book appt
                                        </a>
                                    {{-- Either: Both choices --}}
                                    @else
                                        <button type="button" class="btn btn-success btn-xs btn-done-modal" data-id="{{ $sd->id }}" data-service="{{ $sd->serviceType?->service_type_name }}" data-patient="{{ $patientName }}">
                                            <i class="fas fa-check mr-1"></i>Done now
                                        </button>
                                        <a href="{{ url('admin/appointments/create?customer_id=' . ($sale?->customer_id ?? '') . '&service_detail_id=' . $sd->id . '&sale_master_id=' . ($sale?->id ?? '') . '&sale_invoice_no=' . ($sale?->sale_invoice_no ?? '') . '&service_id=' . ($sd->serviceType?->appointment_service_id ?? '')) }}" class="btn btn-primary btn-xs">
                                            <i class="fas fa-calendar-plus mr-1"></i>Book appt
                                        </a>
                                    @endif
                                @elseif($sd->fulfillment_status === 'booked')
                                    <button type="button" class="btn btn-outline-success btn-xs btn-done-modal" data-id="{{ $sd->id }}" data-service="{{ $sd->serviceType?->service_type_name }}" data-patient="{{ $patientName }}" title="Patient completed service">
                                        <i class="fas fa-check mr-1"></i>Done now
                                    </button>
                                    @if($sd->appointment_id)
                                        <a href="{{ url('admin/appointments/' . $sd->appointment_id . '/edit') }}" class="btn btn-outline-info btn-xs" target="_blank" title="View Appointment">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    @endif
                                @else
                                    <span class="text-success font-weight-600" style="font-size: 12px;"><i class="fas fa-check-double mr-1"></i>Done</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px; color: var(--page-text-muted);">
                            <i class="fas fa-check-circle" style="font-size: 32px; color: #10b981; display: block; margin-bottom: 8px;"></i>
                            No pending services found matching this criteria.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="padding: 16px 20px;">
            {{ $services->appends(request()->query())->links() }}
        </div>
    </div>
</div>

{{-- Simple "Done Now" Modal --}}
<div class="modal fade" id="doneNowModal" tabindex="-1" role="dialog" aria-labelledby="doneNowModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background: var(--table-header-bg, #f8fafc); border-bottom: 1px solid var(--card-border, #e2e8f0); border-radius: 12px 12px 0 0;">
                <h5 class="modal-title" id="doneNowModalTitle" style="font-size: 16px; font-weight: 700; color: var(--page-text);">
                    <i class="fas fa-check-circle text-success mr-2"></i>Mark Service as Done
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="doneNowForm" method="POST" action="">
                @csrf
                <div class="modal-body" style="padding: 20px;">
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                        <div style="font-size: 12px; color: #166534; font-weight: 600;">Service to deliver:</div>
                        <div id="modalServiceName" style="font-size: 15px; font-weight: 700; color: #14532d;"></div>
                        <div id="modalPatientName" style="font-size: 13px; color: #15803d; margin-top: 2px;"></div>
                    </div>

                    <div class="form-group">
                        <label for="modal_done_by" style="font-size: 13px; font-weight: 600;">Delivered By <span class="text-danger">*</span></label>
                        <select name="done_by" id="modal_done_by" class="form-control" required>
                            @foreach($professionals as $p)
                                <option value="{{ $p->id }}" {{ auth()->id() == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="modal_notes" style="font-size: 13px; font-weight: 600;">Clinical Notes / Remarks <small class="text-muted">(Optional)</small></label>
                        <textarea name="notes" id="modal_notes" rows="2" class="form-control" placeholder="e.g. Completed without issues, patient satisfied..."></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--card-border, #e2e8f0); background: var(--table-header-bg, #f8fafc); border-radius: 0 0 12px 12px;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm" id="btnConfirmDone">
                        <i class="fas fa-check mr-1"></i>Confirm Done Today
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
$(document).ready(function () {
    var baseDoneUrl = "{{ url('admin/sales/service-detail') }}";

    $('.btn-done-modal').on('click', function () {
        var serviceId = $(this).data('id');
        var serviceName = $(this).data('service');
        var patientName = $(this).data('patient');

        $('#modalServiceName').text(serviceName);
        $('#modalPatientName').text('Patient: ' + patientName);
        $('#doneNowForm').attr('action', baseDoneUrl + '/' + serviceId + '/done-now');
        $('#doneNowModal').modal('show');
    });

    $('#doneNowForm').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var url = $form.attr('action');
        var $btn = $('#btnConfirmDone');

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving...');

        $.ajax({
            url: url,
            method: 'POST',
            data: $form.serialize(),
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function (res) {
                $('#doneNowModal').modal('hide');
                if (typeof toastr !== 'undefined') {
                    toastr.success(res.message);
                } else {
                    alert(res.message);
                }
                setTimeout(function () {
                    window.location.reload();
                }, 600);
            },
            error: function () {
                alert('Could not mark service as done. Please try again.');
                $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i>Confirm Done Today');
            }
        });
    });
});
</script>
@endpush
