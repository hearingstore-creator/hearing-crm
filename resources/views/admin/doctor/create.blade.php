@extends('admin.layouts.master')
@section('page_title', 'Add Doctor')
@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<style>
    .chamber-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 16px;
        margin-bottom: 14px;
        position: relative;
        transition: all 0.2s ease;
    }
    .chamber-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    }
    .chamber-card.is-primary {
        background: #f0fdf4;
        border-color: #86efac;
    }
    .primary-badge-label {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        color: var(--page-text-muted);
        margin: 0;
        user-select: none;
    }
    .chamber-card.is-primary .primary-badge-label {
        color: #15803d;
    }
    .btn-del-chamber, .btn-del-rule {
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        border: 1px solid #fecdd3;
        background: #fff1f2;
        color: #e11d48;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .btn-del-chamber:hover, .btn-del-rule:hover {
        background: #e11d48;
        color: #fff;
    }
    .rule-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 8px;
    }
    .rule-table th {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--page-text-muted);
        letter-spacing: 0.5px;
        padding: 4px 8px;
    }
    .rule-table td {
        padding: 4px 8px;
        vertical-align: middle;
    }
</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-user-md" style="color: var(--accent-500); margin-right: 8px;"></i>Add Doctor & Referral</h1>
            <p>Register a doctor with practice chambers and customized category/service commission rules</p>
        </div>
        <div class="header-actions">
            @can('permission', 'doctor_list.index')
            <a href="{{ url('admin/doctor') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>Doctor List</a>
            @endcan
        </div>
    </div>

    @include('admin.alert_message')

    <form action="{{ url('admin/doctor') }}" method="post" id="doctorForm">
        @csrf

        {{-- 1. Doctor Profile --}}
        <div class="crm-form-section crm-animate-in delay-1">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--accent-500);"><i class="fas fa-user-md"></i></div>
                <h6>Doctor Profile</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-5">
                        <div class="form-group">
                            <label for="name">Full Name <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" class="form-control" id="name" placeholder="e.g. Prof. Dr. Md. Rafiqul Islam / Quack Motin" name="name" required value="{{ old('name') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="referrer_type">Referrer Type <span style="color: var(--danger-500);">*</span></label>
                            <select class="form-control select2" id="referrer_type" name="referrer_type" required>
                                <option value="doctor" {{ old('referrer_type', 'doctor') === 'doctor' ? 'selected' : '' }}>🩺 Qualified Doctor / ENT</option>
                                <option value="quack" {{ old('referrer_type') === 'quack' ? 'selected' : '' }}>🌿 Village Doctor / Quack / RMP</option>
                                <option value="agent" {{ old('referrer_type') === 'agent' ? 'selected' : '' }}>🤝 RF / Referral Agent</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="qualification">Degree / Qualification / Role</label>
                            <input type="text" class="form-control" id="qualification" placeholder="e.g. MBBS, FCPS / RMP / Local Broker" name="qualification" value="{{ old('qualification') }}">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="contact_number">Personal Mobile Number</label>
                            <input type="text" class="form-control" id="contact_number" placeholder="e.g. 01711XXXXXX" name="contact_number" value="{{ old('contact_number') }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" class="form-control" id="email" placeholder="e.g. doctor@hospital.com" name="email" value="{{ old('email') }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Status <span style="color: var(--danger-500);">*</span></label>
                            <div class="mt-2">
                                <label class="radio-inline mr-3"><input type="radio" name="status" value="1" checked> <span class="badge badge-success">Active</span></label>
                                <label class="radio-inline"><input type="radio" name="status" value="0"> <span class="badge badge-secondary">Inactive</span></label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. Chambers Management (Multiple Chambers) --}}
        <div class="crm-form-section crm-animate-in delay-2">
            <div class="crm-form-section-header" style="justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="section-icon" style="background: #0ea5e9;"><i class="fas fa-hospital-user"></i></div>
                    <div>
                        <h6 style="margin: 0;">Chamber Addresses & Visiting Locations</h6>
                        <small style="color: var(--page-text-muted);">Add all clinics, diagnostic centers, and hospitals where this doctor sits</small>
                    </div>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-add-chamber">
                    <i class="fas fa-plus mr-1"></i>Add Chamber
                </button>
            </div>
            <div class="crm-form-section-body">
                <div id="chambers-container">
                    {{-- Chamber Card Template (First Chamber) --}}
                    <div class="chamber-card is-primary" data-index="0">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <label class="primary-badge-label">
                                <input type="radio" name="primary_chamber_index" value="0" checked onchange="updatePrimaryChamber(this)">
                                <span class="primary-text"><i class="fas fa-check-circle mr-1"></i>Primary Chamber</span>
                            </label>
                            <button type="button" class="btn-del-chamber" onclick="removeChamber(this)" title="Remove Chamber" style="display: none;">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-2">
                                    <label class="crm-form-label" style="font-size: 11px;">Chamber / Hospital Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="chambers[0][chamber_name]" placeholder="e.g. Popular Diagnostic Center, Room 304" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-2">
                                    <label class="crm-form-label" style="font-size: 11px;">Chamber / Assistant Phone</label>
                                    <input type="text" class="form-control" name="chambers[0][phone]" placeholder="e.g. 017XXXXXXXX / Serial Desk">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-2">
                                    <label class="crm-form-label" style="font-size: 11px;">Full Address / Location <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="chambers[0][address]" rows="2" placeholder="Street, Area, City, District" required></textarea>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group mb-2">
                                    <label class="crm-form-label" style="font-size: 11px;">Visiting Days</label>
                                    <input type="text" class="form-control" name="chambers[0][visiting_days]" placeholder="e.g. Sat - Thu">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group mb-2">
                                    <label class="crm-form-label" style="font-size: 11px;">Visiting Hours</label>
                                    <input type="text" class="form-control" name="chambers[0][visiting_time]" placeholder="e.g. 5:00 PM - 9:00 PM">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. Product Commissions (Default + Category-Wise Overrides) --}}
        <div class="crm-form-section crm-animate-in delay-3">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--success-500);"><i class="fas fa-boxes"></i></div>
                <div>
                    <h6 style="margin: 0;">Product Commission Settings</h6>
                    <small style="color: var(--page-text-muted);">Set default product commission + custom category-wise percentages or fixed amounts</small>
                </div>
            </div>
            <div class="crm-form-section-body">
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 18px; margin-bottom: 20px;">
                    <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: #0f172a; margin-bottom: 8px;">
                        <i class="fas fa-tag mr-1 text-primary"></i>Default Product Commission (Fallback Rate)
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label for="product_commission_percent">Default Commission Percentage (%)</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" max="100" class="form-control" id="product_commission_percent" placeholder="0.00" name="product_commission_percent" value="{{ old('product_commission_percent') ?? '0.00' }}">
                                    <div class="input-group-append"><span class="input-group-text">%</span></div>
                                </div>
                                <small class="text-muted">Applies to any product whose category is not listed below.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label for="product_commission_amount">OR Fixed Default Commission (৳)</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">৳</span></div>
                                    <input type="number" step="0.01" min="0" class="form-control" id="product_commission_amount" placeholder="0.00" name="product_commission_amount" value="{{ old('product_commission_amount') ?? '0.00' }}">
                                </div>
                                <small class="text-muted">Fixed amount per product item sold.</small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Category Overrides Table --}}
                <div style="border-top: 1px dashed #cbd5e1; padding-top: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <div>
                            <strong style="font-size: 13px; color: #0f172a;"><i class="fas fa-layer-group mr-1 text-success"></i>Category-Wise Commission Overrides</strong>
                            <div style="font-size: 12px; color: var(--page-text-muted);">Configure specific % or Fixed ৳ rates for specific product categories (e.g. Hearing Aid, Accessories).</div>
                        </div>
                        <button type="button" class="btn btn-outline-success btn-xs" id="btn-add-cat-comm">
                            <i class="fas fa-plus mr-1"></i>Add Category Override
                        </button>
                    </div>

                    <table class="rule-table" id="cat-comm-table">
                        <thead>
                            <tr>
                                <th style="width: 40%;">Product Category</th>
                                <th style="width: 25%;">Commission Type</th>
                                <th style="width: 25%;">Commission Value</th>
                                <th style="width: 10%; text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="cat-comm-body">
                            {{-- Dynamically populated rows --}}
                        </tbody>
                    </table>
                    <div id="no-cat-comm-msg" style="text-align: center; padding: 16px; color: var(--page-text-muted); font-size: 13px; background: #fff; border: 1px dashed #e2e8f0; border-radius: 8px;">
                        No category overrides configured yet. All product sales will use the default product rate above.
                    </div>
                </div>
            </div>
        </div>

        @include('admin.doctor.partials.product-rules', ['products' => $products ?? collect(), 'rules' => collect()])

        {{-- 4. Service Commissions (All-in-Total + Per-Service Overrides) --}}
        <div class="crm-form-section crm-animate-in delay-4">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: #6366f1;"><i class="fas fa-stethoscope"></i></div>
                <div>
                    <h6 style="margin: 0;">Service Commission Settings</h6>
                    <small style="color: var(--page-text-muted);">Set All-in-Total service rate + custom per-service overrides</small>
                </div>
            </div>
            <div class="crm-form-section-body">
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 18px; margin-bottom: 20px;">
                    <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: #0f172a; margin-bottom: 8px;">
                        <i class="fas fa-hand-holding-usd mr-1 text-primary"></i>All-in-Total Service Commission (Global Fallback)
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label for="service_commission_percent">All-in-Total Percentage (%)</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" max="100" class="form-control" id="service_commission_percent" placeholder="0.00" name="service_commission_percent" value="{{ old('service_commission_percent') ?? '0.00' }}">
                                    <div class="input-group-append"><span class="input-group-text">%</span></div>
                                </div>
                                <small class="text-muted">Applies to all services unless a specific service override is added below.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label for="service_commission_amount">OR All-in-Total Fixed Amount (৳)</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">৳</span></div>
                                    <input type="number" step="0.01" min="0" class="form-control" id="service_commission_amount" placeholder="0.00" name="service_commission_amount" value="{{ old('service_commission_amount') ?? '0.00' }}">
                                </div>
                                <small class="text-muted">Fixed amount per service item rendered.</small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Service Overrides Table --}}
                <div style="border-top: 1px dashed #cbd5e1; padding-top: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <div>
                            <strong style="font-size: 13px; color: #0f172a;"><i class="fas fa-vial mr-1 text-indigo"></i>Per-Service Wise Commission Overrides</strong>
                            <div style="font-size: 12px; color: var(--page-text-muted);">Set custom commission rates for individual diagnostic tests or clinical services (e.g. Pure Tone Audiometry).</div>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-xs" id="btn-add-srv-comm">
                            <i class="fas fa-plus mr-1"></i>Add Service Override
                        </button>
                    </div>

                    <table class="rule-table" id="srv-comm-table">
                        <thead>
                            <tr>
                                <th style="width: 40%;">Service / Test</th>
                                <th style="width: 25%;">Commission Type</th>
                                <th style="width: 25%;">Commission Value</th>
                                <th style="width: 10%; text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="srv-comm-body">
                            {{-- Dynamically populated rows --}}
                        </tbody>
                    </table>
                    <div id="no-srv-comm-msg" style="text-align: center; padding: 16px; color: var(--page-text-muted); font-size: 13px; background: #fff; border: 1px dashed #e2e8f0; border-radius: 8px;">
                        No service-specific overrides configured yet. All services will use the All-in-Total rate above.
                    </div>
                </div>
            </div>
        </div>

        <div class="crm-form-submit-bar">
            <a href="{{ url('admin/doctor') }}" class="btn btn-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Save Doctor</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
const availableCategories = @json($categories ?? []);
const availableServices = @json($serviceTypes ?? []);

let chamberCount = 1;
let catRuleCount = 0;
let srvRuleCount = 0;

// Add Chamber
$('#btn-add-chamber').on('click', function() {
    const idx = chamberCount++;
    const cardHtml = `
        <div class="chamber-card" data-index="${idx}">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <label class="primary-badge-label">
                    <input type="radio" name="primary_chamber_index" value="${idx}" onchange="updatePrimaryChamber(this)">
                    <span class="primary-text"><i class="far fa-circle mr-1"></i>Set as Primary Chamber</span>
                </label>
                <button type="button" class="btn-del-chamber" onclick="removeChamber(this)" title="Remove Chamber">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-2">
                        <label class="crm-form-label" style="font-size: 11px;">Chamber / Hospital Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="chambers[${idx}][chamber_name]" placeholder="e.g. Square Hospital - Room 402" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-2">
                        <label class="crm-form-label" style="font-size: 11px;">Chamber / Assistant Phone</label>
                        <input type="text" class="form-control" name="chambers[${idx}][phone]" placeholder="e.g. 017XXXXXXXX">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-2">
                        <label class="crm-form-label" style="font-size: 11px;">Full Address / Location <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="chambers[${idx}][address]" rows="2" placeholder="Street, Area, City" required></textarea>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-2">
                        <label class="crm-form-label" style="font-size: 11px;">Visiting Days</label>
                        <input type="text" class="form-control" name="chambers[${idx}][visiting_days]" placeholder="e.g. Sun, Tue, Thu">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-2">
                        <label class="crm-form-label" style="font-size: 11px;">Visiting Hours</label>
                        <input type="text" class="form-control" name="chambers[${idx}][visiting_time]" placeholder="e.g. 6:00 PM - 9:00 PM">
                    </div>
                </div>
            </div>
        </div>
    `;
    $('#chambers-container').append(cardHtml);
    checkChamberDeleteButtons();
});

function removeChamber(btn) {
    const $card = $(btn).closest('.chamber-card');
    const wasPrimary = $card.hasClass('is-primary');
    $card.remove();

    if (wasPrimary) {
        const $first = $('#chambers-container .chamber-card').first();
        if ($first.length) {
            $first.find('input[type="radio"]').prop('checked', true).trigger('change');
        }
    }
    checkChamberDeleteButtons();
}

function updatePrimaryChamber(radio) {
    $('#chambers-container .chamber-card').removeClass('is-primary');
    $('#chambers-container .primary-text').html('<i class="far fa-circle mr-1"></i>Set as Primary Chamber');

    const $card = $(radio).closest('.chamber-card');
    $card.addClass('is-primary');
    $card.find('.primary-text').html('<i class="fas fa-check-circle mr-1"></i>Primary Chamber');
}

function checkChamberDeleteButtons() {
    const count = $('#chambers-container .chamber-card').length;
    if (count <= 1) {
        $('#chambers-container .btn-del-chamber').hide();
    } else {
        $('#chambers-container .btn-del-chamber').show();
    }
}

// Add Category Commission
$('#btn-add-cat-comm').on('click', function() {
    const idx = catRuleCount++;
    let catOptions = '';
    availableCategories.forEach(c => {
        catOptions += `<option value="${c.id}">${c.category_name}</option>`;
    });

    const rowHtml = `
        <tr data-index="${idx}">
            <td>
                <select name="category_commissions[${idx}][category_id]" class="form-control" required>
                    ${catOptions}
                </select>
            </td>
            <td>
                <select name="category_commissions[${idx}][commission_type]" class="form-control" required>
                    <option value="percent">Percentage (%)</option>
                    <option value="fixed">Fixed Amount (৳)</option>
                </select>
            </td>
            <td>
                <input type="number" step="0.01" min="0" name="category_commissions[${idx}][commission_value]" class="form-control" placeholder="Value" required>
            </td>
            <td style="text-align: center;">
                <button type="button" class="btn-del-rule" onclick="$(this).closest('tr').remove(); checkCatTable();">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        </tr>
    `;
    $('#cat-comm-body').append(rowHtml);
    checkCatTable();
});

function checkCatTable() {
    const hasRows = $('#cat-comm-body tr').length > 0;
    $('#no-cat-comm-msg').toggle(!hasRows);
}

// Add Service Commission
$('#btn-add-srv-comm').on('click', function() {
    const idx = srvRuleCount++;
    let srvOptions = '';
    availableServices.forEach(s => {
        srvOptions += `<option value="${s.id}">${s.service_type_name} (৳${s.price})</option>`;
    });

    const rowHtml = `
        <tr data-index="${idx}">
            <td>
                <select name="service_commissions[${idx}][service_type_id]" class="form-control" required>
                    ${srvOptions}
                </select>
            </td>
            <td>
                <select name="service_commissions[${idx}][commission_type]" class="form-control" required>
                    <option value="percent">Percentage (%)</option>
                    <option value="fixed">Fixed Amount (৳)</option>
                </select>
            </td>
            <td>
                <input type="number" step="0.01" min="0" name="service_commissions[${idx}][commission_value]" class="form-control" placeholder="Value" required>
            </td>
            <td style="text-align: center;">
                <button type="button" class="btn-del-rule" onclick="$(this).closest('tr').remove(); checkSrvTable();">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        </tr>
    `;
    $('#srv-comm-body').append(rowHtml);
    checkSrvTable();
});

function checkSrvTable() {
    const hasRows = $('#srv-comm-body tr').length > 0;
    $('#no-srv-comm-msg').toggle(!hasRows);
}
</script>
@endpush
