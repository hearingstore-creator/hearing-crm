@extends('admin.layouts.master')
@section('page_title', 'Invoice #' . $saleMaster->sale_invoice_no)
@section('breadcrumb_parent', 'Sales')
@section('breadcrumb_parent_url', url('admin/sale'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/sales-invoice.css') }}">
<style>
.stepper-container {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    margin-bottom: 22px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.stepper-header {
    padding: 12px 20px;
    background: linear-gradient(to right, #f8fafc, #f1f5f9);
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}
.stepper-body {
    padding: 18px 24px;
}
.stepper-steps-wrapper {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    position: relative;
}
.stepper-progress-bar {
    position: absolute;
    top: 20px;
    left: 40px;
    right: 40px;
    height: 4px;
    background: #e2e8f0;
    z-index: 1;
}
.stepper-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #4f46e5, #10b981);
    transition: width 0.3s ease;
}
.stepper-step {
    flex: 1;
    text-align: center;
    position: relative;
    z-index: 2;
}
.btn-step-action {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    border: 2px solid;
    cursor: pointer;
    transition: all 0.2s ease;
    outline: none !important;
}
.btn-step-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
}
</style>
@endpush

@section('content')
@php
    // ---- Line calculations (unchanged business logic) ----
    $subtotal = 0;
    $subTotalDiscount = 0;
    $serviceSubtotal = 0;
    $serviceSubTotalDiscount = 0;

    $productRows = [];
    foreach (($saleDetail ?? []) as $item) {
        $discountTaka = ($item->qty * $item->mrp_price / 100) * $item->discount;
        $totalPrice = $item->qty * $item->mrp_price - $discountTaka;
        $subtotal += $totalPrice;
        $subTotalDiscount += $discountTaka;
        $productRows[] = [
            'item' => $item,
            'discountTaka' => $discountTaka,
            'totalPrice' => $totalPrice,
            'serial' => !empty($item->serial_no) ? implode(', ', (array) $item->serial_no) : null,
        ];
    }

    $serviceRows = [];
    foreach (($serviceDetails ?? []) as $item) {
        $sDisc = ($item->qty * $item->service_price / 100) * ($item->discount ?? 0);
        $sTotal = $item->qty * $item->service_price - $sDisc;
        $serviceSubtotal += $sTotal;
        $serviceSubTotalDiscount += $sDisc;
        $serviceRows[] = ['item' => $item, 'discountTaka' => $sDisc, 'totalPrice' => $sTotal];
    }

    $totalSubtotal = $subtotal + $serviceSubtotal;
    $totalSubTotalDiscount = $subTotalDiscount + $serviceSubTotalDiscount;
    $invoiceDiscount = $saleMaster->total_discount - $totalSubTotalDiscount;
    $computedTotal = $totalSubtotal - $invoiceDiscount + $saleMaster->vat;
    $grandTotal = (float) $saleMaster->total_amount;
    $pointsRedeemed = $computedTotal - $grandTotal;
    $paid = (float) $saleMaster->paid;
    $due = $grandTotal - $paid;

    // ---- Status ----
    if ($due <= 0.009) { $status = 'paid'; $statusLabel = 'Paid'; }
    elseif ($paid > 0) { $status = 'partial'; $statusLabel = 'Partially Paid'; }
    else { $status = 'unpaid'; $statusLabel = 'Unpaid'; }
    $paidPct = $grandTotal > 0 ? min(100, max(0, round($paid / $grandTotal * 100))) : 100;

    $paymentLabel = \App\Classes\Helper::paymentType()[$saleMaster->payment_id] ?? null;
    $deliveryEnum = $saleMaster->delivery_status ? \App\Enums\DeliveryStatus::tryFrom($saleMaster->delivery_status) : null;
    $deliveryLabel = $deliveryEnum?->label($saleMaster->isServiceSale());
    $doctorName = $saleMaster->doctor->name ?? null;
    $customerLead = $saleMaster->customer?->lead;
    $itemCount = count($productRows) + count($serviceRows);
    $companyInitial = strtoupper(mb_substr($setting->company_name ?? 'C', 0, 1));
@endphp

<div class="container-fluid sale-page">

    {{-- Screen-only header --}}
    <div class="crm-page-header crm-animate-in no-print">
        <div class="page-title-group">
            <h1><i class="fas fa-file-invoice" style="color: var(--accent-500); margin-right: 8px;"></i>Invoice #{{ $saleMaster->sale_invoice_no }}</h1>
            <p>Issued {{ $saleMaster->date->format('d M Y') }} to {{ $customerLead->full_name ?? 'Customer' }}</p>
        </div>
        <div class="header-actions">
            @if ($due > 0.009)
                <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#collectDueModal">
                    <i class="fas fa-hand-holding-usd mr-1"></i>Collect Due
                </button>
            @endif
            @can('permission', 'sale_list.index')
                <a href="{{ url('admin/sale') }}" class="btn btn-secondary btn-sm" id="btn-back-sales"><i class="fas fa-arrow-left mr-1"></i>Sales List</a>
            @endcan
            <button type="button" onclick="window.print()" class="btn btn-primary btn-sm" id="btn-print-top"><i class="fas fa-print mr-1"></i>Print / PDF</button>
        </div>
    </div>

    {{-- Interactive Fulfillment Stepper Bar (Screen-only) --}}
    @php
        $isService = $saleMaster->isServiceSale();
        $cases = \App\Enums\DeliveryStatus::casesFor($isService);
        $currentStatus = $saleMaster->delivery_status ?? 'ordered';
        $currentIdx = 0;
        foreach ($cases as $idx => $c) {
            if ($c->value === $currentStatus) {
                $currentIdx = $idx;
                break;
            }
        }
        $progressPct = count($cases) > 1 ? min(100, max(0, round(($currentIdx / (count($cases) - 1)) * 100))) : 0;
    @endphp

    <div class="stepper-container no-print crm-animate-in">
        <div class="stepper-header">
            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <div style="width: 38px; height: 38px; border-radius: 10px; background: {{ $isService ? '#4f46e5' : '#0284c7' }}; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                    <i class="fas {{ $isService ? 'fa-stethoscope' : 'fa-box-open' }}"></i>
                </div>
                <div>
                    <div style="font-size: 14px; font-weight: 700; color: #0f172a;">
                        {{ $isService ? 'Assessment & Report Fulfillment Tracking' : 'Hearing Aid & Device Fulfillment Tracking' }}
                    </div>
                    <div style="font-size: 11.5px; color: #64748b;">
                        Click any stage below for instant 1-click status update.
                    </div>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                @if ($saleMaster->delivery_date_time)
                    <span class="badge" style="background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; font-size: 11.5px; padding: 5px 10px; border-radius: 6px;">
                        <i class="far fa-clock mr-1"></i>Promised: {{ $saleMaster->delivery_date_time->format('d M Y, h:i A') }}
                    </span>
                @endif
                @if ($saleMaster->lab_name)
                    <span class="badge" style="background: #f5f3ff; color: #7c3aed; border: 1px solid #ddd6fe; font-size: 11.5px; padding: 5px 10px; border-radius: 6px;">
                        <i class="fas fa-flask mr-1"></i>Lab: {{ $saleMaster->lab_name }}
                    </span>
                @endif
                <button type="button" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#updateFulfillmentModal" style="border-radius: 8px; font-size: 12px; font-weight: 600; padding: 4px 10px;">
                    <i class="fas fa-edit mr-1"></i>Edit Promised Date / Lab
                </button>
            </div>
        </div>

        <div class="stepper-body">
            <div class="stepper-steps-wrapper">
                <div class="stepper-progress-bar">
                    <div class="stepper-progress-fill" style="width: {{ $progressPct }}%;"></div>
                </div>
                @foreach ($cases as $idx => $stCase)
                    @php
                        $isCurrent = ($stCase->value === $currentStatus);
                        $isPassed = ($idx < $currentIdx);
                    @endphp
                    <div class="stepper-step {{ $isCurrent ? 'active' : ($isPassed ? 'completed' : '') }}">
                        <button type="button" 
                                class="btn-step-action"
                                data-status="{{ $stCase->value }}"
                                data-due="{{ $due }}"
                                title="Click to set status to {{ $stCase->label($isService) }}"
                                style="background: {{ $isCurrent ? $stCase->badgeColor() : ($isPassed ? '#10b981' : '#fff') }}; 
                                       color: {{ ($isCurrent || $isPassed) ? '#fff' : '#64748b' }}; 
                                       border-color: {{ $isCurrent ? $stCase->badgeColor() : ($isPassed ? '#10b981' : '#cbd5e1') }}; 
                                       box-shadow: {{ $isCurrent ? '0 0 0 4px ' . $stCase->badgeColor() . '30' : 'none' }};">
                            <i class="fas {{ $isPassed ? 'fa-check' : $stCase->icon() }}"></i>
                        </button>
                        <div style="margin-top: 8px;">
                            <div style="font-size: 12px; font-weight: {{ $isCurrent ? '800' : '600' }}; color: {{ $isCurrent ? '#0f172a' : '#64748b' }};">
                                {{ $stCase->label($isService) }}
                            </div>
                            @if ($isCurrent)
                                <span class="badge" style="font-size: 10px; background: {{ $stCase->badgeBg() }}; color: {{ $stCase->badgeColor() }}; border: 1px solid {{ $stCase->badgeColor() }}40; border-radius: 9999px; padding: 2px 8px; margin-top: 2px;">
                                    Current Stage
                                </span>
                            @endif
                            @if ($stCase->value === 'delivered' && $saleMaster->delivered_at)
                                <div style="font-size: 10px; color: #10b981; margin-top: 2px;">
                                    {{ $saleMaster->delivered_at->format('d M, h:i A') }}
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    {{-- Services in this sale panel (Screen-only) --}}
    @if ($serviceDetails->isNotEmpty())
        <div class="services-panel-card no-print crm-animate-in" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 14px rgba(0,0,0,0.04);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 8px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 36px; height: 36px; border-radius: 8px; background: #e0e7ff; color: #4338ca; display: flex; align-items: center; justify-content: center; font-size: 16px;">
                        <i class="fas fa-stethoscope"></i>
                    </div>
                    <div>
                        <h3 style="font-size: 16px; font-weight: 700; color: #1e1b4b; margin: 0;">Services in this sale</h3>
                        <p style="font-size: 12px; color: #64748b; margin: 0;">Perform walk-in services now or schedule appointment visits for the patient.</p>
                    </div>
                </div>
                <a href="{{ route('sales.pending-services') }}" class="btn btn-outline-secondary btn-sm" style="font-size: 12px;">
                    <i class="fas fa-list mr-1"></i>View All Pending Services
                </a>
            </div>

            <div class="table-responsive" style="margin: 0;">
                <table class="table table-bordered table-hover" style="margin: 0; font-size: 13px;">
                    <thead style="background: #f8fafc;">
                        <tr>
                            <th>Service</th>
                            <th style="width: 70px; text-align: center;">Qty</th>
                            <th style="width: 140px;">Booking Type</th>
                            <th style="width: 140px;">Status</th>
                            <th>Fulfillment Info</th>
                            <th style="width: 250px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($serviceDetails as $sd)
                            @php
                                $bType = $sd->serviceType?->booking_type ?? 'either';
                                $fStatus = $sd->fulfillment_status ?? 'not_booked';
                            @endphp
                            <tr id="sale-svc-row-{{ $sd->id }}">
                                <td>
                                    <div style="font-weight: 700; color: #0f172a;">{{ $sd->serviceType?->service_type_name ?? 'Service' }}</div>
                                    <div style="font-size: 11px; color: #64748b;">৳{{ number_format($sd->service_price, 2) }} each</div>
                                </td>
                                <td style="text-align: center; font-weight: 600;">{{ $sd->qty }}</td>
                                <td>
                                    @if ($bType === 'walk_in')
                                        <span class="badge badge-success"><i class="fas fa-walking mr-1"></i>Walk-in</span>
                                    @elseif ($bType === 'appointment_required')
                                        <span class="badge badge-warning text-dark"><i class="fas fa-calendar-alt mr-1"></i>Appointment req.</span>
                                    @else
                                        <span class="badge badge-info"><i class="fas fa-exchange-alt mr-1"></i>Either</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="{{ $sd->statusBadgeClass() }}" id="sale-sd-badge-{{ $sd->id }}">
                                        {{ $sd->statusLabel() }}
                                    </span>
                                </td>
                                <td id="sale-sd-info-{{ $sd->id }}">
                                    @if ($fStatus === 'booked' && $sd->appointment)
                                        <div style="font-size: 12px; color: #0369a1;">
                                            <i class="far fa-calendar-check mr-1"></i>Booked for <strong>{{ $sd->appointment->appointment_date?->format('d M Y') }}</strong>
                                            @if ($sd->appointment->user)
                                                with {{ $sd->appointment->user->name }}
                                            @endif
                                        </div>
                                    @elseif ($fStatus === 'done')
                                        <div style="font-size: 12px; color: #15803d;">
                                            <i class="fas fa-check-circle mr-1"></i>Delivered {{ $sd->done_at ? $sd->done_at->format('d M Y, h:i A') : 'Today' }}
                                            @if ($sd->doneByUser) by <strong>{{ $sd->doneByUser->name }}</strong> @endif
                                        </div>
                                    @else
                                        <span class="text-muted" style="font-size: 12px;">Not yet scheduled or delivered</span>
                                    @endif
                                </td>
                                <td style="text-align: center;" id="sale-sd-actions-{{ $sd->id }}">
                                    <div class="d-flex justify-content-center align-items-center" style="gap: 6px;">
                                        @if ($fStatus === 'not_booked')
                                            {{-- Walk-in: Done now --}}
                                            @if ($bType === 'walk_in')
                                                <button type="button" class="btn btn-success btn-sm btn-done-modal" data-id="{{ $sd->id }}" data-service="{{ $sd->serviceType?->service_type_name }}" data-patient="{{ $customerLead?->full_name ?? 'Patient' }}">
                                                    <i class="fas fa-check mr-1"></i>Done now
                                                </button>
                                            {{-- Appointment required: Book appointment / Book later --}}
                                            @elseif ($bType === 'appointment_required')
                                                <a href="{{ url('admin/appointments/create?customer_id=' . $saleMaster->customer_id . '&service_detail_id=' . $sd->id . '&sale_master_id=' . $saleMaster->id . '&sale_invoice_no=' . $saleMaster->sale_invoice_no . '&service_id=' . ($sd->serviceType?->appointment_service_id ?? '')) }}" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-calendar-plus mr-1"></i>Book appointment
                                                </a>
                                                <button type="button" class="btn btn-outline-secondary btn-sm btn-book-later" data-id="{{ $sd->id }}">
                                                    <i class="fas fa-clock mr-1"></i>Book later
                                                </button>
                                            {{-- Either: Done now / Book appointment / Book later --}}
                                            @else
                                                <button type="button" class="btn btn-success btn-sm btn-done-modal" data-id="{{ $sd->id }}" data-service="{{ $sd->serviceType?->service_type_name }}" data-patient="{{ $customerLead?->full_name ?? 'Patient' }}">
                                                    <i class="fas fa-check mr-1"></i>Done now
                                                </button>
                                                <a href="{{ url('admin/appointments/create?customer_id=' . $saleMaster->customer_id . '&service_detail_id=' . $sd->id . '&sale_master_id=' . $saleMaster->id . '&sale_invoice_no=' . $saleMaster->sale_invoice_no . '&service_id=' . ($sd->serviceType?->appointment_service_id ?? '')) }}" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-calendar-plus mr-1"></i>Book appointment
                                                </a>
                                                <button type="button" class="btn btn-outline-secondary btn-sm btn-book-later" data-id="{{ $sd->id }}">
                                                    <i class="fas fa-clock mr-1"></i>Book later
                                                </button>
                                            @endif
                                        @elseif ($fStatus === 'booked')
                                            <button type="button" class="btn btn-outline-success btn-sm btn-done-modal" data-id="{{ $sd->id }}" data-service="{{ $sd->serviceType?->service_type_name }}" data-patient="{{ $customerLead?->full_name ?? 'Patient' }}" title="Mark Delivered Today">
                                                <i class="fas fa-check mr-1"></i>Done now
                                            </button>
                                            @if ($sd->appointment_id)
                                                <a href="{{ url('admin/appointments/' . $sd->appointment_id . '/edit') }}" class="btn btn-outline-info btn-sm" target="_blank" title="View Appointment">
                                                    <i class="fas fa-external-link-alt mr-1"></i>Appointment
                                                </a>
                                            @endif
                                        @else
                                            <span class="text-success font-weight-600" style="font-size: 13px;">
                                                <i class="fas fa-check-double mr-1"></i>Done
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="inv-layout">
        {{-- ================= INVOICE PAPER ================= --}}
        <div class="inv-paper crm-animate-in delay-1" id="print-area">
            <div class="inv-topbar"></div>
            <div class="inv-pad">

                {{-- Dual-Branding Document Header (Hearing Store HQ + Branch) --}}
                @include('admin.partials.document_header', [
                    'vendor'       => $saleMaster->vendor,
                    'setting'      => $setting,
                    'documentType' => 'TAX INVOICE',
                    'documentNo'   => '#' . $saleMaster->sale_invoice_no,
                    'date'         => $saleMaster->date,
                    'status'       => $statusLabel,
                    'statusClass'  => $status,
                ])

                {{-- Parties --}}
                <div class="inv-parties">
                    <div class="inv-party">
                        <div class="lbl">Bill To</div>
                        <div class="name">{{ $customerLead->full_name ?? 'N/A' }}</div>
                        @if (!empty($customerLead?->phone_number))
                            <div class="line"><i class="fas fa-phone-alt"></i>{{ $customerLead->phone_number }}</div>
                        @endif
                        @if (!empty($customerLead?->address))
                            <div class="line"><i class="fas fa-map-marker-alt"></i>{{ $customerLead->address }}</div>
                        @endif
                        @if (!empty($customerLead?->email))
                            <div class="line"><i class="fas fa-envelope"></i>{{ $customerLead->email }}</div>
                        @endif
                    </div>
                    <div class="inv-party">
                        <div class="lbl">Invoice Info</div>
                        <div class="inv-meta-list">
                            <div>Date <b>{{ $saleMaster->date->format('d M Y') }}</b></div>
                            @if ($paymentLabel)
                                <div>Payment <b>{{ $paymentLabel }}</b></div>
                            @endif
                            @if ($deliveryLabel)
                                <div>{{ $saleMaster->isServiceSale() ? 'Report' : 'Fulfillment' }} <b>{{ $deliveryLabel }}</b></div>
                            @endif
                            @if ($saleMaster->delivery_date_time)
                                <div>Promised <b>{{ $saleMaster->delivery_date_time->format('d M Y, h:i A') }}</b></div>
                            @endif
                            @if ($saleMaster->lab_name)
                                <div>Lab <b>{{ $saleMaster->lab_name }}</b></div>
                            @endif
                            @if ($saleMaster->delivered_at)
                                <div>Handed Over <b>{{ $saleMaster->delivered_at->format('d M Y') }} {{ $saleMaster->deliveredBy ? '(' . $saleMaster->deliveredBy->name . ')' : '' }}</b></div>
                            @endif
                            @if ($doctorName)
                                <div>Referred <b>{{ $doctorName }}</b></div>
                                @if ($saleMaster->doctorChamber)
                                    <div>Chamber <b>{{ $saleMaster->doctorChamber->chamber_name }}</b></div>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Product items --}}
                @if (count($productRows) > 0)
                    @if (count($serviceRows) > 0)
                        <div class="inv-section-label">Products</div>
                    @endif
                    <table class="inv-items">
                        <thead>
                            <tr>
                                <th class="ctr" style="width: 44px;">#</th>
                                <th>Item Description</th>
                                <th class="ctr" style="width: 60px;">Qty</th>
                                <th class="num" style="width: 100px;">Unit Price</th>
                                <th class="num" style="width: 100px;">Discount</th>
                                <th class="num" style="width: 110px;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($productRows as $i => $row)
                                @php $item = $row['item']; @endphp
                                <tr>
                                    <td class="ctr">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</td>
                                    <td>
                                        <div class="item-name">{{ trim(($item->product?->category?->category_name ?? '') . ' - ' . ($item->product?->model ?? ''), ' -') ?: 'Product' }}</div>
                                        @if ($row['serial'] || $item->batch?->batch_no)
                                            <div class="item-sub">
                                                @if ($row['serial']) Serial: {{ $row['serial'] }} @endif
                                                @if ($row['serial'] && $item->batch?->batch_no) &middot; @endif
                                                @if ($item->batch?->batch_no) Batch: {{ $item->batch->batch_no }} @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="ctr">{{ $item->qty }}</td>
                                    <td class="num">{{ number_format($item->mrp_price, 2) }}</td>
                                    <td class="num disc">
                                        @if ($row['discountTaka'] > 0)
                                            -{{ number_format($row['discountTaka'], 2) }}
                                            <div class="item-sub">{{ rtrim(rtrim(number_format($item->discount, 2), '0'), '.') }}%</div>
                                        @else
                                            <span style="color:#cbd5e1;">—</span>
                                        @endif
                                    </td>
                                    <td class="num amt">{{ number_format($row['totalPrice'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                {{-- Service items --}}
                @if (count($serviceRows) > 0)
                    @if (count($productRows) > 0)
                        <div class="inv-section-label">Services</div>
                    @endif
                    <table class="inv-items" @if(count($productRows) === 0) style="margin-top:0;" @endif>
                        <thead>
                            <tr>
                                <th class="ctr" style="width: 44px;">#</th>
                                <th>Service Description</th>
                                <th class="ctr" style="width: 60px;">Qty</th>
                                <th class="num" style="width: 100px;">Unit Price</th>
                                <th class="num" style="width: 100px;">Discount</th>
                                <th class="num" style="width: 110px;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($serviceRows as $i => $row)
                                @php $item = $row['item']; @endphp
                                <tr>
                                    <td class="ctr">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</td>
                                    <td>
                                        <div class="item-name">{{ $item->serviceType->service_type_name ?? 'N/A' }}</div>
                                        <div class="no-print mt-1" style="font-size: 11px;">
                                            <span class="{{ $item->statusBadgeClass() }}">{{ $item->statusLabel() }}</span>
                                            @if ($item->fulfillment_status === 'booked' && $item->appointment)
                                                <a href="{{ url('admin/appointments/' . $item->appointment_id . '/edit') }}" class="ml-1 text-primary font-weight-500">
                                                    <i class="far fa-calendar-check mr-1"></i>Appt: {{ $item->appointment->appointment_date?->format('d M Y') }}
                                                </a>
                                            @elseif ($item->fulfillment_status === 'done')
                                                <span class="text-success ml-1"><i class="fas fa-check-circle mr-1"></i>Delivered</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="ctr">{{ $item->qty }}</td>
                                    <td class="num">{{ number_format($item->service_price, 2) }}</td>
                                    <td class="num disc">
                                        @if ($row['discountTaka'] > 0)
                                            -{{ number_format($row['discountTaka'], 2) }}
                                            <div class="item-sub">{{ rtrim(rtrim(number_format($item->discount ?? 0, 2), '0'), '.') }}%</div>
                                        @else
                                            <span style="color:#cbd5e1;">—</span>
                                        @endif
                                    </td>
                                    <td class="num amt">{{ number_format($row['totalPrice'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                {{-- Notes + Totals --}}
                <div class="inv-bottom">
                    <div>
                        <div class="inv-note-box">
                            <h4>Notes</h4>
                            {{ $saleMaster->details ?: 'Thank you for your purchase. Please keep this invoice for future reference and warranty claims.' }}
                        </div>
                        <div class="inv-thanks">Thank you for your business!</div>
                    </div>
                    <div>
                        <table class="inv-totals">
                            <tr>
                                <td>Sub Total</td>
                                <td>{{ number_format($totalSubtotal, 2) }}</td>
                            </tr>
                            @if ($saleMaster->vat > 0)
                                <tr>
                                    <td>VAT</td>
                                    <td>+{{ number_format($saleMaster->vat, 2) }}</td>
                                </tr>
                            @endif
                            @if ($invoiceDiscount > 0.009)
                                <tr class="disc">
                                    <td>Invoice Discount</td>
                                    <td>-{{ number_format($invoiceDiscount, 2) }}</td>
                                </tr>
                            @endif
                            @if ($pointsRedeemed > 0.009)
                                <tr class="disc">
                                    <td>Points Redeemed</td>
                                    <td>-{{ number_format($pointsRedeemed, 2) }}</td>
                                </tr>
                            @endif
                        </table>
                        <div class="inv-grand">
                            <span>Total</span>
                            <b>৳ {{ number_format($grandTotal, 2) }}</b>
                        </div>
                        <div class="inv-paid-due">
                            <div class="p">Paid<b>{{ number_format($paid, 2) }}</b></div>
                            <div class="d {{ $due <= 0.009 ? 'zero' : '' }}">Due<b>{{ number_format(max(0, $due), 2) }}</b></div>
                        </div>
                        @if (($saleMaster->applied_advance_amount ?? 0) > 0)
                            <div class="inv-advance-breakdown" style="background: #f0fdf4; border: 1px dashed #86efac; border-radius: 6px; padding: 6px 10px; margin: 6px 0; font-size: 11.5px;">
                                <div style="display:flex; justify-content:space-between; color: #166534; font-weight:600;">
                                    <span><i class="fas fa-receipt mr-1"></i> Paid via Advance:</span>
                                    <span>৳ {{ number_format($saleMaster->applied_advance_amount, 2) }}</span>
                                </div>
                                @if($saleMaster->appliedAdvance)
                                    <div style="font-size: 10.5px; color: #15803d; margin-top: 2px;">
                                        Receipt #{{ $saleMaster->appliedAdvance->receipt_no }} ({{ ucfirst($saleMaster->appliedAdvance->payment_method) }})
                                    </div>
                                @endif
                            </div>
                        @endif
                        @if ($saleMaster->due_date && $due > 0.009)
                            <div style="font-size: 11px; text-align: right; margin-top: 4px; color: #dc2626; font-weight: 600;">
                                <i class="far fa-clock mr-1"></i>Promise Date: {{ $saleMaster->due_date->format('d M Y') }}
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Signatures --}}
                <div class="inv-signature">
                    <div>Customer Signature</div>
                    <div>Authorized Signature</div>
                </div>

                {{-- Footer --}}
                <div class="inv-foot">
                    <div>
                        <h5>Questions?</h5>
                        <p>
                            @if (!empty($setting->email)) Email: {{ $setting->email }}<br>@endif
                            @if (!empty($setting->mobile)) Call: {{ $setting->mobile }}@endif
                        </p>
                    </div>
                    <div>
                        <h5>Payment Info</h5>
                        <p>
                            Method: {{ $paymentLabel ?? 'N/A' }}<br>
                            Status: {{ $statusLabel }}
                        </p>
                    </div>
                    <div>
                        <h5>Terms &amp; Conditions</h5>
                        <p>Goods once sold are subject to the store's return policy. Please retain this invoice.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ================= SIDE PANEL ================= --}}
        <aside class="inv-side no-print crm-animate-in delay-2">
            <div class="inv-side-card">
                <h4>Payment Status</h4>
                <div class="pay-ring-row">
                    <div class="pay-ring" style="--p: {{ $paidPct }};"><span>{{ $paidPct }}%</span></div>
                    <div class="pay-ring-info">
                        <div class="big">৳ {{ number_format($grandTotal, 2) }}</div>
                        <div class="small">Invoice total</div>
                        <span class="inv-status {{ $status }}" style="margin-top:6px;">{{ $statusLabel }}</span>
                    </div>
                </div>
                <div style="margin-top: 14px;">
                    <div class="side-kv"><span>Paid</span><b class="green">৳ {{ number_format($paid, 2) }}</b></div>
                    @if (($saleMaster->applied_advance_amount ?? 0) > 0)
                        <div class="side-kv" style="color: #166534; font-size: 11.5px;">
                            <span>↳ Advance Applied</span>
                            <b>৳ {{ number_format($saleMaster->applied_advance_amount, 2) }}</b>
                        </div>
                    @endif
                    <div class="side-kv"><span>Due</span><b class="{{ $due > 0.009 ? 'red' : '' }}">৳ {{ number_format(max(0, $due), 2) }}</b></div>
                    @if ($saleMaster->due_date && $due > 0.009)
                        <div class="side-kv"><span>Promise Date</span><b style="color: #ef4444;"><i class="far fa-clock mr-1"></i>{{ $saleMaster->due_date->format('d M Y') }}</b></div>
                    @endif
                    @if ($saleMaster->reference_amount > 0)
                        <div class="side-kv"><span>Doctor Commission</span><b>৳ {{ number_format($saleMaster->reference_amount, 2) }}</b></div>
                    @endif
                </div>
            </div>

            <div class="inv-side-card">
                <h4>Summary</h4>
                <div class="side-kv"><span>Items</span><b>{{ $itemCount }}</b></div>
                <div class="side-kv"><span>Type</span><b><span class="side-pill">{{ count($serviceRows) > 0 && count($productRows) === 0 ? 'Service' : (count($serviceRows) > 0 ? 'Mixed' : 'Product') }}</span></b></div>
                @if ($deliveryLabel)
                    <div class="side-kv"><span>Delivery</span><b>{{ $deliveryLabel }}</b></div>
                @endif
                @if ($paymentLabel)
                    <div class="side-kv"><span>Payment Method</span><b>{{ $paymentLabel }}</b></div>
                @endif
                @if ($doctorName)
                    <div class="side-kv"><span>Referred By</span><b>{{ $doctorName }}</b></div>
                    @if ($saleMaster->doctorChamber)
                        <div class="side-kv"><span>Chamber</span><b title="{{ $saleMaster->doctorChamber->address }}">{{ $saleMaster->doctorChamber->chamber_name }}</b></div>
                    @endif
                @endif
                <div class="side-kv"><span>Total Discount</span><b class="green">৳ {{ number_format($saleMaster->total_discount, 2) }}</b></div>
            </div>

            <div class="inv-side-card">
                <h4>Actions</h4>
                <div class="side-actions">
                    <button type="button" class="side-btn" style="background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%); color: #fff; font-weight: 600;" data-toggle="modal" data-target="#updateFulfillmentModal">
                        <i class="fas fa-clipboard-check"></i> {{ $saleMaster->isServiceSale() ? 'Update Report Status' : 'Update Fulfillment' }}
                    </button>
                    @if ($due > 0.009)
                        <button type="button" class="side-btn" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff; font-weight: 600;" data-toggle="modal" data-target="#collectDueModal">
                            <i class="fas fa-hand-holding-usd"></i> Collect Due Payment
                        </button>
                    @endif
                    <button type="button" onclick="window.print()" class="side-btn primary" id="btn-print-side">
                        <i class="fas fa-print"></i> Print / Save as PDF
                    </button>
                    <a href="{{ route('sale.pos-print', $saleMaster->id) }}" target="_blank" class="side-btn" id="btn-pos-print">
                        <i class="fas fa-receipt ic-blue"></i> POS Receipt
                    </a>
                    @if ((int) $saleMaster->vendor_id === (int) \App\Classes\Helper::currentBranchId())
                    @can('permission', 'sale_list.edit')
                        <a href="{{ url('admin/sale/' . $saleMaster->id . '/edit') }}" class="side-btn" id="btn-edit-sale">
                            <i class="fas fa-pen ic-amber"></i> Edit Sale
                        </a>
                    @endcan
                    @endif
                    @can('permission', 'add_sale.create')
                        <a href="{{ url('admin/sale/create') }}" class="side-btn" id="btn-new-sale">
                            <i class="fas fa-plus ic-green"></i> New Sale
                        </a>
                    @endcan
                    @can('permission', 'sale_list.index')
                        <a href="{{ url('admin/sale') }}" class="side-btn" id="btn-sales-list">
                            <i class="fas fa-list ic-gray"></i> All Sales
                        </a>
                    @endcan
                </div>
            </div>
        </aside>
    </div>
</div>

{{-- Update Fulfillment / Report Status Modal --}}
<div class="modal fade" id="updateFulfillmentModal" tabindex="-1" role="dialog" aria-labelledby="updateFulfillmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 540px;">
        <div class="modal-content" style="border-radius: 14px; overflow: hidden; border: none; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
            <div class="modal-header" style="background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%); color: #fff; padding: 16px 20px;">
                <h5 class="modal-title" id="updateFulfillmentModalLabel" style="font-weight: 700; font-size: 16px; color:#fff;">
                    <i class="fas fa-clipboard-check mr-2"></i>{{ $saleMaster->isServiceSale() ? 'Update Report Status' : 'Update Fulfillment Status' }}
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.85;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="{{ route('sale.update-delivery-status', $saleMaster->id) }}">
                @csrf
                <div class="modal-body" style="padding: 20px;">
                    <div class="form-group">
                        <label style="font-weight: 700; font-size: 13px; color: #1e293b;">
                            Status <span class="text-danger">*</span>
                        </label>
                        <select class="form-control" name="delivery_status" id="details_delivery_status" required style="font-weight:600;">
                            @foreach (\App\Enums\DeliveryStatus::casesFor($saleMaster->isServiceSale()) as $st)
                                <option value="{{ $st->value }}" @selected($saleMaster->delivery_status === $st->value)>
                                    {{ $st->label($saleMaster->isServiceSale()) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label style="font-weight: 700; font-size: 13px; color: #1e293b;">
                            {{ $saleMaster->isServiceSale() ? 'Promised Report Date & Time' : 'Promised Device Fitting / Delivery Time' }}
                        </label>
                        <input type="datetime-local" class="form-control" name="delivery_date_time" value="{{ old('delivery_date_time', $saleMaster->delivery_date_time?->format('Y-m-d\TH:i')) }}">
                    </div>

                    @if (!$saleMaster->isServiceSale())
                    <div class="form-group">
                        <label style="font-weight: 700; font-size: 13px; color: #1e293b;">
                            <i class="fas fa-flask mr-1" style="color: #7c3aed;"></i>Lab / Fabricator (Ear Mold & Shell)
                        </label>
                        <input type="text" class="form-control" name="lab_name" value="{{ old('lab_name', $saleMaster->lab_name) }}" placeholder="e.g. In-house Lab, Signia Lab">
                    </div>
                    @endif

                    @if ($due > 0.009)
                    <div style="background:#fff1f2; border:1px solid #fecdd3; border-radius:8px; padding:12px; margin-top:12px;">
                        <div class="custom-control custom-checkbox" style="margin-bottom:6px;">
                            <input type="checkbox" class="custom-control-input" id="details_collect_due_chk" name="collect_due" value="1">
                            <label class="custom-control-label" for="details_collect_due_chk" style="font-weight:700; color:#b91c1c; font-size:13px; cursor:pointer;">
                                <i class="fas fa-hand-holding-usd mr-1"></i>Collect Remaining Due Balance (৳{{ number_format($due, 2) }})
                            </label>
                        </div>
                        <div id="details_due_fields" style="display:none; padding-top:6px;">
                            <div class="row">
                                <div class="col-md-6 form-group mb-2">
                                    <label style="font-size:11px; font-weight:700; color:#475569;">Amount (৳)</label>
                                    <input type="number" step="0.01" class="form-control" name="paid_amount" value="{{ number_format($due, 2, '.', '') }}" max="{{ number_format($due, 2, '.', '') }}">
                                </div>
                                <div class="col-md-6 form-group mb-2">
                                    <label style="font-size:11px; font-weight:700; color:#475569;">Payment Date</label>
                                    <input type="date" class="form-control" name="payment_date" value="{{ date('Y-m-d') }}">
                                </div>
                            </div>
                            <div class="form-group mb-0">
                                <label style="font-size:11px; font-weight:700; color:#475569;">Remarks / Note</label>
                                <input type="text" class="form-control" name="payment_details" placeholder="Collected on handover / report delivery">
                            </div>
                        </div>
                    </div>
                    @endif

                    @php
                        $patientPhone = $saleMaster->customer?->lead?->phone_number ?? $saleMaster->customer?->phone ?? null;
                    @endphp
                    @if (!empty($patientPhone))
                    <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:12px; margin-top:12px;">
                        <div class="custom-control custom-checkbox" style="margin-bottom:6px;">
                            <input type="checkbox" class="custom-control-input" id="details_send_sms_chk" name="send_sms" value="1">
                            <label class="custom-control-label" for="details_send_sms_chk" style="font-weight:700; color:#15803d; font-size:13px; cursor:pointer;">
                                <i class="fas fa-sms mr-1"></i>Send Patient SMS Alert ({{ $patientPhone }})
                            </label>
                        </div>
                        <div id="details_sms_custom_wrap" style="display:none; padding-top:4px;">
                            <textarea class="form-control" name="custom_message" rows="2" style="font-size:12px;" placeholder="Optional custom SMS message…"></textarea>
                        </div>
                    </div>
                    @endif
                </div>
                <div class="modal-footer" style="padding: 14px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" style="font-weight:600;">
                        <i class="fas fa-check-circle mr-1"></i>Save &amp; Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@if ($due > 0.009)
{{-- Collect Due Modal --}}
<div class="modal fade" id="collectDueModal" tabindex="-1" role="dialog" aria-labelledby="collectDueModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 500px;">
        <div class="modal-content" style="border-radius: 14px; overflow: hidden; border: none; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
            <div class="modal-header" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff; padding: 16px 20px;">
                <h5 class="modal-title" id="collectDueModalLabel" style="font-weight: 700; font-size: 16px;">
                    <i class="fas fa-hand-holding-usd mr-2"></i>Collect Due Payment #{{ $saleMaster->sale_invoice_no }}
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.85;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" action="{{ route('sale.collect-due') }}">
                @csrf
                <input type="hidden" name="sale_invoice_no" value="{{ $saleMaster->sale_invoice_no }}">

                <div class="modal-body" style="padding: 20px;">
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; margin-bottom: 16px; text-align: center;">
                        <div style="font-size: 12px; color: var(--page-text-muted); text-transform: uppercase;">Current Invoice Due</div>
                        <div style="font-size: 22px; font-weight: 800; color: #ef4444; margin-top: 2px;">
                            ৳{{ number_format(max(0, $due), 2) }}
                        </div>
                        <div style="font-size: 11px; color: var(--page-text-muted); margin-top: 4px;">
                            Total: ৳{{ number_format($grandTotal, 2) }} · Already Paid: ৳{{ number_format($paid, 2) }}
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="crm-form-label">Payment Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="date" value="{{ date('Y-m-d') }}" required>
                    </div>

                    <div class="form-group">
                        <label class="crm-form-label">Amount Collected (৳) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" max="{{ number_format(max(0, $due), 2, '.', '') }}" class="form-control" name="paid_amount" id="details_paid_amount" value="{{ number_format(max(0, $due), 2, '.', '') }}" required style="font-size: 16px; font-weight: 700;">
                    </div>

                    <div class="form-group" id="wrap_details_new_due_date" style="display: none;">
                        <label class="crm-form-label"><i class="fas fa-calendar-alt mr-1" style="color: #4f46e5;"></i>New Promise / Due Date</label>
                        <input type="date" class="form-control" name="new_due_date" min="{{ date('Y-m-d') }}" value="{{ $saleMaster->due_date ? $saleMaster->due_date->format('Y-m-d') : '' }}">
                        <small class="text-muted">If partial payment, set the next promised date.</small>
                    </div>

                    <div class="form-group">
                        <label class="crm-form-label">Remarks / Note</label>
                        <textarea class="form-control" name="details" rows="2" placeholder="e.g. Received via Cash / Bank / bKash"></textarea>
                    </div>
                </div>

                <div class="modal-footer" style="padding: 14px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="fas fa-check mr-1"></i>Confirm & Save Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@push('scripts')
<script>
$(document).ready(function() {
    // Auto-open collect due modal if passed in query (?collect_due=1)
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('collect_due') === '1') {
        $('#collectDueModal').modal('show');
    }

    // 1-Click Stepper Button Status Update
    $(document).on('click', '.btn-step-action', function(e) {
        e.preventDefault();
        var btn = $(this);
        var targetStatus = btn.data('status');
        var due = parseFloat(btn.data('due')) || 0;

        // If target is delivered and there's a due balance, prompt to collect payment
        if (targetStatus === 'delivered' && due > 0.009) {
            $('#collectDueModal').modal('show');
            return;
        }

        var origHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: '{{ url("admin/sales/" . $saleMaster->id . "/update-delivery-status") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                delivery_status: targetStatus,
                send_sms: (targetStatus === 'ready_for_delivery' ? 1 : 0)
            },
            success: function(res) {
                if (res.success) {
                    if (window.toastr) {
                        toastr.success(res.message);
                    }
                    setTimeout(function() {
                        window.location.reload();
                    }, 500);
                } else {
                    btn.prop('disabled', false).html(origHtml);
                    alert(res.message || 'Status update failed');
                }
            },
            error: function(xhr) {
                btn.prop('disabled', false).html(origHtml);
                var err = xhr.responseJSON ? xhr.responseJSON.message : 'Server error occurred';
                alert(err);
            }
        });
    });

    @if ($due > 0.009)
    var fullDue = {{ max(0, $due) }};
    $('#details_paid_amount').on('input', function() {
        var val = parseFloat($(this).val()) || 0;
        if (val < (fullDue - 0.009)) {
            $('#wrap_details_new_due_date').slideDown(150);
        } else {
            $('#wrap_details_new_due_date').slideUp(150);
        }
    });
    @endif

    $('#details_collect_due_chk').on('change', function() {
        if ($(this).is(':checked')) {
            $('#details_due_fields').slideDown(150);
        } else {
            $('#details_due_fields').slideUp(150);
        }
    });

    $('#details_send_sms_chk').on('change', function() {
        if ($(this).is(':checked')) {
            $('#details_sms_custom_wrap').slideDown(150);
        } else {
            $('#details_sms_custom_wrap').slideUp(150);
        }
    });

    // Done Now modal handler
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
                }, 500);
            },
            error: function () {
                alert('Could not mark service as done. Please try again.');
                $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i>Confirm Done Today');
            }
        });
    });

    // Book later handler
    $('.btn-book-later').on('click', function () {
        var serviceId = $(this).data('id');
        var $btn = $(this);

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: baseDoneUrl + '/' + serviceId + '/book-later',
            method: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            success: function (res) {
                $btn.prop('disabled', false).html('<i class="fas fa-clock mr-1"></i>Book later');
                if (typeof toastr !== 'undefined') {
                    toastr.info(res.message);
                } else {
                    alert(res.message);
                }
            },
            error: function () {
                $btn.prop('disabled', false).html('<i class="fas fa-clock mr-1"></i>Book later');
                alert('Error updating service.');
            }
        });
    });
});
</script>

{{-- Simple "Done Now" Modal for Services in this sale --}}
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
                            @foreach(($professionals ?? []) as $p)
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
@endpush
@endsection
