@extends('admin.layouts.master')
@section('page_title', 'Book Appointment')
@section('breadcrumb_parent', 'Appointments')
@section('breadcrumb_parent_url', url('admin/appointments'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <style>
        /* Step progress indicator */
        .booking-steps-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            margin-bottom: 2rem;
            max-width: 720px;
            margin-left: auto;
            margin-right: auto;
        }
        .booking-step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            cursor: pointer;
        }
        .step-circle {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #fff;
            border: 2px solid #e2e8f0;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        .booking-step-item.active .step-circle {
            background: #4f46e5;
            border-color: #4f46e5;
            color: #fff;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35);
        }
        .booking-step-item.completed .step-circle {
            background: #10b981;
            border-color: #10b981;
            color: #fff;
        }
        .step-title {
            margin-top: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
        }
        .booking-step-item.active .step-title {
            color: #1e293b;
        }
        .steps-line-bar {
            position: absolute;
            top: 20px;
            left: 50px;
            right: 50px;
            height: 2px;
            background: #e2e8f0;
            z-index: 1;
        }
        .steps-line-progress {
            height: 100%;
            width: 0%;
            background: #4f46e5;
            transition: width 0.3s ease;
        }

        /* Patient Search Box */
        .patient-search-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .patient-results-dropdown {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            max-height: 260px;
            overflow-y: auto;
            background: #fff;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
            position: absolute;
            width: 100%;
            z-index: 100;
            display: none;
        }
        .patient-result-item {
            padding: 10px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.15s;
        }
        .patient-result-item:hover {
            background: #f8fafc;
        }
        .patient-selected-box {
            background: #f0fdf4;
            border: 2px solid #86efac;
            border-radius: 10px;
            padding: 16px 20px;
            display: none;
        }

        /* Service Cards */
        .service-selection-card {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            cursor: pointer;
            transition: all 0.2s;
            background: #fff;
            height: 100%;
            position: relative;
        }
        .service-selection-card:hover {
            border-color: #818cf8;
            transform: translateY(-2px);
        }
        .service-selection-card.selected {
            border-color: #4f46e5;
            background: #f5f3ff;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.15);
        }
        .service-selection-card.selected::after {
            content: '\f00c';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            top: 10px;
            right: 12px;
            color: #4f46e5;
            font-size: 14px;
        }

        /* Time slot buttons */
        .time-pill-btn {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 10px 14px;
            font-weight: 600;
            font-size: 13.5px;
            color: #1e293b;
            background: #fff;
            transition: all 0.15s;
            cursor: pointer;
            width: 100%;
            text-align: center;
        }
        .time-pill-btn:hover {
            border-color: #4f46e5;
            background: #f8fafc;
            color: #4f46e5;
        }
        .time-pill-btn.selected {
            background: #4f46e5;
            border-color: #4f46e5;
            color: #fff;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.35);
        }

        /* Summary receipt sidebar */
        .booking-summary-panel {
            background: #0f172a;
            color: #fff;
            border-radius: 12px;
            padding: 24px;
        }
        .summary-label {
            color: #94a3b8;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .summary-value {
            font-weight: 700;
            font-size: 14px;
            color: #f8fafc;
        }
    </style>
@endpush

@section('content')
<div class="container-fluid py-4" style="max-width: 1180px;">

    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h3 class="font-weight-bold mb-1" style="color: #1e293b;">
                <i class="fas fa-calendar-check text-primary mr-2"></i>Schedule Clinical Appointment
            </h3>
            <p class="text-muted mb-0">Follow 3 quick steps to reserve an appointment</p>
        </div>
        <div>
            <a href="{{ url('admin/appointments') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to Appointments
            </a>
        </div>
    </div>

    @include('admin.alert_message')

    @if(!empty($serviceDetail))
        <div class="alert alert-info shadow-sm mb-4" style="border-left: 4px solid #2563eb; background: #eff6ff; color: #1e40af;">
            <div class="d-flex align-items-center">
                <i class="fas fa-receipt mr-3" style="font-size: 24px;"></i>
                <div>
                    <strong>Booking for Sold Service:</strong>
                    <span>{{ $serviceDetail->serviceType?->service_type_name }} &middot; Invoice #{{ $serviceDetail->saleMaster?->sale_invoice_no }}</span>
                    @if($preselectedCustomer?->lead)
                        <div>Patient: <strong>{{ $preselectedCustomer->lead->full_name }}</strong> ({{ $preselectedCustomer->lead->phone_number }})</div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Progress Steps --}}
    <div class="booking-steps-nav">
        <div class="steps-line-bar"><div class="steps-line-progress" id="stepsProgressLine"></div></div>
        <div class="booking-step-item active" id="navStep1" onclick="navigateToStep(1)">
            <div class="step-circle" id="circle1">1</div>
            <div class="step-title">Patient</div>
        </div>
        <div class="booking-step-item" id="navStep2" onclick="navigateToStep(2)">
            <div class="step-circle" id="circle2">2</div>
            <div class="step-title">Clinic & Service</div>
        </div>
        <div class="booking-step-item" id="navStep3" onclick="navigateToStep(3)">
            <div class="step-circle" id="circle3">3</div>
            <div class="step-title">Date & Time</div>
        </div>
    </div>

    {{-- Main Booking Form --}}
    <form action="{{ url('admin/appointments') }}" method="POST" id="bookingForm">
        @csrf

        {{-- Hidden fields for sale linkage --}}
        @if(!empty($serviceDetailId))
            <input type="hidden" name="service_detail_id" value="{{ $serviceDetailId }}">
        @endif
        @if(!empty($saleMasterId))
            <input type="hidden" name="sale_master_id" value="{{ $saleMasterId }}">
        @endif
        @if(!empty($saleInvoiceNo))
            <input type="hidden" name="sale_invoice_no" value="{{ $saleInvoiceNo }}">
        @endif

        {{-- Hidden payload fields --}}
        <input type="hidden" name="lead_id" id="hidden_lead_id" value="{{ old('lead_id', $preselectedCustomer?->lead_id) }}">
        <input type="hidden" name="customer_id" id="hidden_customer_id" value="{{ old('customer_id', $preselectedCustomerId) }}">
        <input type="hidden" name="service_id" id="hidden_service_id" value="{{ old('service_id', $preselectedServiceId) }}">
        <input type="hidden" name="user_id" id="hidden_user_id" value="{{ old('user_id') }}">
        <input type="hidden" name="resource_id" id="hidden_resource_id" value="{{ old('resource_id') }}">
        <input type="hidden" name="start_time" id="hidden_start_time" value="{{ old('start_time') }}">

        {{-- STEP 1: PATIENT IDENTIFICATION --}}
        <div id="stepPanel1" class="booking-step-panel">
            <div class="patient-search-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="font-weight-bold mb-0" style="color: #1e293b;">
                        <i class="fas fa-user-injured text-indigo mr-2"></i>Step 1: Patient Details
                    </h5>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="toggleQuickLeadModal(true)">
                        <i class="fas fa-plus mr-1"></i> Register New Patient
                    </button>
                </div>

                <div id="patientSearchSection">
                    <label class="font-weight-semibold text-secondary" style="font-size: 13px;">SEARCH BY PHONE NUMBER, NAME, OR CUSTOMER ID</label>
                    <div class="position-relative">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                            </div>
                            <input type="text" class="form-control form-control-lg" id="patientSearchInput" placeholder="Enter mobile number (e.g. 017...) or name..." autocomplete="off">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-primary" onclick="executePatientSearch()">Search</button>
                            </div>
                        </div>
                        <div class="patient-results-dropdown" id="patientResultsDropdown"></div>
                    </div>
                </div>

                {{-- Selected Patient Card --}}
                <div class="patient-selected-box mt-3" id="patientSelectedBox">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 44px; height: 44px; font-size: 20px;">
                                <i class="fas fa-check"></i>
                            </div>
                            <div>
                                <h6 class="font-weight-bold mb-0 text-success" id="selectedPatientName">Patient Name</h6>
                                <span class="text-muted" style="font-size: 13px;" id="selectedPatientMeta">Mobile: 017... &middot; Customer</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearSelectedPatient()">
                            <i class="fas fa-times mr-1"></i> Change Patient
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                    <button type="button" class="btn btn-primary px-4" id="btnStep1Next" onclick="goToStep(2)" disabled>
                        Next: Clinic & Service <i class="fas fa-arrow-right ml-1"></i>
                    </button>
                </div>
            </div>
        </div>

        {{-- STEP 2: CLINIC, SERVICE & DATE --}}
        <div id="stepPanel2" class="booking-step-panel" style="display: none;">
            <div class="card shadow-sm border-0 rounded-lg p-4">
                <h5 class="font-weight-bold mb-4" style="color: #1e293b;">
                    <i class="fas fa-clinic-medical text-primary mr-2"></i>Step 2: Choose Branch, Service & Date
                </h5>

                <div class="row">
                    {{-- Branch Selection --}}
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold text-secondary">Branch / Clinic Location <span class="text-danger">*</span></label>
                        <select name="branch_id" id="selectBranch" class="form-control form-control-lg" {{ !$canChangeBranch ? 'readonly' : '' }}>
                            @foreach ($branches as $branchId => $branchName)
                                <option value="{{ $branchId }}" {{ $currentBranchId == $branchId ? 'selected' : '' }}>
                                    {{ $branchName }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Date Selection --}}
                    <div class="col-md-6 mb-3">
                        <label class="font-weight-bold text-secondary">Appointment Date <span class="text-danger">*</span></label>
                        <input type="date" name="appointment_date" id="appointmentDate" class="form-control form-control-lg" value="{{ date('Y-m-d') }}" min="{{ date('Y-m-d') }}" required>
                    </div>
                </div>

                {{-- Service Cards Grid --}}
                <div class="mb-4">
                    <label class="font-weight-bold text-secondary d-block mb-2">Select Clinical Service <span class="text-danger">*</span></label>
                    <div class="row">
                        @foreach ($appointmentServices as $svc)
                            @php
                                $price = $svc->serviceType?->price;
                                $bookingType = $svc->serviceType?->booking_type;
                            @endphp
                            <div class="col-md-6 col-lg-3 mb-3">
                                <div class="service-selection-card {{ $preselectedServiceId == $svc->id ? 'selected' : '' }}" data-service-id="{{ $svc->id }}" onclick="selectServiceCard(this, {{ $svc->id }})">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="badge" style="background: {{ $svc->color_hex }}; color: #fff;">
                                            {{ ucfirst($svc->category?->value ?? 'Clinical') }}
                                        </span>
                                        @if($svc->requires_resource)
                                            <span class="badge badge-warning text-dark ml-2" title="Requires Sound Booth">
                                                <i class="fas fa-door-closed mr-1"></i>Sound Booth
                                            </span>
                                        @endif
                                    </div>
                                    <h6 class="font-weight-bold mb-1" style="color: #1e293b;">{{ $svc->name }}</h6>
                                    <div class="text-muted" style="font-size: 12px; margin-bottom: 8px;">
                                        <i class="fas fa-clock mr-1"></i>{{ $svc->duration_minutes }} mins (+{{ $svc->buffer_after_minutes }}m buffer)
                                    </div>
                                    @if($price !== null)
                                        <div class="font-weight-bold text-success" style="font-size: 14px;">
                                            ৳{{ number_format($price, 2) }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Specialist Selection (Optional) --}}
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="font-weight-bold text-secondary">Preferred Specialist (Optional)</label>
                        <select id="selectSpecialist" class="form-control">
                            <option value="all">Any Available Specialist (Recommended)</option>
                            @foreach ($professionals as $prof)
                                <option value="{{ $prof->id }}">{{ $prof->name }} ({{ $prof->designation?->label() ?? 'Specialist' }})</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Leave as "Any Available Specialist" to see all openings across on-duty doctors.</small>
                    </div>
                </div>

                <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                    <button type="button" class="btn btn-outline-secondary px-4" onclick="goToStep(1)">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Patient
                    </button>
                    <button type="button" class="btn btn-primary px-4" id="btnStep2Next" onclick="goToStep(3)">
                        Next: Pick Time <i class="fas fa-arrow-right ml-1"></i>
                    </button>
                </div>
            </div>
        </div>

        {{-- STEP 3: TIME SLOTS & CONFIRMATION --}}
        <div id="stepPanel3" class="booking-step-panel" style="display: none;">
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="card shadow-sm border-0 rounded-lg p-4">
                        <h5 class="font-weight-bold mb-3" style="color: #1e293b;">
                            <i class="fas fa-clock text-primary mr-2"></i>Step 3: Select Available Time
                        </h5>

                        {{-- Alert for Closed Day or Holiday --}}
                        <div id="closureAlertBox" class="alert alert-danger" style="display:none;">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <span id="closureAlertText">Branch is closed on this date.</span>
                        </div>

                        {{-- Loading Spinner --}}
                        <div id="slotsLoadingSpinner" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status"></div>
                            <div class="text-muted mt-2">Checking clinic & sound booth availability...</div>
                        </div>

                        {{-- Time Slot Grid --}}
                        <div id="slotsContainer" style="display:none;">
                            {{-- Morning --}}
                            <div class="mb-3" id="periodMorningSection">
                                <h6 class="font-weight-bold text-muted text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">
                                    <i class="fas fa-sun text-warning mr-1"></i> Morning (09:00 – 12:00)
                                </h6>
                                <div class="row" id="gridMorning"></div>
                            </div>

                            {{-- Afternoon --}}
                            <div class="mb-3" id="periodAfternoonSection">
                                <h6 class="font-weight-bold text-muted text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">
                                    <i class="fas fa-cloud-sun text-primary mr-1"></i> Afternoon (12:00 – 16:00)
                                </h6>
                                <div class="row" id="gridAfternoon"></div>
                            </div>

                            {{-- Evening --}}
                            <div class="mb-3" id="periodEveningSection">
                                <h6 class="font-weight-bold text-muted text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">
                                    <i class="fas fa-moon text-indigo mr-1"></i> Evening (16:00 – 19:00)
                                </h6>
                                <div class="row" id="gridEvening"></div>
                            </div>

                            <div id="noSlotsMessage" class="alert alert-warning text-center" style="display:none;">
                                <i class="fas fa-info-circle mr-1"></i> No available start times found on this date. Please try another date or doctor.
                            </div>
                        </div>

                        {{-- Advance Payment Option --}}
                        <div class="border rounded p-3 mt-4 bg-light">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="takeAdvanceCheck" name="take_advance" value="1" onchange="toggleAdvanceInputs(this.checked)">
                                <label class="custom-control-label font-weight-bold" for="takeAdvanceCheck">
                                    Collect Advance Payment Now (Optional)
                                </label>
                            </div>

                            <div id="advanceInputsRow" class="row mt-3" style="display:none;">
                                <div class="col-md-4 mb-2">
                                    <label style="font-size:12px;">Advance Amount (৳)</label>
                                    <input type="number" step="0.01" name="advance_amount" class="form-control form-control-sm" placeholder="e.g. 500.00">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label style="font-size:12px;">Payment Method</label>
                                    <select name="advance_payment_method" class="form-control form-control-sm">
                                        <option value="cash">Cash</option>
                                        <option value="bkash">bKash</option>
                                        <option value="nagad">Nagad</option>
                                        <option value="bank">Bank Transfer</option>
                                        <option value="pos">Card (POS)</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label style="font-size:12px;">Transaction Reference</label>
                                    <input type="text" name="advance_reference" class="form-control form-control-sm" placeholder="TRX ID...">
                                </div>
                            </div>
                        </div>

                        {{-- Notes --}}
                        <div class="mt-3">
                            <label class="font-weight-semibold text-secondary" style="font-size:13px;">Clinical / Reception Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Special patient requests, medical background, or referral source..."></textarea>
                        </div>

                        <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                            <button type="button" class="btn btn-outline-secondary px-4" onclick="goToStep(2)">
                                <i class="fas fa-arrow-left mr-1"></i> Back to Clinic & Service
                            </button>
                            <button type="submit" class="btn btn-success px-4 font-weight-bold" id="btnSubmitAppointment" disabled>
                                <i class="fas fa-check-circle mr-1"></i> CONFIRM BOOKING
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Summary Sidebar --}}
                <div class="col-lg-4 mb-4">
                    <div class="booking-summary-panel shadow-sm">
                        <h6 class="font-weight-bold text-uppercase border-bottom pb-2 mb-3" style="color: #38bdf8; letter-spacing: 0.5px;">
                            <i class="fas fa-clipboard-list mr-1"></i> Booking Summary
                        </h6>

                        <div class="mb-3">
                            <div class="summary-label">Patient</div>
                            <div class="summary-value" id="summaryPatientName">—</div>
                        </div>

                        <div class="mb-3">
                            <div class="summary-label">Branch</div>
                            <div class="summary-value" id="summaryBranchName">—</div>
                        </div>

                        <div class="mb-3">
                            <div class="summary-label">Service</div>
                            <div class="summary-value" id="summaryServiceName">—</div>
                        </div>

                        <div class="mb-3">
                            <div class="summary-label">Specialist</div>
                            <div class="summary-value" id="summaryDoctorName">—</div>
                        </div>

                        <div class="mb-3">
                            <div class="summary-label">Date & Time</div>
                            <div class="summary-value text-warning" id="summaryDateTime">—</div>
                        </div>

                        <div class="mb-0">
                            <div class="summary-label">Facility / Room</div>
                            <div class="summary-value" id="summaryResourceName">Standard Consultation Room</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </form>
</div>

{{-- Quick Register Lead Modal --}}
<div class="modal fade" id="quickLeadModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-user-plus mr-2"></i>Quick Register New Patient</h5>
                <button type="button" class="close text-white" onclick="toggleQuickLeadModal(false)"><span>&times;</span></button>
            </div>
            <div class="modal-body p-4">
                <div class="form-group">
                    <label class="font-weight-bold">Patient Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="quickLeadName" placeholder="e.g. Kabir Hossain" required>
                </div>
                <div class="form-group">
                    <label class="font-weight-bold">Mobile Number <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="quickLeadPhone" placeholder="e.g. 01711000000" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" onclick="toggleQuickLeadModal(false)">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="submitQuickLead()">Save & Select Patient</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let currentStep = 1;
    let selectedPatient = null;
    let selectedService = null;
    let selectedSlot = null;
    let searchDebounceTimeout = null;

    // Services Catalog lookup
    const servicesCatalog = {
        @foreach ($appointmentServices as $svc)
            {{ $svc->id }}: {
                id: {{ $svc->id }},
                name: "{{ addslashes($svc->name) }}",
                duration: {{ $svc->duration_minutes }},
                requiresResource: {{ $svc->requires_resource ? 'true' : 'false' }},
                resourceType: "{{ $svc->resource_type }}",
                price: "{{ $svc->serviceType?->price ? '৳' . number_format($svc->serviceType->price, 2) : '' }}"
            },
        @endforeach
    };

    document.addEventListener('DOMContentLoaded', function() {
        // If preselected patient from sale
        @if(!empty($preselectedCustomer?->lead))
            selectPatient({
                type: 'customer',
                id: {{ $preselectedCustomer->id }},
                customer_id: {{ $preselectedCustomer->id }},
                lead_id: {{ $preselectedCustomer->lead->id }},
                name: "{{ addslashes($preselectedCustomer->lead->full_name) }}",
                phone: "{{ $preselectedCustomer->lead->phone_number }}"
            });
        @endif

        // If preselected service
        const preSvcId = document.getElementById('hidden_service_id').value;
        if (preSvcId && servicesCatalog[preSvcId]) {
            selectedService = servicesCatalog[preSvcId];
            updateSummary();
        }

        // Search input keyup
        document.getElementById('patientSearchInput').addEventListener('input', function(e) {
            clearTimeout(searchDebounceTimeout);
            const val = e.target.value.trim();
            if (val.length >= 2) {
                searchDebounceTimeout = setTimeout(() => executePatientSearch(val), 250);
            } else {
                document.getElementById('patientResultsDropdown').style.display = 'none';
            }
        });

        // Trigger dynamic availability on date, branch, or specialist change
        document.getElementById('selectBranch').addEventListener('change', () => {
            updateSummary();
            if (currentStep === 3) fetchAvailableSlots();
        });
        document.getElementById('appointmentDate').addEventListener('change', () => {
            updateSummary();
            if (currentStep === 3) fetchAvailableSlots();
        });
        document.getElementById('selectSpecialist').addEventListener('change', () => {
            updateSummary();
            if (currentStep === 3) fetchAvailableSlots();
        });
    });

    /* ================= STEP NAVIGATION ================= */
    function goToStep(step) {
        if (step === 2 && !selectedPatient) {
            alert('Please select or register a patient first.');
            return;
        }
        if (step === 3 && !selectedService) {
            alert('Please choose a clinical service.');
            return;
        }

        document.getElementById('stepPanel1').style.display = (step === 1 ? 'block' : 'none');
        document.getElementById('stepPanel2').style.display = (step === 2 ? 'block' : 'none');
        document.getElementById('stepPanel3').style.display = (step === 3 ? 'block' : 'none');

        document.getElementById('navStep1').className = 'booking-step-item ' + (step === 1 ? 'active' : 'completed');
        document.getElementById('navStep2').className = 'booking-step-item ' + (step === 2 ? 'active' : (step > 2 ? 'completed' : ''));
        document.getElementById('navStep3').className = 'booking-step-item ' + (step === 3 ? 'active' : '');

        const progressPercent = step === 1 ? '0%' : (step === 2 ? '50%' : '100%');
        document.getElementById('stepsProgressLine').style.width = progressPercent;

        currentStep = step;
        updateSummary();

        if (step === 3) {
            fetchAvailableSlots();
        }
    }

    function navigateToStep(step) {
        if (step < currentStep) {
            goToStep(step);
        } else if (step === 2 && selectedPatient) {
            goToStep(2);
        } else if (step === 3 && selectedPatient && selectedService) {
            goToStep(3);
        }
    }

    /* ================= PATIENT SEARCH & QUICK CREATE ================= */
    function executePatientSearch(queryText) {
        const q = queryText || document.getElementById('patientSearchInput').value.trim();
        if (q.length < 2) return;

        fetch(`{{ url('admin/appointments/search-patient') }}?q=${encodeURIComponent(q)}`)
            .then(res => res.json())
            .then(data => {
                const dropdown = document.getElementById('patientResultsDropdown');
                dropdown.innerHTML = '';
                if (data.length === 0) {
                    dropdown.innerHTML = '<div class="p-3 text-muted text-center">No matching patients found. Click "+ Register New Patient" above.</div>';
                } else {
                    data.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'patient-result-item';
                        div.innerHTML = `
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <strong class="text-dark">${item.name}</strong>
                                    <span class="text-muted ml-2" style="font-size:12px;">Mobile: ${item.phone}</span>
                                </div>
                                <span class="badge ${item.badge_class}">${item.badge}</span>
                            </div>
                        `;
                        div.onclick = () => selectPatient(item);
                        dropdown.appendChild(div);
                    });
                }
                dropdown.style.display = 'block';
            });
    }

    function selectPatient(patient) {
        selectedPatient = patient;
        document.getElementById('hidden_customer_id').value = patient.customer_id || '';
        document.getElementById('hidden_lead_id').value = patient.lead_id || (patient.type === 'lead' ? patient.id : '');

        document.getElementById('selectedPatientName').textContent = patient.name;
        document.getElementById('selectedPatientMeta').textContent = `Mobile: ${patient.phone} · ${patient.badge || 'Registered'}`;
        document.getElementById('patientSearchSection').style.display = 'none';
        document.getElementById('patientSelectedBox').style.display = 'block';
        document.getElementById('patientResultsDropdown').style.display = 'none';
        document.getElementById('btnStep1Next').removeAttribute('disabled');

        updateSummary();
    }

    function clearSelectedPatient() {
        selectedPatient = null;
        document.getElementById('hidden_customer_id').value = '';
        document.getElementById('hidden_lead_id').value = '';
        document.getElementById('patientSearchSection').style.display = 'block';
        document.getElementById('patientSelectedBox').style.display = 'none';
        document.getElementById('patientSearchInput').value = '';
        document.getElementById('btnStep1Next').setAttribute('disabled', 'disabled');
        updateSummary();
    }

    function toggleQuickLeadModal(show) {
        if (show) {
            $('#quickLeadModal').modal('show');
        } else {
            $('#quickLeadModal').modal('hide');
        }
    }

    function submitQuickLead() {
        const name = document.getElementById('quickLeadName').value.trim();
        const phone = document.getElementById('quickLeadPhone').value.trim();
        const branchId = document.getElementById('selectBranch').value;

        if (!name || !phone) {
            alert('Please enter both name and mobile number.');
            return;
        }

        fetch(`{{ url('admin/appointments/quick-lead') }}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ full_name: name, phone_number: phone, vendor_id: branchId })
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                toggleQuickLeadModal(false);
                selectPatient({
                    type: 'lead',
                    id: res.lead.id,
                    lead_id: res.lead.id,
                    customer_id: null,
                    name: res.lead.name,
                    phone: res.lead.phone,
                    badge: 'New Lead',
                    badge_class: 'badge-info'
                });
            }
        });
    }

    /* ================= SERVICE SELECTION ================= */
    function selectServiceCard(el, serviceId) {
        document.querySelectorAll('.service-selection-card').forEach(c => c.classList.remove('selected'));
        el.classList.add('selected');

        selectedService = servicesCatalog[serviceId];
        document.getElementById('hidden_service_id').value = serviceId;
        updateSummary();
    }

    /* ================= DYNAMIC AVAILABILITY ================= */
    function fetchAvailableSlots() {
        if (!selectedService) return;

        const branchId = document.getElementById('selectBranch').value;
        const date = document.getElementById('appointmentDate').value;
        const specialistId = document.getElementById('selectSpecialist').value;

        document.getElementById('slotsLoadingSpinner').style.display = 'block';
        document.getElementById('slotsContainer').style.display = 'none';
        document.getElementById('closureAlertBox').style.display = 'none';
        document.getElementById('btnSubmitAppointment').setAttribute('disabled', 'disabled');

        let url = `{{ url('admin/appointments/dynamic-availability') }}?branch_id=${branchId}&service_id=${selectedService.id}&date=${date}`;
        if (specialistId && specialistId !== 'all') {
            url += `&user_id=${specialistId}`;
        }

        fetch(url)
            .then(res => res.json())
            .then(data => {
                document.getElementById('slotsLoadingSpinner').style.display = 'none';

                if (data.is_closed) {
                    document.getElementById('closureAlertText').textContent = data.closure_reason || 'Clinic is closed on this date.';
                    document.getElementById('closureAlertBox').style.display = 'block';
                    return;
                }

                renderSlots(data.slots || []);
            })
            .catch(() => {
                document.getElementById('slotsLoadingSpinner').style.display = 'none';
                document.getElementById('closureAlertText').textContent = 'Unable to fetch time slots. Please try again.';
                document.getElementById('closureAlertBox').style.display = 'block';
            });
    }

    function renderSlots(slots) {
        const gridM = document.getElementById('gridMorning');
        const gridA = document.getElementById('gridAfternoon');
        const gridE = document.getElementById('gridEvening');

        gridM.innerHTML = '';
        gridA.innerHTML = '';
        gridE.innerHTML = '';

        let countM = 0, countA = 0, countE = 0;

        if (slots.length === 0) {
            document.getElementById('noSlotsMessage').style.display = 'block';
            document.getElementById('slotsContainer').style.display = 'block';
            return;
        }
        document.getElementById('noSlotsMessage').style.display = 'none';

        slots.forEach(slot => {
            const col = document.createElement('div');
            col.className = 'col-6 col-sm-4 col-md-3 mb-2';

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'time-pill-btn';
            btn.textContent = slot.label;
            btn.onclick = () => selectSlot(btn, slot);

            col.appendChild(btn);

            if (slot.period === 'morning') {
                gridM.appendChild(col);
                countM++;
            } else if (slot.period === 'afternoon') {
                gridA.appendChild(col);
                countA++;
            } else {
                gridE.appendChild(col);
                countE++;
            }
        });

        document.getElementById('periodMorningSection').style.display = countM > 0 ? 'block' : 'none';
        document.getElementById('periodAfternoonSection').style.display = countA > 0 ? 'block' : 'none';
        document.getElementById('periodEveningSection').style.display = countE > 0 ? 'block' : 'none';

        document.getElementById('slotsContainer').style.display = 'block';
    }

    function selectSlot(btn, slot) {
        document.querySelectorAll('.time-pill-btn').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');

        selectedSlot = slot;
        document.getElementById('hidden_start_time').value = slot.time_full || slot.time;
        document.getElementById('hidden_user_id').value = slot.user_id;
        document.getElementById('hidden_resource_id').value = slot.resource_id || '';

        document.getElementById('btnSubmitAppointment').removeAttribute('disabled');
        updateSummary();
    }

    function toggleAdvanceInputs(checked) {
        document.getElementById('advanceInputsRow').style.display = checked ? 'flex' : 'none';
    }

    /* ================= SUMMARY PANEL ================= */
    function updateSummary() {
        // Patient
        document.getElementById('summaryPatientName').textContent = selectedPatient ? selectedPatient.name : '—';

        // Branch
        const branchSelect = document.getElementById('selectBranch');
        document.getElementById('summaryBranchName').textContent = branchSelect.options[branchSelect.selectedIndex]?.text || '—';

        // Service
        document.getElementById('summaryServiceName').textContent = selectedService ? `${selectedService.name} (${selectedService.duration}m)` : '—';

        // Doctor
        if (selectedSlot) {
            document.getElementById('summaryDoctorName').textContent = selectedSlot.user_name || 'Assigned Specialist';
        } else {
            const specSelect = document.getElementById('selectSpecialist');
            document.getElementById('summaryDoctorName').textContent = specSelect.value === 'all' ? 'Any Available Specialist' : specSelect.options[specSelect.selectedIndex].text;
        }

        // Date & Time
        const dateVal = document.getElementById('appointmentDate').value;
        if (selectedSlot && dateVal) {
            document.getElementById('summaryDateTime').textContent = `${dateVal} at ${selectedSlot.label}`;
        } else if (dateVal) {
            document.getElementById('summaryDateTime').textContent = `${dateVal} (Time not chosen)`;
        }

        // Resource
        if (selectedService && selectedService.requiresResource) {
            document.getElementById('summaryResourceName').textContent = 'Sound Booth (Clinical Hearing Evaluation)';
        } else {
            document.getElementById('summaryResourceName').textContent = 'Standard Consultation Room';
        }
    }
</script>
@endpush
