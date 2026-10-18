@extends('admin.layouts.master')
@section('page_title', 'Quick Walk-in Counter Service')
@section('breadcrumb_parent', 'Repairs')
@section('breadcrumb_parent_url', url('admin/repairs'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <style>
        .service-select-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 13px 16px;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            background: #ffffff;
            margin-bottom: 10px;
        }
        .service-select-card:hover {
            border-color: #f59e0b;
            background: #fffbeb;
            transform: translateY(-1px);
        }
        .service-select-card.selected {
            border-color: #d97706;
            background: #fef3c7;
            box-shadow: 0 0 0 2px rgba(217, 119, 6, 0.25);
        }
        .service-select-card.selected .svc-icon {
            color: #d97706 !important;
        }
        .custom-service-badge {
            background: #e0f2fe;
            color: #0369a1;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
            display: inline-block;
        }
    </style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px; max-width: 900px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-bolt" style="color: #f59e0b; margin-right: 8px;"></i>Quick Walk-in Counter Service</h1>
            <p>1-step immediate servicing: Earmold buffing, wax filter, sound tube change, and instant handover</p>
        </div>
        <div class="header-actions">
            <a href="{{ route('repairs.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i>Back to Jobs
            </a>
        </div>
    </div>

    <form action="{{ route('repairs.store') }}" method="POST">
        @csrf
        <input type="hidden" name="is_quick_service" value="1">
        <input type="hidden" name="job_type" value="quick_service">
        <input type="hidden" name="customer_id" id="quickCustomerId">
        <input type="hidden" name="product_id" id="quickProductId">
        <input type="hidden" name="sale_master_id" id="quickSaleMasterId">
        <input type="hidden" name="warranty_status" id="quickWarrantyStatus" value="out_of_warranty">
        <input type="hidden" name="print_after_save" value="1">

        {{-- Main Card --}}
        <div class="card crm-animate-in" style="border: 1px solid #e2e8f0; border-radius: 8px;">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 font-weight-bold text-dark"><i class="fas fa-barcode text-warning mr-2"></i>1. Scan Device or Enter Patient</h5>
            </div>
            <div class="card-body p-4">
                
                <div class="form-group mb-4">
                    <label class="font-weight-bold">Scan Hearing Aid Serial Number / Barcode <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" name="serial_no" id="quickSerialInput" class="form-control font-weight-bold"
                               placeholder="Scan serial number (e.g. SN-88219)..." autofocus autocomplete="off" required>
                        <div class="input-group-append">
                            <button type="button" class="btn btn-warning font-weight-bold" id="btnQuickVerify">
                                <i class="fas fa-search mr-1"></i>Lookup
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Dynamic Device Info Box --}}
                <div id="quickInfoBox" class="p-3 bg-light border rounded mb-4" style="display: none;"></div>

                {{-- Walk-in Name if not registered --}}
                <div id="quickThirdPartyRow" class="row mb-4">
                    <div class="col-md-6 form-group">
                        <label class="small font-weight-bold">Patient Name (if not registered)</label>
                        <input type="text" name="third_party_customer_name" class="form-control form-control-sm" placeholder="e.g. Mr. Rafiqul Islam">
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="small font-weight-bold">Patient Phone</label>
                        <input type="text" name="third_party_phone" class="form-control form-control-sm" placeholder="e.g. 01711-223344">
                    </div>
                </div>

                {{-- Quick Service Options --}}
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="font-weight-bold text-dark mb-0">
                        <i class="fas fa-hand-holding-medical mr-2 text-warning"></i>2. Select Counter Services Rendered:
                    </h6>
                    <a href="{{ url('admin/service-type') }}" target="_blank" class="btn btn-sm btn-outline-secondary" style="font-size: 12px; border-radius: 6px;" title="Configure services and rates in Admin Catalog">
                        <i class="fas fa-sliders-h mr-1 text-primary"></i>Manage Service Catalog &amp; Rates
                    </a>
                </div>

                <div id="serviceCardsContainer">
                    @forelse($quickServices as $svc)
                        <div class="service-select-card" data-service="{{ $svc->service_type_name }}" data-cost="{{ (float)$svc->price }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <i class="far fa-square mr-3 svc-icon" style="font-size: 18px; color: #94a3b8;"></i>
                                    <div>
                                        <div class="font-weight-bold text-dark service-name">{{ $svc->service_type_name }}</div>
                                        <small class="text-muted">
                                            @if($svc->isWalkIn())
                                                <span class="badge badge-light border text-muted"><i class="fas fa-walking mr-1"></i>Walk-in Counter Service</span>
                                            @else
                                                <span class="badge badge-light border text-muted">Standard Service</span>
                                            @endif
                                        </small>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="font-weight-bold text-dark" style="font-size: 15px;">৳{{ number_format($svc->price, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="alert alert-light border text-muted small p-3">
                            No active services found in catalog. You can add custom services below or configure them in <a href="{{ url('admin/service-type') }}" target="_blank">Sales &gt; Service Types</a>.
                        </div>
                    @endforelse
                </div>

                {{-- Add Other / Custom Service On-The-Fly --}}
                <div class="card mt-2 mb-4" style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="font-weight-bold small text-dark">
                                <i class="fas fa-plus-circle text-warning mr-1"></i>Add Other / Custom Service On-The-Fly:
                            </span>
                            <small class="text-muted">Enter any service name and custom rate for this patient</small>
                        </div>
                        <div class="form-row align-items-center">
                            <div class="col-md-7 mb-2 mb-md-0">
                                <input type="text" id="customServiceName" class="form-control form-control-sm" placeholder="e.g. Battery Door Replacement, Receiver Wire Solder...">
                            </div>
                            <div class="col-md-3 mb-2 mb-md-0">
                                <div class="input-group input-group-sm">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-white font-weight-bold">৳</span>
                                    </div>
                                    <input type="number" step="0.01" min="0" id="customServiceRate" class="form-control" placeholder="0.00" value="0.00">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-warning btn-sm btn-block font-weight-bold" id="btnAddCustomService">
                                    <i class="fas fa-plus mr-1"></i>Add
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="reported_issues" id="quickReportedIssues" value="Walk-in Quick Counter Service">

                {{-- Billing & Handover --}}
                <div class="p-3 bg-light border rounded mt-4">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <label class="font-weight-bold text-dark mb-1">Total Service Charge (৳):</label>
                            <input type="number" step="0.01" name="quick_service_charge" id="quickTotalCharge" class="form-control form-control-lg font-weight-bold text-primary" value="0.00">
                            <small class="text-muted">Enter 0 if covered free under Product Warranty.</small>
                        </div>
                        <div class="col-md-6 pt-3">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" name="quick_deliver_now" class="custom-control-input" id="quickDeliverNow" value="1" checked>
                                <label class="custom-control-label font-weight-bold text-success" for="quickDeliverNow" style="font-size: 14px;">
                                    <i class="fas fa-check-circle mr-1"></i>Completed &amp; Handed Over Now
                                </label>
                                <div class="small text-muted pl-4">Auto-marks job delivered and updates payment immediately.</div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="card-footer bg-white border-top py-3 text-right">
                <button type="submit" class="btn btn-warning px-4 font-weight-bold" id="btnSubmitQuick">
                    <i class="fas fa-bolt mr-1"></i>Complete Service &amp; Print Slip
                </button>
            </div>
        </div>
    </form>

</div>
@endsection

@push('js')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        let total = 0;
        const totalInput = document.getElementById('quickTotalCharge');
        const notesInput = document.getElementById('quickReportedIssues');
        const btnSubmit = document.getElementById('btnSubmitQuick');

        const container = document.getElementById('serviceCardsContainer');
        const customNameInput = document.getElementById('customServiceName');
        const customRateInput = document.getElementById('customServiceRate');
        const btnAddCustom = document.getElementById('btnAddCustomService');

        function toggleCard(card) {
            card.classList.toggle('selected');
            const icon = card.querySelector('.svc-icon');
            if (icon) {
                if (card.classList.contains('selected')) {
                    icon.classList.remove('fa-square');
                    icon.classList.add('fa-check-square');
                } else {
                    icon.classList.remove('fa-check-square');
                    icon.classList.add('fa-square');
                }
            }
            recalculate();
        }

        // Delegated click for existing and dynamically added service cards
        if (container) {
            container.addEventListener('click', function (e) {
                // If clicked remove button on custom service card
                const removeBtn = e.target.closest('.btn-remove-custom');
                if (removeBtn) {
                    e.stopPropagation();
                    const card = removeBtn.closest('.service-select-card');
                    if (card) {
                        card.remove();
                        recalculate();
                    }
                    return;
                }

                const card = e.target.closest('.service-select-card');
                if (card) {
                    toggleCard(card);
                }
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function addCustomService() {
            const name = customNameInput ? customNameInput.value.trim() : '';
            const rate = customRateInput ? (parseFloat(customRateInput.value) || 0) : 0;

            if (!name) {
                if (customNameInput) customNameInput.focus();
                alert('Please enter a service name.');
                return;
            }

            const card = document.createElement('div');
            card.className = 'service-select-card selected custom-card';
            card.setAttribute('data-service', name);
            card.setAttribute('data-cost', rate.toFixed(2));
            card.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="far fa-check-square mr-3 svc-icon" style="font-size: 18px; color: #d97706;"></i>
                        <div>
                            <div class="font-weight-bold text-dark service-name">${escapeHtml(name)}</div>
                            <span class="custom-service-badge"><i class="fas fa-magic mr-1"></i>Custom Counter Service</span>
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="font-weight-bold text-dark mr-3" style="font-size: 15px;">৳${rate.toFixed(2)}</span>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-custom py-0 px-2" title="Remove custom service">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;

            container.appendChild(card);
            customNameInput.value = '';
            customRateInput.value = '0.00';
            recalculate();
        }

        if (btnAddCustom) {
            btnAddCustom.addEventListener('click', addCustomService);
        }
        if (customNameInput) {
            customNameInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addCustomService();
                }
            });
        }
        if (customRateInput) {
            customRateInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addCustomService();
                }
            });
        }

        function recalculate() {
            total = 0;
            const selectedServices = [];
            if (container) {
                container.querySelectorAll('.service-select-card.selected').forEach(c => {
                    total += parseFloat(c.getAttribute('data-cost')) || 0;
                    selectedServices.push(c.getAttribute('data-service'));
                });
            }

            totalInput.value = total.toFixed(2);
            notesInput.value = selectedServices.length > 0 ? selectedServices.join(', ') : 'Walk-in Quick Counter Service';
        }

        // Lookup Serial
        document.getElementById('btnQuickVerify').addEventListener('click', function () {
            const serial = document.getElementById('quickSerialInput').value.trim();
            if (!serial) return;

            const box = document.getElementById('quickInfoBox');
            box.style.display = 'block';
            box.className = 'alert alert-info mt-2';
            box.innerHTML = '<i class="fas fa-spinner fa-spin mr-2 text-primary"></i>Looking up serial...';

            fetch(`{{ route('repairs.verify-warranty') }}?serial=${encodeURIComponent(serial)}`)
                .then(res => res.json())
                .then(res => {
                    if (res.found) {
                        // Check if blocked for repair intake
                        if (res.allow_repair === false) {
                            if (btnSubmit) btnSubmit.setAttribute('disabled', 'disabled');

                            if (res.block_reason === 'open_job') {
                                const jobUrl = res.details?.job_url || `{{ url('admin/repairs') }}/${res.details?.job_id || ''}`;
                                const jobNo = res.details?.job_no || 'Job';
                                const stageName = res.details?.stage_name || 'In Progress';

                                box.className = 'alert alert-warning mt-2 border-warning text-dark';
                                box.innerHTML = `
                                    <div class="d-flex align-items-start">
                                        <i class="fas fa-exclamation-triangle fa-2x text-warning mr-2 mt-1"></i>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <b class="text-dark">Device Already in Repair Pipeline (${stageName})</b>
                                                <a href="${jobUrl}" target="_blank" class="btn btn-warning btn-sm font-weight-bold text-dark ml-2">
                                                    <i class="fas fa-external-link-alt mr-1"></i>View Job #${jobNo}
                                                </a>
                                            </div>
                                            <div class="small mt-1">${res.message || `This device is already in repair job #${jobNo}.`}</div>
                                        </div>
                                    </div>
                                `;
                                return;
                            }

                            if (res.block_reason === 'replaced') {
                                box.className = 'alert alert-danger mt-2';
                                let switchHtml = '';
                                if (res.replaced_by_serial) {
                                    switchHtml = `
                                        <div class="mt-2 pt-2 border-top">
                                            <button type="button" class="btn btn-sm btn-success font-weight-bold" id="quickSwitchBtn" data-sn="${res.replaced_by_serial}">
                                                <i class="fas fa-sync-alt mr-1"></i>Switch to Active Unit (S/N: ${res.replaced_by_serial}) &amp; Verify
                                            </button>
                                        </div>
                                    `;
                                }

                                box.innerHTML = `
                                    <div class="d-flex align-items-start">
                                        <i class="fas fa-ban fa-2x text-danger mr-2 mt-1"></i>
                                        <div>
                                            <b class="text-danger">Cannot Service S/N ${res.serial_no} (Old Replaced Serial)</b>
                                            <div class="small mt-1">${res.message}</div>
                                            ${switchHtml}
                                        </div>
                                    </div>
                                `;

                                const switchBtn = document.getElementById('quickSwitchBtn');
                                if (switchBtn) {
                                    switchBtn.addEventListener('click', function () {
                                        document.getElementById('quickSerialInput').value = this.getAttribute('data-sn');
                                        document.getElementById('btnQuickVerify').click();
                                    });
                                }
                                return;
                            }

                            // Decommissioned
                            box.className = 'alert alert-danger mt-2';
                            box.innerHTML = `
                                <div class="d-flex align-items-start">
                                    <i class="fas fa-ban fa-2x text-danger mr-2 mt-1"></i>
                                    <div>
                                        <b class="text-danger">Cannot Service S/N ${res.serial_no} (Unit Decommissioned)</b>
                                        <div class="small mt-1">${res.message || 'This unit is decommissioned.'}</div>
                                    </div>
                                </div>
                            `;
                            return;
                        }


                        // Valid active device
                        if (btnSubmit) btnSubmit.removeAttribute('disabled');
                        box.className = 'alert alert-light border mt-2';

                        if (res.customer) document.getElementById('quickCustomerId').value = res.customer.id;
                        if (res.product) document.getElementById('quickProductId').value = res.product.id;
                        if (res.sale_master_id) document.getElementById('quickSaleMasterId').value = res.sale_master_id;
                        document.getElementById('quickWarrantyStatus').value = res.active_tier;

                        const isCovered = res.active_tier === 'product_warranty';

                        box.innerHTML = `
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <b>${res.product ? res.product.name : 'Hearing Aid'}</b> (S/N: ${res.serial_no})<br>
                                    <span class="text-muted">Patient: ${res.customer ? res.customer.name : 'Customer'} (${res.customer ? res.customer.phone : ''})</span>
                                </div>
                                <span class="badge ${isCovered ? 'badge-success' : 'badge-secondary'} p-2">
                                    ${isCovered ? 'UNDER PRODUCT WARRANTY' : 'OUT OF WARRANTY'}
                                </span>
                            </div>
                        `;

                        if (isCovered) {
                            totalInput.value = '0.00';
                        }
                    } else {
                        if (btnSubmit) btnSubmit.removeAttribute('disabled');
                        box.className = 'alert alert-light border mt-2';
                        box.innerHTML = `<span class="text-muted"><i class="fas fa-info-circle mr-1"></i>${res.message} Recording as walk-in.</span>`;
                    }
                });
        });
    });
</script>
@endpush
