@extends('admin.layouts.master')
@section('page_title', 'New Sale')
@section('breadcrumb_parent', 'Sales')
@section('breadcrumb_parent_url', url('admin/sale'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/sales-invoice.css') }}">
@endpush

@section('content')
<div class="container-fluid sale-page">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-file-invoice-dollar" style="color: var(--accent-500); margin-right: 8px;"></i>New Sale</h1>
            <p>Create an invoice for products or services in three quick steps</p>
        </div>
        <div class="header-actions">
            @can('permission', 'sale_list.index')
                <a href="{{ url('admin/sale') }}" class="btn btn-secondary btn-sm" id="btn-sale-list">
                    <i class="fas fa-list mr-1"></i>Sale List
                </a>
            @endcan
            <button type="submit" form="saleForm" class="btn btn-primary btn-sm" id="btn-save-sale-top">
                <i class="fas fa-check mr-1"></i>Save Sale
            </button>
        </div>
    </div>

    @include('admin.alert_message')

    @if(isset($fromProposal))
        <div class="alert alert-info d-flex align-items-center mb-3" style="background:#eff6ff; border:1px solid #bfdbfe; color:#1e40af; border-radius:8px; padding:12px 16px;">
            <i class="fas fa-file-invoice text-primary mr-3" style="font-size:24px;"></i>
            <div style="flex: 1;">
                <div class="font-weight-bold" style="font-size: 14px;">Converting Quotation: {{ $fromProposal->proposal_no }} &mdash; {{ $fromProposal->title }}</div>
                <div style="font-size: 12px; color: #3b82f6;">
                    Recipient: <strong>{{ $fromProposal->display_recipient_name }}</strong>
                    @if($fromProposal->recipient_phone) &bull; {{ $fromProposal->recipient_phone }} @endif
                    &bull; Quotation Total: <strong>৳{{ number_format($fromProposal->grand_total, 2) }}</strong>
                </div>
            </div>
            <a href="{{ route('proposal.show', $fromProposal->id) }}" target="_blank" class="btn btn-outline-primary btn-sm ml-2">
                <i class="fas fa-external-link-alt mr-1"></i>View Quote
            </a>
        </div>
    @endif

    <form id="saleForm" action="{{ url('admin/sale') }}" enctype="multipart/form-data" method="post"
        data-product-search="{{ url('/admin/product-search') }}"
        data-customer-search="{{ url('/admin/customer-search') }}"
        data-product-info="{{ url('admin/product-info') }}"
        data-sale-add-row="{{ route('sale.add-row') }}"
        data-sale-add-service-row="{{ route('sale.add-service-row') }}"
        data-product-numbers-numbers="{{ url('admin/product-serial-numbers') }}"
        data-customer-redeem-points="{{ url('/admin/customer-redeem-points') }}"
        data-point-value="{{ \App\Models\LoyaltySetting::current()->point_value }}"
        data-min-redeem-points="{{ \App\Models\LoyaltySetting::current()->min_redeem_points }}"
        data-max-redeem-percent="{{ \App\Models\LoyaltySetting::current()->max_redeem_percent }}">
        @csrf
        @if(isset($fromProposal))
            <input type="hidden" name="from_proposal_id" value="{{ $fromProposal->id }}">
        @endif

        <div class="sale-layout">
            {{-- ================= MAIN COLUMN ================= --}}
            <div class="sale-main">

                {{-- STEP 1: Customer & Invoice --}}
                <div class="sale-card crm-animate-in delay-1">
                    <div class="sale-card-header">
                        <div class="sale-card-title">
                            <span class="sale-step">1</span>
                            <div>
                                <h3>Customer &amp; Invoice Details</h3>
                                <small>Who is buying and how they pay</small>
                            </div>
                        </div>
                    </div>
                    <div class="sale-card-body">
                        <div class="sale-grid">
                            <div class="sale-field sale-col-8">
                                <label for="customer_id">Customer <i class="req">*</i></label>
                                <div class="sale-input-group">
                                    <div class="sale-input-grow">
                                        <select class="form-control customer" name="customer_id" id="customer_id" required>
                                            <option value="">Search by name or phone…</option>
                                            @if (!empty($prefillCustomer))
                                                <option value="{{ $prefillCustomer->id }}" selected>{{ $prefillCustomer->lead->full_name ?? 'Customer' }}</option>
                                            @endif
                                        </select>
                                    </div>
                                    <button type="button" class="btn-soft-accent" data-toggle="modal" data-target="#customerModal" id="btn-add-customer">
                                        <i class="fas fa-user-plus"></i> New
                                    </button>
                                </div>
                            </div>
                            <div class="sale-field sale-col-4">
                                <label for="date">Invoice Date <i class="req">*</i></label>
                                <input type="date" name="date" value="{{ date('Y-m-d') }}" class="form-control date" id="date" required>
                            </div>

                            <div class="sale-field sale-col-4">
                                <label for="payment_type_id">Payment Method <i class="req">*</i></label>
                                <select name="payment_type_id" class="form-control select required" id="payment_type_id" required>
                                    <option selected="selected" value="">Select payment method</option>
                                    @foreach ($paymentTypes as $key => $val)
                                        <option value="{{ $key }}">{{ $val }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sale-field sale-col-4">
                                <label for="reference_by">Referred By</label>
                                <select class="form-control doctor-select" name="reference_by" id="reference_by">
                                    <option value="">Select Doctor / Referrer (Optional)</option>
                                    @foreach ($doctors ?? [] as $doctor)
                                        @php
                                            $typePrefix = match($doctor->referrer_type ?? 'doctor') {
                                                'quack' => '[Quack/RMP] ',
                                                'agent' => '[RF Agent] ',
                                                default => '[Doctor] '
                                            };
                                        @endphp
                                        <option value="{{ $doctor->id }}"
                                            {{ (isset($fromProposal) && $fromProposal->doctor_id == $doctor->id) ? 'selected' : '' }}
                                            data-referrer-type="{{ $doctor->referrer_type ?? 'doctor' }}"
                                            data-product-commission-percent="{{ $doctor->product_commission_percent }}"
                                            data-product-commission-amount="{{ $doctor->product_commission_amount }}"
                                            data-service-commission-percent="{{ $doctor->service_commission_percent }}"
                                            data-service-commission-amount="{{ $doctor->service_commission_amount }}">
                                            {{ $typePrefix }}{{ $doctor->name }} ({{ $doctor->qualification ?? 'N/A' }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sale-field sale-col-4" id="doctor-chamber-wrap" style="display: none;">
                                <label for="doctor_chamber_id"><i class="fas fa-hospital-alt text-primary mr-1"></i>Doctor Chamber / Location</label>
                                <select class="form-control select" name="doctor_chamber_id" id="doctor_chamber_id">
                                    <option value="">Select Chamber (Optional)</option>
                                </select>
                            </div>
                            <div class="sale-field sale-col-4">
                                <label for="delivery_status">Delivery Status</label>
                                <select name="delivery_status" class="form-control select2" id="delivery_status">
                                    @foreach (\App\Enums\DeliveryStatus::options() as $option)
                                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="sale-field sale-col-12">
                                <label for="details">Notes / Details</label>
                                <input class="form-control" id="details" type="text" name="details" 
                                    value="{{ old('details', isset($fromProposal) ? ('Converted from Quote ' . $fromProposal->proposal_no . ($fromProposal->notes ? ' - ' . $fromProposal->notes : '')) : '') }}" 
                                    placeholder="Add any note for this invoice (optional)">
                            </div>
                        </div>

                        {{-- Loyalty --}}
                        <div class="loyalty-strip">
                            <span class="loyalty-icon"><i class="fas fa-star"></i></span>
                            <div class="loyalty-info">
                                <div class="lbl">Available Loyalty Points</div>
                                <input type="text" class="form-control val-input" id="available-redeem-points" value="0.00" readonly tabindex="-1">
                            </div>
                            <div class="loyalty-redeem">
                                <label for="redeem-points-input">Redeem now</label>
                                <input type="number" class="form-control" name="redeem_points" id="redeem-points-input" value="0" min="0" step="1">
                                <small id="redeem-hint" class="text-muted" style="display:block; font-size: 11px; margin-top: 3px;"></small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- STEP 2: Items --}}
                <div class="sale-card crm-animate-in delay-2">
                    <div class="sale-card-header">
                        <div class="sale-card-title">
                            <span class="sale-step">2</span>
                            <div>
                                <h3>Items</h3>
                                <small>Add the products or services being sold</small>
                            </div>
                        </div>
                        <div class="seg-toggle" role="radiogroup" aria-label="Sale type">
                            <input type="radio" name="sale_type" id="sale_type_product" value="product" {{ (isset($fromProposal) && $fromProposal->productItems->isEmpty() && $fromProposal->serviceItems->isNotEmpty()) ? '' : 'checked' }}>
                            <label for="sale_type_product"><i class="fas fa-box"></i> Product</label>
                            <input type="radio" name="sale_type" id="sale_type_service" value="service" {{ (isset($fromProposal) && $fromProposal->productItems->isEmpty() && $fromProposal->serviceItems->isNotEmpty()) ? 'checked' : '' }}>
                            <label for="sale_type_service"><i class="fas fa-concierge-bell"></i> Service</label>
                        </div>
                    </div>

                    <div class="commission-bar">
                        <span><i class="fas fa-user-md mr-1"></i> Doctor commission calculation</span>
                        <div id="product_commission_div" {!! (isset($fromProposal) && $fromProposal->productItems->isEmpty() && $fromProposal->serviceItems->isNotEmpty()) ? 'style="display: none;"' : '' !!}>
                            <label class="commission-chip" for="is_percent_product_commission">
                                <input type="checkbox" name="is_percent_product_commission" id="is_percent_product_commission" value="1" checked>
                                Use percentage for product commission
                            </label>
                        </div>
                        <div id="service_commission_div" {!! (isset($fromProposal) && $fromProposal->productItems->isEmpty() && $fromProposal->serviceItems->isNotEmpty()) ? '' : 'style="display: none;"' !!}>
                            <label class="commission-chip" for="is_percent_service_commission">
                                <input type="checkbox" name="is_percent_service_commission" id="is_percent_service_commission" value="1" checked>
                                Use percentage for service commission
                            </label>
                        </div>
                    </div>

                    {{-- Product items --}}
                    <div class="items-scroll" id="product-items-wrap" {!! (isset($fromProposal) && $fromProposal->productItems->isEmpty() && $fromProposal->serviceItems->isNotEmpty()) ? 'style="display: none;"' : '' !!}>
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th style="min-width: 260px;">Product</th>
                                    <th style="width: 150px;">Batch</th>
                                    <th style="width: 90px;" class="text-center">Stock</th>
                                    <th style="min-width: 180px;">Serial No</th>
                                    <th style="width: 90px;" class="text-center">Qty</th>
                                    <th style="width: 120px;" class="text-right">Unit Price</th>
                                    <th style="width: 90px;" class="text-right">Disc %</th>
                                    <th style="width: 110px;" class="text-right">Disc ৳</th>
                                    <th style="width: 130px;" class="text-right">Total</th>
                                    <th style="width: 60px;"></th>
                                </tr>
                            </thead>
                            <tbody class="neworderbody">
                                @if(isset($fromProposal) && $fromProposal->productItems->isNotEmpty())
                                    @foreach($fromProposal->productItems as $idx => $pItem)
                                        <tr data-index="{{ $idx }}">
                                            <td>
                                                <select class="form-control product" name="product_id[]" required>
                                                    @if($pItem->product)
                                                        <option value="{{ $pItem->product_id }}" selected>{{ $pItem->product->name }}</option>
                                                    @else
                                                        <option value="">Search product…</option>
                                                    @endif
                                                </select>
                                            </td>
                                            <td>
                                                <select class="form-control batch" name="batch_id[]">
                                                    <option value="">Select Batch</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" autocomplete="off" name="stock_qty[]" class="form-control stock-qty" placeholder="—" readonly tabindex="-1">
                                            </td>
                                            <td>
                                                <select class="form-control serial-no" multiple name="serial_no[{{ $idx }}][]">
                                                    <option value="">Select Serial No</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" name="qty[]" class="form-control qty" value="{{ $pItem->qty }}" placeholder="0" required>
                                            </td>
                                            <td>
                                                <input type="hidden" name="purchase_price[]" class="form-control purchase-price" value="{{ $pItem->unit_price }}">
                                                <input type="text" name="unit_price[]" class="form-control unit-price" value="{{ number_format($pItem->unit_price, 2, '.', '') }}" placeholder="0.00" required>
                                            </td>
                                            <td>
                                                <input type="text" name="discount[]" class="form-control discount" value="{{ number_format($pItem->discount_percent ?? 0, 2, '.', '') }}">
                                            </td>
                                            <td>
                                                <input type="text" name="discount_taka[]" class="form-control discount-taka" value="{{ number_format($pItem->discount ?? 0, 2, '.', '') }}">
                                            </td>
                                            <td>
                                                <input type="text" name="total_price[]" class="form-control total-price" value="{{ number_format($pItem->total_price, 2, '.', '') }}" placeholder="0.00" readonly="readonly" tabindex="-1">
                                            </td>
                                            <td>
                                                @if($loop->first)
                                                    <span class="row-lock" title="First line"><i class="fas fa-lock"></i></span>
                                                @else
                                                    <button type="button" class="btn btn-danger btn-sm delete" title="Remove line"><i class="fas fa-trash-alt"></i></button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td>
                                            <select class="form-control product" name="product_id[]">
                                                <option value="">Search product…</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select class="form-control batch" name="batch_id[]">
                                                <option value="">Select Batch</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" autocomplete="off" name="stock_qty[]" class="form-control stock-qty" placeholder="—" readonly tabindex="-1">
                                        </td>
                                        <td>
                                            <select class="form-control serial-no" multiple name="serial_no[0][]">
                                                <option value="">Select Serial No</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="qty[]" class="form-control qty" placeholder="0">
                                        </td>
                                        <td>
                                            <input type="hidden" name="purchase_price[]" class="form-control purchase-price">
                                            <input type="text" name="unit_price[]" class="form-control unit-price" placeholder="0.00">
                                        </td>
                                        <td>
                                            <input type="text" name="discount[]" class="form-control discount" value="0">
                                        </td>
                                        <td>
                                            <input type="text" name="discount_taka[]" class="form-control discount-taka" value="0">
                                        </td>
                                        <td>
                                            <input type="text" name="total_price[]" class="form-control total-price" placeholder="0.00" readonly="readonly" tabindex="-1">
                                        </td>
                                        <td>
                                            <span class="row-lock" title="First line"><i class="fas fa-lock"></i></span>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                    {{-- Service items --}}
                    <div class="items-scroll" id="service-items-wrap" {!! (isset($fromProposal) && $fromProposal->productItems->isEmpty() && $fromProposal->serviceItems->isNotEmpty()) ? '' : 'style="display: none;"' !!}>
                        <table class="items-table service-table">
                            <thead class="service-head" {!! (isset($fromProposal) && $fromProposal->productItems->isEmpty() && $fromProposal->serviceItems->isNotEmpty()) ? '' : 'style="display: none;"' !!}>
                                <tr>
                                    <th style="min-width: 280px;">Service Type <i class="text-danger">*</i></th>
                                    <th style="width: 100px;" class="text-center">Qty <i class="text-danger">*</i></th>
                                    <th style="width: 140px;" class="text-right">Unit Price <i class="text-danger">*</i></th>
                                    <th style="width: 100px;" class="text-right">Disc %</th>
                                    <th style="width: 120px;" class="text-right">Disc ৳</th>
                                    <th style="width: 140px;" class="text-right">Total</th>
                                    <th style="width: 60px;"></th>
                                </tr>
                            </thead>
                            <tbody class="service-body">
                                @if(isset($fromProposal) && $fromProposal->serviceItems->isNotEmpty())
                                    @foreach($fromProposal->serviceItems as $sIdx => $sItem)
                                        <tr data-index="{{ $sIdx }}" class="service-row">
                                            <td>
                                                <select class="form-control service-type" name="service_type_id[]">
                                                    <option value="">Select Service Type</option>
                                                    @foreach($serviceTypes as $serviceType)
                                                        <option value="{{ $serviceType->id }}" {{ $sItem->service_id == $serviceType->id ? 'selected' : '' }} data-price="{{ $serviceType->price ?? 0 }}">{{ $serviceType->service_type_name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" name="service_qty[]" class="form-control service-qty" value="{{ $sItem->qty }}" required>
                                            </td>
                                            <td>
                                                <input type="text" name="service_price[]" class="form-control service-price" value="{{ number_format($sItem->unit_price, 2, '.', '') }}" placeholder="0.00" required>
                                            </td>
                                            <td>
                                                <input type="text" name="service_discount[]" class="form-control service-discount" value="{{ number_format($sItem->discount_percent ?? 0, 2, '.', '') }}">
                                            </td>
                                            <td>
                                                <input type="text" name="service_discount_taka[]" class="form-control service-discount-taka" value="{{ number_format($sItem->discount ?? 0, 2, '.', '') }}">
                                            </td>
                                            <td>
                                                <input type="text" name="service_total_price[]" class="form-control service-total-price" value="{{ number_format($sItem->total_price, 2, '.', '') }}" placeholder="0.00" readonly="readonly" tabindex="-1">
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-danger btn-sm delete-service" title="Remove line"><i class="fas fa-trash-alt"></i></button>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            </tbody>
                        </table>
                    </div>

                    <div class="items-footer">
                        <div>
                            <button type="button" class="btn-add-line add" id="add_product_btn" {!! (isset($fromProposal) && $fromProposal->productItems->isEmpty() && $fromProposal->serviceItems->isNotEmpty()) ? 'style="display: none;"' : '' !!}>
                                <i class="fas fa-plus-circle"></i> Add product line
                            </button>
                            <button type="button" class="btn-add-line service add-service" id="add_service_btn" {!! (isset($fromProposal) && $fromProposal->productItems->isEmpty() && $fromProposal->serviceItems->isNotEmpty()) ? '' : 'style="display: none;"' !!}>
                                <i class="fas fa-plus-circle"></i> Add service line
                            </button>
                        </div>
                        <div class="items-count"><b id="items-count">1</b> line item(s)</div>
                    </div>
                </div>
            </div>

            {{-- ================= SUMMARY COLUMN ================= --}}
            <aside class="sale-aside">
                <div class="sale-card summary-card crm-animate-in delay-3">
                    <div class="summary-hero">
                        <div class="lbl">Grand Total (৳)</div>
                        <input type="text" class="form-control grand-total" name="total_amount" value="{{ isset($fromProposal) ? number_format($fromProposal->grand_total, 2, '.', '') : '0.00' }}" readonly="readonly" tabindex="-1">
                        <div class="sub">Step 3 — review &amp; collect payment</div>
                    </div>

                    <div class="summary-body">
                        <div class="summary-row">
                            <span class="k"><i class="fas fa-receipt"></i>Sub Total</span>
                            <input type="text" class="form-control v-plain sub-total" name="sub_total" value="{{ isset($fromProposal) ? number_format($fromProposal->subtotal, 2, '.', '') : '0.00' }}" placeholder="0.00" readonly tabindex="-1">
                        </div>
                        <div class="summary-row">
                            <label class="k mb-0" for="vat"><i class="fas fa-percent"></i>VAT (৳)</label>
                            <input type="text" class="form-control v-input vat" id="vat" name="vat" value="{{ isset($fromProposal) ? number_format($fromProposal->vat_amount, 2, '.', '') : '0.00' }}" placeholder="0.00">
                        </div>
                        <div class="summary-row">
                            <label class="k mb-0" for="invoice_discount"><i class="fas fa-tag"></i>Invoice Discount</label>
                            <input type="text" class="form-control v-input invoice-discount" id="invoice_discount" name="invoice_discount" value="{{ isset($fromProposal) ? number_format($fromProposal->discount_amount, 2, '.', '') : '0.00' }}" placeholder="0.00">
                        </div>
                        <div class="summary-row is-discount">
                            <span class="k"><i class="fas fa-tags"></i>Total Discount</span>
                            <input type="text" class="form-control v-plain total-discount" name="total_discount" value="0.00" readonly="readonly" tabindex="-1">
                        </div>
                        <div class="summary-row">
                            <label class="k mb-0" for="reference_amount"><i class="fas fa-user-md"></i>Doctor Commission</label>
                            <input type="text" class="form-control v-input reference-amount" id="reference_amount" name="reference_amount" value="0.00" placeholder="0.00">
                        </div>
                        <div id="doc-comm-breakdown" style="display:none; font-size:11px; color:#4338ca; background:#eef2ff; border-radius:6px; padding:5px 8px; margin:-4px 0 10px 0; border-left:3px solid #6366f1; line-height: 1.3;"></div>
                    {{-- Customer Advance Offer Banner (Part C) --}}
                    <div id="wrap-customer-advance" style="display:none; margin-bottom:12px; padding:12px; background: #f0fdf4; border: 1.5px solid #86efac; border-radius: 8px;">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:4px;">
                            <span style="font-size:12px; font-weight:700; color:#15803d;">
                                <i class="fas fa-hand-holding-usd mr-1"></i>Advance Available
                            </span>
                            <span class="badge badge-success" id="badge-advance-amount">৳0.00</span>
                        </div>
                        <div style="font-size:11.5px; color:#166534; margin-bottom:8px;" id="text-advance-details">
                            This customer has an advance on file.
                        </div>
                        <div class="d-flex align-items-center" style="gap:8px;">
                            <button type="button" class="btn btn-xs btn-success" id="btn-apply-advance">
                                <i class="fas fa-check-circle mr-1"></i>Apply Advance to this Bill
                            </button>
                            <button type="button" class="btn btn-xs btn-outline-secondary" id="btn-cancel-advance" style="display:none;">
                                Remove
                            </button>
                        </div>
                        <input type="hidden" name="applied_advance_id" id="applied_advance_id" value="">
                        <input type="hidden" name="applied_advance_amount" id="applied_advance_amount" value="0">
                    </div>

                    <div class="summary-pay">
                        <div class="summary-pay-head">
                            <label for="paid_amount">Amount Received</label>
                            <button type="button" class="btn-pay-full" id="btn-pay-full"><i class="fas fa-check mr-1"></i>Full payment</button>
                        </div>
                        <input type="text" class="form-control paid-amount" id="paid_amount" name="paid" value="0.00" placeholder="0.00">
                        <div class="summary-due" id="summary-due">
                            <span class="k">Due Amount</span>
                            <input type="text" class="form-control due-amount" name="due_amount" value="0.00" placeholder="0.00" readonly="readonly" tabindex="-1">
                        </div>
                        <div class="summary-due-date" id="wrap-due-date" style="display:none; margin-top:10px; padding:10px 12px; background:#fff1f2; border:1px solid #fecdd3; border-radius:8px;">
                            <label for="due_date" style="display:flex; align-items:center; justify-content:space-between; font-size:11.5px; font-weight:700; color:#b91c1c; margin-bottom:4px;">
                                <span><i class="far fa-calendar-alt mr-1"></i>Due Promise Date</span>
                                <small style="font-weight:600; color:#e11d48;">Patient Promise</small>
                            </label>
                            <input type="date" class="form-control" id="due_date" name="due_date" value="{{ old('due_date') }}" min="{{ date('Y-m-d') }}" style="background:#fff; border-color:#fda4af; font-size:12.5px; font-weight:600;">
                        </div>

                        {{-- Direct Handover / Quick Checkout Mode Switch --}}
                        <div class="direct-handover-box" style="margin-top:12px; background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%); border: 1.5px solid #a7f3d0; border-radius: 8px; padding: 10px 12px;">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="chk_direct_handover" name="is_direct_handover" value="1" checked>
                                <label class="custom-control-label" for="chk_direct_handover" style="font-size: 12.5px; font-weight: 700; color: #065f46; cursor: pointer;">
                                    <i class="fas fa-bolt text-warning mr-1"></i>Direct Sale (Handover / Delivered Now)
                                </label>
                            </div>
                            <div id="direct_handover_hint" style="font-size: 11px; color: #047857; margin-top: 3px; margin-left: 36px; line-height: 1.3;">
                                Product/test handed over immediately. Will be marked <b>Delivered</b>.
                            </div>
                        </div>

                        {{-- Hidden delivery_status field --}}
                        <input type="hidden" name="delivery_status" id="form_delivery_status" value="delivered">

                        {{-- Scheduled Delivery / Report Box (Visible when direct handover is toggled OFF) --}}
                        <div class="summary-delivery-box" id="wrap-delivery-box" style="display:none; margin-top:10px; padding:10px 12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:5px;">
                                <label for="delivery_date_time" style="font-size:11.5px; font-weight:700; color:#1e293b; margin-bottom:0;">
                                    <i class="fas fa-clock mr-1" style="color:var(--accent-500);"></i>
                                    <span id="lbl_sale_delivery_title">Promised Delivery / Report Time</span>
                                </label>
                                <span id="badge_sale_track" style="font-size:10px; font-weight:700; padding:1px 5px; border-radius:4px; background:#eef2ff; color:#4338ca;">Service</span>
                            </div>
                            <input type="datetime-local" class="form-control" id="delivery_date_time" name="delivery_date_time" value="{{ old('delivery_date_time') }}" style="font-size:12px; font-weight:600;">
                            <div class="d-flex flex-wrap gap-1 mt-1" style="gap:4px;">
                                <button type="button" class="btn btn-xs btn-light border py-0 px-1" style="font-size:10.5px;" onclick="setCreateTimePreset(1)">+1h</button>
                                <button type="button" class="btn btn-xs btn-light border py-0 px-1" style="font-size:10.5px;" onclick="setCreateTimePreset(2)">+2h</button>
                                <button type="button" class="btn btn-xs btn-light border py-0 px-1" style="font-size:10.5px;" onclick="setCreateTimePreset('today_5pm')">Today 5pm</button>
                                <button type="button" class="btn btn-xs btn-light border py-0 px-1" style="font-size:10.5px;" onclick="setCreateTimePreset('tomorrow_11am')">Tmrw 11am</button>
                            </div>
                            
                            <div id="wrap_sale_lab" style="margin-top:8px; display:none;">
                                <label for="sale_lab_name" style="font-size:11px; font-weight:700; color:#475569; margin-bottom:2px; display:block;">
                                    <i class="fas fa-flask mr-1" style="color:#7c3aed;"></i>Send to Lab (Ear Mold / Fabrication)
                                </label>
                                <input type="text" class="form-control" id="sale_lab_name" name="lab_name" value="{{ old('lab_name') }}" placeholder="e.g. In-house Lab, Signia Lab" style="font-size:12px;" list="lab_suggestions_create">
                                <datalist id="lab_suggestions_create">
                                    <option value="In-house Lab">
                                    <option value="Signia Custom Mold Lab">
                                    <option value="Phonak Shell Fabrication">
                                    <option value="Siemens Ear Mold Department">
                                </datalist>
                            </div>
                        </div>
                    </div>

                    <div class="summary-actions">
                        {{-- Direct Checkout button --}}
                        <button type="button" class="btn-save-sale" id="btn-quick-checkout" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color:#fff; font-weight:700; font-size:14px; box-shadow:0 4px 14px rgba(16,185,129,0.35); border:none; border-radius:8px; padding:11px 16px; width:100%; display:flex; align-items:center; justify-content:center; gap:8px;">
                            <i class="fas fa-shopping-bag"></i> ⚡ Direct Checkout &amp; Deliver
                        </button>

                        {{-- Save as Scheduled / Lab Order button (Visible when scheduled) --}}
                        <button type="submit" class="btn-save-sale" id="btn-save-sale" style="display:none; background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);">
                            <i class="fas fa-calendar-check mr-1"></i> Save as Scheduled / Lab Order
                        </button>

                        <a href="{{ url('admin/sale') }}" class="btn-cancel-sale" id="btn-cancel-sale">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <p class="summary-note"><i class="fas fa-info-circle mr-1"></i>Totals update automatically as you edit items.</p>
                    </div>
                </div>
            </aside>
        </div>
    </form>
</div>

{{-- Customer Creation Modal --}}
<div class="modal fade sale-modal" id="customerModal" tabindex="-1" role="dialog" aria-labelledby="customerModalTitle">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 560px;">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center">
                    <span class="modal-icon"><i class="fas fa-user-plus"></i></span>
                    <h5 class="modal-title" id="customerModalTitle">
                        Add New Customer
                        <small>The customer will be selected automatically</small>
                    </h5>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="customerForm" action="{{ url('admin/customers') }}" method="post">
                @csrf
                <div class="modal-body">
                    <div class="sale-grid">
                        <div class="sale-field sale-col-12">
                            <label for="full_name">Full Name <i class="req">*</i></label>
                            <input type="text" class="form-control" id="full_name" placeholder="e.g. Rahim Uddin" name="full_name" autocomplete="off" required>
                        </div>
                        <div class="sale-field sale-col-8">
                            <label for="phone_number">Phone Number <i class="req">*</i></label>
                            <input type="text" class="form-control" id="phone_number" placeholder="01XXXXXXXXX" name="phone_number" autocomplete="off" required>
                        </div>
                        <div class="sale-field sale-col-4">
                            <label for="age">Age <i class="req">*</i></label>
                            <input type="number" class="form-control" id="age" placeholder="Age" name="age" min="0" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveCustomerBtn">
                        <i class="fas fa-spinner fa-spin d-none"></i> Save Customer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        window.crmDoctorRules = @json(($doctors ?? collect())->mapWithKeys(fn($d) => [$d->id => $d->getCommissionRulesArray()]));
    </script>
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('assets/sales.js') }}?v={{ @filemtime(public_path('assets/sales.js')) ?: time() }}"></script>
    <script>
        // UI-only enhancements for the redesigned sale form (calculation logic lives in sales.js)
        $(function () {
            @if (!empty($prefillCustomer))
                // Customer pre-selected from a won deal: load redeem points as if picked manually
                $('.customer').trigger({ type: 'select2:select', params: { data: { id: {{ $prefillCustomer->id }} } } });
                checkCustomerAdvance({{ $prefillCustomer->id }});
            @endif

            // Customer Advance Detection (Part C)
            var activeAdvance = null;
            var advanceApplied = false;

            function checkCustomerAdvance(customerId) {
                if (!customerId) {
                    $('#wrap-customer-advance').slideUp(150);
                    activeAdvance = null;
                    advanceApplied = false;
                    $('#applied_advance_id').val('');
                    $('#applied_advance_amount').val(0);
                    return;
                }

                $.get('{{ url("admin/customers") }}/' + customerId + '/active-advance', function (res) {
                    if (res.success && res.total_balance > 0) {
                        activeAdvance = res.advances[0];
                        $('#badge-advance-amount').text('৳' + res.total_balance.toFixed(2));
                        var aptText = activeAdvance.appointment_id ? (' from Appointment #' + activeAdvance.appointment_id) : '';
                        $('#text-advance-details').html('This customer has an advance of <strong>৳' + res.total_balance.toFixed(2) + '</strong>' + aptText + ' &mdash; apply to this bill?');
                        $('#wrap-customer-advance').slideDown(150);
                        $('#btn-apply-advance').show();
                        $('#btn-cancel-advance').hide();
                        advanceApplied = false;
                        $('#applied_advance_id').val('');
                        $('#applied_advance_amount').val(0);
                    } else {
                        $('#wrap-customer-advance').slideUp(150);
                        activeAdvance = null;
                    }
                });
            }

            $('.customer').on('change select2:select', function () {
                var cId = $(this).val();
                checkCustomerAdvance(cId);
            });

            $('#btn-apply-advance').on('click', function () {
                if (!activeAdvance) return;
                var grand = parseFloat($('.grand-total').val()) || 0;
                var bal = parseFloat(activeAdvance.balance) || 0;
                var toApply = Math.min(grand, bal);

                if (toApply <= 0 && grand <= 0) {
                    alert('Please add products or services to the bill before applying advance.');
                    return;
                }

                $('#applied_advance_id').val(activeAdvance.id);
                $('#applied_advance_amount').val(toApply.toFixed(2));
                advanceApplied = true;

                $('.paid-amount').val(toApply.toFixed(2)).trigger('keyup');
                $('#btn-apply-advance').hide();
                $('#btn-cancel-advance').show();
                $('#text-advance-details').html('<span class="text-success font-weight-bold"><i class="fas fa-check-circle mr-1"></i>৳' + toApply.toFixed(2) + ' Advance Applied from ' + activeAdvance.receipt_no + '</span>');
            });

            $('#btn-cancel-advance').on('click', function () {
                advanceApplied = false;
                $('#applied_advance_id').val('');
                $('#applied_advance_amount').val(0);
                $('.paid-amount').val('0.00').trigger('keyup');
                $('#btn-apply-advance').show();
                $('#btn-cancel-advance').hide();
                if (activeAdvance) {
                    $('#text-advance-details').html('This customer has an advance of <strong>৳' + parseFloat(activeAdvance.balance).toFixed(2) + '</strong> &mdash; apply to this bill?');
                }
            });

            var $productWrap = $('#product-items-wrap');
            var $serviceWrap = $('#service-items-wrap');

            function updateCount() {
                var type = $('input[name="sale_type"]:checked').val();
                var n = type === 'service' ? $('.service-body tr').length : $('.neworderbody tr').length;
                $('#items-count').text(n);
            }

            function refreshDue() {
                var due = parseFloat($('.due-amount').val()) || 0;
                var grand = parseFloat($('.grand-total').val()) || 0;
                $('#summary-due').toggleClass('is-clear', grand > 0 && due <= 0);
                if (due > 0.009) {
                    $('#wrap-due-date').slideDown(150);
                } else {
                    $('#wrap-due-date').slideUp(150);
                }
            }

            function syncSaleType() {
                var type = $('input[name="sale_type"]:checked').val();
                $productWrap.toggle(type === 'product');
                $serviceWrap.toggle(type === 'service');
                if (type === 'service' && $('.service-body tr').length === 0) {
                    $('#add_service_btn').trigger('click');
                }
                if (type === 'service') {
                    $('#lbl_sale_delivery_title').text('Promised Report Delivery');
                    $('#badge_sale_track').text('Assessment Report').css({'background':'#eef2ff', 'color':'#4338ca'});
                    $('#wrap_sale_lab').hide();
                } else {
                    $('#lbl_sale_delivery_title').text('Promised Device Fitting / Delivery');
                    $('#badge_sale_track').text('Hearing Aid / Product').css({'background':'#f5f3ff', 'color':'#7c3aed'});
                    $('#wrap_sale_lab').show();
                }
                updateCount();
            }

            window.setCreateTimePreset = function(preset) {
                var now = new Date();
                if (typeof preset === 'number') {
                    now.setHours(now.getHours() + preset);
                } else if (preset === 'today_5pm') {
                    now.setHours(17, 0, 0, 0);
                } else if (preset === 'tomorrow_11am') {
                    now.setDate(now.getDate() + 1);
                    now.setHours(11, 0, 0, 0);
                }
                function pad(n) { return n < 10 ? '0' + n : n; }
                var str = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
                var input = document.getElementById('delivery_date_time');
                if (input) input.value = str;
            };

            $('input[name="sale_type"]').on('change', syncSaleType);

            // Keep the item counter in sync with row add/remove
            if (window.MutationObserver) {
                var obs = new MutationObserver(updateCount);
                $('.neworderbody, .service-body').each(function () {
                    obs.observe(this, { childList: true });
                });
            }

            // Toggle Direct Handover Mode
            $('#chk_direct_handover').on('change', function() {
                var isDirect = $(this).is(':checked');
                if (isDirect) {
                    $('#form_delivery_status').val('delivered');
                    $('#wrap-delivery-box').slideUp(150);
                    $('#btn-quick-checkout').show();
                    $('#btn-save-sale').hide();
                    $('#direct_handover_hint').html('Product/test handed over immediately. Will be marked <b>Delivered</b>.');
                } else {
                    $('#form_delivery_status').val('ordered');
                    $('#wrap-delivery-box').slideDown(150);
                    $('#btn-quick-checkout').hide();
                    $('#btn-save-sale').show();
                    $('#direct_handover_hint').html('Scheduled for later pickup or lab fabrication. Status: <b>Ordered / Booked</b>.');
                }
            });

            // 1-Click Direct Checkout & Deliver Button
            $('#btn-quick-checkout').on('click', function(e) {
                e.preventDefault();
                var grand = parseFloat($('.grand-total').val()) || 0;
                var paid = parseFloat($('#paid_amount').val()) || 0;
                if (paid <= 0.001 && grand > 0) {
                    $('.paid-amount').val(grand.toFixed(2)).trigger('keyup');
                }
                $('#form_delivery_status').val('delivered');
                $('#saleForm').submit();
            });

            // One-click full payment
            $('#btn-pay-full').on('click', function () {
                var grand = parseFloat($('.grand-total').val()) || 0;
                $('.paid-amount').val(grand.toFixed(2)).trigger('keyup');
            });

            // Select the whole value when focusing numeric inputs for faster editing
            $(document).on('focus', '#saleForm .qty, #saleForm .unit-price, #saleForm .discount, #saleForm .discount-taka, ' +
                '#saleForm .service-qty, #saleForm .service-price, #saleForm .service-discount, #saleForm .service-discount-taka, ' +
                '#saleForm .vat, #saleForm .invoice-discount, #saleForm .paid-amount, #saleForm .reference-amount', function () {
                var el = this;
                setTimeout(function () { el.select(); }, 0);
            });

            $(document).on('keyup change input', '#saleForm', function () { setTimeout(refreshDue, 0); });
            @if(isset($fromProposal))
                setTimeout(function () {
                    $('.neworderbody tr').each(function () {
                        var $p = $(this).find('.product');
                        if ($p.val()) {
                            $p.trigger('change');
                        }
                    });
                    refreshDue();
                    updateCount();
                }, 300);
            @endif

            updateCount();
            refreshDue();
        });
    </script>
@endpush
@endsection
