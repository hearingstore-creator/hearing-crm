@extends('admin.layouts.master')
@section('page_title', 'Job Card #' . $job->job_no)
@section('breadcrumb_parent', 'Repairs')
@section('breadcrumb_parent_url', url('admin/repairs'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <style>
        .modal {
            z-index: 1060 !important;
        }
        .modal-backdrop {
            z-index: 1050 !important;
        }
        .timeline-item {
            position: relative;
            padding-left: 30px;
            margin-bottom: 20px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 6px;
            bottom: -20px;
            width: 2px;
            background: #e2e8f0;
        }
        .timeline-item:last-child::before {
            display: none;
        }
        .timeline-dot {
            position: absolute;
            left: 0;
            top: 4px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #3b82f6;
            border: 3px solid #ffffff;
            box-shadow: 0 0 0 1px #3b82f6;
        }
        .lifecycle-stepper {
            display: flex;
            justify-content: space-between;
            background: #ffffff;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
        }
        .lifecycle-step {
            text-align: center;
            flex: 1;
            position: relative;
        }
        .lifecycle-step::after {
            content: '';
            position: absolute;
            top: 14px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #e2e8f0;
            z-index: 1;
        }
        .lifecycle-step:last-child::after {
            display: none;
        }
        .lifecycle-step.completed::after {
            background: #10b981;
        }
        .step-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #f1f5f9;
            color: #64748b;
            font-weight: 700;
            position: relative;
            z-index: 2;
            margin-bottom: 6px;
        }
        .lifecycle-step.completed .step-pill {
            background: #10b981;
            color: #ffffff;
        }
        .lifecycle-step.active .step-pill {
            background: #3b82f6;
            color: #ffffff;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2);
        }
    </style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <div class="d-flex align-items-center">
                <h1 class="mr-3 mb-0">
                    <i class="fas fa-tools text-primary mr-2"></i>Job Card: {{ $job->job_no }}
                </h1>
                @php
                    $wStatus = $job->warranty_status;
                    $badgeColor = match($wStatus) {
                        'under_product_warranty' => 'badge-success',
                        'under_service_warranty' => 'badge-info',
                        'void'                   => 'badge-danger',
                        default                  => 'badge-secondary'
                    };
                    $wText = match($wStatus) {
                        'under_product_warranty' => 'Product Warranty (Supplier Backed)',
                        'under_service_warranty' => 'Service Warranty Guarantee',
                        'void'                   => 'Warranty VOID',
                        'third_party'            => 'Third-Party Product',
                        default                  => 'Out of Warranty'
                    };
                @endphp
                <span class="badge {{ $badgeColor }} px-3 py-1 font-weight-bold mr-2 text-uppercase">
                    {{ $wText }}
                </span>
                <span class="badge badge-dark px-3 py-1 font-weight-bold text-uppercase">
                    Status: {{ str_replace('_', ' ', $job->current_status) }}
                </span>
            </div>
            <p class="mt-1 mb-0">Created on {{ $job->created_at->format('d M Y, h:i A') }} by {{ $job->receivedBy?->name ?? 'Staff' }}</p>
        </div>
        <div class="header-actions">
            <a href="{{ route('repairs.print-job-card', $job->id) }}" target="_blank" class="btn btn-outline-primary btn-sm mr-2">
                <i class="fas fa-print mr-1"></i>Job Card
            </a>
            <a href="{{ route('repairs.print-sticker', $job->id) }}" target="_blank" class="btn btn-outline-info btn-sm mr-2">
                <i class="fas fa-tag mr-1"></i>Sticker
            </a>
            <a href="{{ route('repairs.print-delivery-note', $job->id) }}" target="_blank" class="btn btn-outline-dark btn-sm mr-2">
                <i class="fas fa-file-contract mr-1"></i>Delivery Note
            </a>
            <a href="{{ route('repairs.print-invoice', $job->id) }}" target="_blank" class="btn btn-outline-success btn-sm mr-2">
                <i class="fas fa-file-invoice-dollar mr-1"></i>Tax Invoice
            </a>
            @if($job->replaced_serial_no)
                <a href="{{ route('repairs.print-replacement-certificate', $job->id) }}" target="_blank" class="btn btn-outline-info btn-sm mr-2">
                    <i class="fas fa-certificate mr-1"></i>Replacement Cert
                </a>
            @endif
            <a href="{{ route('repairs.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-list mr-1"></i>All Jobs
            </a>
        </div>
    </div>

    {{-- Replacement Alert Banner if Unit Replaced --}}
    @if($job->replaced_serial_no)
        <div class="alert alert-info border-info shadow-sm crm-animate-in mb-4 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <i class="fas fa-exchange-alt fa-2x text-info mr-3"></i>
                <div>
                    <h6 class="font-weight-bold mb-1 text-info">
                        <i class="fas fa-check-circle text-success mr-1"></i>REPLACEMENT RECEIVED FROM SUPPLIER LAB &mdash; {{ $job->current_status === 'delivered' ? 'DELIVERED TO PATIENT' : 'READY FOR PICKUP' }}
                    </h6>
                    <div class="text-dark small">
                        Defective S/N: <b class="text-danger font-monospace">{{ $job->original_serial_no }}</b>
                        &rarr; New Replacement S/N: <b class="text-success font-monospace">{{ $job->replaced_serial_no }}</b>
                        @if($job->replacementChain && $job->replacementChain->warranty_end_date)
                            | Active Inherited Warranty Expiry: <b>{{ $job->replacementChain->warranty_end_date->format('d M Y') }}</b>
                            ({{ max(0, now()->diffInDays($job->replacementChain->warranty_end_date, false)) }} days left)
                        @endif
                        @if($job->lab_ref_no)
                            <br><span class="text-muted">Supplier Replacement Challan:</span> <b class="font-monospace text-primary">{{ $job->lab_ref_no }}</b>
                            ({{ $job->supplier?->supplier_name ?? 'Manufacturer Lab' }})
                            | <span class="text-muted">Supplier Fee:</span> <b>{{ $job->total_supplier_cost > 0 ? '৳' . number_format($job->total_supplier_cost, 2) : 'Free Under Warranty (৳0.00)' }}</b>
                        @endif
                    </div>
                </div>
            </div>
            <div>
                <a href="{{ route('repairs.print-replacement-certificate', $job->id) }}" target="_blank" class="btn btn-info btn-sm font-weight-bold shadow-sm">
                    <i class="fas fa-certificate mr-1"></i>Print Replacement Certificate
                </a>
            </div>
        </div>
    @endif

    {{-- Lifecycle Progression Stepper --}}
    @php
        $statuses = ['received', 'sent_to_lab', 'in_lab', 'lab_received', 'ready_for_pickup', 'delivered'];
        $currentIndex = array_search($job->current_status, $statuses);
        if ($currentIndex === false && $job->current_status === 'delivered') $currentIndex = 5;
    @endphp
    <div class="lifecycle-stepper crm-animate-in">
        <div class="lifecycle-step {{ $currentIndex >= 0 ? 'completed' : '' }} {{ $job->current_status === 'received' ? 'active' : '' }}">
            <div class="step-pill"><i class="fas fa-clipboard-check"></i></div>
            <div class="small font-weight-bold">1. Received</div>
            <div class="text-muted" style="font-size: 11px;">{{ $job->created_at->format('d M') }}</div>
        </div>
        <div class="lifecycle-step {{ $currentIndex >= 1 ? 'completed' : '' }} {{ $job->current_status === 'sent_to_lab' ? 'active' : '' }}">
            <div class="step-pill"><i class="fas fa-paper-plane"></i></div>
            <div class="small font-weight-bold">2. Sent to Lab</div>
            <div class="text-muted" style="font-size: 11px;">{{ $job->sent_to_lab_at ? $job->sent_to_lab_at->format('d M') : '--' }}</div>
        </div>
        <div class="lifecycle-step {{ $currentIndex >= 2 ? 'completed' : '' }} {{ $job->current_status === 'in_lab' ? 'active' : '' }}">
            <div class="step-pill"><i class="fas fa-flask"></i></div>
            <div class="small font-weight-bold">3. In Lab</div>
            <div class="text-muted" style="font-size: 11px;">Processing</div>
        </div>
        <div class="lifecycle-step {{ $currentIndex >= 3 ? 'completed' : '' }} {{ $job->current_status === 'lab_received' ? 'active' : '' }}">
            <div class="step-pill"><i class="fas fa-box-open"></i></div>
            <div class="small font-weight-bold">4. Lab Received</div>
            <div class="text-muted" style="font-size: 11px;">{{ $job->lab_received_at ? $job->lab_received_at->format('d M') : '--' }}</div>
        </div>
        <div class="lifecycle-step {{ $currentIndex >= 4 ? 'completed' : '' }} {{ $job->current_status === 'ready_for_pickup' ? 'active' : '' }}">
            <div class="step-pill"><i class="fas fa-bell"></i></div>
            <div class="small font-weight-bold">5. Ready for Pickup</div>
            <div class="text-muted" style="font-size: 11px;">Patient Notified</div>
        </div>
        <div class="lifecycle-step {{ $currentIndex >= 5 ? 'completed' : '' }} {{ $job->current_status === 'delivered' ? 'active' : '' }}">
            <div class="step-pill"><i class="fas fa-hand-holding"></i></div>
            <div class="small font-weight-bold">6. Delivered</div>
            <div class="text-muted" style="font-size: 11px;">{{ $job->delivered_at ? $job->delivered_at->format('d M') : '--' }}</div>
        </div>
    </div>

    {{-- Patient Decision Banner (if awaiting approval on quotation / void) --}}
    @if($job->current_status === 'waiting_patient_approval')
        <div class="card p-3 mb-4 bg-white border-warning shadow-sm crm-animate-in" style="border-left: 5px solid #f59e0b !important;">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h5 class="font-weight-bold text-dark mb-1">
                        <i class="fas fa-exclamation-triangle text-warning mr-2"></i>Action Required: Waiting for Patient Decision on Quotation
                    </h5>
                    <div class="text-muted small">
                        Repair Quotation of <b>৳{{ number_format($job->estimate_amount, 2) }}</b> (Total Bill: <b>৳{{ number_format($job->total_customer_cost, 2) }}</b>) communicated to patient.
                        @if($job->warranty_status === 'void')
                            <span class="text-danger font-weight-bold ml-1"><i class="fas fa-ban mr-1"></i>Warranty Void ({{ $job->void_reason }})</span>
                        @endif
                    </div>
                </div>
                <div class="mt-2 mt-md-0 d-flex" style="gap: 8px;">
                    <form action="{{ route('repairs.patient-decision', $job->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Confirm patient has APPROVED repair quotation of ৳{{ number_format($job->estimate_amount, 2) }}?');">
                        @csrf
                        <input type="hidden" name="decision" value="approved">
                        <button type="submit" class="btn btn-success font-weight-bold shadow-sm">
                            <i class="fas fa-check-circle mr-1"></i>Patient Approved (Proceed with Repair)
                        </button>
                    </form>
                    <form action="{{ route('repairs.patient-decision', $job->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Patient DECLINED quotation? Repair charge will be removed and unit returned unrepaired.');">
                        @csrf
                        <input type="hidden" name="decision" value="declined">
                        <button type="submit" class="btn btn-outline-danger font-weight-bold shadow-sm">
                            <i class="fas fa-times-circle mr-1"></i>Patient Declined (Return Unrepaired)
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Contextual Quick Action Ribbon --}}
    @if($job->current_status !== 'delivered' && $job->current_status !== 'cancelled')
        <div class="card p-3 mb-4 bg-white border shadow-sm crm-animate-in">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h6 class="font-weight-bold text-dark mb-1">
                        <i class="fas fa-arrow-circle-right text-primary mr-2"></i>Next Suggested Action:
                    </h6>
                    <span class="text-muted small">Update status as work progresses to keep staff and patient aligned.</span>
                </div>
                <div class="mt-2 mt-md-0">
                    @if($job->current_status === 'received')
                        <button type="button" class="btn btn-warning btn-sm font-weight-bold" data-toggle="modal" data-target="#sendLabModal">
                            <i class="fas fa-paper-plane mr-1"></i>Send to External Lab
                        </button>
                        <button type="button" class="btn btn-success btn-sm font-weight-bold ml-2" data-toggle="modal" data-target="#readyModal">
                            <i class="fas fa-bell mr-1"></i>Mark Ready for Pickup
                        </button>
                    @elseif($job->current_status === 'sent_to_lab' || $job->current_status === 'in_lab')
                        <button type="button" class="btn btn-info btn-sm font-weight-bold" data-toggle="modal" data-target="#labReturnModal">
                            <i class="fas fa-box-open mr-1"></i>Receive Repaired Unit from Lab
                        </button>
                        <button type="button" class="btn btn-primary btn-sm font-weight-bold ml-2 shadow-sm" data-toggle="modal" data-target="#replacementModal">
                            <i class="fas fa-exchange-alt mr-1"></i>Receive Replacement from Lab
                        </button>
                    @elseif($job->current_status === 'lab_received' || $job->current_status === 'patient_declined')
                        <button type="button" class="btn btn-success btn-sm font-weight-bold" data-toggle="modal" data-target="#readyModal">
                            <i class="fas fa-bell mr-1"></i>Mark Ready for Pickup (Sends SMS)
                        </button>
                    @elseif($job->current_status === 'ready_for_pickup')
                        <button type="button" class="btn btn-dark btn-sm font-weight-bold" data-toggle="modal" data-target="#deliverModal">
                            <i class="fas fa-hand-holding mr-1"></i>Handover &amp; Deliver to Patient
                        </button>
                    @endif

                    @if($job->warranty_status !== 'void' && $job->current_status !== 'delivered' && $job->current_status !== 'cancelled')
                        <button type="button" class="btn btn-outline-danger btn-sm font-weight-bold ml-2" data-toggle="modal" data-target="#voidWarrantyModal" title="Lab found moisture / corrosion / physical damage">
                            <i class="fas fa-ban mr-1"></i>Declare Void (Lab Finding)
                        </button>
                    @endif

                    @if($job->current_status !== 'delivered' && $job->current_status !== 'cancelled')
                        <button type="button" class="btn btn-outline-info btn-sm font-weight-bold ml-2" data-toggle="modal" data-target="#updateEstimateModal" title="Update service quotation from lab">
                            <i class="fas fa-edit mr-1"></i>Update Estimate
                        </button>
                    @endif

                    @if(!$job->replaced_serial_no && $job->current_status !== 'delivered' && $job->current_status !== 'cancelled' && $job->current_status !== 'sent_to_lab' && $job->current_status !== 'in_lab')
                        <button type="button" class="btn btn-outline-primary btn-sm font-weight-bold ml-2" data-toggle="modal" data-target="#replacementModal">
                            <i class="fas fa-exchange-alt mr-1"></i>Record Lab Replacement
                        </button>
                    @endif

                    @if($job->customer_due > 0 && $job->current_status !== 'delivered')
                        <button type="button" class="btn btn-outline-success btn-sm font-weight-bold ml-2" data-toggle="modal" data-target="#collectPaymentModal">
                            <i class="fas fa-hand-holding-usd mr-1"></i>Collect Payment
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div class="row">
        {{-- Left Column: Patient, Device & History --}}
        <div class="col-md-7">
            
            {{-- Device & Symptoms Card --}}
            <div class="card mb-4 border shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-microchip text-primary mr-2"></i>Device &amp; Complaint Details</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="small text-muted">Device Name / Model</div>
                            <div class="font-weight-bold text-dark" style="font-size: 15px;">{{ $job->device_name }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Serial Number (S/N)</div>
                            <div class="font-weight-bold text-primary font-monospace" style="font-size: 15px;">{{ $job->original_serial_no }}</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="small text-muted">Ear Side</div>
                            <div class="font-weight-bold text-uppercase">{{ $job->side !== 'na' ? $job->side . ' Ear' : 'N/A' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted">Original Purchase Invoice</div>
                            @if($job->saleMaster)
                                <div><a href="{{ url('admin/sale/' . $job->sale_master_id) }}" class="font-weight-bold text-primary">#{{ $job->saleMaster->sale_invoice_no }}</a> ({{ $job->saleMaster->date ? $job->saleMaster->date->format('d M Y') : '' }})</div>
                            @else
                                <div class="text-muted">Walk-in / Third-Party Device</div>
                            @endif
                        </div>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <div class="small text-muted font-weight-bold mb-1">Reported Symptoms / Complaints:</div>
                        <div class="p-2 bg-light border rounded text-dark">
                            {{ $job->reported_issues ?: 'No specific issues logged.' }}
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="small text-muted font-weight-bold mb-1">Accessories Received at Clinic:</div>
                        @if(!empty($job->accessories_included) && count($job->accessories_included) > 0)
                            <div>
                                @foreach($job->accessories_included as $acc)
                                    <span class="badge badge-light border mr-1 mb-1 p-2"><i class="fas fa-check-circle text-success mr-1"></i>{{ $acc }}</span>
                                @endforeach
                            </div>
                        @else
                            <div class="text-muted small">Unit only, no extra accessories kept.</div>
                        @endif
                    </div>

                    @if($job->physical_condition)
                        <div>
                            <div class="small text-muted font-weight-bold mb-1">Physical Condition at Intake:</div>
                            <div class="text-muted small">{{ $job->physical_condition }}</div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Parts, Labor & Billing Line Items Card --}}
            <div class="card mb-4 border shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 font-weight-bold text-dark">
                        <i class="fas fa-cogs text-primary mr-2"></i>Parts, Labor &amp; Service Items
                    </h5>
                    @if($job->current_status !== 'delivered')
                        <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addItemModal">
                            <i class="fas fa-plus mr-1"></i>Add Item
                        </button>
                    @endif
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" style="font-size: 13px;">
                            <thead class="bg-light text-muted">
                                <tr>
                                    <th>Item / Description</th>
                                    <th>Type</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-right">Price (৳)</th>
                                    <th class="text-right">Total (৳)</th>
                                    @if(auth()->user()->canViewAllBranches() || auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Super Admin'))
                                        <th class="text-right text-muted">Cost (৳)</th>
                                    @endif
                                    <th class="text-center" style="width: 50px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($job->items as $item)
                                    <tr>
                                        <td>
                                            <div class="font-weight-bold text-dark">{{ $item->item_name }}</div>
                                            @if($item->is_warranty_covered)
                                                <span class="badge badge-success" style="font-size: 10px;">FREE UNDER WARRANTY</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge-light border text-uppercase" style="font-size: 11px;">
                                                {{ str_replace('_', ' ', $item->item_type) }}
                                            </span>
                                        </td>
                                        <td class="text-center">{{ number_format($item->qty, 2) }}</td>
                                        <td class="text-right">৳{{ number_format($item->customer_unit_price, 2) }}</td>
                                        <td class="text-right font-weight-bold">৳{{ number_format($item->customer_total, 2) }}</td>
                                        @if(auth()->user()->canViewAllBranches() || auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Super Admin'))
                                            <td class="text-right text-muted">৳{{ number_format($item->supplier_total, 2) }}</td>
                                        @endif
                                        <td class="text-center">
                                            @if($job->current_status !== 'delivered')
                                                <form action="{{ route('repairs.items.destroy', [$job->id, $item->id]) }}" method="POST" onsubmit="return confirm('Remove this line item?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-link text-danger p-0" title="Delete Item">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-muted">--</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-3 text-muted">
                                            No itemized lines yet.
                                            @if($job->estimate_amount > 0)
                                                (Estimate: ৳{{ number_format($job->estimate_amount, 2) }})
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Status History / Audit Trail --}}
            <div class="card mb-4 border shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-history text-primary mr-2"></i>Lifecycle Timeline &amp; Audit Logs</h5>
                </div>
                <div class="card-body">
                    <div class="timeline-wrapper">
                        @forelse($job->statusLogs as $log)
                            <div class="timeline-item">
                                <div class="timeline-dot"></div>
                                <div class="d-flex justify-content-between">
                                    <span class="font-weight-bold text-dark text-capitalize">
                                        {{ str_replace('_', ' ', $log->to_status) }}
                                    </span>
                                    <span class="small text-muted">{{ $log->created_at->format('d M Y, h:i A') }}</span>
                                </div>
                                <div class="small text-muted">
                                    By: <b>{{ $log->user?->name ?? 'System' }}</b>
                                    @if($log->remarks)
                                        · <i>"{{ $log->remarks }}"</i>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-muted small">No logs recorded yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Photos & Diagnostic Attachments Card --}}
            <div class="card mb-4 border shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 font-weight-bold text-dark">
                        <i class="fas fa-camera text-primary mr-2"></i>Photos &amp; Documents
                    </h5>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#uploadAttachmentModal">
                        <i class="fas fa-upload mr-1"></i>Upload Photo / Doc
                    </button>
                </div>
                <div class="card-body">
                    @if($job->attachments && $job->attachments->count() > 0)
                        <div class="row">
                            @foreach($job->attachments as $att)
                                <div class="col-md-4 mb-3">
                                    <div class="border rounded p-2 text-center bg-light position-relative">
                                        @if(preg_match('/\.(jpg|jpeg|png|webp)$/i', $att->file_path))
                                            <a href="{{ asset($att->file_path) }}" target="_blank">
                                                <img src="{{ asset($att->file_path) }}" alt="Attachment" class="img-fluid rounded mb-2" style="max-height: 120px; object-fit: cover;">
                                            </a>
                                        @else
                                            <div class="py-4">
                                                <a href="{{ asset($att->file_path) }}" target="_blank" class="text-danger">
                                                    <i class="fas fa-file-pdf fa-3x mb-2"></i>
                                                    <div class="small font-weight-bold">View Document</div>
                                                </a>
                                            </div>
                                        @endif
                                        <div class="small font-weight-bold text-dark text-truncate">{{ $att->caption ?: ucfirst(str_replace('_', ' ', $att->attachment_type)) }}</div>
                                        <div class="text-muted" style="font-size: 10px;">By: {{ $att->uploader?->name ?? 'Staff' }} &middot; {{ $att->created_at->format('d M') }}</div>

                                        <form action="{{ route('repairs.attachments.destroy', [$job->id, $att->id]) }}" method="POST" class="position-absolute" style="top: 5px; right: 5px;" onsubmit="return confirm('Delete this attachment?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-xs p-1" style="line-height: 1;" title="Delete">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-3 text-muted">
                            <i class="fas fa-images fa-2x mb-2 text-muted"></i>
                            <div class="small">No condition photos or damage reports attached yet.</div>
                        </div>
                    @endif
                </div>
            </div>

        </div>

        {{-- Right Column: Patient, Financials & Lab --}}
        <div class="col-md-5">

            {{-- Patient Info Card --}}
            <div class="card mb-4 border shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-user text-primary mr-2"></i>Patient Information</h5>
                </div>
                <div class="card-body">
                    <div class="font-weight-bold text-dark" style="font-size: 16px;">
                        {{ $job->customer?->lead?->full_name ?? 'Walk-in Patient' }}
                    </div>
                    <div class="text-muted small mb-2 font-monospace">
                        <i class="fas fa-id-card mr-1"></i>UID: {{ $job->customer?->customer_uid ?? 'N/A' }}
                    </div>
                    <div class="mb-2">
                        <i class="fas fa-phone-alt text-success mr-2"></i>
                        <a href="tel:{{ $job->customer?->lead?->phone_number }}" class="font-weight-bold text-dark">
                            {{ $job->customer?->lead?->phone_number ?? 'No Phone' }}
                        </a>
                    </div>
                    @if($job->customer?->lead?->address)
                        <div class="small text-muted">
                            <i class="fas fa-map-marker-alt text-danger mr-2"></i>{{ $job->customer->lead->address }}
                        </div>
                    @endif
                    @if($job->vendor)
                        <div class="mt-2 pt-2 border-top small text-muted">
                            <i class="fas fa-clinic-medical mr-1"></i>Receiving Branch: <b>{{ $job->vendor->name }}</b>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Financial & Billing Card --}}
            <div class="card mb-4 border shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-file-invoice-dollar text-primary mr-2"></i>Billing &amp; Payment</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table mb-0" style="font-size: 14px;">
                        <tr>
                            <td class="text-muted">Quotation Status:</td>
                            <td class="text-right">
                                @if($job->estimate_approved)
                                    <span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i>Approved</span>
                                @elseif($job->current_status === 'patient_declined')
                                    <span class="badge badge-danger"><i class="fas fa-times-circle mr-1"></i>Declined</span>
                                @elseif($job->current_status === 'waiting_patient_approval')
                                    <span class="badge badge-warning text-dark"><i class="fas fa-clock mr-1"></i>Awaiting Approval</span>
                                @else
                                    <span class="badge badge-light border">Provisional</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Branch Inspection Fee:</td>
                            <td class="text-right font-weight-bold">৳{{ number_format($job->inspection_fee, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Repair / Parts Estimate:</td>
                            <td class="text-right font-weight-bold">৳{{ number_format($job->estimate_amount, 2) }}</td>
                        </tr>
                        <tr class="table-light">
                            <td class="font-weight-bold text-dark">Total Customer Bill:</td>
                            <td class="text-right font-weight-bold text-dark" style="font-size: 16px;">৳{{ number_format($job->total_customer_cost, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Total Paid / Advance:</td>
                            <td class="text-right font-weight-bold text-success">৳{{ number_format($job->total_customer_paid, 2) }}</td>
                        </tr>
                        <tr class="{{ $job->customer_due > 0 ? 'table-danger' : 'table-success' }}">
                            <td class="font-weight-bold">Net Balance Due:</td>
                            <td class="text-right font-weight-bold" style="font-size: 16px;">৳{{ number_format($job->customer_due, 2) }}</td>
                        </tr>
                    </table>
                </div>
                <div class="card-footer bg-white border-top p-2 d-flex justify-content-between align-items-center flex-wrap" style="gap: 6px;">
                    <div>
                        @if($job->customer_due > 0)
                            <button type="button" class="btn btn-success btn-sm font-weight-bold mr-1" data-toggle="modal" data-target="#collectPaymentModal">
                                <i class="fas fa-hand-holding-usd mr-1"></i>Collect Due
                            </button>
                        @endif
                        @if($job->current_status !== 'delivered' && $job->current_status !== 'cancelled')
                            <button type="button" class="btn btn-outline-info btn-sm font-weight-bold" data-toggle="modal" data-target="#updateEstimateModal">
                                <i class="fas fa-edit mr-1"></i>Update Estimate
                            </button>
                        @endif
                    </div>
                    <a href="{{ route('repairs.print-invoice', $job->id) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-file-invoice mr-1"></i>Print Invoice
                    </a>
                </div>
            </div>

            {{-- External Lab / Supplier Card --}}
            <div class="card mb-4 border shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-flask text-primary mr-2"></i>External Lab Information</h5>
                    <button type="button" class="btn btn-outline-info btn-sm font-weight-bold" data-toggle="modal" data-target="#syncLabBillModal">
                        <i class="fas fa-edit mr-1"></i>Sync Lab Bill
                    </button>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <span class="text-muted small">Servicing Lab:</span>
                        <div class="font-weight-bold text-dark">{{ $job->supplier?->name ?? 'In-House Clinic Lab' }}</div>
                    </div>
                    @if($job->lab_ref_no)
                        <div class="mb-2">
                            <span class="text-muted small">Challan / Ref #:</span>
                            <div class="font-weight-bold font-monospace">{{ $job->lab_ref_no }}</div>
                        </div>
                    @endif
                    <div class="mb-2">
                        <span class="text-muted small">Lab Invoiced Cost:</span>
                        <div class="font-weight-bold text-dark">৳{{ number_format($job->total_supplier_cost, 2) }}</div>
                    </div>
                    @if($job->sent_to_lab_at)
                        <div class="mb-2">
                            <span class="text-muted small">Sent to Lab on:</span>
                            <div>{{ $job->sent_to_lab_at->format('d M Y, h:i A') }}</div>
                        </div>
                    @endif
                    @if($job->lab_received_at)
                        <div class="mb-2">
                            <span class="text-muted small">Returned from Lab:</span>
                            <div class="text-success font-weight-bold">{{ $job->lab_received_at->format('d M Y, h:i A') }}</div>
                        </div>
                    @endif
                    @if($job->lab_diagnosis)
                        <div>
                            <span class="text-muted small">Lab Diagnosis / Findings:</span>
                            <div class="small p-2 bg-light border rounded">{{ $job->lab_diagnosis }}</div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Service Guarantee Card --}}
            @if($job->repair_warranty_until)
                <div class="card mb-4 border-info shadow-sm bg-light">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-shield-alt fa-2x text-info mr-3"></i>
                            <div>
                                <h6 class="font-weight-bold mb-1 text-info">Post-Warranty Service Guarantee</h6>
                                <div class="small text-dark">
                                    Guaranteed for <b>{{ $job->repair_warranty_months }} Months</b> until: <b>{{ Carbon\Carbon::parse($job->repair_warranty_until)->format('d M Y') }}</b>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @elseif($job->warranty_status === 'under_product_warranty')
                <div class="card mb-4 border-success shadow-sm bg-light">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-shield-alt fa-2x text-success mr-3"></i>
                            <div>
                                <h6 class="font-weight-bold mb-1 text-success">Original Manufacturer Warranty Active</h6>
                                <div class="small text-dark">
                                    This unit retains its original manufacturer warranty in full (Option A). No separate service guarantee override needed.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>

    {{-- Modals for Lifecycle Actions --}}

    {{-- 1. Send to Lab Modal --}}
    <div class="modal fade" id="sendLabModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="{{ route('repairs.update-status', $job->id) }}" method="POST">
                @csrf
                <input type="hidden" name="to_status" value="sent_to_lab">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title font-weight-bold"><i class="fas fa-paper-plane mr-2"></i>Send Device to External Lab</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="font-weight-bold">Select External Lab / Supplier <span class="text-danger">*</span></label>
                            <select name="supplier_id" class="form-control" required>
                                <option value="">-- Choose Lab --</option>
                                @foreach($suppliers as $sup)
                                    <option value="{{ $sup->id }}" {{ $job->supplier_id == $sup->id ? 'selected' : '' }}>{{ $sup->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Challan / Courier Reference #</label>
                            <input type="text" name="lab_ref_no" class="form-control" placeholder="e.g. Sundarban #12345 or Signia Ticket #991">
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Dispatch Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2" placeholder="Notes for courier or lab technician..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning btn-sm font-weight-bold"><i class="fas fa-check mr-1"></i>Confirm Dispatch</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- 2. Log Lab Returned Modal --}}
    <div class="modal fade" id="labReturnModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="{{ route('repairs.update-status', $job->id) }}" method="POST">
                @csrf
                <input type="hidden" name="to_status" value="lab_received">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title font-weight-bold"><i class="fas fa-box-open mr-2"></i>Log Return from Lab to Clinic</h5>
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="font-weight-bold">Lab Diagnosis &amp; Work Completed</label>
                            <textarea name="lab_diagnosis" class="form-control" rows="3" placeholder="e.g. Replaced receiver wire, recalibrated amplifier, cleaned microphone..."></textarea>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Supplier / Lab Invoice Cost (৳)</label>
                            <input type="number" step="0.01" name="total_supplier_cost" class="form-control" value="0.00">
                            <small class="text-muted">Enter 0 if covered free under manufacturer warranty.</small>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Clinic Check Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2" placeholder="Tested in hearing booth, sound is clear..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info btn-sm font-weight-bold"><i class="fas fa-check mr-1"></i>Confirm Lab Receipt</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- 3. Ready for Pickup Modal --}}
    <div class="modal fade" id="readyModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="{{ route('repairs.update-status', $job->id) }}" method="POST">
                @csrf
                <input type="hidden" name="to_status" value="ready_for_pickup">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title font-weight-bold"><i class="fas fa-bell mr-2"></i>Mark Ready for Patient Pickup</h5>
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">This confirms device testing is completed and the hearing aid is safely stored in the pickup tray.</p>
                        <div class="custom-control custom-checkbox p-2 bg-light border rounded mb-3">
                            <input type="checkbox" name="send_ready_sms" class="custom-control-input" id="sendReadySmsCheck" value="1" checked>
                            <label class="custom-control-label font-weight-bold text-dark" for="sendReadySmsCheck">
                                <i class="fas fa-sms text-success mr-1"></i>Send "Ready for Collection" SMS to Patient
                            </label>
                            <div class="small text-muted pl-4">
                                Notifies {{ $job->customer?->lead?->phone_number }} with due balance ৳{{ number_format($job->customer_due, 2) }}.
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Notes</label>
                            <input type="text" name="remarks" class="form-control" placeholder="Device packaged in velvet bag with fresh batteries...">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm font-weight-bold"><i class="fas fa-check mr-1"></i>Mark Ready &amp; Notify</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- 4. Deliver & Handover Modal --}}
    <div class="modal fade" id="deliverModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="{{ route('repairs.update-status', $job->id) }}" method="POST">
                @csrf
                <input type="hidden" name="to_status" value="delivered">
                <div class="modal-content">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title font-weight-bold"><i class="fas fa-hand-holding mr-2"></i>Handover &amp; Deliver Hearing Aid</h5>
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning mb-3">
                            <i class="fas fa-exclamation-triangle mr-1"></i>Please verify patient satisfaction and collect any outstanding due.
                        </div>

                        @if($job->customer_due > 0)
                            <div class="form-group p-3 bg-light border rounded">
                                <label class="font-weight-bold text-danger">Collect Due Balance (৳):</label>
                                <input type="number" step="0.01" name="collected_amount" class="form-control form-control-lg font-weight-bold text-primary" value="{{ $job->customer_due }}" max="{{ $job->customer_due }}">
                                <small class="text-muted">Auto-credits customer ledger &amp; income upon completion.</small>
                            </div>
                        @endif

                        <div class="form-group">
                            <label class="font-weight-bold">Grant Post-Warranty Service Guarantee:</label>
                            <select name="repair_warranty_months" class="form-control">
                                <option value="0">No Guarantee (Under original warranty)</option>
                                <option value="1">1 Month Guarantee</option>
                                <option value="3" {{ $job->repair_warranty_months == 3 ? 'selected' : '' }}>3 Months Guarantee (Standard)</option>
                                <option value="6" {{ $job->repair_warranty_months == 6 ? 'selected' : '' }}>6 Months Guarantee</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Handover Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2" placeholder="Tested on patient ear, listening comfort confirmed, delivery note signed..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-dark btn-sm font-weight-bold"><i class="fas fa-check mr-1"></i>Complete Delivery</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- 5. Add Line Item Modal --}}
    <div class="modal fade" id="addItemModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="{{ route('repairs.items.store', $job->id) }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title font-weight-bold"><i class="fas fa-plus-circle mr-2"></i>Add Part or Service Charge</h5>
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="font-weight-bold">Item Category <span class="text-danger">*</span></label>
                            <select name="item_type" class="form-control" required>
                                <option value="part">Spare Part (Receiver, Shell, Mic, Amplifier, etc.)</option>
                                <option value="service_charge">Service / Labor Charge</option>
                                <option value="lab_charge">External Lab Processing Fee</option>
                                <option value="inspection_fee">Inspection Fee</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Description / Part Name <span class="text-danger">*</span></label>
                            <input type="text" name="item_name" class="form-control" placeholder="e.g. Signia Receiver Wire 2M, Shell Reshelling" required>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Select from Inventory (Optional)</label>
                            <select name="product_id" class="form-control">
                                <option value="">-- Non-inventory / Manual Service --</option>
                                @foreach($products as $p)
                                    <option value="{{ $p->id }}">{{ $p->product_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="font-weight-bold">Quantity</label>
                                    <input type="number" step="1" min="1" name="qty" class="form-control" value="1" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="font-weight-bold">Customer Price (৳)</label>
                                    <input type="number" step="0.01" min="0" name="customer_unit_price" class="form-control" value="0.00" id="itemCustomerPrice">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="font-weight-bold">Supplier Cost (৳)</label>
                                    <input type="number" step="0.01" min="0" name="supplier_unit_cost" class="form-control" value="0.00">
                                </div>
                            </div>
                        </div>
                        <div class="custom-control custom-checkbox p-2 bg-light border rounded">
                            <input type="checkbox" name="is_warranty_covered" class="custom-control-input" id="itemWarrantyCovered" value="1" onchange="document.getElementById('itemCustomerPrice').disabled = this.checked;">
                            <label class="custom-control-label font-weight-bold text-success" for="itemWarrantyCovered">
                                <i class="fas fa-shield-alt mr-1"></i>Covered Free under Manufacturer Warranty (৳0.00 to Patient)
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm font-weight-bold"><i class="fas fa-check mr-1"></i>Add Item to Bill</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- 6. Collect Payment Modal --}}
    <div class="modal fade" id="collectPaymentModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="{{ route('repairs.collect-payment', $job->id) }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title font-weight-bold"><i class="fas fa-hand-holding-usd mr-2"></i>Collect Customer Payment</h5>
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex justify-content-between p-2 mb-3 bg-light border rounded">
                            <div>
                                <span class="text-muted small">Total Billed:</span>
                                <div class="font-weight-bold">৳{{ number_format($job->total_customer_cost, 2) }}</div>
                            </div>
                            <div>
                                <span class="text-muted small">Already Paid:</span>
                                <div class="font-weight-bold text-success">৳{{ number_format($job->total_customer_paid, 2) }}</div>
                            </div>
                            <div>
                                <span class="text-muted small">Net Due:</span>
                                <div class="font-weight-bold text-danger">৳{{ number_format($job->customer_due, 2) }}</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Payment Amount (৳) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="1" max="{{ $job->customer_due }}" name="amount" class="form-control form-control-lg font-weight-bold text-success" value="{{ $job->customer_due }}" required>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Payment Method</label>
                            <select name="payment_method" class="form-control">
                                <option value="Cash">Cash</option>
                                <option value="bKash">bKash (MFS)</option>
                                <option value="Nagad">Nagad (MFS)</option>
                                <option value="Card (POS)">Card (POS Terminal)</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                            </select>
                        </div>
                        <small class="text-muted">Recording this payment will immediately credit the customer's ledger and log Repair &amp; Service Income.</small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success btn-sm font-weight-bold"><i class="fas fa-check mr-1"></i>Confirm &amp; Record Payment</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- 7. Sync Supplier Lab Bill Modal --}}
    <div class="modal fade" id="syncLabBillModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="{{ route('repairs.sync-lab-bill', $job->id) }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title font-weight-bold"><i class="fas fa-file-invoice mr-2"></i>Sync Supplier / Lab Bill</h5>
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="font-weight-bold">Servicing Supplier / Lab <span class="text-danger">*</span></label>
                            <select name="supplier_id" class="form-control" required>
                                <option value="">-- Select Supplier --</option>
                                @foreach($suppliers as $sup)
                                    <option value="{{ $sup->id }}" {{ $job->supplier_id == $sup->id ? 'selected' : '' }}>{{ $sup->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Supplier Invoice / Lab Cost (৳) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="supplier_cost" class="form-control" value="{{ $job->total_supplier_cost }}" required>
                            <small class="text-muted">Enter 0.00 if this was repaired 100% free under manufacturer warranty.</small>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Supplier Challan / Memo / Ticket #</label>
                            <input type="text" name="lab_ref_no" class="form-control" value="{{ $job->lab_ref_no }}" placeholder="e.g. Signia-INV-9921">
                        </div>
                        <small class="text-muted">This posts directly to the Supplier's Ledger as Accounts Payable, keeping clinic accounts fully reconciled.</small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info btn-sm font-weight-bold"><i class="fas fa-check mr-1"></i>Sync Lab Payable</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- 8. Receive Replacement from Supplier Lab Modal --}}
    <div class="modal fade" id="replacementModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form action="{{ route('repairs.authorize-replacement', $job->id) }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title font-weight-bold"><i class="fas fa-exchange-alt mr-2"></i>Receive Replacement Hearing Aid from Supplier Lab</h5>
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-primary mb-3">
                            <i class="fas fa-info-circle mr-1"></i>
                            <b>Supplier Lab Exchange:</b> The manufacturer lab inspected and retained the defective unit (S/N: <b class="text-danger">{{ $job->original_serial_no }}</b>) and returned a new replacement device with a Replacement Challan.
                            <div class="small mt-1 text-muted">
                                <b>Option A Rule:</b> The new replacement unit strictly inherits the unexpired balance of the patient's original purchase warranty.
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold">Servicing Supplier / Lab <span class="text-danger">*</span></label>
                                    <select name="supplier_id" class="form-control" required>
                                        <option value="">-- Select Servicing Supplier --</option>
                                        @foreach($suppliers as $s)
                                            <option value="{{ $s->id }}" {{ ($job->supplier_id == $s->id) ? 'selected' : '' }}>
                                                {{ $s->supplier_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold">Supplier Replacement Challan / Memo # <span class="text-danger">*</span></label>
                                    <input type="text" name="supplier_memo_no" class="form-control font-weight-bold font-monospace" placeholder="e.g. CH-2026-8812 / CM-4401" value="{{ old('supplier_memo_no', $job->lab_ref_no) }}" required>
                                    <small class="text-muted">Challan number issued by manufacturer with the new unit.</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">New Replacement Serial Number (S/N) <span class="text-danger">*</span></label>
                            <input type="text" name="replacement_serial_no" class="form-control form-control-lg font-monospace font-weight-bold text-success" placeholder="e.g. SN-998822" required>
                            <small class="text-muted">Serial number printed on the new replacement device/box.</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold">Supplier Replacement Fee (৳) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0" name="supplier_cost" class="form-control font-weight-bold text-danger" value="0.00" required>
                                    <small class="text-muted">Enter 0.00 for free warranty replacement. Posts to Supplier Ledger.</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold">Charge to Customer / Patient (৳)</label>
                                    <input type="number" step="0.01" min="0" name="charge_amount" class="form-control font-weight-bold" value="0.00">
                                    <small class="text-muted">Enter 0.00 if free warranty replacement for patient.</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Replacement Reason / Lab Notes</label>
                            <input type="text" name="replacement_reason" class="form-control" placeholder="e.g. Amplifier dead, manufacturer issued replacement under warranty">
                        </div>

                        <div class="p-3 bg-light border rounded mb-3">
                            <div class="custom-control custom-checkbox mb-2">
                                <input type="checkbox" name="set_ready_status" class="custom-control-input" id="setReadyCheck" value="1" checked>
                                <label class="custom-control-label font-weight-bold text-success" for="setReadyCheck">
                                    <i class="fas fa-check-circle mr-1"></i>Advance Status to "Ready for Pickup" (Unit is in clinic)
                                </label>
                            </div>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" name="send_ready_sms" class="custom-control-input" id="sendReadySmsCheck" value="1" checked>
                                <label class="custom-control-label font-weight-bold text-primary" for="sendReadySmsCheck">
                                    <i class="fas fa-comment-dots mr-1"></i>Send "Ready for Pickup" SMS Notification to Patient
                                </label>
                            </div>
                        </div>

                        {{-- Collapsible rare local inventory override --}}
                        <div class="mb-2">
                            <a class="small text-muted" data-toggle="collapse" href="#advancedStockToggle" role="button" aria-expanded="false">
                                <i class="fas fa-cog mr-1"></i>Rare: Issued from local clinic shelf stock instead of supplier lab?
                            </a>
                            <div class="collapse mt-2" id="advancedStockToggle">
                                <div class="card card-body bg-light border py-2">
                                    <div class="form-group mb-2">
                                        <label class="small font-weight-bold">Select Model from Clinic Inventory</label>
                                        <select name="replacement_product_id" class="form-control form-control-sm">
                                            <option value="">-- Same Product Model ({{ $job->device_name }}) --</option>
                                            @foreach($products as $p)
                                                <option value="{{ $p->id }}">{{ $p->product_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" name="deduct_stock" class="custom-control-input" id="deductStockCheck" value="1">
                                        <label class="custom-control-label small font-weight-bold text-dark" for="deductStockCheck">
                                            Deduct 1 unit from branch shelf inventory (StockOut)
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm font-weight-bold"><i class="fas fa-check-circle mr-1"></i>Receive Replacement &amp; Mark Ready</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- 9. Upload Attachment Modal --}}
    <div class="modal fade" id="uploadAttachmentModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="{{ route('repairs.attachments.store', $job->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title font-weight-bold"><i class="fas fa-camera mr-2"></i>Upload Photo or Diagnostic Proof</h5>
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="font-weight-bold">Attachment Category <span class="text-danger">*</span></label>
                            <select name="attachment_type" class="form-control" required>
                                <option value="intake_condition">Intake Physical Condition Photo</option>
                                <option value="void_proof">Warranty Void Damage Proof (Liquid / Acid / Crack)</option>
                                <option value="lab_report">Supplier / Manufacturer Lab Report</option>
                                <option value="customer_signed_slip">Customer Signed Delivery Slip</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Select File (JPG, PNG, WebP, PDF - max 10MB) <span class="text-danger">*</span></label>
                            <input type="file" name="file" class="form-control-file" accept="image/*,.pdf" required>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Notes / Caption</label>
                            <input type="text" name="caption" class="form-control" placeholder="e.g. Green corrosion found in battery bay">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm font-weight-bold"><i class="fas fa-upload mr-1"></i>Upload Attachment</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- 10. Declare Warranty Void Modal (Lab Findings / Teardown) --}}
    <div class="modal fade" id="voidWarrantyModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form action="{{ route('repairs.void-warranty', $job->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title font-weight-bold">
                            <i class="fas fa-ban mr-2"></i>Declare Warranty Void (Lab Findings / Internal Inspection)
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning mb-3">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <b>Technician / Lab Discovery:</b> Hearing aids may appear intact externally at the counter, but internal teardown by the manufacturer lab often reveals sweat/liquid ingress, battery acid leakage, or amplifier corrosion. This action voids the warranty, logs the cause, and moves the status to <b>Waiting for Patient Approval</b> with an out-of-warranty repair quotation.
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Void Reason Category <span class="text-danger">*</span></label>
                            <select name="void_reason" class="form-control" required>
                                <option value="Liquid / Moisture / Sweat Ingress">Liquid / Moisture / Sweat Ingress (Corroded Microphone / Amplifier / Receiver)</option>
                                <option value="Battery Acid Leakage">Battery Acid Leakage / Corrosion in Chamber</option>
                                <option value="Physical Impact / Casing Fracture">Physical Impact / Broken Receiver / Housing Fracture</option>
                                <option value="Unauthorized Disassembly / Tampering">Unauthorized Disassembly / Broken Factory Seal</option>
                                <option value="Other Physical/Environmental Abuse">Other Physical/Environmental Damage</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Lab Teardown Findings / Diagnostic Details</label>
                            <textarea name="lab_diagnosis" class="form-control" rows="2" placeholder="e.g. Lab teardown confirmed sweat ingress behind microphone port; amplifier corroded."></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="font-weight-bold text-primary">New Customer Repair Quotation (৳)</label>
                                <input type="number" step="0.01" min="0" name="new_estimate_amount" class="form-control font-weight-bold text-primary" placeholder="e.g. 3500.00" value="{{ $job->estimate_amount > 0 ? $job->estimate_amount : '' }}">
                                <small class="text-muted">Quoted amount to bill the patient once they approve.</small>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="font-weight-bold">External Lab / Supplier Cost (৳)</label>
                                <input type="number" step="0.01" min="0" name="supplier_cost" class="form-control font-weight-bold" placeholder="e.g. 2000.00" value="{{ $job->total_supplier_cost > 0 ? $job->total_supplier_cost : '' }}">
                                <small class="text-muted">Payable to manufacturer / lab for the repair.</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Upload Lab Diagnosis Proof / Photo (Optional)</label>
                            <input type="file" name="proof_file" class="form-control-file" accept="image/*,.pdf">
                            <small class="text-muted">Attach photo of corrosion or manufacturer inspection report to share with patient.</small>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm font-weight-bold">
                            <i class="fas fa-check mr-1"></i>Declare Void &amp; Request Patient Approval
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- 11. Update Estimate & Lab Quotation Modal --}}
    <div class="modal fade" id="updateEstimateModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form action="{{ route('repairs.update-estimate', $job->id) }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title font-weight-bold">
                            <i class="fas fa-calculator mr-2"></i>Update Service Estimate &amp; Quotation
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="font-weight-bold text-primary">Customer Estimate Amount (৳) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="estimate_amount" class="form-control form-control-lg font-weight-bold text-primary" value="{{ $job->estimate_amount }}" required>
                            <small class="text-muted">Customer will be billed this amount plus inspection fee (৳{{ number_format($job->inspection_fee, 2) }}).</small>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Supplier / External Lab Cost (৳)</label>
                            <input type="number" step="0.01" min="0" name="supplier_cost" class="form-control" value="{{ $job->total_supplier_cost }}">
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Lab Memo / Challan #</label>
                            <input type="text" name="lab_ref_no" class="form-control" value="{{ $job->lab_ref_no }}" placeholder="e.g. Signia-Q-4491">
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Quotation Remarks / Replaced Parts Detail</label>
                            <textarea name="remarks" rows="2" class="form-control" placeholder="e.g. Receiver replacement and shell recasting quote from lab..."></textarea>
                        </div>

                        <div class="custom-control custom-checkbox p-2 bg-light border rounded">
                            <input type="checkbox" name="set_waiting_approval" class="custom-control-input" id="setWaitingApprovalCheck" value="1" checked>
                            <label class="custom-control-label font-weight-bold text-dark small" for="setWaitingApprovalCheck">
                                <i class="fas fa-clock text-warning mr-1"></i>Set status to "Waiting for Patient Approval" (requires patient authorization before repair)
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info btn-sm font-weight-bold">
                            <i class="fas fa-save mr-1"></i>Save Quotation
                        </button>
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
        // Relocate all modals to body so backdrop never traps them in stacking context
        $('.modal').appendTo('body');
    });
</script>
@endpush
