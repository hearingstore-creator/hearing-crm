@extends('admin.layouts.master')
@section('page_title', 'New Repair Intake Wizard')
@section('breadcrumb_parent', 'Repairs')
@section('breadcrumb_parent_url', url('admin/repairs'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <style>
        .wizard-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
            position: relative;
        }
        .wizard-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 5%;
            right: 5%;
            height: 2px;
            background: #e2e8f0;
            z-index: 1;
        }
        .wizard-step {
            position: relative;
            z-index: 2;
            text-align: center;
            flex: 1;
        }
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #ffffff;
            border: 2px solid #cbd5e1;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin: 0 auto 8px;
            transition: all 0.3s;
        }
        .wizard-step.active .step-circle {
            background: #3b82f6;
            border-color: #3b82f6;
            color: #ffffff;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2);
        }
        .wizard-step.completed .step-circle {
            background: #10b981;
            border-color: #10b981;
            color: #ffffff;
        }
        .step-title {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
        }
        .wizard-step.active .step-title {
            color: #1e3a8a;
            font-weight: 700;
        }
        .wizard-pane {
            display: none;
        }
        .wizard-pane.active {
            display: block;
        }
        .symptom-tag-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            cursor: pointer;
            transition: all 0.2s;
            user-select: none;
        }
        .symptom-tag-card:hover {
            border-color: #93c5fd;
            background: #f8fafc;
        }
        .symptom-tag-card.selected {
            border-color: #3b82f6;
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 600;
        }
        .warranty-card {
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .warranty-card.tier-1 {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }
        .warranty-card.tier-2 {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            color: #0369a1;
        }
        .warranty-card.out {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #334155;
        }
        .warranty-card.void {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
    </style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px; max-width: 1000px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-file-medical-alt" style="color: var(--accent-500); margin-right: 8px;"></i>New Service &amp; Repair Intake</h1>
            <p>4-step guided intake wizard with instant warranty verification &amp; barcode job card generation</p>
        </div>
        <div class="header-actions">
            <a href="{{ route('repairs.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i>Back to Jobs Board
            </a>
        </div>
    </div>

    {{-- Wizard Stepper Bar --}}
    <div class="wizard-steps crm-animate-in">
        <div class="wizard-step active" id="stepIndicator1">
            <div class="step-circle">1</div>
            <div class="step-title">Device &amp; Warranty</div>
        </div>
        <div class="wizard-step" id="stepIndicator2">
            <div class="step-circle">2</div>
            <div class="step-title">Symptoms &amp; Accessories</div>
        </div>
        <div class="wizard-step" id="stepIndicator3">
            <div class="step-circle">3</div>
            <div class="step-title">Lab, Service &amp; Billing</div>
        </div>
        <div class="wizard-step" id="stepIndicator4">
            <div class="step-circle">4</div>
            <div class="step-title">Confirmation &amp; Print</div>
        </div>
    </div>

    <form id="intakeForm" action="{{ route('repairs.store') }}" method="POST">
        @csrf

        {{-- Hidden State Fields populated by Warranty Verification --}}
        <input type="hidden" name="customer_id" id="hiddenCustomerId">
        @if (request('ticket_id'))<input type="hidden" name="ticket_id" value="{{ (int) request('ticket_id') }}">@endif
        <input type="hidden" name="sale_master_id" id="hiddenSaleMasterId">
        <input type="hidden" name="sale_detail_id" id="hiddenSaleDetailId">
        <input type="hidden" name="product_id" id="hiddenProductId">
        <input type="hidden" name="warranty_status" id="hiddenWarrantyStatus" value="under_product_warranty">
        <input type="hidden" name="parent_repair_job_id" id="hiddenParentJobId">
        <input type="hidden" name="warranty_start_date" id="hiddenWarrantyStartDate">
        <input type="hidden" name="warranty_end_date" id="hiddenWarrantyEndDate">
        <input type="hidden" name="print_after_save" id="hiddenPrintAfterSave" value="1">

        {{-- ==================== STEP 1: DEVICE & WARRANTY ==================== --}}
        <div class="card wizard-pane active crm-animate-in" id="pane1">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-barcode text-primary mr-2"></i>Step 1: Scan or Identify Product</h5>
            </div>
            <div class="card-body p-4">
                
                <div class="form-group mb-4">
                    <label class="font-weight-bold" style="font-size: 14px;">Scan or Enter Serial Number / Barcode <span class="text-danger">*</span></label>
                    <div class="input-group input-group-lg">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-primary text-white"><i class="fas fa-barcode fa-lg"></i></span>
                        </div>
                        <input type="text" name="serial_no" id="serialInput" class="form-control font-weight-bold"
                               placeholder="Scan serial with barcode gun or type serial (e.g. SN-88219)..."
                               value="{{ $prefillSerial ?? old('serial_no') }}" autofocus autocomplete="off" required>
                        <div class="input-group-append">
                            <button type="button" class="btn btn-primary px-4 font-weight-bold" id="btnVerifyWarranty">
                                <i class="fas fa-shield-alt mr-1"></i>Verify Warranty
                            </button>
                        </div>
                    </div>
                    <small class="form-text text-muted mt-2">
                        <i class="fas fa-lightbulb text-warning mr-1"></i>Scanning a barcode auto-verifies whether the unit is under <b>Product Warranty (Supplier-Backed)</b>, <b>Service Warranty</b>, or <b>Out of Warranty</b>.
                    </small>
                </div>

                {{-- Alternative: Search by Invoice or Customer --}}
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <a href="javascript:void(0);" id="toggleSearchPurchase" class="text-primary small font-weight-bold">
                        <i class="fas fa-search mr-1"></i>Search by Sales Invoice # or Patient Phone
                    </a>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" name="is_third_party" class="custom-control-input" id="isThirdPartyCheck" value="1">
                        <label class="custom-control-label small font-weight-bold text-dark" for="isThirdPartyCheck">
                            Third-Party Device (Not purchased from our store)
                        </label>
                    </div>
                </div>

                {{-- Hidden Search Box for Invoice/Customer --}}
                <div id="searchPurchaseSection" class="p-3 bg-light border rounded mb-3" style="display: none;">
                    <label class="font-weight-bold small">Search Customer Sales History:</label>
                    <div class="input-group mb-2">
                        <input type="text" id="purchaseSearchInput" class="form-control form-control-sm" placeholder="Type invoice number, patient phone (e.g. 017...) or name...">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-secondary btn-sm" id="btnSearchPurchase">Search Sales</button>
                        </div>
                    </div>
                    <div id="purchaseSearchResults" class="list-group small" style="max-height: 200px; overflow-y: auto;"></div>
                </div>

                {{-- Third Party Input Box (shown if checked) --}}
                <div id="thirdPartySection" class="p-3 bg-light border rounded mb-3" style="display: none;">
                    <h6 class="font-weight-bold text-dark mb-2"><i class="fas fa-clinic-medical mr-1 text-secondary"></i>Third-Party Walk-in Details</h6>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label class="small font-weight-bold">Brand Name</label>
                            <input type="text" name="custom_brand" id="customBrand" class="form-control form-control-sm" placeholder="e.g. Phonak, Oticon, Widex, Bernafon...">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="small font-weight-bold">Model Name / Number</label>
                            <input type="text" name="custom_model" id="customModel" class="form-control form-control-sm" placeholder="e.g. Marvel M50, Real 1...">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="small font-weight-bold">Customer Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="third_party_customer_name" id="thirdPartyName" class="form-control form-control-sm" placeholder="Patient's Name">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="small font-weight-bold">Customer Phone <span class="text-danger">*</span></label>
                            <input type="text" name="third_party_phone" id="thirdPartyPhone" class="form-control form-control-sm" placeholder="e.g. 01711...">
                        </div>
                    </div>
                </div>

                {{-- Dynamic Warranty Verification Result Box --}}
                <div id="warrantyResultCard" style="display: none;">
                    {{-- Dynamically populated via JS --}}
                </div>

                {{-- Physical Inspection Checkbox (Void Guard) --}}
                <div class="p-3 bg-light border rounded mt-3" id="physicalInspectionSection">
                    <label class="font-weight-bold text-dark mb-2">Physical Condition &amp; Void Check:</label>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="condition_check" id="condIntact" value="intact" checked>
                        <label class="form-check-label font-weight-bold text-success" for="condIntact">
                            <i class="fas fa-check-circle mr-1"></i>External Shell Intact (Provisional - Final Warranty Subject to Internal Lab Inspection)
                        </label>
                        <div class="small text-muted pl-4">
                            Outer casing appears normal without evident cracks. Internal examination (sweat, moisture ingress, battery acid, amplifier corrosion) will be evaluated upon lab teardown.
                        </div>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="condition_check" id="condVoid" value="void">
                        <label class="form-check-label font-weight-bold text-danger" for="condVoid">
                            <i class="fas fa-exclamation-triangle mr-1"></i>Evident Damage Detected at Counter (Warranty Void Now)
                        </label>
                        <div class="small text-muted pl-4">
                            Liquid ingress visible in battery drawer, green/blue corrosion visible, crushed casing, or tampered seal noticed immediately at counter.
                        </div>
                    </div>
                    <div id="voidReasonBox" class="mt-2" style="display: none;">
                        <label class="small font-weight-bold text-danger">Void Reason &amp; Visual Proof Note:</label>
                        <textarea name="void_reason" id="voidReason" class="form-control" rows="2" placeholder="Specify: Battery acid leak in chamber / severe housing crack / water immersion..."></textarea>
                    </div>
                </div>

            </div>
            <div class="card-footer bg-white border-top py-3 text-right">
                <button type="button" class="btn btn-primary px-4 font-weight-bold" id="btnNext1">
                    Next: Symptoms &amp; Accessories <i class="fas fa-arrow-right ml-1"></i>
                </button>
            </div>
        </div>

        {{-- ==================== STEP 2: SYMPTOMS & ACCESSORIES ==================== --}}
        <div class="card wizard-pane crm-animate-in" id="pane2">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-stethoscope text-primary mr-2"></i>Step 2: Symptoms &amp; Accessories Checklist</h5>
            </div>
            <div class="card-body p-4">

                <div class="row mb-4">
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">Ear Side <span class="text-danger">*</span></label>
                        <select name="side" class="form-control">
                            <option value="left">Left Ear (L)</option>
                            <option value="right">Right Ear (R)</option>
                            <option value="both">Both Ears (Pair / Binaural)</option>
                            <option value="na">Not Applicable / Body Worn</option>
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">Job Type</label>
                        <select name="job_type" class="form-control">
                            <option value="repair">Electronic Repair / Servicing</option>
                            <option value="replacement">Warranty Unit Replacement</option>
                            <option value="earmold_modification">Ear Mold Modification / Recast</option>
                            <option value="servicing_only">Acoustic Servicing / Cleaning</option>
                        </select>
                    </div>
                </div>

                {{-- Common Symptoms Multi-Select --}}
                <label class="font-weight-bold mb-2">Reported Problem / Symptoms (Click all that apply):</label>
                <div class="row mb-3">
                    @php
                        $commonSymptoms = [
                            'No Sound / Dead', 'Whistling / Feedback Noise', 'Distorted / Muffled Sound',
                            'Low Volume / Weak', 'Intermittent Working', 'Battery / Charging Failure',
                            'Physical Shell Crack', 'Sound Tube Broken / Loose', 'Wax Filter Blockage'
                        ];
                    @endphp
                    @foreach($commonSymptoms as $symptom)
                        <div class="col-md-4 mb-2">
                            <div class="symptom-tag-card" data-val="{{ $symptom }}">
                                <i class="far fa-square mr-2 tag-icon"></i>{{ $symptom }}
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="form-group mb-4">
                    <label class="font-weight-bold">Additional Problem Notes / Patient Complaint:</label>
                    <textarea name="reported_issues" id="reportedIssues" class="form-control" rows="3" placeholder="Describe the specific complaint (e.g. whistling when chewing, died after sweating in rain)..."></textarea>
                </div>

                {{-- Accessories Kept by Clinic --}}
                <label class="font-weight-bold mb-2">Accessories Received &amp; Kept at Clinic:</label>
                <div class="row mb-3">
                    @php
                        $accessories = [
                            'Hearing Aid Unit Only', 'Charging Case / Dock', 'Power Cable / Adapter',
                            'Custom Ear Mold', 'Silicon Dome / Tip', 'Wax Guard Box', 'Original Carry Case'
                        ];
                    @endphp
                    @foreach($accessories as $acc)
                        <div class="col-md-4 mb-2">
                            <div class="custom-control custom-checkbox p-2 bg-light border rounded">
                                <input type="checkbox" name="accessories_included[]" value="{{ $acc }}" class="custom-control-input" id="acc_{{ Str::slug($acc) }}">
                                <label class="custom-control-label small font-weight-bold" for="acc_{{ Str::slug($acc) }}">{{ $acc }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="form-group">
                    <label class="font-weight-bold">Physical Condition Observed at Counter:</label>
                    <textarea name="physical_condition" class="form-control" rows="2" placeholder="e.g. Minor surface scratches, ear hook intact, battery door tight..."></textarea>
                </div>

            </div>
            <div class="card-footer bg-white border-top py-3 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary px-4 font-weight-bold" id="btnBack2">
                    <i class="fas fa-arrow-left mr-1"></i>Back
                </button>
                <button type="button" class="btn btn-primary px-4 font-weight-bold" id="btnNext2">
                    Next: Lab &amp; Billing <i class="fas fa-arrow-right ml-1"></i>
                </button>
            </div>
        </div>

        {{-- ==================== STEP 3: LAB, SERVICE & BILLING ==================== --}}
        <div class="card wizard-pane crm-animate-in" id="pane3">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-flask text-primary mr-2"></i>Step 3: Lab Assignment, Delivery Date &amp; Financials</h5>
            </div>
            <div class="card-body p-4">

                <div class="row mb-4">
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">Target Lab / Supplier</label>
                        <select name="supplier_id" id="supplierSelect" class="form-control">
                            <option value="">In-House Clinic Lab (No external dispatch)</option>
                            @foreach($suppliers as $sup)
                                <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Select external lab (e.g. Signia Lab, Phonak Lab, Earmold Lab) if unit is being dispatched.</small>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">Lab Challan / Reference # (Optional)</label>
                        <input type="text" name="lab_ref_no" class="form-control" placeholder="Challan / Courier Tracking #">
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">Promised Delivery Date &amp; Time <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="promised_date" id="promisedDateInput" class="form-control"
                               value="{{ Carbon\Carbon::now()->addDays(5)->format('Y-m-d\T16:00') }}" required>
                        <div class="btn-group btn-group-sm mt-2" role="group">
                            <button type="button" class="btn btn-outline-secondary" onclick="setPresetDate(3)">+3 Days</button>
                            <button type="button" class="btn btn-outline-secondary" onclick="setPresetDate(5)">+5 Days</button>
                            <button type="button" class="btn btn-outline-secondary" onclick="setPresetDate(7)">+7 Days</button>
                            <button type="button" class="btn btn-outline-secondary" onclick="setPresetDate(10)">+10 Days</button>
                        </div>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="font-weight-bold">Post-Warranty Service Guarantee</label>
                        <input type="hidden" name="repair_warranty_months" id="hiddenWarrantyMonths" value="0">
                        <select id="repairWarrantyMonths" class="form-control" onchange="document.getElementById('hiddenWarrantyMonths').value = this.value;">
                            <option value="retain_original" id="optRetainOriginal" selected>Retain Original Manufacturer Warranty (Active)</option>
                            <option value="0">No Extra Service Guarantee</option>
                            <option value="1">1 Month Service Guarantee</option>
                            <option value="3">3 Months Service Guarantee (Standard for Paid Repairs)</option>
                            <option value="6">6 Months Service Guarantee</option>
                            <option value="12">12 Months Extended Service Guarantee</option>
                        </select>
                        <small class="text-muted" id="warrantyGuaranteeHelp">Units under manufacturer warranty automatically retain their original remaining warranty.</small>
                    </div>
                </div>

                {{-- Financial & Billing Details --}}
                <div class="card p-3 bg-light border mb-4">
                    <h6 class="font-weight-bold text-dark mb-3"><i class="fas fa-file-invoice-dollar mr-2 text-primary"></i>Financial &amp; Pricing Setup</h6>
                    <div class="custom-control custom-checkbox mb-3 p-2 bg-white border rounded">
                        <input type="checkbox" name="is_cost_pending_lab" class="custom-control-input" id="costPendingLabCheck" value="1">
                        <label class="custom-control-label font-weight-bold text-primary" for="costPendingLabCheck">
                            <i class="fas fa-flask mr-1"></i>Cost Pending Lab Inspection (Quotation provided after lab disassembly &amp; diagnosis)
                        </label>
                        <div class="small text-muted pl-4">
                            Check this if repair cost or spare parts cannot be determined at front counter. Estimate will remain provisional (৳0.00) until the lab report arrives.
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label class="small font-weight-bold">Branch Inspection Fee (৳):</label>
                            <input type="number" step="0.01" name="inspection_fee" id="inspectionFeeInput" class="form-control" value="0.00">
                            <small class="text-muted">Free at HQ. Set fee if received at branch.</small>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="small font-weight-bold">Estimated Repair Cost (৳):</label>
                            <input type="number" step="0.01" name="estimate_amount" id="estimateAmountInput" class="form-control" value="0.00">
                            <small class="text-muted">Leave 0 if covered under Product Warranty or pending lab.</small>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="small font-weight-bold">Advance Payment Collected (৳):</label>
                            <input type="number" step="0.01" name="advance_paid" id="advancePaidInput" class="form-control" value="0.00">
                            <small class="text-muted">Amount patient pays today at intake.</small>
                        </div>
                    </div>
                    <div class="custom-control custom-checkbox mt-2">
                        <input type="checkbox" name="estimate_approved" class="custom-control-input" id="estimateApprovedCheck" value="1" checked>
                        <label class="custom-control-label small font-weight-bold text-dark" for="estimateApprovedCheck">
                            Patient has approved this estimate and authorized service
                        </label>
                    </div>
                </div>

                {{-- SMS Notification Option --}}
                <div class="custom-control custom-checkbox p-2 border rounded bg-white mb-2">
                    <input type="checkbox" name="send_intake_sms" class="custom-control-input" id="sendIntakeSmsCheck" value="1" checked>
                    <label class="custom-control-label font-weight-bold text-dark" for="sendIntakeSmsCheck">
                        <i class="fas fa-sms text-success mr-1"></i>Send Instant Intake SMS Confirmation to Patient
                    </label>
                    <div class="small text-muted pl-4">
                        Sends Job Card #, Serial Number, and Promised Date via SMS gateway.
                    </div>
                </div>

            </div>
            <div class="card-footer bg-white border-top py-3 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary px-4 font-weight-bold" id="btnBack3">
                    <i class="fas fa-arrow-left mr-1"></i>Back
                </button>
                <button type="button" class="btn btn-primary px-4 font-weight-bold" id="btnNext3">
                    Next: Review &amp; Confirm <i class="fas fa-arrow-right ml-1"></i>
                </button>
            </div>
        </div>

        {{-- ==================== STEP 4: CONFIRMATION & PRINT ==================== --}}
        <div class="card wizard-pane crm-animate-in" id="pane4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-check-circle text-success mr-2"></i>Step 4: Review Summary &amp; Print Options</h5>
            </div>
            <div class="card-body p-4">

                <div class="alert alert-info border-0 shadow-sm mb-4">
                    <h6 class="font-weight-bold mb-1"><i class="fas fa-info-circle mr-1"></i>Intake Ready to Register</h6>
                    <p class="mb-0 small">Please review the details below. Once confirmed, the system will allocate a Job Card number, link warranty history, and generate the printable Job Card with barcode.</p>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="p-3 bg-light border rounded h-100">
                            <h6 class="font-weight-bold text-muted small text-uppercase mb-2">Patient &amp; Device</h6>
                            <div class="mb-1">Patient: <b id="revPatient">--</b></div>
                            <div class="mb-1">Phone: <b id="revPhone">--</b></div>
                            <div class="mb-1">Device: <b id="revDevice">--</b></div>
                            <div class="mb-1">Serial #: <span class="badge badge-light border font-monospace" id="revSerial">--</span></div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="p-3 bg-light border rounded h-100">
                            <h6 class="font-weight-bold text-muted small text-uppercase mb-2">Warranty &amp; Financials</h6>
                            <div class="mb-1">Warranty: <b id="revWarranty">--</b></div>
                            <div class="mb-1">Promised Date: <b id="revPromised">--</b></div>
                            <div class="mb-1">Total Bill: <b id="revTotal">৳0.00</b></div>
                            <div class="mb-1">Advance Paid: <b id="revAdvance" class="text-success">৳0.00</b></div>
                        </div>
                    </div>
                </div>

                <div class="form-group mt-3">
                    <label class="font-weight-bold">Internal Reception Remarks (Optional):</label>
                    <textarea name="remarks" class="form-control" rows="2" placeholder="Any internal instructions for lab or counter staff..."></textarea>
                </div>

            </div>
            <div class="card-footer bg-white border-top py-3 d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary px-4 font-weight-bold" id="btnBack4">
                    <i class="fas fa-arrow-left mr-1"></i>Back
                </button>
                <div>
                    <button type="submit" class="btn btn-outline-primary px-3 mr-2 font-weight-bold" onclick="document.getElementById('hiddenPrintAfterSave').value='0';">
                        <i class="fas fa-save mr-1"></i>Save Only
                    </button>
                    <button type="submit" class="btn btn-success px-4 font-weight-bold" onclick="document.getElementById('hiddenPrintAfterSave').value='1';">
                        <i class="fas fa-print mr-1"></i>Save &amp; Print Job Card
                    </button>
                </div>
            </div>
        </div>

    </form>

</div>
@endsection

@push('js')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        let currentStep = 1;
        const totalSteps = 4;

        // Stepper Navigation
        function showStep(step) {
            currentStep = step;
            for (let i = 1; i <= totalSteps; i++) {
                const pane = document.getElementById('pane' + i);
                const indicator = document.getElementById('stepIndicator' + i);
                if (pane && indicator) {
                    if (i === step) {
                        pane.classList.add('active');
                        indicator.classList.add('active');
                    } else {
                        pane.classList.remove('active');
                        indicator.classList.remove('active');
                        if (i < step) {
                            indicator.classList.add('completed');
                        } else {
                            indicator.classList.remove('completed');
                        }
                    }
                }
            }
            const activePane = document.getElementById('pane' + step);
            if (activePane) {
                const offset = 80;
                const bodyRect = document.body.getBoundingClientRect().top;
                const elementRect = activePane.getBoundingClientRect().top;
                const elementPosition = elementRect - bodyRect;
                const offsetPosition = elementPosition - offset;

                window.scrollTo({
                    top: Math.max(0, offsetPosition),
                    behavior: 'smooth'
                });
            }
        }

        document.getElementById('btnNext1').addEventListener('click', function () {
            const serial = document.getElementById('serialInput').value.trim();
            if (!serial) {
                alert('Please enter or scan a serial number.');
                document.getElementById('serialInput').focus();
                return;
            }
            if (window.isSerialBlocked) {
                alert('Cannot proceed: This serial number was replaced or decommissioned and is permanently retained by supplier. Please switch to the active unit.');
                return;
            }
            showStep(2);
        });

        document.getElementById('btnBack2').addEventListener('click', () => showStep(1));
        document.getElementById('btnNext2').addEventListener('click', () => showStep(3));
        document.getElementById('btnBack3').addEventListener('click', () => showStep(2));
        document.getElementById('btnNext3').addEventListener('click', function () {
            populateReviewSummary();
            showStep(4);
        });
        document.getElementById('btnBack4').addEventListener('click', () => showStep(3));

        // Symptom Tags multi-select
        const reportedTextarea = document.getElementById('reportedIssues');
        document.querySelectorAll('.symptom-tag-card').forEach(card => {
            card.addEventListener('click', function () {
                this.classList.toggle('selected');
                const icon = this.querySelector('.tag-icon');
                if (this.classList.contains('selected')) {
                    icon.classList.remove('fa-square');
                    icon.classList.add('fa-check-square');
                } else {
                    icon.classList.remove('fa-check-square');
                    icon.classList.add('fa-square');
                }

                // Collect all selected tags into textarea
                const selected = [];
                document.querySelectorAll('.symptom-tag-card.selected').forEach(c => {
                    selected.push(c.getAttribute('data-val'));
                });
                reportedTextarea.value = selected.join(', ');
            });
        });

        // Void Condition Radio Toggle
        document.getElementById('condVoid').addEventListener('change', function () {
            document.getElementById('voidReasonBox').style.display = 'block';
            document.getElementById('hiddenWarrantyStatus').value = 'void';
        });
        document.getElementById('condIntact').addEventListener('change', function () {
            document.getElementById('voidReasonBox').style.display = 'none';
        });

        // Cost Pending Lab Toggle
        const costPendingCheck = document.getElementById('costPendingLabCheck');
        const estimateInput = document.getElementById('estimateAmountInput');
        const estimateApprovedCheck = document.getElementById('estimateApprovedCheck');
        if (costPendingCheck && estimateInput) {
            costPendingCheck.addEventListener('change', function () {
                if (this.checked) {
                    estimateInput.value = '0.00';
                    estimateInput.setAttribute('readonly', 'readonly');
                    estimateInput.classList.add('bg-light');
                    if (estimateApprovedCheck) {
                        estimateApprovedCheck.checked = false;
                    }
                } else {
                    estimateInput.removeAttribute('readonly');
                    estimateInput.classList.remove('bg-light');
                    if (estimateApprovedCheck) {
                        estimateApprovedCheck.checked = true;
                    }
                }
            });
        }

        // Third Party Toggle
        document.getElementById('isThirdPartyCheck').addEventListener('change', function () {
            const sec = document.getElementById('thirdPartySection');
            if (this.checked) {
                sec.style.display = 'block';
                document.getElementById('hiddenWarrantyStatus').value = 'third_party';
                document.getElementById('warrantyResultCard').style.display = 'none';
            } else {
                sec.style.display = 'none';
            }
        });

        // Search Purchase Toggle
        document.getElementById('toggleSearchPurchase').addEventListener('click', function () {
            const sec = document.getElementById('searchPurchaseSection');
            sec.style.display = (sec.style.display === 'none') ? 'block' : 'none';
        });

        // Search Purchase AJAX
        document.getElementById('btnSearchPurchase').addEventListener('click', function () {
            const q = document.getElementById('purchaseSearchInput').value.trim();
            if (!q) return;

            const resList = document.getElementById('purchaseSearchResults');
            resList.innerHTML = '<div class="p-2 text-muted text-center"><i class="fas fa-spinner fa-spin mr-1"></i>Searching...</div>';

            fetch(`{{ route('repairs.search-purchases') }}?query=${encodeURIComponent(q)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.length === 0) {
                        resList.innerHTML = '<div class="p-2 text-muted text-center">No purchases found.</div>';
                        return;
                    }
                    let html = '';
                    data.forEach(item => {
                        let badgeHtml = '';
                        if (item.is_replaced) {
                            badgeHtml = `<span class="badge badge-warning text-dark"><i class="fas fa-exchange-alt mr-1"></i>Replaced by S/N: ${item.replaced_by_serial}</span>`;
                        } else {
                            badgeHtml = `<span class="badge ${item.is_warranty_valid ? 'badge-success' : 'badge-secondary'}">${item.warranty_until}</span>`;
                        }

                        html += `
                            <a href="javascript:void(0);" class="list-group-item list-group-item-action py-2 select-purchase-item"
                               data-serial="${item.active_serial || item.serial_no}" data-sale="${item.sale_id}" data-product="${item.product_id}" data-customer="${item.customer_id}">
                                <div class="d-flex justify-content-between align-items-center">
                                    <b>${item.product_name}</b>
                                    ${badgeHtml}
                                </div>
                                <div class="text-muted font-monospace small">
                                    S/N: ${item.serial_no || 'No Serial'}
                                    ${item.is_replaced ? `<span class="text-success font-weight-bold ml-1">&rarr; Active: ${item.active_serial}</span>` : ''}
                                    | Inv: #${item.sale_invoice_no} | Pt: ${item.customer_name} (${item.customer_phone})
                                </div>
                            </a>
                        `;
                    });
                    resList.innerHTML = html;

                    document.querySelectorAll('.select-purchase-item').forEach(el => {
                        el.addEventListener('click', function () {
                            const sn = this.getAttribute('data-serial');
                            if (sn) {
                                document.getElementById('serialInput').value = sn;
                                document.getElementById('btnVerifyWarranty').click();
                                document.getElementById('searchPurchaseSection').style.display = 'none';
                            }
                        });
                    });
                })
                .catch(() => {
                    resList.innerHTML = '<div class="p-2 text-danger text-center">Error fetching sales.</div>';
                });
        });

        // Verify Warranty AJAX
        document.getElementById('btnVerifyWarranty').addEventListener('click', function () {
            const serial = document.getElementById('serialInput').value.trim();
            if (!serial) return;

            const card = document.getElementById('warrantyResultCard');
            card.style.display = 'block';
            card.className = 'warranty-card';
            card.innerHTML = '<div class="text-center p-3"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><div class="mt-2 text-muted">Checking sales and warranty records...</div></div>';

            fetch(`{{ route('repairs.verify-warranty') }}?serial=${encodeURIComponent(serial)}`)
                .then(res => res.json())
                .then(res => {
                    if (!res.found) {
                        window.isSerialBlocked = false;
                        document.getElementById('btnNext1').removeAttribute('disabled');
                        card.className = 'warranty-card out';
                        card.innerHTML = `
                            <div class="d-flex align-items-center">
                                <i class="fas fa-question-circle fa-2x text-secondary mr-3"></i>
                                <div>
                                    <h6 class="font-weight-bold mb-1 text-dark">Serial Not Found in Sales Records</h6>
                                    <p class="mb-0 small text-muted">${res.message} You can proceed as a Third-Party or walk-in device.</p>
                                </div>
                            </div>
                        `;
                        document.getElementById('hiddenWarrantyStatus').value = 'out_of_warranty';
                        return;
                    }

                    // CHECK IF BLOCKED FOR REPAIR INTAKE
                    if (res.allow_repair === false) {
                        window.isSerialBlocked = true;
                        document.getElementById('btnNext1').setAttribute('disabled', 'disabled');

                        if (res.block_reason === 'open_job') {
                            // AMBER CARD FOR OPEN JOB
                            const jobUrl = res.details?.job_url || `{{ url('admin/repairs') }}/${res.details?.job_id || ''}`;
                            const jobNo = res.details?.job_no || 'Job';
                            const stageName = res.details?.stage_name || 'In Progress';

                            card.className = 'warranty-card border-warning';
                            card.style.borderLeft = '6px solid #f59e0b';
                            card.style.background = '#fffbeb';
                            card.innerHTML = `
                                <div class="d-flex align-items-start">
                                    <i class="fas fa-exclamation-triangle fa-2x text-warning mr-3 mt-1"></i>
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <h6 class="font-weight-bold text-dark mb-0" style="font-size: 16px;">
                                                DEVICE ALREADY IN REPAIR PIPELINE
                                            </h6>
                                            <span class="badge badge-warning px-2 py-1 font-weight-bold text-dark text-uppercase">${stageName}</span>
                                        </div>
                                        <p class="mb-2 text-dark font-weight-bold" style="font-size: 13.5px; line-height: 1.5;">
                                            ${res.message || `This device is already in repair job #${jobNo} (stage: ${stageName}).`}
                                        </p>
                                        <div class="p-3 bg-white border border-warning rounded d-flex align-items-center justify-content-between mt-2">
                                            <div>
                                                <div>Scanned S/N: <b class="font-monospace text-dark">${res.serial_no}</b></div>
                                                <div class="small text-muted">A second repair ticket cannot be created while the existing ticket is open.</div>
                                            </div>
                                            <a href="${jobUrl}" target="_blank" class="btn btn-warning btn-sm font-weight-bold px-3 py-2 text-dark shadow-sm">
                                                <i class="fas fa-external-link-alt mr-1"></i>View Repair Job #${jobNo}
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            `;
                            return;
                        }

                        if (res.block_reason === 'replaced') {
                            // RED CARD FOR OLD REPLACED SERIAL
                            let switchBtnHtml = '';
                            if (res.replaced_by_serial) {
                                switchBtnHtml = `
                                    <div class="mt-3 pt-2 border-top">
                                        <div class="text-dark font-weight-bold mb-1">Active Device for this Patient:</div>
                                        <button type="button" class="btn btn-sm btn-success font-weight-bold px-3 py-2 shadow-sm" id="btnSwitchToActiveSerial" data-sn="${res.replaced_by_serial}">
                                            <i class="fas fa-sync-alt mr-1"></i>Switch to Active Unit (S/N: ${res.replaced_by_serial}) &amp; Verify
                                        </button>
                                    </div>
                                `;
                            }

                            card.className = 'warranty-card alert-danger border-danger';
                            card.style.borderLeft = '6px solid #dc3545';
                            card.style.background = '#fff5f5';
                            card.innerHTML = `
                                <div class="d-flex align-items-start">
                                    <i class="fas fa-ban fa-2x text-danger mr-3 mt-1"></i>
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <h6 class="font-weight-bold text-danger mb-0" style="font-size: 16px;">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>CANNOT ISSUE REPAIR: OLD REPLACED SERIAL
                                            </h6>
                                            <span class="badge badge-danger px-2 py-1 text-uppercase">${res.status ? res.status.replace(/_/g, ' ') : 'Retained by Supplier'}</span>
                                        </div>
                                        <p class="mb-2 text-dark font-weight-bold" style="font-size: 13.5px; line-height: 1.5;">
                                            ${res.message}
                                        </p>
                                        <div class="row small text-muted bg-white p-2 border rounded">
                                            <div class="col-md-6">
                                                <div>Scanned S/N: <b class="text-danger font-monospace">${res.serial_no}</b></div>
                                                <div>Status: <b class="text-danger">${res.status_label || 'Retained by Supplier'}</b></div>
                                                ${res.supplier_name ? `<div>Supplier: <b>${res.supplier_name}</b></div>` : ''}
                                            </div>
                                            <div class="col-md-6">
                                                ${res.replaced_by_serial ? `<div>Active Replacement S/N: <b class="text-success font-monospace">${res.replaced_by_serial}</b></div>` : ''}
                                                ${res.replacement_challan_no ? `<div>Supplier Challan: <b>#${res.replacement_challan_no}</b></div>` : ''}
                                                ${res.replacement_date ? `<div>Replacement Date: <b>${res.replacement_date}</b></div>` : ''}
                                            </div>
                                        </div>
                                        ${switchBtnHtml}
                                    </div>
                                </div>
                            `;

                            const switchBtn = document.getElementById('btnSwitchToActiveSerial');
                            if (switchBtn) {
                                switchBtn.addEventListener('click', function () {
                                    const activeSn = this.getAttribute('data-sn');
                                    document.getElementById('serialInput').value = activeSn;
                                    document.getElementById('btnVerifyWarranty').click();
                                });
                            }
                            return;
                        }

                        // DECOMMISSIONED CARD
                        card.className = 'warranty-card alert-danger border-danger';
                        card.style.borderLeft = '6px solid #dc3545';
                        card.style.background = '#fff5f5';
                        card.innerHTML = `
                            <div class="d-flex align-items-start">
                                <i class="fas fa-ban fa-2x text-danger mr-3 mt-1"></i>
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <h6 class="font-weight-bold text-danger mb-0" style="font-size: 16px;">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>CANNOT ISSUE REPAIR: UNIT DECOMMISSIONED
                                        </h6>
                                        <span class="badge badge-danger px-2 py-1 text-uppercase">${res.status_label || 'Decommissioned'}</span>
                                    </div>
                                    <p class="mb-2 text-dark font-weight-bold" style="font-size: 13.5px; line-height: 1.5;">
                                        ${res.message || 'This device is decommissioned and cannot be accepted for repair.'}
                                    </p>
                                    <div class="p-2 bg-white border rounded small text-muted">
                                        <div>Scanned S/N: <b class="text-danger font-monospace">${res.serial_no}</b></div>
                                        <div>Status: <b class="text-danger">${res.status_label || 'Decommissioned'}</b></div>
                                    </div>
                                </div>
                            </div>
                        `;
                        return;
                    }


                    // Device is valid and active
                    window.isSerialBlocked = false;
                    document.getElementById('btnNext1').removeAttribute('disabled');

                    // Save IDs into hidden form inputs
                    if (res.customer) document.getElementById('hiddenCustomerId').value = res.customer.id;
                    if (res.sale_master_id) document.getElementById('hiddenSaleMasterId').value = res.sale_master_id;
                    if (res.sale_detail_id) document.getElementById('hiddenSaleDetailId').value = res.sale_detail_id;
                    if (res.product) document.getElementById('hiddenProductId').value = res.product.id;
                    if (res.parent_service_job_id) document.getElementById('hiddenParentJobId').value = res.parent_service_job_id;
                    if (res.product_warranty_until_raw) document.getElementById('hiddenWarrantyEndDate').value = res.product_warranty_until_raw;

                    const warrantySelect = document.getElementById('repairWarrantyMonths');
                    const optRetain = document.getElementById('optRetainOriginal');
                    const warrantyHelp = document.getElementById('warrantyGuaranteeHelp');
                    const hiddenMonths = document.getElementById('hiddenWarrantyMonths');

                    if (res.active_tier === 'product_warranty') {
                        // TIER 1: Product Warranty (Supplier-Backed, ৳0)
                        card.className = 'warranty-card tier-1';
                        document.getElementById('hiddenWarrantyStatus').value = 'under_product_warranty';
                        document.getElementById('estimateAmountInput').value = '0.00';
                        document.getElementById('inspectionFeeInput').value = '0.00';

                        if (warrantySelect && optRetain) {
                            optRetain.text = `Retain Original Manufacturer Warranty (Active until ${res.product_warranty_until})`;
                            optRetain.style.display = 'block';
                            warrantySelect.value = 'retain_original';
                            warrantySelect.setAttribute('disabled', 'disabled');
                            if (hiddenMonths) hiddenMonths.value = '0';
                            if (warrantyHelp) warrantyHelp.innerHTML = `<span class="text-success font-weight-bold"><i class="fas fa-check-circle mr-1"></i>Device is under active warranty until ${res.product_warranty_until}. Retaining original warranty period.</span>`;
                        }

                        card.innerHTML = `
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="font-weight-bold" style="font-size: 15px;">
                                    <i class="fas fa-shield-alt mr-2 text-success"></i>[✓ ACTIVE] UNDER PRODUCT WARRANTY
                                </div>
                                <span class="badge badge-success px-3 py-1 font-weight-bold">SUPPLIER BACKED: ৳0 COST</span>
                            </div>
                            <div class="row small mt-2">
                                <div class="col-md-6">
                                    <div>Product: <b>${res.product ? res.product.name : 'Hearing Aid'}</b></div>
                                    <div>Patient: <b>${res.customer ? res.customer.name : 'Unknown'}</b> (${res.customer ? res.customer.phone : ''})</div>
                                    <div>Invoice: <b>#${res.sale_invoice_no}</b> (Sold: ${res.sale_date})</div>
                                </div>
                                <div class="col-md-6">
                                    <div>Warranty Period: <b>${res.product_warranty_months} Months</b></div>
                                    <div>Valid Until: <b class="text-success">${res.product_warranty_until}</b> (${res.product_warranty_days_left} Days Left)</div>
                                    <div class="text-muted mt-1">Free repair/parts covered 100% under manufacturer warranty.</div>
                                </div>
                            </div>
                        `;
                    } else if (res.active_tier === 'service_warranty') {
                        // TIER 2: Service Warranty (Post-Warranty Guarantee)
                        card.className = 'warranty-card tier-2';
                        document.getElementById('hiddenWarrantyStatus').value = 'under_service_warranty';
                        document.getElementById('estimateAmountInput').value = '0.00';

                        if (warrantySelect && optRetain) {
                            optRetain.text = `Retain Service Warranty (Active until ${res.service_warranty_until})`;
                            optRetain.style.display = 'block';
                            warrantySelect.value = 'retain_original';
                            warrantySelect.setAttribute('disabled', 'disabled');
                            if (hiddenMonths) hiddenMonths.value = '0';
                            if (warrantyHelp) warrantyHelp.innerHTML = `<span class="text-info font-weight-bold"><i class="fas fa-check-circle mr-1"></i>Device has active service guarantee until ${res.service_warranty_until}. Retained automatically.</span>`;
                        }

                        card.innerHTML = `
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="font-weight-bold" style="font-size: 15px;">
                                    <i class="fas fa-tools mr-2 text-info"></i>[✓ ACTIVE] UNDER POST-WARRANTY SERVICE GUARANTEE
                                </div>
                                <span class="badge badge-info px-3 py-1 font-weight-bold">REPEAT SERVICE: ৳0</span>
                            </div>
                            <div class="row small mt-2">
                                <div class="col-md-6">
                                    <div>Product: <b>${res.product ? res.product.name : 'Hearing Aid'}</b></div>
                                    <div>Patient: <b>${res.customer ? res.customer.name : 'Unknown'}</b> (${res.customer ? res.customer.phone : ''})</div>
                                    <div>Prior Job Card: <b>#${res.parent_service_job_no}</b></div>
                                </div>
                                <div class="col-md-6">
                                    <div>Service Guarantee Expiry: <b class="text-primary">${res.service_warranty_until}</b></div>
                                    <div>Days Remaining: <b>${res.service_warranty_days_left} Days</b></div>
                                    <div class="text-muted mt-1">Covered free of charge under previous repair service guarantee.</div>
                                </div>
                            </div>
                        `;
                    } else {
                        // Out of Warranty
                        card.className = 'warranty-card out';
                        document.getElementById('hiddenWarrantyStatus').value = 'out_of_warranty';

                        if (warrantySelect) {
                            if (optRetain) optRetain.style.display = 'none';
                            warrantySelect.removeAttribute('disabled');
                            warrantySelect.value = '3';
                            if (hiddenMonths) hiddenMonths.value = '3';
                            if (warrantyHelp) warrantyHelp.innerHTML = `Standard 3-Month Service Guarantee will be granted on this paid repair.`;
                        }

                        card.innerHTML = `
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="font-weight-bold text-dark" style="font-size: 15px;">
                                    <i class="fas fa-clock mr-2 text-secondary"></i>OUT OF WARRANTY (CHARGEABLE REPAIR)
                                </div>
                                <span class="badge badge-secondary px-3 py-1 font-weight-bold">PAID SERVICE</span>
                            </div>
                            <div class="row small mt-2">
                                <div class="col-md-6">
                                    <div>Product: <b>${res.product ? res.product.name : 'Hearing Aid'}</b></div>
                                    <div>Patient: <b>${res.customer ? res.customer.name : 'Unknown'}</b> (${res.customer ? res.customer.phone : ''})</div>
                                </div>
                                <div class="col-md-6">
                                    <div>Original Warranty Expired: <b>${res.product_warranty_until || 'Expired'}</b></div>
                                    <div class="text-muted mt-1">Provide repair estimate. Standard Service Guarantee will be granted upon completion.</div>
                                </div>
                            </div>
                        `;
                    }
                })
                .catch(() => {
                    card.className = 'warranty-card out';
                    card.innerHTML = '<div class="text-danger p-2">Error connecting to warranty service.</div>';
                });
        });

        // Trigger verify immediately if serial prefilled
        if (document.getElementById('serialInput').value.trim() !== '') {
            document.getElementById('btnVerifyWarranty').click();
        }

        // Populate Summary Review on Step 4
        function populateReviewSummary() {
            const serial = document.getElementById('serialInput').value.trim();
            const wStatus = document.getElementById('hiddenWarrantyStatus').value;
            const promised = document.getElementById('promisedDateInput').value;
            const insp = parseFloat(document.getElementById('inspectionFeeInput').value) || 0;
            const est = parseFloat(document.getElementById('estimateAmountInput').value) || 0;
            const adv = parseFloat(document.getElementById('advancePaidInput').value) || 0;

            document.getElementById('revSerial').innerText = serial;
            document.getElementById('revWarranty').innerText = wStatus.replace(/_/g, ' ').toUpperCase();
            document.getElementById('revPromised').innerText = promised.replace('T', ' ');
            document.getElementById('revTotal').innerText = '৳' + (insp + est).toFixed(2);
            document.getElementById('revAdvance').innerText = '৳' + adv.toFixed(2);

            // Patient
            const tpName = document.getElementById('thirdPartyName').value;
            const tpPhone = document.getElementById('thirdPartyPhone').value;
            if (tpName) {
                document.getElementById('revPatient').innerText = tpName;
                document.getElementById('revPhone').innerText = tpPhone;
                document.getElementById('revDevice').innerText = document.getElementById('customBrand').value + ' ' + document.getElementById('customModel').value;
            } else {
                const card = document.getElementById('warrantyResultCard');
                const ptEl = card.querySelector('b');
                document.getElementById('revPatient').innerText = ptEl ? ptEl.innerText : 'Existing Customer';
                document.getElementById('revPhone').innerText = 'Registered Phone';
                document.getElementById('revDevice').innerText = 'Clinic Hearing Aid';
            }
        }
    });

    function setPresetDate(days) {
        const d = new Date();
        d.setDate(d.getDate() + days);
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        document.getElementById('promisedDateInput').value = `${yyyy}-${mm}-${dd}T16:00`;
    }
</script>
@endpush
