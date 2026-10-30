@extends('admin.layouts.master')
@section('page_title', 'Edit Sale')
@section('breadcrumb_parent', 'Sales')
@section('breadcrumb_parent_url', url('admin/sale'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/sales-invoice.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/sale-edit.css') }}?v={{ @filemtime(public_path('assets/css/sale-edit.css')) ?: time() }}">
@endpush

@section('content')
@php
    $currentDelivery = \App\Enums\DeliveryStatus::tryFrom((string) $saleMaster->delivery_status);
    $secondaryRef = $saleMaster->referrals->first(function($r) use ($saleMaster) {
        return $r->doctor_id != $saleMaster->reference_by || in_array($r->role, ['quack', 'agent']);
    });
    $primaryRef = $saleMaster->referrals->first(function($r) use ($saleMaster) {
        return $r->doctor_id == $saleMaster->reference_by;
    });
    $secondaryAmount = $secondaryRef ? (float)$secondaryRef->commission_amount : 0.0;
    $primaryAmount = $primaryRef ? (float)$primaryRef->commission_amount : max(0, (float)$saleMaster->reference_amount - $secondaryAmount);
    $subTotal = 0;
    $subTotalDiscount = 0;
    $serviceSubTotal = 0;
    $serviceSubTotalDiscount = 0;
@endphp
<div class="container-fluid sale-page sale-edit-page">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-file-invoice-dollar" style="color: var(--accent-500); margin-right: 8px;"></i>Edit Sale</h1>
            <p>
                @if($saleMaster->sale_invoice_no)<span class="se-chip" id="se-invoice-no"><i class="fas fa-hashtag"></i>{{ $saleMaster->sale_invoice_no }}</span>@endif
                @if($currentDelivery)<span class="se-chip se-chip-accent" id="se-delivery-status"><i class="fas fa-clipboard-check"></i>{{ $currentDelivery->label($saleMaster->isServiceSale()) }}</span>@endif
                <span class="se-sub">Change the invoice details, items and payment, then update.</span>
            </p>
        </div>
        <div class="header-actions">
            @can('permission', 'sale_list.index')
                <a href="{{ url('admin/sale') }}" class="btn btn-secondary btn-sm" id="btn-sale-list">
                    <i class="fas fa-list mr-1"></i>Sale List
                </a>
            @endcan
            <button type="submit" form="saleForm" class="btn btn-primary btn-sm" id="btn-save-sale-top">
                <i class="fas fa-check mr-1"></i><span>Update Sale</span>
            </button>
        </div>
    </div>

    @include('admin.alert_message')

    <form id="saleForm" action="{{ route('sale.update', $saleMaster->id) }}" enctype="multipart/form-data" method="post"
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
        @method('put')

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
                                <small>Who bought, how they pay and who referred them</small>
                            </div>
                        </div>
                    </div>
                    <div class="sale-card-body">
                        <div class="sale-grid">
                            <div class="sale-field sale-col-8">
                                <label for="customer_id">Customer <i class="req">*</i></label>
                                <select class="form-control customer" name="customer_id" id="customer_id" required>
                                    <option value="">Select</option>
                                    <option value="{{ $saleMaster->customer_id }}" selected>
                                        {{ $saleMaster->customer->lead->full_name }}
                                    </option>
                                </select>
                                @if(optional($saleMaster->customer?->lead)->phone_number)
                                    <span class="hint"><i class="fas fa-phone-alt mr-1"></i>{{ $saleMaster->customer->lead->phone_number }}</span>
                                @endif
                            </div>
                            <div class="sale-field sale-col-4">
                                <label for="date">Invoice Date <i class="req">*</i></label>
                                <input type="date" name="date" value="{{ $saleMaster->date->format('Y-m-d') }}" class="form-control date" id="date" required>
                            </div>

                            <div class="sale-field sale-col-4">
                                <label for="payment_type_id">Payment Method <i class="req">*</i></label>
                                <select name="payment_type_id" class="form-control select required" id="payment_type_id" required>
                                    <option value="">Select Payment Type</option>
                                    @foreach ($paymentTypes as $key => $val)
                                        <option value="{{ $key }}" {{ $saleMaster->payment_id == $key ? 'selected' : '' }}>{{ $val }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sale-field sale-col-8" id="wrap-digital-payment-details" style="{{ in_array($saleMaster->payment_id, [3, 5, 6, 7, 8]) ? '' : 'display: none;' }}">
                                <div class="sale-grid">
                                    <div class="sale-field sale-col-6">
                                        <label for="payment_trx_no"><i class="fas fa-receipt text-info mr-1"></i>Slip # / Approval / Trx ID</label>
                                        <input type="text" name="payment_trx_no" id="payment_trx_no" class="form-control" value="{{ old('payment_trx_no', $saleMaster->payment_trx_no) }}" placeholder="e.g. Appr 04918 or Trx ID">
                                    </div>
                                    <div class="sale-field sale-col-6">
                                        <label for="pos_terminal_name"><i class="fas fa-credit-card text-success mr-1"></i>POS Terminal / Machine</label>
                                        <input type="text" name="pos_terminal_name" id="pos_terminal_name" class="form-control" value="{{ old('pos_terminal_name', $saleMaster->pos_terminal_name) }}" placeholder="e.g. City Bank POS" list="pos_machines_edit">
                                        <datalist id="pos_machines_edit">
                                            <option value="City Bank POS">
                                            <option value="BRAC Bank POS">
                                            <option value="Eastern Bank (EBL) POS">
                                            <option value="bKash Merchant QR">
                                            <option value="Nagad Merchant">
                                        </datalist>
                                    </div>
                                </div>
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
                                            {{ $saleMaster->reference_by == $doctor->id ? 'selected' : '' }}
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
                            <div class="sale-field sale-col-4" id="doctor-chamber-wrap" style="{{ $saleMaster->reference_by ? '' : 'display: none;' }}">
                                <label for="doctor_chamber_id"><i class="fas fa-hospital-alt text-primary mr-1"></i>Doctor Chamber / Location</label>
                                <select class="form-control select" name="doctor_chamber_id" id="doctor_chamber_id" data-initial-chamber="{{ $saleMaster->doctor_chamber_id }}">
                                    <option value="">Select Chamber (Optional)</option>
                                    @if($saleMaster->reference_by)
                                        @php
                                            $selectedDoc = ($doctors ?? collect())->firstWhere('id', $saleMaster->reference_by);
                                        @endphp
                                        @if($selectedDoc)
                                            @foreach($selectedDoc->chambers as $chamber)
                                                <option value="{{ $chamber->id }}" {{ $saleMaster->doctor_chamber_id == $chamber->id ? 'selected' : '' }}>
                                                    {{ $chamber->chamber_name }} ({{ $chamber->address }})
                                                </option>
                                            @endforeach
                                        @endif
                                    @endif
                                </select>
                            </div>
                            <div class="sale-field sale-col-4">
                                <label for="delivery_status">Delivery Status</label>
                                <select name="delivery_status" class="form-control select2" id="delivery_status">
                                    @foreach (\App\Enums\DeliveryStatus::options() as $option)
                                        <option value="{{ $option['value'] }}" {{ $saleMaster->delivery_status === $option['value'] ? 'selected' : '' }}>{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Secondary / Accompanying Referrer --}}
                            <div class="sale-field sale-col-12" style="margin-top: -6px; margin-bottom: 6px;">
                                <a href="javascript:void(0)" id="toggle-secondary-referrer" class="text-primary font-weight-bold" style="font-size: 12.5px; text-decoration: none; {{ $secondaryRef ? 'display:none;' : '' }}">
                                    <i class="fas fa-plus-circle mr-1"></i>Add Accompanying Referrer (Quack / Village Doctor / RF Agent)
                                </a>
                            </div>

                            <div id="secondary-referrer-box" class="sale-field sale-col-12 se-secondary" style="{{ $secondaryRef ? '' : 'display:none;' }}">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="font-weight-bold text-dark" style="font-size: 13px;">
                                        <i class="fas fa-user-friends text-info mr-1"></i>Accompanying Referrer / Escort
                                    </span>
                                    <button type="button" class="btn btn-xs btn-outline-danger" id="btn-remove-secondary-referrer" style="font-size: 11px; padding: 2px 8px; border-radius: 6px;">
                                        <i class="fas fa-times mr-1"></i>Remove
                                    </button>
                                </div>
                                <div class="form-row">
                                    <div class="col-md-5 mb-2">
                                        <label for="secondary_reference_by" class="small font-weight-bold text-muted mb-1">Select Quack / Village Doctor / RF</label>
                                        <select class="form-control select" name="secondary_reference_by" id="secondary_reference_by">
                                            <option value="">Select Accompanying Referrer</option>
                                            @foreach ($doctors ?? [] as $doc)
                                                @php
                                                    $docTypePrefix = match($doc->referrer_type ?? 'doctor') {
                                                        'quack' => '[Quack/RMP] ',
                                                        'agent' => '[RF Agent] ',
                                                        default => '[Doctor] '
                                                    };
                                                @endphp
                                                <option value="{{ $doc->id }}" data-type="{{ $doc->referrer_type ?? 'doctor' }}" {{ ($secondaryRef && $secondaryRef->doctor_id == $doc->id) ? 'selected' : '' }}>
                                                    {{ $docTypePrefix }}{{ $doc->name }} ({{ $doc->contact_number ?? 'No phone' }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label for="secondary_reference_amount" class="small font-weight-bold text-muted mb-1">Commission for Accompanying Referrer (৳)</label>
                                        <input type="number" step="0.01" min="0" class="form-control font-weight-bold" name="secondary_reference_amount" id="secondary_reference_amount" value="{{ number_format($secondaryAmount, 2, '.', '') }}" placeholder="0.00">
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label for="secondary_role" class="small font-weight-bold text-muted mb-1">Role / Tag</label>
                                        <select name="secondary_role" id="secondary_role" class="form-control">
                                            <option value="quack" {{ ($secondaryRef && $secondaryRef->role === 'quack') ? 'selected' : '' }}>Village Doctor / Quack (RMP)</option>
                                            <option value="agent" {{ ($secondaryRef && $secondaryRef->role === 'agent') ? 'selected' : '' }}>RF / Accompanying Agent</option>
                                            <option value="doctor" {{ ($secondaryRef && $secondaryRef->role === 'doctor') ? 'selected' : '' }}>Co-Referring Doctor</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-row mt-1">
                                    <div class="col-12">
                                        <input type="text" name="secondary_notes" id="secondary_notes" class="form-control form-control-sm" value="{{ $secondaryRef ? $secondaryRef->notes : '' }}" aria-label="Notes for the accompanying referrer" placeholder="Optional notes (e.g. Brought patient in person, negotiated ৳500 cash on spot)">
                                    </div>
                                </div>
                            </div>

                            <div class="sale-field sale-col-12">
                                <label for="details">Notes / Details</label>
                                <input class="form-control" id="details" type="text" name="details" value="{{ $saleMaster->details }}" placeholder="Add any note for this invoice (optional)">
                            </div>
                        </div>

                        {{-- Loyalty --}}
                        <div class="loyalty-strip">
                            <span class="loyalty-icon"><i class="fas fa-star"></i></span>
                            <div class="loyalty-info">
                                <div class="lbl">Available Loyalty Points</div>
                                <input type="text" class="form-control val-input" id="available-redeem-points" value="{{ $saleMaster->customer->getAvailableRedeemPoints() }}" readonly tabindex="-1" aria-label="Available loyalty points">
                            </div>
                            <div class="loyalty-redeem">
                                <label for="redeem-points-input">Redeem now</label>
                                <input type="number" class="form-control" name="redeem_points" id="redeem-points-input"
                                       value="{{ $existingRedeemPoints }}"
                                       data-existing-redeemed="{{ $existingRedeemPoints }}"
                                       min="0" step="1">
                                <small id="redeem-hint" class="text-muted" style="display:block; font-size: 11px; margin-top: 3px;">Points to use in this sale</small>
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
                                <small>The products and services on this invoice</small>
                            </div>
                        </div>
                    </div>

                    <div class="commission-bar">
                        <span><i class="fas fa-user-md mr-1"></i> Doctor commission calculation</span>
                        <div id="product_commission_div" @if($saleMaster->is_percent_service_commission) style="display: none;" @endif>
                            <label class="commission-chip" for="is_percent_product_commission">
                                <input type="checkbox" name="is_percent_product_commission" id="is_percent_product_commission" value="1" @if($saleMaster->is_percent_product_commission) checked @endif>
                                Use percentage for product commission
                            </label>
                        </div>
                        <div id="service_commission_div" @if(!$saleMaster->is_percent_service_commission) style="display: none;" @endif>
                            <label class="commission-chip" for="is_percent_service_commission">
                                <input type="checkbox" name="is_percent_service_commission" id="is_percent_service_commission" value="1" @if($saleMaster->is_percent_service_commission) checked @endif>
                                Use percentage for service commission
                            </label>
                        </div>
                    </div>

                    {{-- Product items --}}
                    <div class="items-scroll" id="product-items-wrap" tabindex="0" role="region" aria-label="Product items (scrolls sideways on small screens)">
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
                                    <th style="width: 60px;"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody class="neworderbody">
                                @foreach ($saleDetail as $key => $item)
                                    @php
                                        $discountTaka = ($item->qty * $item->mrp_price / 100) * $item->discount;
                                        $totalPrice = $item->qty * $item->mrp_price - $discountTaka;
                                    @endphp
                                    <tr data-category-id="{{ $item->product?->category_id }}">
                                        <td>
                                            <input type="hidden" autocomplete="off" name="old[{{ $key }}][id]" class="form-control" value="{{ $item->id }}">
                                            <select class="form-control product" name="old[{{ $key }}][product_id]" required>
                                                <option value="">Select Product</option>
                                                <option value="{{ $item->product_id }}" selected>{{ ($item->product?->category?->category_name) . ' - ' . $item->product?->model }}</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select class="form-control batch" name="old[{{ $key }}][batch_id]" @if(empty($item->batch_id)) disabled @endif>
                                                <option value="">Select Batch</option>
                                                @if($item->batch_id)
                                                    <option value="{{ $item->batch_id }}" selected>{{ $item->batch->batch_no ?? 'Batch' }}</option>
                                                @endif
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" autocomplete="off" name="old[{{ $key }}][stock_qty]" class="form-control stock-qty" value="{{ $item->stock ?? 0 }}" readonly tabindex="-1">
                                        </td>
                                        <td>
                                            <select class="form-control serial-no" multiple name="old[{{ $key }}][serial_no][]" @if(empty($productSerials[$item->product_id])) disabled @endif>
                                                @if(isset($productSerials[$item->product_id]))
                                                    @foreach ($productSerials[$item->product_id] as $serial)
                                                        <option value="{{ $serial['serial_number'] }}" {{ $serial['selected'] ? 'selected' : '' }} data-mrp-price="{{ $serial['mrp_price'] }}">
                                                            {{ $serial['serial_number'] }}
                                                        </option>
                                                    @endforeach
                                                @else
                                                    <option value="">No serial numbers available</option>
                                                @endif
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="old[{{ $key }}][qty]" class="form-control qty" value="{{ $item->qty }}" required>
                                        </td>
                                        <td>
                                            <input type="hidden" name="old[{{ $key }}][purchase_price]" class="form-control purchase-price" value="{{ $item->purchase_price }}">
                                            <input type="text" name="old[{{ $key }}][unit_price]" class="form-control unit-price" value="{{ $item->mrp_price }}" required>
                                        </td>
                                        <td>
                                            <input type="text" name="old[{{ $key }}][discount]" class="form-control discount" value="{{ $item->discount }}">
                                        </td>
                                        <td>
                                            <input type="text" name="old[{{ $key }}][discount_taka]" class="form-control discount-taka" value="{{ number_format($discountTaka, 2) }}">
                                        </td>
                                        <td>
                                            <input type="text" name="old[{{ $key }}][total_price]" class="form-control total-price" value="{{ number_format($totalPrice, 2) }}" readonly="readonly" tabindex="-1">
                                        </td>
                                        <td>
                                        </td>
                                    </tr>
                                    @php
                                        $subTotal += $totalPrice;
                                        $subTotalDiscount += $discountTaka;
                                    @endphp
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Service items --}}
                    @if(count($serviceDetails) > 0)
                    <div class="items-scroll" id="service-items-wrap" tabindex="0" role="region" aria-label="Service items (scrolls sideways on small screens)">
                        <table class="items-table service-table">
                            <thead class="service-head">
                                <tr>
                                    <th style="min-width: 280px;">Service Type <i class="text-danger">*</i></th>
                                    <th style="width: 100px;" class="text-center">Qty <i class="text-danger">*</i></th>
                                    <th style="width: 140px;" class="text-right">Unit Price <i class="text-danger">*</i></th>
                                    <th style="width: 100px;" class="text-right">Disc %</th>
                                    <th style="width: 120px;" class="text-right">Disc ৳</th>
                                    <th style="width: 140px;" class="text-right">Total</th>
                                    <th style="width: 60px;"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody class="service-body">
                                @foreach ($serviceDetails as $key => $service)
                                    @php
                                        $serviceDiscountTaka = ($service->qty * $service->service_price / 100) * ($service->discount ?? 0);
                                        $serviceTotalPrice = $service->qty * $service->service_price - $serviceDiscountTaka;
                                    @endphp
                                    <tr>
                                        <td>
                                            <input type="hidden" autocomplete="off" name="old_service[{{ $key }}][id]" class="form-control" value="{{ $service->id }}">
                                            <select class="form-control service-type" name="old_service[{{ $key }}][service_type_id]" required>
                                                <option value="">Select Service Type</option>
                                                @if(isset($serviceTypes))
                                                    @foreach ($serviceTypes as $type)
                                                        <option value="{{ $type->id }}" {{ $service->service_type_id == $type->id ? 'selected' : '' }}>{{ $type->service_type_name }}</option>
                                                    @endforeach
                                                @endif
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="old_service[{{ $key }}][service_qty]" class="form-control service-qty" value="{{ $service->qty }}" required>
                                        </td>
                                        <td>
                                            <input type="text" name="old_service[{{ $key }}][service_price]" class="form-control service-price" value="{{ $service->service_price }}" required>
                                        </td>
                                        <td>
                                            <input type="text" name="old_service[{{ $key }}][discount]" class="form-control service-discount" value="{{ $service->discount ?? 0 }}">
                                        </td>
                                        <td>
                                            <input type="text" name="old_service[{{ $key }}][discount_taka]" class="form-control service-discount-taka" value="{{ number_format($serviceDiscountTaka, 2) }}">
                                        </td>
                                        <td>
                                            <input type="text" name="old_service[{{ $key }}][total_price]" class="form-control service-total-price" value="{{ number_format($serviceTotalPrice, 2) }}" readonly="readonly" tabindex="-1">
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-danger btn-sm delete" title="Remove line" aria-label="Remove service line"><i class="fas fa-trash-alt"></i></button>
                                        </td>
                                    </tr>
                                    @php
                                        $serviceSubTotal += $serviceTotalPrice;
                                        $serviceSubTotalDiscount += $serviceDiscountTaka;
                                    @endphp
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif

                    <div class="items-footer">
                        <div>
                            @if($saleMaster->is_percent_product_commission)
                            <button type="button" class="btn-add-line add" id="add_product_btn" title="Add Product">
                                <i class="fas fa-plus-circle"></i> Add product line
                            </button>
                            @endif
                            @if($saleMaster->is_percent_service_commission)
                            <button type="button" class="btn-add-line service add-service" id="add_service_btn" title="Add Service">
                                <i class="fas fa-plus-circle"></i> Add service line
                            </button>
                            @endif
                        </div>
                        <div class="items-count"><b id="items-count">{{ count($saleDetail) + count($serviceDetails) }}</b> line item(s)</div>
                    </div>
                </div>
            </div>

            {{-- ================= SUMMARY COLUMN ================= --}}
            <aside class="sale-aside">
                <div class="sale-card summary-card crm-animate-in delay-3">
                    <div class="summary-hero">
                        <div class="lbl"><label for="se-grand-total" style="margin:0;color:inherit;">Grand Total (৳)</label></div>
                        <input type="text" class="form-control grand-total" id="se-grand-total" name="total_amount" value="{{ $saleMaster->total_amount }}" readonly="readonly" tabindex="-1">
                        <div class="sub">Step 3 — review &amp; update payment</div>
                    </div>

                    <div class="summary-body">
                        <div class="summary-row">
                            <label class="k mb-0" for="se-sub-total"><i class="fas fa-receipt"></i>Sub Total</label>
                            <input type="text" class="form-control v-plain sub-total" id="se-sub-total" name="sub_total" value="{{ number_format($subTotal + $serviceSubTotal, 2) }}" placeholder="0.00" readonly tabindex="-1">
                        </div>
                        <div class="summary-row">
                            <label class="k mb-0" for="vat"><i class="fas fa-percent"></i>VAT (৳)</label>
                            <input type="text" class="form-control v-input vat" id="vat" name="vat" value="{{ $saleMaster->vat }}" placeholder="0.00">
                        </div>
                        <div class="summary-row">
                            <label class="k mb-0" for="invoice_discount"><i class="fas fa-tag"></i>Invoice Discount</label>
                            <input type="text" class="form-control v-input invoice-discount" id="invoice_discount" name="invoice_discount" value="{{ $saleMaster->total_discount - ($subTotalDiscount + $serviceSubTotalDiscount) }}" placeholder="0.00">
                        </div>
                        <div class="summary-row is-discount">
                            <label class="k mb-0" for="se-total-discount"><i class="fas fa-tags"></i>Total Discount</label>
                            <input type="text" class="form-control v-plain total-discount" id="se-total-discount" name="total_discount" value="{{ $saleMaster->total_discount }}" readonly="readonly" tabindex="-1">
                        </div>
                        <div class="summary-row">
                            <label class="k mb-0" for="primary_reference_amount">
                                <i class="fas fa-user-md"></i>Doctor Commission
                                @if($saleMaster->reference_by && $saleMaster->referral_settlement_status === 'pending')
                                    <span class="badge badge-warning ml-1"><i class="fas fa-clock mr-1"></i>Negotiation Pending</span>
                                @elseif($saleMaster->reference_by && $saleMaster->referral_settlement_status === 'settled')
                                    <span class="badge badge-success ml-1"><i class="fas fa-check-circle mr-1"></i>Settled</span>
                                @endif
                            </label>
                            <input type="text" class="form-control v-input" id="primary_reference_amount" name="primary_reference_amount" value="{{ number_format($primaryAmount, 2, '.', '') }}" placeholder="0.00">
                        </div>
                        @if($saleMaster->reference_by && $saleMaster->referral_settlement_status === 'pending')
                            <div class="hint" style="font-size:11px; color: var(--page-text-muted); margin: -4px 0 8px;">Leave 0 if commission will be negotiated later at closing time.</div>
                        @endif
                        <div id="doc-comm-breakdown" style="display:none; font-size:11px; color:#4338ca; background:#eef2ff; border-radius:6px; padding:5px 8px; margin:-4px 0 10px 0; border-left:3px solid #6366f1; line-height: 1.3;"></div>
                        <div class="summary-row" id="summary-secondary-comm-row" style="{{ $secondaryAmount > 0.001 ? '' : 'display:none;' }}">
                            <label class="k mb-0" for="secondary_comm_display"><i class="fas fa-user-friends text-info"></i>Quack / RF Commission</label>
                            <input type="text" class="form-control v-plain" id="secondary_comm_display" value="৳{{ number_format($secondaryAmount, 2, '.', '') }}" readonly tabindex="-1" style="font-weight: 700; color: #0284c7;">
                        </div>
                        <div class="summary-row" id="row-total-referral-comm">
                            <label class="k mb-0" for="reference_amount"><i class="fas fa-handshake"></i>Total Referral Commission</label>
                            <input type="text" class="form-control v-plain reference-amount" id="reference_amount" name="reference_amount" value="{{ number_format($saleMaster->reference_amount, 2, '.', '') }}" placeholder="0.00" readonly tabindex="-1">
                        </div>
                    </div>

                    <div class="summary-pay">
                        <div class="summary-pay-head">
                            <label for="paid_amount">Amount Received</label>
                        </div>
                        <input type="text" class="form-control paid-amount" id="paid_amount" name="paid" value="{{ $saleMaster->paid }}" placeholder="0.00">
                        <div class="summary-due" id="summary-due">
                            <label class="k" for="se-due" style="margin:0;">Due Amount</label>
                            <input type="text" class="form-control due-amount" id="se-due" name="due_amount" value="{{ $saleMaster->total_amount - $saleMaster->paid }}" placeholder="0.00" readonly="readonly" tabindex="-1">
                        </div>
                        <div class="se-due-date" id="wrap-due-date" style="{{ ($saleMaster->total_amount - $saleMaster->paid) > 0.009 ? '' : 'display:none;' }}">
                            <label for="due_date"><span><i class="far fa-calendar-alt mr-1"></i>Due Promise Date</span><small>Patient Promise</small></label>
                            <input type="date" class="form-control" id="due_date" name="due_date" value="{{ old('due_date', $saleMaster->due_date?->format('Y-m-d')) }}" min="{{ date('Y-m-d') }}">
                        </div>

                        {{-- Fulfilment --}}
                        <div class="se-fulfil">
                            <div class="se-fulfil-row">
                                <label for="se-fulfil-status"><i class="fas fa-clipboard-check mr-1"></i>Fulfillment Status</label>
                                <select class="form-control" name="delivery_status" id="se-fulfil-status" style="font-weight:600;">
                                    @foreach (\App\Enums\DeliveryStatus::casesFrom($saleMaster->delivery_status, $saleMaster->isServiceSale()) as $st)
                                        <option value="{{ $st->value }}" @selected($saleMaster->delivery_status === $st->value)>
                                            {{ $st->label($saleMaster->isServiceSale()) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="se-fulfil-row">
                                <label for="se-delivery-time"><i class="far fa-clock mr-1"></i>Promised Delivery / Report Time</label>
                                <input type="datetime-local" class="form-control" id="se-delivery-time" name="delivery_date_time" value="{{ old('delivery_date_time', $saleMaster->delivery_date_time?->format('Y-m-d\TH:i')) }}">
                            </div>
                            @if(!$saleMaster->isServiceSale())
                            <div class="se-fulfil-row">
                                <label for="se-lab-name"><i class="fas fa-flask mr-1"></i>Lab / Fabricator</label>
                                <input type="text" class="form-control" id="se-lab-name" name="lab_name" value="{{ old('lab_name', $saleMaster->lab_name) }}" placeholder="e.g. In-house Lab, Signia Lab">
                            </div>
                            @endif
                        </div>
                    </div>

                    <div class="summary-actions">
                        <button type="submit" class="btn-save-sale" id="btn-save-sale">
                            <i class="fas fa-check"></i> <span>Update Sale</span>
                        </button>
                        @can('permission', 'sale_list.index')
                            <a href="{{ url('admin/sale') }}" class="btn-cancel-sale" id="btn-cancel-sale"><i class="fas fa-times"></i> Cancel</a>
                        @else
                            <a href="javascript:history.back()" class="btn-cancel-sale" id="btn-cancel-sale"><i class="fas fa-times"></i> Cancel</a>
                        @endcan
                        <p class="summary-note"><i class="fas fa-info-circle mr-1"></i>Totals update automatically as you edit items.</p>
                    </div>
                </div>
            </aside>
        </div>
    </form>
</div>

@push('scripts')
    <script>
        window.crmDoctorRules = @json(($doctors ?? collect())->mapWithKeys(fn($d) => [$d->id => $d->getCommissionRulesArray()]));
    </script>
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('assets/sales.js') }}?v={{ @filemtime(public_path('assets/sales.js')) ?: time() }}"></script>
    <script>
        // UI-only enhancements (calculation logic lives in sales.js)
        $(function () {
            function refreshDue() {
                var due = parseFloat($('.due-amount').val()) || 0;
                var grand = parseFloat($('.grand-total').val()) || 0;
                $('#summary-due').toggleClass('is-clear', grand > 0 && due <= 0);
                if (due > 0.009) {
                    $('#wrap-due-date').show();
                } else {
                    $('#wrap-due-date').hide();
                }
            }
            $(document).on('keyup change input', '#saleForm', function () { setTimeout(refreshDue, 50); });
            refreshDue();

            function toggleDigitalPaymentFields(val) {
                var digitalTypes = ['3', '5', '6', '7', '8'];
                if (digitalTypes.indexOf(String(val)) !== -1) {
                    $('#wrap-digital-payment-details').slideDown(150);
                } else {
                    $('#wrap-digital-payment-details').slideUp(150);
                }
            }
            $('#payment_type_id').on('change', function () {
                toggleDigitalPaymentFields($(this).val());
            });

            function updateCount() {
                $('#items-count').text($('.neworderbody tr').length + $('.service-body tr').length);
            }
            if (window.MutationObserver) {
                var obs = new MutationObserver(updateCount);
                $('.neworderbody, .service-body').each(function () { obs.observe(this, { childList: true }); });
            }

            // Select the whole value when focusing numeric inputs for faster editing
            $(document).on('focus', '#saleForm .qty, #saleForm .unit-price, #saleForm .discount, #saleForm .discount-taka, ' +
                '#saleForm .service-qty, #saleForm .service-price, #saleForm .service-discount, #saleForm .service-discount-taka, ' +
                '#saleForm .vat, #saleForm .invoice-discount, #saleForm .paid-amount', function () {
                var el = this;
                setTimeout(function () { el.select(); }, 0);
            });

            // Ignore a second click on Update right after the first; show a saving state once the page really submits.
            var saveBtns = document.querySelectorAll('#btn-save-sale, #btn-save-sale-top');
            var lastClick = 0;
            saveBtns.forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    var now = Date.now();
                    if (now - lastClick < 1200) { e.preventDefault(); e.stopImmediatePropagation(); return; }
                    lastClick = now;
                }, true);
            });
            function setSaving(on) {
                saveBtns.forEach(function (btn) {
                    btn.classList.toggle('disabled', on);
                    if (on) { btn.setAttribute('aria-busy', 'true'); } else { btn.removeAttribute('aria-busy'); }
                    var s = btn.querySelector('span'); if (s) { s.textContent = on ? 'Saving…' : 'Update Sale'; }
                });
            }
            window.addEventListener('beforeunload', function () { setSaving(true); });
            window.addEventListener('pageshow', function () { setSaving(false); });
        });
    </script>
@endpush
@endsection