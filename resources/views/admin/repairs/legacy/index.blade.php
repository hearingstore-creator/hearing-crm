@extends('admin.layouts.master')
@section('page_title', 'Repair & Warranty Management')
@section('breadcrumb_parent', 'Operations')
@section('breadcrumb_parent_url', url('admin/repairs'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/kpi-cards.css') }}">
    <style>
        #quickStatusModal {
            z-index: 1060 !important;
        }
        .modal-backdrop {
            z-index: 1050 !important;
        }
        .warranty-pill {
            display: inline-flex;
            align-items: center;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .warranty-pill.tier-1 {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }
        .warranty-pill.tier-2 {
            background-color: #e0f2fe;
            color: #0369a1;
            border: 1px solid #0284c7;
        }
        .warranty-pill.out {
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }
        .warranty-pill.void {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        .status-pill {
            font-size: 11px;
            font-weight: 600;
            padding: 4px 9px;
            border-radius: 6px;
        }
        .search-scanner-bar {
            background: #ffffff;
            border-radius: 8px;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
    </style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Plan B Dual-Run Banner --}}
    <div class="alert alert-info d-flex align-items-center justify-content-between mb-4 shadow-sm" style="border-left: 5px solid #0284c7; background: #e0f2fe; color: #0369a1; border-radius: 8px; padding: 12px 20px;">
        <div class="d-flex align-items-center">
            <i class="fas fa-history mr-3 fa-lg"></i>
            <div>
                <strong>You are currently viewing Plan A (Legacy Mode).</strong>
                <span class="d-block small text-muted">All original tables and filters are preserved. Test the new 6-stage pipeline anytime.</span>
            </div>
        </div>
        <a href="{{ route('repairs.index') }}" class="btn btn-primary btn-sm px-3 shadow-sm font-weight-bold">
            <i class="fas fa-stream mr-1"></i>Switch to Plan B (6-Stage Pipeline)
        </a>
    </div>

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-tools" style="color: var(--accent-500); margin-right: 8px;"></i>Repair, Service &amp; Warranty Hub</h1>
            <p>Track hearing aid repairs, manufacturer warranty claims, external lab challans, and customer pick-ups</p>
        </div>
        <div class="header-actions">
            <a href="{{ route('repairs.rma.index') }}" class="btn btn-outline-danger btn-sm mr-2">
                <i class="fas fa-exchange-alt mr-1"></i>Supplier RMA Stock
            </a>
            <a href="{{ route('repairs.quick') }}" class="btn btn-outline-warning btn-sm mr-2">
                <i class="fas fa-bolt mr-1"></i>Quick Walk-in Service
            </a>
            <a href="{{ route('repairs.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus-circle mr-1"></i>New Repair Intake
            </a>
        </div>
    </div>

    {{-- Scan / Search Bar --}}
    <div class="search-scanner-bar crm-animate-in">
        <form method="GET" action="{{ route('repairs.index') }}" class="row align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-light text-primary"><i class="fas fa-barcode"></i></span>
                    </div>
                    <input type="text" name="search" id="quickScannerInput" class="form-control"
                           placeholder="Scan Barcode / Serial / Job # / Phone / Patient..."
                           value="{{ $search }}" autofocus autocomplete="off">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-control">
                    <option value="all" {{ $status == 'all' ? 'selected' : '' }}>All Statuses</option>
                    <option value="in_clinic" {{ $status == 'in_clinic' ? 'selected' : '' }}>In Clinic (Intake / Evaluated)</option>
                    <option value="in_lab" {{ $status == 'in_lab' ? 'selected' : '' }}>In Lab (External / Supplier)</option>
                    <option value="ready" {{ $status == 'ready' ? 'selected' : '' }}>Ready for Pickup</option>
                    <option value="delivered" {{ $status == 'delivered' ? 'selected' : '' }}>Delivered / Handed Over</option>
                    <option value="quick" {{ $status == 'quick' ? 'selected' : '' }}>Quick Counter Services</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-search mr-1"></i>Filter</button>
            </div>
            <div class="col-md-2 text-right">
                @if(!empty($search) || $status != 'all')
                    <a href="{{ route('repairs.index') }}" class="btn btn-outline-secondary btn-block"><i class="fas fa-undo mr-1"></i>Reset</a>
                @endif
            </div>
        </form>
    </div>

    {{-- KPI Cards --}}
    <div class="kpi-row crm-animate-in">
        @include('admin.partials.kpi-card', [
            'href'  => route('repairs.index', ['status' => 'in_clinic']),
            'color' => 'blue', 'icon' => 'fas fa-clinic-medical', 'id' => 'kpi-in-clinic',
            'label' => 'In Clinic', 'value' => $counts['in_clinic'],
            'sub'   => 'Intake / Under Evaluation',
        ])
        @include('admin.partials.kpi-card', [
            'href'  => route('repairs.index', ['status' => 'in_lab']),
            'color' => 'orange', 'icon' => 'fas fa-flask', 'id' => 'kpi-in-lab',
            'label' => 'With Supplier Lab', 'value' => $counts['in_lab'],
            'sub'   => 'Dispatched to External Lab',
        ])
        @if(($counts['delayed_lab'] ?? 0) > 0)
            @include('admin.partials.kpi-card', [
                'href'  => route('repairs.index', ['status' => 'delayed_lab']),
                'color' => 'red', 'icon' => 'fas fa-hourglass-half', 'id' => 'kpi-delayed-lab',
                'label' => 'Delayed in Lab (>7d)', 'value' => $counts['delayed_lab'],
                'sub'   => 'Requires Lab Escalation',
            ])
        @endif
        @include('admin.partials.kpi-card', [
            'href'  => route('repairs.index', ['status' => 'ready']),
            'color' => 'green', 'icon' => 'fas fa-bell', 'id' => 'kpi-ready',
            'label' => 'Ready for Pickup', 'value' => $counts['ready'],
            'sub'   => 'Awaiting Patient Collection',
        ])
        @include('admin.partials.kpi-card', [
            'href'  => route('repairs.index', ['status' => 'delivered']),
            'color' => 'purple', 'icon' => 'fas fa-check-circle', 'id' => 'kpi-delivered',
            'label' => 'Delivered (This Month)', 'value' => $counts['delivered_month'],
            'sub'   => 'Successfully Handed Over',
        ])
        @include('admin.partials.kpi-card', [
            'href'  => route('repairs.index', ['status' => 'ready']),
            'color' => 'red', 'icon' => 'fas fa-coins', 'id' => 'kpi-due',
            'label' => 'Due on Ready Units', 'value' => '৳' . number_format($counts['ready_due'], 2),
            'sub'   => 'To Collect on Handover',
        ])
    </div>

    {{-- Jobs Data Table --}}
    <div class="card crm-animate-in" style="border: 1px solid #e2e8f0; border-radius: 8px;">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-list-alt text-primary mr-2"></i>Service &amp; Repair Jobs ({{ $jobs->total() }})</h5>
            <div class="text-muted small">
                Showing {{ $jobs->firstItem() ?? 0 }} to {{ $jobs->lastItem() ?? 0 }} of {{ $jobs->total() }} entries
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size: 13px;">
                    <thead class="thead-light">
                        <tr>
                            <th style="width: 140px;">Job Card #</th>
                            <th>Patient / Customer</th>
                            <th>Device Details</th>
                            <th style="width: 180px;">Warranty Coverage</th>
                            <th style="width: 130px;">Lifecycle Status</th>
                            <th style="width: 130px;">Promised Date</th>
                            <th style="width: 140px;" class="text-right">Billing (৳)</th>
                            <th style="width: 130px;" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($jobs as $job)
                            @php
                                $wStatus = $job->warranty_status;
                                $pillClass = 'out';
                                $pillText = 'Out of Warranty';

                                if ($wStatus === 'under_product_warranty') {
                                    $pillClass = 'tier-1';
                                    $pillText = 'Product Warranty (৳0)';
                                } elseif ($wStatus === 'under_service_warranty') {
                                    $pillClass = 'tier-2';
                                    $pillText = 'Service Warranty';
                                } elseif ($wStatus === 'void') {
                                    $pillClass = 'void';
                                    $pillText = 'Void (Physical/Water)';
                                } elseif ($wStatus === 'third_party') {
                                    $pillClass = 'out';
                                    $pillText = 'Third-Party Product';
                                }

                                $statusBadge = match($job->current_status) {
                                    'received'                => 'badge-primary',
                                    'sent_to_lab', 'in_lab'   => 'badge-warning',
                                    'lab_received'            => 'badge-info',
                                    'waiting_patient_approval'=> 'badge-secondary',
                                    'ready_for_pickup'        => 'badge-success',
                                    'delivered'               => 'badge-dark',
                                    'cancelled'               => 'badge-danger',
                                    default                   => 'badge-light'
                                };
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('repairs.show', $job->id) }}" class="font-weight-bold text-primary">
                                        {{ $job->job_no }}
                                    </a>
                                    <div class="text-muted small">
                                        <i class="far fa-calendar-alt mr-1"></i>{{ $job->created_at->format('d M Y') }}
                                    </div>
                                    @if($job->is_quick_service)
                                        <span class="badge badge-warning" style="font-size: 10px;"><i class="fas fa-bolt mr-1"></i>Quick</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="font-weight-bold text-dark">
                                        {{ $job->customer?->lead?->full_name ?? 'Walk-in Customer' }}
                                    </div>
                                    <div class="text-muted small">
                                        <i class="fas fa-phone-alt mr-1"></i>{{ $job->customer?->lead?->phone_number ?? 'No Phone' }}
                                    </div>
                                    @if($job->vendor)
                                        <div class="badge badge-light border text-muted" style="font-size: 10px;">
                                            <i class="fas fa-map-marker-alt mr-1"></i>{{ $job->vendor->name }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <div class="font-weight-600 text-dark">
                                        {{ $job->device_name }}
                                    </div>
                                    <div class="text-muted small font-monospace">
                                        S/N: <b>{{ $job->original_serial_no }}</b>
                                        @if($job->side !== 'na')
                                            <span class="badge badge-light border text-uppercase ml-1">{{ $job->side }}</span>
                                        @endif
                                    </div>
                                    @if($job->supplier)
                                        <div class="small text-info mt-1">
                                            <i class="fas fa-building mr-1"></i>Lab: {{ $job->supplier->name }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <span class="warranty-pill {{ $pillClass }}">
                                        {{ $pillText }}
                                    </span>
                                    @if($job->repair_warranty_until)
                                        <div class="small text-muted mt-1">
                                            <i class="fas fa-shield-alt text-info mr-1"></i>Guar. until: {{ Carbon\Carbon::parse($job->repair_warranty_until)->format('d M Y') }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $statusBadge }} status-pill text-uppercase">
                                        {{ str_replace('_', ' ', $job->current_status) }}
                                    </span>
                                    @if(in_array($job->current_status, ['sent_to_lab', 'in_lab']) && $job->sent_to_lab_at)
                                        @php
                                            $daysInLab = (int)$job->sent_to_lab_at->diffInDays(now());
                                        @endphp
                                        @if($daysInLab >= 14)
                                            <div class="small text-danger font-weight-bold mt-1">
                                                <i class="fas fa-exclamation-circle mr-1"></i>Lab: {{ $daysInLab }}d (Critical)
                                            </div>
                                        @elseif($daysInLab >= 7)
                                            <div class="small text-warning font-weight-bold mt-1">
                                                <i class="fas fa-clock mr-1"></i>Lab: {{ $daysInLab }}d
                                            </div>
                                        @endif
                                    @endif
                                </td>
                                <td>
                                    @if($job->promised_date)
                                        @php
                                            $isOverdue = Carbon\Carbon::parse($job->promised_date)->isPast() && $job->current_status !== 'delivered';
                                        @endphp
                                        <span class="{{ $isOverdue ? 'text-danger font-weight-bold' : 'text-dark' }}">
                                            {{ Carbon\Carbon::parse($job->promised_date)->format('d M Y') }}
                                        </span>
                                        @if($isOverdue)
                                            <div class="small text-danger"><i class="fas fa-exclamation-triangle mr-1"></i>Delayed</div>
                                        @endif
                                    @else
                                        <span class="text-muted">--</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <div class="font-weight-bold text-dark">
                                        ৳{{ number_format($job->total_customer_cost, 2) }}
                                    </div>
                                    @if($job->customer_due > 0)
                                        <div class="small text-danger font-weight-bold">
                                            Due: ৳{{ number_format($job->customer_due, 2) }}
                                        </div>
                                    @else
                                        <div class="small text-success">
                                            <i class="fas fa-check mr-1"></i>Paid
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <a href="{{ route('repairs.show', $job->id) }}" class="btn btn-sm btn-outline-primary" title="View Job Card">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('repairs.print-job-card', $job->id) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print Job Card">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <a href="{{ route('repairs.print-sticker', $job->id) }}" target="_blank" class="btn btn-sm btn-outline-info" title="Print Bag Sticker">
                                            <i class="fas fa-tag"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-dark btn-open-status" 
                                                data-job-id="{{ $job->id }}"
                                                data-job-no="{{ $job->job_no }}"
                                                data-status="{{ $job->current_status }}"
                                                data-due="{{ $job->customer_due }}"
                                                data-url="{{ route('repairs.update-status', $job->id) }}"
                                                title="Update Status">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-tools fa-3x mb-3 text-secondary"></i>
                                    <h5>No repair jobs found</h5>
                                    <p class="mb-3">Scan a barcode or create a new repair intake.</p>
                                    <a href="{{ route('repairs.create') }}" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus mr-1"></i>New Repair Intake
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($jobs->hasPages())
            <div class="card-footer bg-white border-top py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>Showing {{ $jobs->firstItem() }} to {{ $jobs->lastItem() }} of {{ $jobs->total() }} results</div>
                    {{ $jobs->links() }}
                </div>
            </div>
        @endif
    </div>

    {{-- Global Reusable Quick Status Modal (Outside of table & card to prevent backdrop hanging) --}}
    <div class="modal fade" id="quickStatusModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form id="quickStatusForm" method="POST" action="">
                @csrf
                <div class="modal-content text-left">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title font-weight-bold">
                            <i class="fas fa-exchange-alt mr-2"></i>Update Status: <span id="quickStatusJobNo">#REP</span>
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="font-weight-bold">Select New Status <span class="text-danger">*</span></label>
                            <select name="to_status" id="quickStatusSelect" class="form-control" required>
                                <option value="received">Received at Clinic</option>
                                <option value="sent_to_lab">Sent to External Lab</option>
                                <option value="in_lab">In Lab / Under Processing</option>
                                <option value="waiting_patient_approval">Waiting for Patient Approval (Quote / Void)</option>
                                <option value="patient_approved">Patient Approved (Proceed with Repair)</option>
                                <option value="patient_declined">Patient Declined (Return Unrepaired)</option>
                                <option value="lab_received">Returned from Lab to Clinic</option>
                                <option value="ready_for_pickup">Ready for Pickup (Sends SMS)</option>
                                <option value="delivered">Delivered / Handed Over to Patient</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group p-3 bg-light border rounded" id="quickStatusDueBox" style="display: none;">
                            <label class="font-weight-bold text-danger">Collect Due Balance upon Handover (৳):</label>
                            <div class="d-flex align-items-center">
                                <input type="number" step="0.01" name="collected_amount" id="quickStatusDueInput" class="form-control font-weight-bold text-primary mr-2" value="0.00">
                                <span class="small font-weight-bold text-muted" id="quickStatusDueText">৳0.00</span>
                            </div>
                            <small class="text-muted">Enter collected payment if handing over to patient now.</small>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Remarks / Notes</label>
                            <textarea name="remarks" id="quickStatusRemarks" rows="2" class="form-control" placeholder="Action remarks, courier tracking, lab diagnosis, or technician notes..."></textarea>
                        </div>
                        <div class="custom-control custom-checkbox p-2 bg-light border rounded">
                            <input type="checkbox" name="send_ready_sms" value="1" class="custom-control-input" id="quickSendSms" checked>
                            <label class="custom-control-label text-dark font-weight-bold small" for="quickSendSms">
                                <i class="fas fa-sms text-success mr-1"></i>Send Ready SMS to Patient if status is "Ready for Pickup"
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm font-weight-bold"><i class="fas fa-check mr-1"></i>Update Status</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

@push('js')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Relocate modal directly to <body> to avoid stacking-context / transform backdrop traps
        if ($('#quickStatusModal').length) {
            $('#quickStatusModal').appendTo('body');
        }

        // Quick Status Modal trigger
        $(document).on('click', '.btn-open-status', function (e) {
            e.preventDefault();
            const btn = $(this);
            const url = btn.data('url');
            const jobNo = btn.data('job-no');
            const currentStatus = btn.data('status');
            const due = parseFloat(btn.data('due')) || 0;

            $('#quickStatusForm').attr('action', url);
            $('#quickStatusJobNo').text('#' + jobNo);
            $('#quickStatusSelect').val(currentStatus);
            $('#quickStatusRemarks').val('');

            if (due > 0) {
                $('#quickStatusDueBox').show();
                $('#quickStatusDueInput').val(due).attr('max', due);
                $('#quickStatusDueText').text('Max Due: ৳' + due.toFixed(2));
            } else {
                $('#quickStatusDueBox').hide();
                $('#quickStatusDueInput').val('0.00');
            }

            $('#quickStatusModal').modal('show');
        });

        // Hardware Scanner Interceptor
        const scannerInput = document.getElementById('quickScannerInput');
        let keyBuffer = '';
        let lastKeyTime = Date.now();

        window.addEventListener('keydown', function (e) {
            const currentTime = Date.now();
            const timeDiff = currentTime - lastKeyTime;
            lastKeyTime = currentTime;

            // If active input is not textarea and user is scanning
            if (document.activeElement.tagName !== 'TEXTAREA') {
                if (timeDiff < 45) {
                    keyBuffer += e.key;
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (scannerInput) {
                            scannerInput.value = keyBuffer.replace('Enter', '').trim();
                            scannerInput.form.submit();
                        }
                        keyBuffer = '';
                    }
                } else {
                    keyBuffer = e.key;
                }
            }
        });
    });
</script>
@endpush
