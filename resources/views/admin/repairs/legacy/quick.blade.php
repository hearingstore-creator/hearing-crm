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
            padding: 14px;
            cursor: pointer;
            transition: all 0.2s;
            background: #ffffff;
            margin-bottom: 12px;
        }
        .service-select-card:hover {
            border-color: #f59e0b;
            background: #fffbeb;
        }
        .service-select-card.selected {
            border-color: #d97706;
            background: #fef3c7;
            box-shadow: 0 0 0 2px rgba(217, 119, 6, 0.2);
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
                <h6 class="font-weight-bold text-dark mb-3"><i class="fas fa-hand-holding-medical mr-2 text-warning"></i>2. Select Counter Services Rendered:</h6>
                
                <div class="service-select-card" data-service="Wax Guard / Filter Replacement" data-cost="150">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="font-weight-bold text-dark"><i class="far fa-square mr-2 svc-icon"></i>Wax Guard / Filter Replacement</div>
                            <small class="text-muted">Replace clogged cerumen protection wax filter on receiver</small>
                        </div>
                        <span class="font-weight-bold text-dark">৳150</span>
                    </div>
                </div>

                <div class="service-select-card" data-service="Sound Tube / Eartip Replacement" data-cost="250">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="font-weight-bold text-dark"><i class="far fa-square mr-2 svc-icon"></i>Sound Tube / Eartip Replacement</div>
                            <small class="text-muted">Replace hardened, yellowed or torn acoustic tube and dome</small>
                        </div>
                        <span class="font-weight-bold text-dark">৳250</span>
                    </div>
                </div>

                <div class="service-select-card" data-service="Earmold Buffing & Precision Polishing" data-cost="200">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="font-weight-bold text-dark"><i class="far fa-square mr-2 svc-icon"></i>Earmold Buffing &amp; Precision Polishing</div>
                            <small class="text-muted">Grind high spots, relieve pressure canal points, surface polish</small>
                        </div>
                        <span class="font-weight-bold text-dark">৳200</span>
                    </div>
                </div>

                <div class="service-select-card" data-service="Acoustic Dehumidifying & Vacuum Cleaning" data-cost="200">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="font-weight-bold text-dark"><i class="far fa-square mr-2 svc-icon"></i>Acoustic Dehumidifying &amp; Vacuum Cleaning</div>
                            <small class="text-muted">Electronic dry-box dehydration and vacuum suction of mic/receiver</small>
                        </div>
                        <span class="font-weight-bold text-dark">৳200</span>
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

        document.querySelectorAll('.service-select-card').forEach(card => {
            card.addEventListener('click', function () {
                this.classList.toggle('selected');
                const icon = this.querySelector('.svc-icon');
                if (this.classList.contains('selected')) {
                    icon.classList.remove('fa-square');
                    icon.classList.add('fa-check-square');
                } else {
                    icon.classList.remove('fa-check-square');
                    icon.classList.add('fa-square');
                }

                recalculate();
            });
        });

        function recalculate() {
            total = 0;
            const selectedServices = [];
            document.querySelectorAll('.service-select-card.selected').forEach(c => {
                total += parseFloat(c.getAttribute('data-cost')) || 0;
                selectedServices.push(c.getAttribute('data-service'));
            });

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
                        // Check if decommissioned or replaced
                        if (res.is_decommissioned || !res.allow_repair) {
                            if (btnSubmit) btnSubmit.setAttribute('disabled', 'disabled');
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
