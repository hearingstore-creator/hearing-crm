@extends('admin.layouts.master')
@section('page_title', 'Create Quotation & Proposal')
@section('breadcrumb_parent', 'Proposals')
@section('breadcrumb_parent_url', route('proposal.index'))

@php
    $prefillCustomer = $prefillCustomer ?? null;
    $prefillLead = $prefillLead ?? null;
    $prefillDeal = $prefillDeal ?? null;
    $nextProposalNo = $nextProposalNo ?? \App\Models\Proposal::generateProposalNo();
@endphp

@push('css')
<link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<style>
    .builder-card {
        background: #ffffff;
        border: 1px solid var(--card-border, #e2e8f0);
        border-radius: 12px;
        margin-bottom: 24px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }
    .builder-header {
        background: #f8fafc;
        padding: 14px 20px;
        border-bottom: 1px solid var(--card-border, #e2e8f0);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .builder-title {
        font-size: 15px;
        font-weight: 700;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
    }
    .builder-body {
        padding: 20px;
    }
    .type-radio-group {
        display: flex;
        gap: 12px;
        background: #f1f5f9;
        padding: 4px;
        border-radius: 10px;
        width: fit-content;
    }
    .type-radio-label {
        margin: 0;
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 6px;
        color: #64748b;
    }
    .type-radio-label input {
        display: none;
    }
    .type-radio-label.active {
        background: #ffffff;
        color: #2563eb;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .items-table {
        table-layout: auto;
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    .items-table th {
        background: #f1f5f9;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #334155;
        font-weight: 700;
        padding: 12px 10px;
        border-top: none;
        vertical-align: middle;
        border-bottom: 2px solid #cbd5e1 !important;
    }
    .items-table td {
        padding: 10px 8px;
        vertical-align: top;
        background: #ffffff;
        border-color: #e2e8f0;
    }
    .items-table .form-control {
        height: 38px !important;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 13px;
        color: #0f172a;
        background-color: #ffffff;
    }
    .items-table .form-control:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    }
    .qty-stepper {
        display: inline-flex;
        align-items: center;
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        overflow: hidden;
        background: #ffffff;
    }
    .qty-stepper .btn-qty {
        width: 32px;
        height: 38px;
        border: none;
        background: #f8fafc;
        color: #475569;
        font-size: 16px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        user-select: none;
        transition: all 0.15s;
        padding: 0;
    }
    .qty-stepper .btn-qty:hover {
        background: #e2e8f0;
        color: #0f172a;
    }
    .qty-stepper .qty-input {
        width: calc(100% - 64px) !important;
        height: 38px !important;
        border: none !important;
        border-left: 1px solid #e2e8f0 !important;
        border-right: 1px solid #e2e8f0 !important;
        border-radius: 0 !important;
        text-align: center !important;
        font-size: 15px !important;
        font-weight: 800 !important;
        color: #0f172a !important;
        background-color: #ffffff !important;
        padding: 0 4px !important;
        box-shadow: none !important;
        -moz-appearance: textfield;
    }
    .qty-stepper .qty-input::-webkit-outer-spin-button,
    .qty-stepper .qty-input::-webkit-inner-spin-button,
    .discount-percent-input::-webkit-outer-spin-button,
    .discount-percent-input::-webkit-inner-spin-button,
    .discount-taka-input::-webkit-outer-spin-button,
    .discount-taka-input::-webkit-inner-spin-button,
    .unit-price-input::-webkit-outer-spin-button,
    .unit-price-input::-webkit-inner-spin-button,
    #special_discount_percent::-webkit-outer-spin-button,
    #special_discount_percent::-webkit-inner-spin-button,
    #discount_amount::-webkit-outer-spin-button,
    #discount_amount::-webkit-inner-spin-button,
    #vat_amount::-webkit-outer-spin-button,
    #vat_amount::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    .discount-percent-input,
    .discount-taka-input,
    .unit-price-input,
    #special_discount_percent,
    #discount_amount,
    #vat_amount {
        -moz-appearance: textfield;
    }
    .items-table {
        min-width: 1040px;
    }
    .totals-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px 24px;
        max-width: 580px;
        width: 100%;
        margin-left: auto;
    }
    .totals-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 7px 0;
        font-size: 13px;
        color: #475569;
    }
    .totals-row.grand-total {
        border-top: 2px dashed #cbd5e1;
        margin-top: 10px;
        padding-top: 14px;
        font-size: 16px;
        font-weight: 800;
        color: #0f172a;
    }
    .select2-container .select2-selection--single {
        height: 38px !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 6px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 36px !important;
        font-size: 13px !important;
        padding-left: 10px !important;
        color: #0f172a !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-file-signature" style="color: var(--accent-500, #3b82f6); margin-right: 8px;"></i>Create Quotation & Treatment Plan</h1>
            <p>Prepare an itemized quotation for Hearing Aids, Diagnostic Services, and Clinical Care</p>
        </div>
        <div class="header-actions">
            <a href="{{ route('proposal.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i>Back to Quotations
            </a>
            <button type="submit" form="proposalForm" class="btn btn-primary btn-sm">
                <i class="fas fa-check mr-1"></i>Save &amp; Generate Quotation
            </button>
        </div>
    </div>

    @include('admin.alert_message')

    <form id="proposalForm" action="{{ route('proposal.store') }}" method="POST">
        @csrf

        {{-- 1. Recipient & Clinical Details Card --}}
        <div class="builder-card crm-animate-in delay-1">
            <div class="builder-header">
                <h3 class="builder-title"><i class="fas fa-user-circle text-primary"></i> 1. Patient / Recipient Information</h3>
                <div class="type-radio-group">
                    <label class="type-radio-label {{ $prefillCustomer ? '' : 'active' }}" id="lbl-type-lead">
                        <input type="radio" name="recipient_type" value="lead" {{ $prefillCustomer ? '' : 'checked' }}>
                        <i class="fas fa-id-badge"></i> Prospective Lead
                    </label>
                    <label class="type-radio-label {{ $prefillCustomer ? 'active' : '' }}" id="lbl-type-customer">
                        <input type="radio" name="recipient_type" value="customer" {{ $prefillCustomer ? 'checked' : '' }}>
                        <i class="fas fa-user-check"></i> Registered Patient
                    </label>
                    <label class="type-radio-label" id="lbl-type-direct">
                        <input type="radio" name="recipient_type" value="direct">
                        <i class="fas fa-envelope-open-text"></i> Direct / Custom
                    </label>
                </div>
            </div>
            <div class="builder-body">
                <div class="row">
                    {{-- Lead Selection --}}
                    <div class="col-md-6 recipient-block" id="block-lead" style="{{ $prefillCustomer ? 'display: none;' : '' }}">
                        <div class="form-group">
                            <label class="font-weight-bold">Select Lead / Inquiry <span class="text-danger">*</span></label>
                            <select class="form-control select2" name="lead_id" id="lead_select">
                                <option value="">— Search Lead by Name or Phone —</option>
                                @foreach($leads as $l)
                                    <option value="{{ $l->id }}" 
                                        data-name="{{ $l->full_name }}" 
                                        data-phone="{{ $l->phone_number }}" 
                                        data-email="{{ $l->email }}"
                                        data-address="{{ $l->address }}"
                                        data-vendor="{{ $l->vendor_id }}"
                                        {{ (old('lead_id', $prefillLead?->id) == $l->id) ? 'selected' : '' }}>
                                        {{ $l->full_name }} ({{ $l->phone_number }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Customer Selection --}}
                    <div class="col-md-6 recipient-block" id="block-customer" style="{{ $prefillCustomer ? '' : 'display: none;' }}">
                        <div class="form-group">
                            <label class="font-weight-bold">Select Registered Patient / Customer <span class="text-danger">*</span></label>
                            <select class="form-control select2" name="customer_id" id="customer_select">
                                <option value="">— Search Patient by Name or Phone —</option>
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}" 
                                        data-name="{{ $c->name }}" 
                                        data-phone="{{ $c->lead?->phone_number }}" 
                                        data-email="{{ $c->lead?->email }}"
                                        data-address="{{ $c->lead?->address }}"
                                        {{ (old('customer_id', $prefillCustomer?->id) == $c->id) ? 'selected' : '' }}>
                                        {{ $c->name }} ({{ $c->customer_uid }} - {{ $c->lead?->phone_number }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Direct Details (Name & Phone) --}}
                    <div class="col-md-6 recipient-block" id="block-direct" style="display: none;">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold">Recipient Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" name="recipient_name" id="direct_name" placeholder="Full Name" value="{{ old('recipient_name') }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="font-weight-bold">Phone Number</label>
                                    <input type="text" class="form-control form-control-sm" name="recipient_phone" id="direct_phone" placeholder="017xxxxxxxx" value="{{ old('recipient_phone') }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Referring Doctor --}}
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="font-weight-bold">Referring Doctor / Audiologist</label>
                            <select class="form-control select2" name="doctor_id" id="doctor_id">
                                <option value="">— None / Direct Walk-in —</option>
                                @foreach($doctors as $doc)
                                    <option value="{{ $doc->id }}" {{ old('doctor_id') == $doc->id ? 'selected' : '' }}>
                                        {{ $doc->name }} ({{ $doc->specialist ?? 'Doctor' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Branch Selection --}}
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="font-weight-bold">Clinic Branch</label>
                            <select class="form-control form-control-sm" name="vendor_id" id="vendor_id">
                                <option value="">— Default / Head Office —</option>
                                @foreach($branches as $bId => $bName)
                                    <option value="{{ $bId }}" {{ (old('vendor_id', auth()->user()->branchId()) == $bId) ? 'selected' : '' }}>
                                        {{ $bName }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Autofill summary banner --}}
                <div class="p-2 px-3 rounded" id="patient-meta-banner" style="background: #f1f5f9; font-size: 12px; color: #475569; display: flex; gap: 20px; align-items: center;">
                    <span><i class="fas fa-user mr-1 text-primary"></i> <strong id="lbl-meta-name">No recipient selected</strong></span>
                    <span><i class="fas fa-phone mr-1 text-success"></i> <span id="lbl-meta-phone">—</span></span>
                    <span><i class="fas fa-envelope mr-1 text-info"></i> <span id="lbl-meta-email">—</span></span>
                    <span><i class="fas fa-map-marker-alt mr-1 text-danger"></i> <span id="lbl-meta-address">—</span></span>
                </div>
            </div>
        </div>

        {{-- 2. Quotation Header & Presets --}}
        <div class="builder-card crm-animate-in delay-2">
            <div class="builder-header">
                <h3 class="builder-title"><i class="fas fa-clipboard-list text-info"></i> 2. Quotation Details &amp; Preset Template</h3>
                <span class="badge badge-primary px-2 py-1" style="font-family: monospace; font-size: 13px;">
                    Quote #: {{ $nextProposalNo }}
                </span>
            </div>
            <div class="builder-body">
                <div class="row">
                    <div class="col-md-5">
                        <div class="form-group">
                            <label class="font-weight-bold">Quotation Title / Treatment Goal <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" id="title" value="{{ old('title', 'Hearing Aid Fitting & Clinical Audiology Treatment Proposal') }}" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="font-weight-bold">Quote Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="proposal_date" id="proposal_date" value="{{ old('proposal_date', date('Y-m-d')) }}" required>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label class="font-weight-bold">Valid Until</label>
                            <input type="date" class="form-control" name="valid_until" id="valid_until" value="{{ old('valid_until', date('Y-m-d', strtotime('+30 days'))) }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label class="font-weight-bold">Status</label>
                            <select class="form-control" name="status" id="status">
                                @foreach(\App\Enums\ProposalStatus::cases() as $case)
                                    <option value="{{ $case->value }}" {{ $case->value === \App\Enums\ProposalStatus::DRAFT->value ? 'selected' : '' }}>
                                        {{ $case->value }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. Itemized Quotation Builder Table --}}
        <div class="builder-card crm-animate-in delay-3">
            <div class="builder-header">
                <h3 class="builder-title"><i class="fas fa-boxes text-success"></i> 3. Itemized Treatment Items &amp; Pricing</h3>
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-add-product">
                        <i class="fas fa-plus-circle mr-1"></i> Add Hearing Aid / Product
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm" id="btn-add-service">
                        <i class="fas fa-stethoscope mr-1"></i> Add Clinical Service
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-add-custom">
                        <i class="fas fa-pen mr-1"></i> Add Custom Item
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered items-table mb-0" id="itemsTable">
                    <thead>
                        <tr>
                            <th style="width: 125px;">Item Type</th>
                            <th>Description / Model</th>
                            <th style="width: 100px; text-align: center;">Warranty</th>
                            <th style="width: 115px; text-align: center;">Qty</th>
                            <th style="width: 130px; text-align: right;">Unit Price (৳)</th>
                            <th style="width: 115px; text-align: right;" title="Discount Percentage (%)">Disc (%)</th>
                            <th style="width: 135px; text-align: right;" title="Fixed Discount Amount in ৳">Disc (৳)</th>
                            <th style="width: 140px; text-align: right;">Total (৳)</th>
                            <th style="width: 48px; text-align: center;"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        {{-- Initial row populated via template or script --}}
                    </tbody>
                </table>
            </div>

            {{-- Totals Summary Section --}}
            <div class="p-3" style="background: #ffffff; border-top: 1px solid #e2e8f0;">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-2">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="font-weight-bold mb-0" style="font-size: 13px;">Clinical Warranty &amp; Aftercare Terms</label>
                                @if(isset($templates) && $templates->count() > 0)
                                    <div class="d-flex align-items-center" style="gap: 6px;">
                                        <span class="text-muted" style="font-size: 11px;"><i class="fas fa-magic text-primary"></i> Preset:</span>
                                        <select id="template_preset_select" class="form-control form-control-sm" style="height: 28px !important; font-size: 11px; padding: 2px 6px; width: 170px; border-radius: 4px;">
                                            <option value="">— Load Template —</option>
                                            @foreach($templates as $tpl)
                                                <option value="{{ $tpl->id }}" data-content="{{ e($tpl->content) }}">{{ $tpl->title }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                            </div>
                            <textarea class="form-control" name="terms_and_conditions" id="terms_and_conditions" rows="4" style="font-size: 12px; line-height: 1.6; height: auto !important;">• Includes standard Manufacturer Warranty on digital hearing aids as indicated.
• Complimentary 3 Trial & Tuning sessions within 60 days of dispensing.
• Free Lifetime Hearing Aid Cleaning, Moisture Removal & Audiological Support.
• Validity: 30 days from date of quotation. Payment: 50% advance upon order confirmation.</textarea>
                            <input type="hidden" name="template_id" id="selected_template_id" value="">
                        </div>
                        <div class="form-group mb-0">
                            <label class="font-weight-bold" style="font-size: 13px;">Internal Sales / Audiologist Notes</label>
                            <textarea class="form-control" name="notes" rows="2" placeholder="Private internal notes regarding patient preferences or doctor requests..." style="font-size: 12px; height: auto !important;"></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="totals-box">
                            <div class="totals-row">
                                <span style="font-size: 13px; color: #475569;">Hearing Devices Subtotal:</span>
                                <span class="font-weight-bold text-dark" id="lbl-subtotal-products" style="font-size: 14px;">৳0.00</span>
                            </div>
                            <div class="totals-row">
                                <span style="font-size: 13px; color: #475569;">Clinical Services Subtotal:</span>
                                <span class="font-weight-bold text-dark" id="lbl-subtotal-services" style="font-size: 14px;">৳0.00</span>
                            </div>
                            <div class="totals-row" style="border-top: 1px solid #e2e8f0; margin-top: 6px; padding-top: 8px;">
                                <span class="font-weight-bold text-dark" style="font-size: 13px;">Combined Subtotal:</span>
                                <span class="font-weight-bold text-dark" id="lbl-subtotal-all" style="font-size: 15px;">৳0.00</span>
                            </div>
                            <div class="totals-row" style="padding: 10px 0; border-top: 1px solid #e2e8f0; margin-top: 4px;">
                                <div style="font-weight: 700; color: #334155; font-size: 13px; display: flex; align-items: center; gap: 6px;">
                                    <i class="fas fa-tag text-warning"></i> Special Discount:
                                </div>
                                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <input type="hidden" name="discount_type" id="discount_type" value="fixed">
                                        <div class="input-group" style="width: 125px;">
                                            <input type="number" step="any" min="0" max="100" class="form-control text-right font-weight-bold" name="discount_percent" id="special_discount_percent" value="0.00" placeholder="0.00" autocomplete="off" style="height: 38px !important; font-size: 13px !important; font-weight: 700 !important; border-radius: 6px 0 0 6px !important; border: 1px solid #cbd5e1 !important; background: #ffffff !important; padding: 0 6px !important;">
                                            <div class="input-group-append">
                                                <span class="input-group-text px-2" style="font-size: 12px; height: 38px !important; background: #f8fafc; border: 1px solid #cbd5e1; border-left: none; border-radius: 0 6px 6px 0; color: #64748b; font-weight: 700;">%</span>
                                            </div>
                                        </div>
                                        <div class="input-group" style="width: 170px;">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text px-2" style="font-size: 12px; height: 38px !important; background: #f8fafc; border: 1px solid #cbd5e1; border-right: none; border-radius: 6px 0 0 6px; color: #64748b; font-weight: 700;">৳</span>
                                            </div>
                                            <input type="number" step="any" min="0" class="form-control text-right font-weight-bold" name="discount_amount" id="discount_amount" value="0.00" placeholder="0.00" autocomplete="off" style="height: 38px !important; font-size: 14px !important; font-weight: 700 !important; border-radius: 0 6px 6px 0 !important; border: 1px solid #cbd5e1 !important; background: #ffffff !important; padding: 0 8px !important;">
                                        </div>
                                    </div>
                                    <small id="special-discount-calc-badge" class="text-success font-weight-bold" style="font-size: 11px;">
                                        Applied: -৳0.00 (0.00% of Subtotal)
                                    </small>
                                </div>
                            </div>
                            <div class="totals-row" style="padding: 8px 0;">
                                <div style="font-weight: 600; color: #475569; font-size: 13px; display: flex; align-items: center; gap: 6px;">
                                    <i class="fas fa-receipt text-secondary"></i> VAT / Tax (optional):
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-size: 13px; font-weight: 700; color: #64748b;">৳</span>
                                    <input type="number" step="any" min="0" class="form-control text-right font-weight-bold" name="vat_amount" id="vat_amount" value="0.00" style="width: 140px !important; height: 38px !important; font-size: 14px !important; font-weight: 700 !important; border-radius: 6px !important; border: 1px solid #cbd5e1 !important; background: #ffffff !important;">
                                </div>
                            </div>
                            <div class="totals-row grand-total" style="padding-top: 14px; margin-top: 8px; border-top: 2px dashed #cbd5e1; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="font-size: 15px; font-weight: 800; color: #0f172a;">Total Quoted Amount:</div>
                                    <small class="text-muted" style="font-size: 11px;">Inclusive of all devices, services &amp; discounts</small>
                                </div>
                                <span class="text-primary" id="lbl-grand-total" style="font-size: 24px; font-weight: 800; letter-spacing: -0.5px;">৳0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Form Submit Bar --}}
        <div class="crm-form-submit-bar mb-4">
            <a href="{{ route('proposal.index') }}" class="btn btn-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-file-check mr-1"></i>Save &amp; Generate Quotation Document
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
<script>
$(document).ready(function() {
    $('.select2').select2({ width: '100%' });

    let rowIndex = 0;

    // Recipient Type Switcher
    $('input[name="recipient_type"]').on('change', function() {
        $('.type-radio-label').removeClass('active');
        $(this).closest('label').addClass('active');

        const type = $(this).val();
        $('.recipient-block').hide();

        if (type === 'lead') {
            $('#block-lead').show();
            updatePatientBannerFromLead();
        } else if (type === 'customer') {
            $('#block-customer').show();
            updatePatientBannerFromCustomer();
        } else {
            $('#block-direct').show();
            updatePatientBannerFromDirect();
        }
    });

    $('#lead_select').on('change', updatePatientBannerFromLead);
    $('#customer_select').on('change', updatePatientBannerFromCustomer);
    $('#direct_name, #direct_phone').on('input', updatePatientBannerFromDirect);

    function updatePatientBannerFromLead() {
        const sel = $('#lead_select').find(':selected');
        if (sel.val()) {
            $('#lbl-meta-name').text(sel.data('name') || 'Unnamed Lead');
            $('#lbl-meta-phone').text(sel.data('phone') || '—');
            $('#lbl-meta-email').text(sel.data('email') || '—');
            $('#lbl-meta-address').text(sel.data('address') || '—');
            if (sel.data('vendor')) {
                $('#vendor_id').val(sel.data('vendor'));
            }
        } else {
            $('#lbl-meta-name').text('No lead selected');
            $('#lbl-meta-phone').text('—');
            $('#lbl-meta-email').text('—');
            $('#lbl-meta-address').text('—');
        }
    }

    function updatePatientBannerFromCustomer() {
        const sel = $('#customer_select').find(':selected');
        if (sel.val()) {
            $('#lbl-meta-name').text(sel.data('name') || 'Unnamed Patient');
            $('#lbl-meta-phone').text(sel.data('phone') || '—');
            $('#lbl-meta-email').text(sel.data('email') || '—');
            $('#lbl-meta-address').text(sel.data('address') || '—');
        } else {
            $('#lbl-meta-name').text('No patient selected');
            $('#lbl-meta-phone').text('—');
            $('#lbl-meta-email').text('—');
            $('#lbl-meta-address').text('—');
        }
    }

    function updatePatientBannerFromDirect() {
        const name = $('#direct_name').val() || 'Direct Recipient';
        const phone = $('#direct_phone').val() || '—';
        $('#lbl-meta-name').text(name);
        $('#lbl-meta-phone').text(phone);
        $('#lbl-meta-email').text('—');
        $('#lbl-meta-address').text('—');
    }

    // Trigger initial state
    $('input[name="recipient_type"]:checked').trigger('change');

    // Add Row Item Handler
    function addRow(type = 'product') {
        rowIndex++;
        $.ajax({
            url: "{{ route('proposal.add-row') }}",
            type: 'GET',
            data: { index: rowIndex, type: type },
            success: function(html) {
                $('#itemsBody').append(html);
                const newRow = $('#itemsBody tr:last');
                newRow.find('.select2-product, .select2-service').select2({ width: '100%' });
                calculateTotals();
            }
        });
    }

    $('#btn-add-product').on('click', () => addRow('product'));
    $('#btn-add-service').on('click', () => addRow('service'));
    $('#btn-add-custom').on('click', () => addRow('custom'));

    // Template Preset Selection Handler
    $('#template_preset_select').on('change', function() {
        const sel = $(this).find(':selected');
        if (sel.val()) {
            const rawContent = sel.data('content');
            if (rawContent) {
                // Strip HTML tags if template was created with rich text editor
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = rawContent;
                const textContent = tempDiv.textContent || tempDiv.innerText || '';
                $('#terms_and_conditions').val(textContent.trim());
            }
            $('#selected_template_id').val(sel.val());
        }
    });

    function syncRowDiscountAndTotal($row, trigger) {
        const qty = parseFloat($row.find('.qty-input').val()) || 0;
        const price = parseFloat($row.find('.unit-price-input').val()) || 0;
        const gross = Math.max(0, qty * price);

        const $pctInput = $row.find('.discount-percent-input');
        const $takaInput = $row.find('.discount-taka-input');

        const pctRaw = $pctInput.val();
        const takaRaw = $takaInput.val();

        let percent = (pctRaw === '' || isNaN(pctRaw)) ? 0 : parseFloat(pctRaw);
        let taka = (takaRaw === '' || isNaN(takaRaw)) ? 0 : parseFloat(takaRaw);

        if (trigger === 'percent') {
            // User actively typing into discount-percent-input. Do not overwrite $pctInput while typing!
            if (percent < 0) percent = 0;
            if (percent > 100) percent = 100;
            taka = gross > 0 ? (gross * percent) / 100 : 0;
            $takaInput.val(taka.toFixed(2));
        } else if (trigger === 'taka') {
            // User actively typing into discount-taka-input. Do not overwrite $takaInput while typing!
            if (taka < 0) taka = 0;
            percent = gross > 0 ? (taka / gross) * 100 : 0;
            if (percent > 100) percent = 100;
            $pctInput.val(percent > 0 ? (percent % 1 === 0 ? percent.toFixed(0) : percent.toFixed(2)) : '0');
        } else {
            // trigger === 'base' (qty or price changed)
            if (percent > 0) {
                taka = gross > 0 ? (gross * percent) / 100 : 0;
                $takaInput.val(taka.toFixed(2));
            } else if (taka > 0) {
                if (gross > 0 && taka > gross) {
                    taka = gross;
                    $takaInput.val(gross.toFixed(2));
                }
                percent = gross > 0 ? (taka / gross) * 100 : 0;
                $pctInput.val(percent > 0 ? (percent % 1 === 0 ? percent.toFixed(0) : percent.toFixed(2)) : '0');
            }
        }

        const lineTotal = Math.max(0, gross - taka);
        $row.find('.total-price-input').val(lineTotal.toFixed(2));
    }

    // Row Type Switcher inside table
    $(document).on('change', '.item-type-select', function() {
        const row = $(this).closest('tr');
        const type = $(this).val();
        row.attr('data-type', type);

        row.find('.item-select-product, .item-select-service, .item-custom-title').hide();

        if (type === 'product') {
            row.find('.item-select-product').show();
            row.find('.warranty-input').val('24');
            row.find('.product-id-input').trigger('change');
        } else if (type === 'service') {
            row.find('.item-select-service').show();
            row.find('.warranty-input').val('0');
            row.find('.service-id-input').trigger('change');
        } else {
            row.find('.item-custom-title').show();
            row.find('.warranty-input').val('');
        }
        syncRowDiscountAndTotal(row, 'base');
        calculateTotals();
    });

    // Quantity Stepper buttons (+ and -)
    $(document).on('click', '.btn-qty-plus', function() {
        const input = $(this).closest('.qty-stepper').find('.qty-input');
        let val = parseFloat(input.val()) || 0;
        val = Math.round((val + 1) * 100) / 100;
        input.val(val).trigger('input');
    });

    $(document).on('click', '.btn-qty-minus', function() {
        const input = $(this).closest('.qty-stepper').find('.qty-input');
        let val = parseFloat(input.val()) || 0;
        if (val > 1) {
            val = Math.round((val - 1) * 100) / 100;
            input.val(val).trigger('input');
        } else if (val > 0.1) {
            val = Math.round((val - 0.5) * 100) / 100;
            if (val < 0.1) val = 0.1;
            input.val(val).trigger('input');
        }
    });

    // Product Selection Auto-fill
    $(document).on('change', '.product-id-input', function() {
        const opt = $(this).find(':selected');
        const row = $(this).closest('tr');
        if (opt.val()) {
            const name = opt.attr('data-name') || opt.data('name') || '';
            const warranty = opt.attr('data-warranty') || opt.data('warranty');
            const price = opt.attr('data-price') || opt.data('price');

            if (name) {
                row.find('.item-name-input').val(name);
            }
            if (warranty !== undefined && warranty !== '') {
                row.find('.warranty-input').val(warranty);
            }
            if (price !== undefined && price !== '') {
                row.find('.unit-price-input').val(parseFloat(price).toFixed(2));
            }
            const currentQty = parseFloat(row.find('.qty-input').val()) || 0;
            if (currentQty <= 0) {
                row.find('.qty-input').val(1);
            }
        }
        syncRowDiscountAndTotal(row, 'base');
        calculateTotals();
    });

    // Service Selection Auto-fill
    $(document).on('change', '.service-id-input', function() {
        const opt = $(this).find(':selected');
        const row = $(this).closest('tr');
        if (opt.val()) {
            const name = opt.attr('data-name') || opt.data('name') || '';
            const price = opt.attr('data-price') || opt.data('price');

            if (name) {
                row.find('.item-name-input').val(name);
            }
            if (price !== undefined && price !== '') {
                row.find('.unit-price-input').val(parseFloat(price).toFixed(2));
            }
            const currentQty = parseFloat(row.find('.qty-input').val()) || 0;
            if (currentQty <= 0) {
                row.find('.qty-input').val(1);
            }
        }
        syncRowDiscountAndTotal(row, 'base');
        calculateTotals();
    });

    // Remove row
    $(document).on('click', '.btn-remove-row', function() {
        $(this).closest('tr').remove();
        calculateTotals();
    });

    // Sync discount vice-versa on row item input
    $(document).on('input', '.discount-percent-input', function() {
        const row = $(this).closest('tr');
        syncRowDiscountAndTotal(row, 'percent');
        calculateTotals();
    });

    $(document).on('blur', '.discount-percent-input', function() {
        let val = parseFloat($(this).val()) || 0;
        if (val < 0) val = 0;
        if (val > 100) val = 100;
        $(this).val(val > 0 ? (val % 1 === 0 ? val.toFixed(0) : val.toFixed(2)) : '0');
        const row = $(this).closest('tr');
        syncRowDiscountAndTotal(row, 'percent');
        calculateTotals();
    });

    $(document).on('input', '.discount-taka-input', function() {
        const row = $(this).closest('tr');
        syncRowDiscountAndTotal(row, 'taka');
        calculateTotals();
    });

    $(document).on('blur', '.discount-taka-input', function() {
        let val = parseFloat($(this).val()) || 0;
        if (val < 0) val = 0;
        const row = $(this).closest('tr');
        const qty = parseFloat(row.find('.qty-input').val()) || 0;
        const price = parseFloat(row.find('.unit-price-input').val()) || 0;
        const gross = Math.max(0, qty * price);
        if (gross > 0 && val > gross) {
            val = gross;
        }
        $(this).val(val.toFixed(2));
        syncRowDiscountAndTotal(row, 'taka');
        calculateTotals();
    });

    $(document).on('input', '.qty-input, .unit-price-input', function() {
        const row = $(this).closest('tr');
        syncRowDiscountAndTotal(row, 'base');
        calculateTotals();
    });

    $(document).on('blur', '.unit-price-input', function() {
        let val = parseFloat($(this).val()) || 0;
        $(this).val(val.toFixed(2));
    });

    // Special Discount (Quotation Level) Vice-Versa
    let lastSpecialTrigger = 'taka';

    $('#special_discount_percent').on('input', function() {
        lastSpecialTrigger = 'percent';
        syncSpecialDiscount('percent');
    });

    $('#special_discount_percent').on('blur', function() {
        let pct = parseFloat($(this).val()) || 0;
        if (pct < 0) pct = 0;
        if (pct > 100) pct = 100;
        $(this).val(pct > 0 ? (pct % 1 === 0 ? pct.toFixed(0) : pct.toFixed(2)) : '0.00');
        syncSpecialDiscount('percent');
    });

    $('#discount_amount').on('input', function() {
        lastSpecialTrigger = 'taka';
        syncSpecialDiscount('taka');
    });

    $('#discount_amount').on('blur', function() {
        let amt = parseFloat($(this).val()) || 0;
        if (amt < 0) amt = 0;
        let subtotal = 0;
        $('#itemsBody tr').each(function() {
            subtotal += parseFloat($(this).find('.total-price-input').val()) || 0;
        });
        if (subtotal > 0 && amt > subtotal) {
            amt = subtotal;
        }
        $(this).val(amt.toFixed(2));
        syncSpecialDiscount('taka');
    });

    $('#vat_amount').on('input', function() {
        syncSpecialDiscount('recalc');
    });

    $('#vat_amount').on('blur', function() {
        let v = parseFloat($(this).val()) || 0;
        if (v < 0) v = 0;
        $(this).val(v.toFixed(2));
        syncSpecialDiscount('recalc');
    });

    function syncSpecialDiscount(trigger) {
        let subtotal = 0;
        $('#itemsBody tr').each(function() {
            subtotal += parseFloat($(this).find('.total-price-input').val()) || 0;
        });

        const $pctInput = $('#special_discount_percent');
        const $amtInput = $('#discount_amount');

        const pctRaw = $pctInput.val();
        const amtRaw = $amtInput.val();

        let pct = (pctRaw === '' || isNaN(pctRaw)) ? 0 : parseFloat(pctRaw);
        let amt = (amtRaw === '' || isNaN(amtRaw)) ? 0 : parseFloat(amtRaw);

        if (trigger === 'percent') {
            if (pct < 0) pct = 0;
            if (pct > 100) pct = 100;
            amt = subtotal > 0 ? (subtotal * pct) / 100 : 0;
            $amtInput.val(amt.toFixed(2));
        } else if (trigger === 'taka') {
            if (amt < 0) amt = 0;
            pct = subtotal > 0 ? (amt / subtotal) * 100 : 0;
            if (pct > 100) pct = 100;
            $pctInput.val(pct > 0 ? (pct % 1 === 0 ? pct.toFixed(0) : pct.toFixed(2)) : '0.00');
        } else {
            // Recalculating due to row item or VAT changes
            const isPctActive = document.activeElement === $pctInput[0];
            const isAmtActive = document.activeElement === $amtInput[0];

            if (isPctActive) {
                amt = subtotal > 0 ? (subtotal * pct) / 100 : 0;
                $amtInput.val(amt.toFixed(2));
            } else if (isAmtActive) {
                pct = subtotal > 0 ? (amt / subtotal) * 100 : 0;
                $pctInput.val(pct > 0 ? (pct % 1 === 0 ? pct.toFixed(0) : pct.toFixed(2)) : '0.00');
            } else {
                if (lastSpecialTrigger === 'percent') {
                    amt = subtotal > 0 ? (subtotal * pct) / 100 : 0;
                    $amtInput.val(amt.toFixed(2));
                } else {
                    pct = subtotal > 0 ? (amt / subtotal) * 100 : 0;
                    $pctInput.val(pct > 0 ? (pct % 1 === 0 ? pct.toFixed(0) : pct.toFixed(2)) : '0.00');
                }
            }
        }

        const overallDisc = parseFloat($amtInput.val()) || 0;
        const currentPct = parseFloat($pctInput.val()) || 0;
        $('#special-discount-calc-badge').text('Applied: -৳' + overallDisc.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' (' + currentPct.toFixed(2) + '% of Subtotal)');

        const vat = parseFloat($('#vat_amount').val()) || 0;
        const grandTotal = Math.max(0, (subtotal - overallDisc) + vat);
        $('#lbl-grand-total').text('৳' + grandTotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
    }

    // Auto-select 0 values on focus so typing immediately replaces the default 0
    $(document).on('focus', '.qty-input, .unit-price-input, .discount-percent-input, .discount-taka-input, #special_discount_percent, #discount_amount, #vat_amount', function() {
        const el = this;
        if (el.value === '0' || el.value === '0.00' || el.value === '0.0') {
            try { el.select(); } catch(e) {}
        }
    });

    function calculateTotals() {
        let prodSubtotal = 0;
        let svcSubtotal = 0;
        let allSubtotal = 0;

        $('#itemsBody tr').each(function() {
            const type = $(this).find('.item-type-select').val();
            const total = parseFloat($(this).find('.total-price-input').val()) || 0;

            if (type === 'product') {
                prodSubtotal += total;
            } else if (type === 'service') {
                svcSubtotal += total;
            }
            allSubtotal += total;
        });

        $('#lbl-subtotal-products').text('৳' + prodSubtotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#lbl-subtotal-services').text('৳' + svcSubtotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#lbl-subtotal-all').text('৳' + allSubtotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));

        syncSpecialDiscount('recalc');
    }

    // Add initial product row
    addRow('product');
});
</script>
@endpush
