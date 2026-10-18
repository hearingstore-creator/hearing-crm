@extends('admin.layouts.master')
@section('page_title', 'Create Sales Return / Credit Note')
@section('breadcrumb_parent', 'Sales Returns')
@section('breadcrumb_parent_url', route('sale-returns.index'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<style>
.return-card { border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.serial-badge { display: inline-flex; align-items: center; background: #e0e7ff; color: #3730a3; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; margin: 2px; }
.settlement-radio-card { border: 2px solid #e2e8f0; border-radius: 8px; padding: 14px; cursor: pointer; transition: all 0.2s; height: 100%; }
.settlement-radio-card.active { border-color: #6366f1; background-color: #f5f3ff; }
.settlement-radio-card:hover { border-color: #a5b4fc; }
.kpi-stat-box { background: #f8fafc; border-radius: 6px; padding: 10px 14px; border-left: 3px solid #6366f1; }
.allocation-bucket-card { border-radius: 8px; padding: 14px; border: 1px solid #cbd5e1; background: #fff; }
.type-badge-product { background: #dcfce7; color: #166534; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 4px; }
.type-badge-service { background: #e0f2fe; color: #0369a1; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 4px; }
</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-undo-alt" style="color: #6366f1; margin-right: 8px;"></i>New Sales Return & Credit Note</h1>
            <p>Return hearing aid products (stock restock) and clinical services (fee cancellation) with smart financial settlement</p>
        </div>
        <div class="header-actions">
            <a href="{{ route('sale-returns.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-list mr-1"></i>All Returns
            </a>
        </div>
    </div>

    @include('admin.alert_message')

    <form action="{{ route('sale-returns.store') }}" method="POST" id="saleReturnForm">
        @csrf

        {{-- Invoice & Patient Lookup Header --}}
        <div class="card return-card mb-4 crm-animate-in">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 font-weight-bold" style="color: #1e293b;">
                    <i class="fas fa-file-invoice text-primary mr-2"></i>1. Invoice & Patient Selection
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-5">
                        <div class="form-group mb-1">
                            <label class="font-weight-bold" style="font-size: 13px;">
                                <i class="fas fa-barcode text-primary mr-1"></i>Sale Invoice Number
                            </label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="sale_invoice_no" id="sale_invoice_no" 
                                    placeholder="e.g. 68F23A4... or paste from receipt" 
                                    value="{{ old('sale_invoice_no', $prefilledSale?->sale_invoice_no) }}" autocomplete="off">
                                <div class="input-group-append">
                                    <button class="btn btn-primary font-weight-bold px-3" type="button" id="btnFetchInvoice" onclick="window.triggerInvoiceFetch && window.triggerInvoiceFetch()">
                                        <i class="fas fa-search mr-1"></i>Load
                                    </button>
                                </div>
                            </div>
                            <div id="invoiceSearchFeedback" class="mt-1" style="font-size: 12px; min-height: 20px;">
                                <small class="text-muted"><i class="fas fa-info-circle mr-1"></i>Type or paste invoice number (auto-searches) or pick patient below.</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group mb-1">
                            <label class="font-weight-bold" style="font-size: 13px;">
                                <i class="fas fa-user text-primary mr-1"></i>Customer / Patient <span class="text-danger">*</span>
                            </label>
                            <select class="form-control select2" name="customer_id" id="customer_id" required>
                                <option value="">Select Patient</option>
                                @foreach ($customers as $cust)
                                    <option value="{{ $cust->id }}" 
                                        {{ old('customer_id', $prefilledSale?->customer_id) == $cust->id ? 'selected' : '' }}>
                                        {{ $cust->lead?->full_name ?? $cust->name }} 
                                        ({{ $cust->lead?->phone_number ?? 'No phone' }}) - {{ $cust->customer_uid }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div id="customerInvoicesWrapper" class="mt-2" style="display: none;">
                            <label class="font-weight-bold text-muted mb-1" style="font-size: 11px; text-transform: uppercase;">
                                <i class="fas fa-history mr-1 text-info"></i>Patient Invoices (Quick Select):
                            </label>
                            <select class="form-control form-control-sm" id="customer_invoice_picker" style="font-size: 12px; height: 34px;">
                                <option value="">-- Choose invoice to load --</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="font-weight-bold" style="font-size: 13px;">
                                <i class="fas fa-calendar-alt text-primary mr-1"></i>Return Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control" name="date" id="date" value="{{ old('date', date('Y-m-d')) }}" required>
                        </div>
                    </div>
                </div>

                {{-- Invoice Summary Snapshot (Loaded via AJAX) --}}
                <div id="invoiceSnapshot" class="mt-3 pt-3 border-top" style="display: none;">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="kpi-stat-box">
                                <span class="text-muted d-block" style="font-size: 11px; text-transform: uppercase;">Invoice Date</span>
                                <span class="font-weight-bold text-dark" id="dispInvoiceDate">—</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="kpi-stat-box" style="border-left-color: #0284c7;">
                                <span class="text-muted d-block" style="font-size: 11px; text-transform: uppercase;">Total Billed</span>
                                <span class="font-weight-bold text-dark" id="dispInvoiceTotal">৳0.00</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="kpi-stat-box" style="border-left-color: #16a34a;">
                                <span class="text-muted d-block" style="font-size: 11px; text-transform: uppercase;">Actual Cash Paid</span>
                                <span class="font-weight-bold text-success" id="dispInvoicePaid">৳0.00</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="kpi-stat-box" style="border-left-color: #e11d48;">
                                <span class="text-muted d-block" style="font-size: 11px; text-transform: uppercase;">Outstanding Due</span>
                                <span class="font-weight-bold text-danger" id="dispInvoiceDue">৳0.00</span>
                            </div>
                        </div>
                    </div>

                    <div id="doctorNotice" class="alert alert-light border mt-3 mb-0 py-2 px-3" style="display: none; font-size: 12px;">
                        <i class="fas fa-user-md text-info mr-2"></i>
                        <span>Referral Doctor: <strong id="dispDoctorName">—</strong> (Original Commission: <strong id="dispDoctorCommission">৳0.00</strong>)</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 2A: Physical Products Table --}}
        <div class="card return-card mb-4 crm-animate-in delay-1" id="productsCard">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <span class="type-badge-product mr-2"><i class="fas fa-boxes mr-1"></i>Physical Products</span>
                    <h6 class="mb-0 font-weight-bold" style="color: #1e293b;">Hearing Aids & Accessories (Restocks Inventory)</h6>
                </div>
                <small class="text-muted">Tick items/serials being returned to clinic inventory stock</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="productItemsTable" style="vertical-align: middle;">
                        <thead style="background: #f8fafc; font-size: 12px; text-transform: uppercase; color: #64748b;">
                            <tr>
                                <th style="width: 50px; text-align: center;">Select</th>
                                <th>Product Details & Serial Numbers</th>
                                <th style="width: 110px;">Sold Qty</th>
                                <th style="width: 120px;">Return Qty</th>
                                <th style="width: 130px;">Unit Price (৳)</th>
                                <th style="width: 150px;">Deduction / Restocking</th>
                                <th style="width: 140px; text-align: right;">Net Return (৳)</th>
                            </tr>
                        </thead>
                        <tbody id="productItemsBody">
                            <tr id="emptyProductPlaceholder">
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="fas fa-search fa-2x mb-2 text-secondary" style="opacity: 0.3;"></i>
                                    <p class="mb-0">Load a Sale Invoice Number to display products.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Section 2B: Clinical Services Table --}}
        <div class="card return-card mb-4 crm-animate-in delay-1" id="servicesCard" style="display: none;">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <span class="type-badge-service mr-2"><i class="fas fa-stethoscope mr-1"></i>Clinical Services</span>
                    <h6 class="mb-0 font-weight-bold" style="color: #1e293b;">Diagnostic Tests & Consultation Fees (Non-Stock Credit)</h6>
                </div>
                <small class="text-muted"><i class="fas fa-info-circle mr-1 text-info"></i>Intangible fees: Credited without stock changes</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="serviceItemsTable" style="vertical-align: middle;">
                        <thead style="background: #f8fafc; font-size: 12px; text-transform: uppercase; color: #64748b;">
                            <tr>
                                <th style="width: 50px; text-align: center;">Select</th>
                                <th>Clinical Service Name & Description</th>
                                <th style="width: 110px;">Billed Qty</th>
                                <th style="width: 120px;">Cancel Qty</th>
                                <th style="width: 130px;">Service Fee (৳)</th>
                                <th style="width: 150px;">Fee Deduction</th>
                                <th style="width: 140px; text-align: right;">Net Credit (৳)</th>
                            </tr>
                        </thead>
                        <tbody id="serviceItemsBody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Smart Correlation & Settlement Engine --}}
        <div class="card return-card mb-4 crm-animate-in delay-2" id="settlementCard">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 font-weight-bold" style="color: #1e293b;">
                    <i class="fas fa-calculator text-primary mr-2"></i>3. Financial Allocation & Settlement Correlation
                </h6>
            </div>
            <div class="card-body">

                {{-- Totals Summary Bar --}}
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="p-3 rounded bg-light border">
                            <span class="text-muted d-block" style="font-size: 11px; text-transform: uppercase;">Gross Return Subtotal</span>
                            <span class="h5 font-weight-bold text-dark mb-0" id="dispGrossTotal">৳0.00</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 rounded bg-light border">
                            <span class="text-muted d-block" style="font-size: 11px; text-transform: uppercase; color: #d97706;">Usage / Restocking Deductions</span>
                            <span class="h5 font-weight-bold text-warning mb-0" id="dispTotalDeduction">-৳0.00</span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 rounded border" style="background: #f0fdf4; border-color: #bbf7d0 !important;">
                            <span class="text-muted d-block" style="font-size: 11px; text-transform: uppercase; color: #166534;">Net Credit Note Value</span>
                            <span class="h4 font-weight-bold text-success mb-0" id="dispNetCreditNote">৳0.00</span>
                        </div>
                    </div>
                </div>

                {{-- Smart Financial Allocation Breakdown --}}
                <div class="allocation-bucket-card mb-4" id="allocationBreakdownCard">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0 font-weight-bold text-dark" style="font-size: 13px;">
                            <i class="fas fa-sitemap text-primary mr-2"></i>Automatic Correlation & Financial Split
                        </h6>
                        <span class="badge badge-pill badge-secondary" style="font-size: 11px;">Golden Rule: Cash refund cannot exceed cash paid</span>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <div class="p-3 rounded border" style="background: #eff6ff; border-color: #bfdbfe !important;">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <span class="badge badge-primary mb-1">Bucket A</span>
                                        <div class="font-weight-bold text-dark" style="font-size: 13px;">Applied to Clear Invoice Due</div>
                                        <small class="text-muted">Reduces patient's unpaid receivable balance on this sale</small>
                                    </div>
                                    <div class="text-right">
                                        <div class="h5 font-weight-bold text-primary mb-0" id="dispBucketADue">৳0.00</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-2">
                            <div class="p-3 rounded border" style="background: #f0fdf4; border-color: #bbf7d0 !important;">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <span class="badge badge-success mb-1">Bucket B</span>
                                        <div class="font-weight-bold text-dark" style="font-size: 13px;">Net Refundable / Store Credit</div>
                                        <small class="text-muted">Remaining net credit after clearing outstanding due</small>
                                    </div>
                                    <div class="text-right">
                                        <div class="h5 font-weight-bold text-success mb-0" id="dispBucketBNet">৳0.00</div>
                                        <small class="text-muted d-block">Max Cash Payout: <strong id="dispMaxCashAllowed">৳0.00</strong></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="allocationLiveNotice" class="alert alert-info border py-2 px-3 mt-3 mb-0" style="font-size: 12px; display: none;">
                        <i class="fas fa-info-circle mr-1"></i><span id="allocationLiveNoticeText">Settlement note</span>
                    </div>
                </div>

                {{-- Settlement Options --}}
                <label class="font-weight-bold d-block mb-3" style="font-size: 13px;">Select Resolution Method for Net Credit:</label>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="settlement-radio-card active" data-type="due_reduction">
                            <div class="custom-control custom-radio">
                                <input type="radio" id="settle_due_reduction" name="settlement_type" value="due_reduction" class="custom-control-input" checked>
                                <label class="custom-control-label font-weight-bold text-dark" for="settle_due_reduction">
                                    <i class="fas fa-file-invoice mr-1 text-primary"></i>Reduce Invoice Due
                                </label>
                            </div>
                            <p class="text-muted mt-2 mb-0" style="font-size: 11px;">
                                Automatically clears unpaid due balance on this invoice. Excess credit (if any) rolls over into Store Credit.
                            </p>
                        </div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <div class="settlement-radio-card" data-type="store_credit">
                            <div class="custom-control custom-radio">
                                <input type="radio" id="settle_store_credit" name="settlement_type" value="store_credit" class="custom-control-input">
                                <label class="custom-control-label font-weight-bold text-dark" for="settle_store_credit">
                                    <i class="fas fa-wallet mr-1 text-success"></i>Store Credit / Advance
                                </label>
                            </div>
                            <p class="text-muted mt-2 mb-0" style="font-size: 11px;">
                                Clears due and credits remaining balance to patient's ledger wallet for their next hearing aid purchase or battery replacement.
                            </p>
                        </div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <div class="settlement-radio-card" data-type="cash_refund">
                            <div class="custom-control custom-radio">
                                <input type="radio" id="settle_cash_refund" name="settlement_type" value="cash_refund" class="custom-control-input">
                                <label class="custom-control-label font-weight-bold text-dark" for="settle_cash_refund">
                                    <i class="fas fa-hand-holding-usd mr-1 text-danger"></i>Cash / Bank Refund
                                </label>
                            </div>
                            <p class="text-muted mt-2 mb-0" style="font-size: 11px;">
                                Clears due, then refunds cash directly to the patient now (capped at actual cash paid by patient).
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Payment Method Selection for Cash Refund --}}
                <div id="refundPaymentMethodRow" class="row mt-2" style="display: none;">
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <label class="font-weight-bold" style="font-size: 12px;">Refund Payout Account / Method</label>
                            <select class="form-control form-control-sm" name="payment_type_id" id="payment_type_id">
                                <option value="1">Cash in Hand</option>
                                <option value="2">Bank Account / Cheque</option>
                                <option value="3">bKash / Nagad / MFS</option>
                                <option value="4">POS Card Refund</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Doctor Commission Adjustment (If applicable) --}}
                <div id="doctorCommissionAdjustmentBox" class="mt-4 pt-3 border-top" style="display: none;">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="adjust_doctor_commission" name="adjust_doctor_commission" value="1">
                        <label class="custom-control-label font-weight-bold text-dark" for="adjust_doctor_commission">
                            Clawback / Adjust Doctor Referral Commission
                        </label>
                    </div>
                    <div class="row mt-2" id="doctorAdjFields" style="display: none;">
                        <div class="col-md-4">
                            <label style="font-size: 12px;" class="text-muted">Doctor Commission Clawback Amount (৳)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" name="doctor_commission_adjusted" id="doctor_commission_adjusted" value="0.00">
                            <small class="text-muted">Debit entry will be posted to doctor's ledger to adjust payable commission.</small>
                        </div>
                    </div>
                </div>

                {{-- Reason / Notes --}}
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="form-group mb-0">
                            <label class="font-weight-bold" style="font-size: 13px;">Reason for Return / Clinical Notes</label>
                            <textarea class="form-control" name="reason" rows="2" placeholder="e.g. Returned within 7-day trial period, exchanged for rechargeable model, audiometry fee waived, etc."></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white border-top py-3 text-right">
                <a href="{{ route('sale-returns.index') }}" class="btn btn-outline-secondary mr-2">Cancel</a>
                <button type="submit" class="btn btn-primary px-4" id="btnSubmitReturn">
                    <i class="fas fa-check-circle mr-1"></i>Generate Credit Note
                </button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    let currentSaleData = null;
    let globalItemIndex = 0;

    // Settlement card selection styling
    $('.settlement-radio-card').on('click', function() {
        $('.settlement-radio-card').removeClass('active');
        $(this).addClass('active');
        $(this).find('input[type="radio"]').prop('checked', true).trigger('change');
    });

    $('input[name="settlement_type"]').on('change', function() {
        const type = $(this).val();
        if (type === 'cash_refund') {
            $('#refundPaymentMethodRow').slideDown(200);
        } else {
            $('#refundPaymentMethodRow').slideUp(200);
        }
        recalculateTotals();
    });

    // Toggle doctor adjustment fields
    $('#adjust_doctor_commission').on('change', function() {
        if ($(this).is(':checked')) {
            $('#doctorAdjFields').slideDown(200);
        } else {
            $('#doctorAdjFields').slideUp(200);
            $('#doctor_commission_adjusted').val('0.00');
        }
    });

    let lastLoadedInvoice = '';
    let searchTimer = null;

    // Direct window function so inline onclick always works
    window.triggerInvoiceFetch = function() {
        clearTimeout(searchTimer);
        const raw = ($('#sale_invoice_no').val() || '').trim();
        const clean = raw.replace(/^#/, '').trim();
        if (!clean) {
            $('#invoiceSearchFeedback').html('<span class="text-danger font-weight-bold"><i class="fas fa-exclamation-circle mr-1"></i>Please enter a Sale Invoice Number</span>');
            return;
        }
        loadInvoiceData(clean);
    };

    // Auto load if prefilled invoice number present
    const prefilledInvoice = ($('#sale_invoice_no').val() || '').trim();
    if (prefilledInvoice) {
        loadInvoiceData(prefilledInvoice);
    }

    // Auto-search on typing (debounce 450ms)
    $('#sale_invoice_no').on('input', function() {
        const raw = ($(this).val() || '').trim();
        const clean = raw.replace(/^#/, '').trim();
        clearTimeout(searchTimer);

        if (!clean) {
            $('#invoiceSearchFeedback').html('<small class="text-muted"><i class="fas fa-info-circle mr-1"></i>Type or paste invoice number (auto-searches) or pick patient below.</small>');
            return;
        }

        if (clean === lastLoadedInvoice) {
            return;
        }

        if (clean.length >= 5) {
            $('#invoiceSearchFeedback').html('<span class="text-muted"><i class="fas fa-circle-notch fa-spin mr-1"></i>Searching invoice #' + clean + '...</span>');
            searchTimer = setTimeout(function() {
                loadInvoiceData(clean);
            }, 450);
        }
    });

    // Auto-search on paste
    $('#sale_invoice_no').on('paste', function() {
        setTimeout(function() {
            const raw = ($('#sale_invoice_no').val() || '').trim();
            const clean = raw.replace(/^#/, '').trim();
            if (clean && clean !== lastLoadedInvoice) {
                loadInvoiceData(clean);
            }
        }, 50);
    });

    // Auto-search on blur
    $('#sale_invoice_no').on('blur', function() {
        const raw = ($(this).val() || '').trim();
        const clean = raw.replace(/^#/, '').trim();
        if (clean && clean !== lastLoadedInvoice) {
            loadInvoiceData(clean);
        }
    });

    // Fetch on Enter key in invoice field
    $('#sale_invoice_no').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            clearTimeout(searchTimer);
            const raw = ($(this).val() || '').trim();
            const clean = raw.replace(/^#/, '').trim();
            if (clean) loadInvoiceData(clean);
        }
    });

    // Fetch on button click
    $('#btnFetchInvoice').on('click', function(e) {
        e.preventDefault();
        window.triggerInvoiceFetch();
    });

    // Customer selection change: load invoices into quick picker
    $('#customer_id').on('change', function() {
        const custId = $(this).val();
        if (!custId) {
            $('#customerInvoicesWrapper').slideUp(200);
            $('#customer_invoice_picker').empty().append('<option value="">-- Choose invoice to load --</option>');
            return;
        }

        $.ajax({
            url: "{{ url('admin/sale-returns/customer-invoices') }}",
            type: "GET",
            data: { customer_id: custId },
            dataType: "json",
            success: function(res) {
                if (res.success && res.invoices && res.invoices.length > 0) {
                    let opts = `<option value="">-- Found ${res.invoices.length} invoice(s) for this patient (click to select) --</option>`;
                    res.invoices.forEach(function(inv) {
                        const isMatch = (lastLoadedInvoice && (inv.invoice_no === lastLoadedInvoice || '#' + inv.invoice_no === lastLoadedInvoice));
                        opts += `<option value="${inv.invoice_no}" ${isMatch ? 'selected' : ''}>${inv.label}</option>`;
                    });
                    $('#customer_invoice_picker').html(opts);
                    $('#customerInvoicesWrapper').slideDown(200);
                } else {
                    $('#customer_invoice_picker').html('<option value="">No past invoices found for this patient</option>');
                    $('#customerInvoicesWrapper').slideDown(200);
                }
            }
        });
    });

    // Customer invoice picker selection
    $('#customer_invoice_picker').on('change', function() {
        const inv = $(this).val();
        if (inv) {
            $('#sale_invoice_no').val(inv);
            loadInvoiceData(inv);
        }
    });

    function loadInvoiceData(invoiceNo) {
        if (!invoiceNo) return;
        const clean = invoiceNo.toString().trim().replace(/^#/, '');
        if (!clean) return;

        $('#btnFetchInvoice').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Loading...');
        $('#invoiceSearchFeedback').html('<span class="text-primary font-weight-bold"><i class="fas fa-spinner fa-spin mr-1"></i>Fetching invoice #' + clean + '...</span>');
        
        $.ajax({
            url: "{{ url('admin/sale-returns/items/by-sale') }}",
            type: "GET",
            data: { invoice_no: clean },
            dataType: "json",
            success: function(res) {
                $('#btnFetchInvoice').prop('disabled', false).html('<i class="fas fa-search mr-1"></i>Load');
                if (res.success) {
                    lastLoadedInvoice = res.sale.invoice_no;
                    $('#sale_invoice_no').val(res.sale.invoice_no);
                    $('#invoiceSearchFeedback').html('<span class="text-success font-weight-bold"><i class="fas fa-check-circle mr-1"></i>Invoice #' + res.sale.invoice_no + ' found & loaded!</span>');
                    currentSaleData = res;
                    populateInvoice(res);
                } else {
                    lastLoadedInvoice = '';
                    $('#invoiceSearchFeedback').html('<span class="text-danger font-weight-bold"><i class="fas fa-exclamation-circle mr-1"></i>' + (res.message || 'Invoice not found.') + '</span>');
                }
            },
            error: function(xhr) {
                $('#btnFetchInvoice').prop('disabled', false).html('<i class="fas fa-search mr-1"></i>Load');
                lastLoadedInvoice = '';
                const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Sale invoice not found. Please verify the invoice number or select patient.';
                $('#invoiceSearchFeedback').html('<span class="text-danger font-weight-bold"><i class="fas fa-exclamation-circle mr-1"></i>' + msg + '</span>');
            }
        });
    }

    function populateInvoice(data) {
        try {
            const sale = data.sale;
            const products = data.products || [];
            const services = data.services || [];
            globalItemIndex = 0;

            // Auto select customer
            if (sale.customer_id) {
                if ($('#customer_id').val() != sale.customer_id) {
                    $('#customer_id').val(sale.customer_id).trigger('change');
                }
            }

            // Show Invoice Snapshot
            $('#dispInvoiceDate').text(sale.date_formatted || sale.date);
            $('#dispInvoiceTotal').text('৳' + parseFloat(sale.total_amount).toLocaleString('en-US', {minimumFractionDigits: 2}));
            $('#dispInvoicePaid').text('৳' + parseFloat(sale.paid).toLocaleString('en-US', {minimumFractionDigits: 2}));
            $('#dispInvoiceDue').text('৳' + parseFloat(sale.due).toLocaleString('en-US', {minimumFractionDigits: 2}));
            $('#invoiceSnapshot').slideDown(200);

            // Doctor referral info
            if (sale.doctor_id && sale.doctor_name) {
                $('#dispDoctorName').text(sale.doctor_name);
                $('#dispDoctorCommission').text('৳' + parseFloat(sale.doctor_commission).toLocaleString('en-US', {minimumFractionDigits: 2}));
                $('#doctorNotice').show();
                $('#doctorCommissionAdjustmentBox').show();
            } else {
                $('#doctorNotice').hide();
                $('#doctorCommissionAdjustmentBox').hide();
            }

            // Default settlement recommendation based on Due balance
            if (parseFloat(sale.due) > 0.009) {
                $('#settle_due_reduction').prop('checked', true);
                $('.settlement-radio-card').removeClass('active');
                $('[data-type="due_reduction"]').addClass('active');
                $('#refundPaymentMethodRow').hide();
            } else {
                $('#settle_store_credit').prop('checked', true);
                $('.settlement-radio-card').removeClass('active');
                $('[data-type="store_credit"]').addClass('active');
                $('#refundPaymentMethodRow').hide();
            }

            // 1. Populate Physical Products
            const pTbody = $('#productItemsBody');
            pTbody.empty();

            if (products.length === 0) {
                pTbody.html('<tr><td colspan="7" class="text-center py-3 text-muted">No physical products found on this invoice.</td></tr>');
            } else {
                products.forEach(function(item) {
                    const idx = globalItemIndex++;
                    const hasSerials = item.has_serials && item.available_serials && item.available_serials.length > 0;
                    const disabled = item.available_return_qty <= 0 ? 'disabled' : '';

                    let serialsHtml = '';
                    if (hasSerials) {
                        serialsHtml = '<div class="mt-2 p-2 rounded" style="background: #f1f5f9; font-size: 12px;">';
                        serialsHtml += '<div class="text-muted mb-1" style="font-size: 11px;">Select serial number(s) to restock:</div>';
                        item.available_serials.forEach(function(sn) {
                            serialsHtml += `
                                <label class="serial-badge mr-2 mb-1" style="cursor: pointer;">
                                    <input type="checkbox" class="item-serial-cb mr-1" data-index="${idx}" name="items[${idx}][serial_no][]" value="${sn}">
                                    ${sn}
                                </label>
                            `;
                        });
                        serialsHtml += '</div>';
                    }

                    const tr = `
                        <tr class="item-row product-row" data-index="${idx}">
                            <td class="text-center">
                                <input type="checkbox" class="item-select-cb" data-index="${idx}" name="items[${idx}][selected]" value="1" ${disabled}>
                                <input type="hidden" name="items[${idx}][item_type]" value="product">
                                <input type="hidden" name="items[${idx}][sale_detail_id]" value="${item.sale_detail_id}">
                                <input type="hidden" name="items[${idx}][product_id]" value="${item.product_id}">
                                <input type="hidden" name="items[${idx}][batch_id]" value="${item.batch_id || ''}">
                                <input type="hidden" name="items[${idx}][sold_qty]" value="${item.sold_qty}">
                            </td>
                            <td>
                                <div class="font-weight-bold text-dark">${item.product_name}</div>
                                <div class="text-muted" style="font-size: 11px;">
                                    ${item.category_name ? '<span class="mr-2"><i class="fas fa-tag mr-1"></i>' + item.category_name + '</span>' : ''}
                                    ${item.batch_no ? '<span><i class="fas fa-layer-group mr-1"></i>Batch: ' + item.batch_no + '</span>' : ''}
                                </div>
                                ${serialsHtml}
                            </td>
                            <td>
                                <span class="text-muted font-weight-bold">${item.sold_qty}</span>
                                ${item.previously_returned > 0 ? '<div class="text-danger" style="font-size: 11px;">(' + item.previously_returned + ' ret)</div>' : ''}
                            </td>
                            <td>
                                <input type="number" step="1" min="1" max="${item.available_return_qty}" 
                                    class="form-control form-control-sm item-qty" 
                                    name="items[${idx}][return_qty]" 
                                    value="${item.available_return_qty > 0 ? 1 : 0}" 
                                    data-max="${item.available_return_qty}"
                                    ${hasSerials ? 'readonly' : ''} ${disabled}>
                                <small class="text-muted">Max: ${item.available_return_qty}</small>
                            </td>
                            <td>
                                <input type="number" step="0.01" class="form-control form-control-sm item-price" 
                                    name="items[${idx}][unit_price]" 
                                    value="${item.net_unit_price}" ${disabled}>
                                <small class="text-muted">Orig MRP: ৳${item.mrp_price}</small>
                            </td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" class="form-control item-deduction-rate" 
                                        name="items[${idx}][deduction_rate]" 
                                        placeholder="%" title="Deduction %" value="0">
                                    <div class="input-group-append">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <input type="number" step="0.01" class="form-control form-control-sm mt-1 item-deduction-amount" 
                                    name="items[${idx}][deduction_amount]" 
                                    placeholder="৳ Amount" value="0">
                            </td>
                            <td class="text-right font-weight-bold text-dark item-net-total">
                                ৳0.00
                            </td>
                        </tr>
                    `;
                    pTbody.append(tr);
                });
            }

            // 2. Populate Clinical Services
            const sTbody = $('#serviceItemsBody');
            sTbody.empty();

            if (services.length > 0) {
                $('#servicesCard').slideDown(200);
                services.forEach(function(item) {
                    const idx = globalItemIndex++;
                    const disabled = item.available_return_qty <= 0 ? 'disabled' : '';

                    const tr = `
                        <tr class="item-row service-row" data-index="${idx}">
                            <td class="text-center">
                                <input type="checkbox" class="item-select-cb" data-index="${idx}" name="items[${idx}][selected]" value="1" ${disabled}>
                                <input type="hidden" name="items[${idx}][item_type]" value="service">
                                <input type="hidden" name="items[${idx}][service_detail_id]" value="${item.service_detail_id}">
                                <input type="hidden" name="items[${idx}][service_type_id]" value="${item.service_type_id}">
                                <input type="hidden" name="items[${idx}][sold_qty]" value="${item.sold_qty}">
                            </td>
                            <td>
                                <div class="font-weight-bold text-dark">${item.service_name}</div>
                                <div class="text-info" style="font-size: 11px;">
                                    <i class="fas fa-stethoscope mr-1"></i>Clinical / Examination Fee (Zero stock impact)
                                </div>
                            </td>
                            <td>
                                <span class="text-muted font-weight-bold">${item.sold_qty}</span>
                                ${item.previously_returned > 0 ? '<div class="text-danger" style="font-size: 11px;">(' + item.previously_returned + ' ret)</div>' : ''}
                            </td>
                            <td>
                                <input type="number" step="1" min="1" max="${item.available_return_qty}" 
                                    class="form-control form-control-sm item-qty" 
                                    name="items[${idx}][return_qty]" 
                                    value="${item.available_return_qty > 0 ? 1 : 0}" 
                                    data-max="${item.available_return_qty}" ${disabled}>
                                <small class="text-muted">Max: ${item.available_return_qty}</small>
                            </td>
                            <td>
                                <input type="number" step="0.01" class="form-control form-control-sm item-price" 
                                    name="items[${idx}][unit_price]" 
                                    value="${item.unit_price}" ${disabled}>
                                <small class="text-muted">Billed Fee: ৳${item.unit_price}</small>
                            </td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" class="form-control item-deduction-rate" 
                                        name="items[${idx}][deduction_rate]" 
                                        placeholder="%" title="Deduction %" value="0">
                                    <div class="input-group-append">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <input type="number" step="0.01" class="form-control form-control-sm mt-1 item-deduction-amount" 
                                    name="items[${idx}][deduction_amount]" 
                                    placeholder="৳ Amount" value="0">
                            </td>
                            <td class="text-right font-weight-bold text-dark item-net-total">
                                ৳0.00
                            </td>
                        </tr>
                    `;
                    sTbody.append(tr);
                });
            } else {
                $('#servicesCard').slideUp(200);
            }

            recalculateTotals();
        } catch (err) {
            console.error('[SaleReturn] populateInvoice error:', err);
            $('#invoiceSearchFeedback').html('<span class="text-danger font-weight-bold"><i class="fas fa-exclamation-triangle mr-1"></i>Error rendering invoice items: ' + err.message + '</span>');
        }
    }

    // Auto-select row when ticking item serial number checkbox
    $(document).on('change', '.item-serial-cb', function() {
        const idx = $(this).data('index');
        const row = $(`tr[data-index="${idx}"]`);
        const checkedSerials = row.find('.item-serial-cb:checked').length;
        
        row.find('.item-qty').val(checkedSerials);
        if (checkedSerials > 0) {
            row.find('.item-select-cb').prop('checked', true);
        } else {
            row.find('.item-select-cb').prop('checked', false);
        }
        recalculateTotals();
    });

    // Row selection toggle
    $(document).on('change', '.item-select-cb', function() {
        recalculateTotals();
    });

    // Value changes (qty, price, deduction)
    $(document).on('input', '.item-qty, .item-price, .item-deduction-rate, .item-deduction-amount', function() {
        const row = $(this).closest('tr');
        const rateInput = row.find('.item-deduction-rate');
        const amtInput = row.find('.item-deduction-amount');

        // If rate % modified, update amount
        if ($(this).hasClass('item-deduction-rate')) {
            const qty = parseFloat(row.find('.item-qty').val()) || 0;
            const price = parseFloat(row.find('.item-price').val()) || 0;
            const rate = parseFloat(rateInput.val()) || 0;
            const gross = qty * price;
            const calcAmt = (gross * rate) / 100;
            amtInput.val(calcAmt.toFixed(2));
        }

        recalculateTotals();
    });

    function recalculateTotals() {
        let grossSubtotal = 0;
        let totalDeductions = 0;

        $('.item-row').each(function() {
            const isSelected = $(this).find('.item-select-cb').is(':checked');
            if (!isSelected) {
                $(this).find('.item-net-total').text('৳0.00');
                return;
            }

            const qty = parseFloat($(this).find('.item-qty').val()) || 0;
            const price = parseFloat($(this).find('.item-price').val()) || 0;
            const gross = qty * price;

            let deduction = parseFloat($(this).find('.item-deduction-amount').val()) || 0;
            if (deduction > gross) {
                deduction = gross;
                $(this).find('.item-deduction-amount').val(deduction.toFixed(2));
            }

            const netLine = Math.max(0, gross - deduction);
            $(this).find('.item-net-total').text('৳' + netLine.toLocaleString('en-US', {minimumFractionDigits: 2}));

            grossSubtotal += gross;
            totalDeductions += deduction;
        });

        const netCreditNote = Math.max(0, grossSubtotal - totalDeductions);

        $('#dispGrossTotal').text('৳' + grossSubtotal.toLocaleString('en-US', {minimumFractionDigits: 2}));
        $('#dispTotalDeduction').text('-৳' + totalDeductions.toLocaleString('en-US', {minimumFractionDigits: 2}));
        $('#dispNetCreditNote').text('৳' + netCreditNote.toLocaleString('en-US', {minimumFractionDigits: 2}));

        // -------------------------------------------------------------
        // Smart Allocation Correlation Engine:
        // Bucket A: Clears unpaid Due on this invoice first
        // Bucket B: Remaining balance (Max cash payout capped at actual cash paid)
        // -------------------------------------------------------------
        let invoiceDue = 0;
        let invoicePaid = 0;

        if (currentSaleData && currentSaleData.sale) {
            invoiceDue = parseFloat(currentSaleData.sale.due) || 0;
            invoicePaid = parseFloat(currentSaleData.sale.paid) || 0;
        }

        const bucketADueCleared = Math.min(netCreditNote, invoiceDue);
        const bucketBNetBalance = Math.max(0, netCreditNote - bucketADueCleared);
        const maxCashRefundAllowed = Math.min(bucketBNetBalance, invoicePaid);

        $('#dispBucketADue').text('৳' + bucketADueCleared.toLocaleString('en-US', {minimumFractionDigits: 2}));
        $('#dispBucketBNet').text('৳' + bucketBNetBalance.toLocaleString('en-US', {minimumFractionDigits: 2}));
        $('#dispMaxCashAllowed').text('৳' + maxCashRefundAllowed.toLocaleString('en-US', {minimumFractionDigits: 2}));

        // Dynamic settlement live explanation
        const selectedSettlement = $('input[name="settlement_type"]:checked').val() || 'due_reduction';
        let noticeText = '';

        if (netCreditNote <= 0) {
            $('#allocationLiveNotice').hide();
        } else {
            $('#allocationLiveNotice').show();
            if (selectedSettlement === 'cash_refund') {
                if (bucketADueCleared > 0 && maxCashRefundAllowed > 0) {
                    noticeText = `Credit of ৳${bucketADueCleared.toFixed(2)} clears unpaid invoice due. Patient will receive ৳${maxCashRefundAllowed.toFixed(2)} in cash payout now.`;
                } else if (bucketADueCleared > 0 && maxCashRefundAllowed <= 0) {
                    noticeText = `Credit of ৳${bucketADueCleared.toFixed(2)} clears unpaid invoice due. No cash refund is payable because patient has no cash credit remaining.`;
                } else {
                    noticeText = `Patient will receive ৳${maxCashRefundAllowed.toFixed(2)} in cash/bank refund payout now.`;
                }

                if (bucketBNetBalance > maxCashRefundAllowed) {
                    const excess = bucketBNetBalance - maxCashRefundAllowed;
                    noticeText += ` (Excess ৳${excess.toFixed(2)} will remain as Store Credit because cash refund cannot exceed ৳${invoicePaid.toFixed(2)} paid).`;
                }
            } else if (selectedSettlement === 'store_credit') {
                if (bucketADueCleared > 0) {
                    noticeText = `Credit of ৳${bucketADueCleared.toFixed(2)} clears invoice due. The remaining ৳${bucketBNetBalance.toFixed(2)} is credited to patient's store wallet.`;
                } else {
                    noticeText = `Full ৳${netCreditNote.toFixed(2)} will be credited to patient's wallet advance / store credit for future visits.`;
                }
            } else { // due_reduction
                if (bucketBNetBalance > 0) {
                    noticeText = `Entire invoice due of ৳${bucketADueCleared.toFixed(2)} is cleared. The remaining ৳${bucketBNetBalance.toFixed(2)} is retained as Store Credit.`;
                } else {
                    noticeText = `Credit of ৳${bucketADueCleared.toFixed(2)} is applied to reduce the invoice due.`;
                }
            }
            $('#allocationLiveNoticeText').text(noticeText);
        }

        // Proportional Doctor commission calculation suggestion
        if (currentSaleData && currentSaleData.sale && currentSaleData.sale.doctor_id) {
            const origTotal = parseFloat(currentSaleData.sale.total_amount) || 1;
            const origComm = parseFloat(currentSaleData.sale.doctor_commission) || 0;
            if (origComm > 0 && origTotal > 0) {
                const propComm = (netCreditNote / origTotal) * origComm;
                $('#doctor_commission_adjusted').val(propComm.toFixed(2));
            }
        }
    }
});
</script>
@endpush
