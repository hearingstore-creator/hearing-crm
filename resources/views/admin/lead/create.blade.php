@extends('admin.layouts.master')
@section('page_title', 'Add New Lead')
@section('breadcrumb_parent', 'Leads')
@section('breadcrumb_parent_url', url('admin/leads'))

@push('css')
  <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
  <style>
    .lf-gate { --gate-color: #6366f1; margin: 4px 0 16px; padding: 14px 16px 4px; border-radius: 12px; border: 1px solid color-mix(in srgb, var(--gate-color) 35%, transparent);
               background: color-mix(in srgb, var(--gate-color) 7%, transparent); animation: lfGateIn .25s ease; }
    .lf-gate-head { font-size: 12.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--gate-color); margin-bottom: 10px; }
    @keyframes lfGateIn { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: none; } }
    .lf-types { display: flex; flex-wrap: wrap; gap: 8px; }
    .lf-type input { position: absolute; opacity: 0; pointer-events: none; }
    .lf-type span { display: inline-flex; align-items: center; gap: 7px; padding: 8px 14px; border-radius: 999px; cursor: pointer; font-size: 13px; font-weight: 600;
                    border: 1.5px solid var(--border-color, rgba(148,163,184,.35)); transition: all .15s; }
    .lf-type span:hover { border-color: var(--accent-500, #6366f1); }
    .lf-type input:checked + span { color: #fff; background: var(--accent-500, #6366f1); border-color: var(--accent-500, #6366f1); box-shadow: 0 6px 14px -6px var(--accent-500, #6366f1); }
    .lf-type input:focus-visible + span { outline: 2px solid var(--accent-500, #6366f1); outline-offset: 2px; }
  </style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-user-plus" style="color: var(--accent-500); margin-right: 8px;"></i>Add New Lead</h1>
            <p>Fill in the details below to create a new lead with an initial follow-up</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/leads') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i>Back to Leads
            </a>
        </div>
    </div>

    {{-- Step Progress --}}
    <div class="crm-form-progress crm-animate-in delay-1" id="leadFormProgress">
        <div class="crm-form-step active" data-step="1">
            <span class="step-number">1</span>
            <span>Basic Info</span>
        </div>
        <div class="step-connector"></div>
        <div class="crm-form-step" data-step="2">
            <span class="step-number">2</span>
            <span>Contact Person</span>
        </div>
        <div class="step-connector"></div>
        <div class="crm-form-step" data-step="3">
            <span class="step-number">3</span>
            <span>Hearing & Details</span>
        </div>
        <div class="step-connector"></div>
        <div class="crm-form-step" data-step="4">
            <span class="step-number">4</span>
            <span>Management</span>
        </div>
        <div class="step-connector"></div>
        <div class="crm-form-step" data-step="5">
            <span class="step-number">5</span>
            <span>First Activity</span>
        </div>
    </div>

    @include('admin.alert_message')

    <form action="{{ url('admin/leads') }}" method="post" enctype="multipart/form-data">
        @csrf

        {{-- SECTION 1: Basic Information --}}
        <div class="crm-form-section crm-animate-in delay-2">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--accent-500);"><i class="fas fa-user"></i></div>
                <h6>Basic Information</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="full_name">Full Name <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" class="form-control @error('full_name') is-invalid @enderror" id="full_name" name="full_name" placeholder="Lead full name" value="{{ old('full_name') }}" required>
                            @error('full_name')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="phone_number">Phone Number <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" class="form-control @error('phone_number') is-invalid @enderror" id="phone_number" name="phone_number" placeholder="Phone number" value="{{ old('phone_number') }}" required>
                            @error('phone_number')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" placeholder="Email address" value="{{ old('email') }}">
                            @error('email')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="row">
                            <div class="col-5 pr-1">
                                <div class="form-group">
                                    <label for="age">Age</label>
                                    <input type="number" class="form-control @error('age') is-invalid @enderror" id="age" name="age" placeholder="Age" min="1" max="150" value="{{ old('age') }}">
                                    @error('age')
                                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-7 pl-1">
                                <div class="form-group">
                                    <label for="date_of_birth">Date of Birth</label>
                                    <input type="date" class="form-control @error('date_of_birth') is-invalid @enderror" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth') }}" max="{{ date('Y-m-d') }}" onchange="(function(v){if(!v)return;var d=new Date(v),n=new Date(),a=n.getFullYear()-d.getFullYear();if(n.getMonth()<d.getMonth()||(n.getMonth()==d.getMonth()&&n.getDate()<d.getDate()))a--;if(a>0)document.getElementById('age').value=a;})(this.value)">
                                    @error('date_of_birth')
                                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select class="form-control select @error('gender') is-invalid @enderror" name="gender" id="gender">
                                <option value="">Select</option>
                                @foreach($genders as $gender)
                                    <option value="{{ $gender->value }}" {{ old('gender') == $gender->value ? 'selected' : '' }}>
                                        {{ ucfirst($gender->value) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('gender')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="district_id">District</label>
                            <select class="form-control select @error('district_id') is-invalid @enderror" name="district_id" id="district_id">
                                <option value="">Select</option>
                                @foreach($districts as $val)
                                    <option value="{{ $val->id }}" {{ old('district_id') == $val->id ? 'selected' : '' }}>
                                        {{ $val->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('district_id')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="lead_type">Lead Type</label>
                            <select class="form-control select @error('lead_type') is-invalid @enderror" name="lead_type" id="lead_type">
                                <option disabled>Select</option>
                                @foreach($leadTypes as $type)
                                    <option value="{{ $type->value }}" {{ old('lead_type') == $type->value ? 'selected' : '' }}>
                                        {{ $type->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('lead_type')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="2" placeholder="Write address...">{{ old('address') }}</textarea>
                            @error('address')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="ref_by">Referred By</label>
                            <input type="text" class="form-control @error('ref_by') is-invalid @enderror" id="ref_by" name="ref_by" placeholder="Referrer name" value="{{ old('ref_by') }}">
                            @error('ref_by')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="referred_by">Referred by member (code or phone)</label>
                            <input type="text" class="form-control @error('referred_by') is-invalid @enderror" id="referred_by" name="referred_by" placeholder="Member referral code or phone" autocomplete="off" value="{{ old('referred_by') }}">
                            @error('referred_by')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- SECTION 2: Contact Person --}}
        <div class="crm-form-section crm-animate-in delay-2">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--gray-500);"><i class="fas fa-users"></i></div>
                <h6>Contact Person</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="contact_person_name">Contact Name</label>
                            <input type="text" class="form-control @error('contact_person_name') is-invalid @enderror" id="contact_person_name" name="contact_person_name" placeholder="Name of contact person" value="{{ old('contact_person_name') }}">
                            @error('contact_person_name')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="relation">Relation</label>
                            <select class="form-control select @error('relation') is-invalid @enderror" name="relation" id="relation">
                                <option value="">Select</option>
                                @foreach($relations as $relation)
                                    <option value="{{ $relation->value }}" {{ old('relation') == $relation->value ? 'selected' : '' }}>
                                        {{ $relation->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('relation')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="contact_person_phone">Contact Phone</label>
                            <input type="text" class="form-control @error('contact_person_phone') is-invalid @enderror" id="contact_person_phone" name="contact_person_phone" placeholder="Phone of contact person" value="{{ old('contact_person_phone') }}">
                            @error('contact_person_phone')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- SECTION 3: Hearing & Requirement --}}
        <div class="crm-form-section crm-animate-in delay-3">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: #7C3AED;"><i class="fas fa-assistive-listening-systems"></i></div>
                <h6>Hearing & Requirement</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="inquiry_for">Inquiry For <span style="color: var(--danger-500);">*</span></label>
                            <select class="form-control select @error('inquiry_for') is-invalid @enderror" name="inquiry_for[]" multiple id="inquiry_for" required>
                                <option disabled>Select</option>
                                @foreach($inquiries as $inquiry)
                                    <option value="{{ $inquiry->value }}" {{ old('inquiry_for') == $inquiry->value ? 'selected' : '' }}>
                                        {{ $inquiry->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('inquiry_for')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="interest_brand_product">Interested Product</label>
                            <input type="text" class="form-control @error('interest_brand_product') is-invalid @enderror" id="interest_brand_product" name="interest_brand_product" placeholder="Brand or product name" value="{{ old('interest_brand_product') }}">
                            @error('interest_brand_product')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="approx_budget">Approx Budget (৳)</label>
                            <input type="number" step="0.01" class="form-control @error('approx_budget') is-invalid @enderror" id="approx_budget" name="approx_budget" placeholder="e.g. 5000" value="{{ old('approx_budget') }}">
                            @error('approx_budget')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Hearing Loss --}}
                <div style="margin-top: var(--space-4); padding-top: var(--space-4); border-top: 1px solid var(--card-border);">
                    <label style="color: #7C3AED; font-weight: 600; margin-bottom: var(--space-4) !important; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-ear-listen"></i> Hearing Loss Assessment
                    </label>
                    <div class="row">
                        {{-- Right Ear --}}
                        <div class="col-md-6">
                            <div style="background: var(--table-header-bg); border-radius: 10px; padding: var(--space-4); border: 1px solid var(--card-border);">
                                <div class="custom-control custom-checkbox mb-3">
                                    <input type="checkbox" class="custom-control-input" id="hearing_right_enabled" name="hearing_loss[right][enabled]" value="1" onclick="toggleHearingEar('right')">
                                    <label class="custom-control-label" for="hearing_right_enabled" style="font-weight: 600 !important;">
                                        <i class="fas fa-ear-listen mr-1" style="color: #7C3AED;"></i> Right Ear
                                    </label>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="hearing_right_degree" style="font-size: 11px !important;">Degree</label>
                                            <select class="form-control select" name="hearing_loss[right][degree]" id="hearing_right_degree" disabled>
                                                <option value="">Select</option>
                                                @foreach($hearingDegrees as $degree)
                                                    <option value="{{ $degree->value }}" {{ old('hearing_loss.right.degree') == $degree->value ? 'selected' : '' }}>
                                                        {{ $degree->label() }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="hearing_right_type" style="font-size: 11px !important;">Type</label>
                                            <select class="form-control select" name="hearing_loss[right][type]" id="hearing_right_type" disabled>
                                                <option value="">Select</option>
                                                @foreach($hearingTypes as $type)
                                                    <option value="{{ $type->value }}" {{ old('hearing_loss.right.type') == $type->value ? 'selected' : '' }}>
                                                        {{ $type->label() }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{-- Left Ear --}}
                        <div class="col-md-6">
                            <div style="background: var(--table-header-bg); border-radius: 10px; padding: var(--space-4); border: 1px solid var(--card-border);">
                                <div class="custom-control custom-checkbox mb-3">
                                    <input type="checkbox" class="custom-control-input" id="hearing_left_enabled" name="hearing_loss[left][enabled]" value="1" onclick="toggleHearingEar('left')">
                                    <label class="custom-control-label" for="hearing_left_enabled" style="font-weight: 600 !important;">
                                        <i class="fas fa-ear-listen mr-1" style="color: #7C3AED;"></i> Left Ear
                                    </label>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="hearing_left_degree" style="font-size: 11px !important;">Degree</label>
                                            <select class="form-control select" name="hearing_loss[left][degree]" id="hearing_left_degree" disabled>
                                                <option value="">Select</option>
                                                @foreach($hearingDegrees as $degree)
                                                    <option value="{{ $degree->value }}" {{ old('hearing_loss.left.degree') == $degree->value ? 'selected' : '' }}>
                                                        {{ $degree->label() }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="hearing_left_type" style="font-size: 11px !important;">Type</label>
                                            <select class="form-control select" name="hearing_loss[left][type]" id="hearing_left_type" disabled>
                                                <option value="">Select</option>
                                                @foreach($hearingTypes as $type)
                                                    <option value="{{ $type->value }}" {{ old('hearing_loss.left.type') == $type->value ? 'selected' : '' }}>
                                                        {{ $type->label() }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- SECTION 4: Lead Management --}}
        <div class="crm-form-section crm-animate-in delay-3">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--warning-500);"><i class="fas fa-filter"></i></div>
                <h6>Lead Management</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="source_of_entry">Source of Entry <i class="text-danger">*</i></label>
                            <select class="form-control select @error('source_of_entry') is-invalid @enderror" name="source_of_entry" id="source_of_entry">
                                <option value="">Select</option>
                                @foreach($sourceOfEntries as $source)
                                    <option value="{{ $source->value }}" {{ old('source_of_entry') == $source->value ? 'selected' : '' }}>
                                        {{ $source->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('source_of_entry')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="campaign_id">Campaign <small class="text-muted">(which ad, camp or leaflet)</small></label>
                            <select class="form-control select @error('campaign_id') is-invalid @enderror" name="campaign_id" id="campaign_id">
                                <option value="">None / not known</option>
                                @foreach($marketingCampaigns ?? [] as $mc)
                                    <option value="{{ $mc->id }}" {{ (string) old('campaign_id') === (string) $mc->id ? 'selected' : '' }}>{{ $mc->name }}</option>
                                @endforeach
                            </select>
                            @error('campaign_id')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="stage_id">Stage <span style="color: var(--danger-500);">*</span></label>
                            <select class="form-control select @error('stage_id') is-invalid @enderror" name="stage_id" id="stage_id" required>
                                @foreach($stages as $stage)
                                    <option value="{{ $stage->id }}" data-color="{{ $stage->color }}" data-required="{{ implode(',', $stage->required_fields ?? []) }}" {{ old('stage_id', $stages->first()?->id) == $stage->id ? 'selected' : '' }}>{{ $stage->name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Same stages and rules as the Deals Board. Won / Lost are set there.</small>
                            @error('stage_id')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        @include('admin.lead.partials.temperature-picker', ['selected' => old('lead_temperature', 'warm')])
                    </div>
                    @if(! auth()->user()->isVendor())
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="vendor_id">Assign to Branch</label>
                            <select class="form-control select @error('vendor_id') is-invalid @enderror" name="vendor_id" id="vendor_id">
                                <option value="">Select</option>
                                @foreach($vendors as $vendor)
                                    <option value="{{ $vendor->id }}" {{ old('vendor_id') == $vendor->id ? 'selected' : '' }}>
                                        {{ $vendor->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('vendor_id')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                    @endif
                </div>

                {{-- What the chosen stage needs (same rules as moving a card on the board) --}}
                <div class="lf-gate" id="stage-gate" style="display: none;">
                    <div class="lf-gate-head"><i class="fas fa-clipboard-check"></i> <span id="stage-gate-title">This stage needs</span></div>
                    <div class="row">
                        <div class="col-md-12" data-gate="note">
                            <div class="form-group">
                                <label for="gate_note">Counselling note <span style="color: var(--danger-500);">*</span></label>
                                <textarea class="form-control @error('note') is-invalid @enderror" id="gate_note" name="note" rows="2" maxlength="1000" placeholder="What was discussed on the call?">{{ old('note') }}</textarea>
                                @error('note')<span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>@enderror
                            </div>
                        </div>
                        <div class="col-md-6" data-gate="appointment_or_walkin">
                            <div class="form-group">
                                <label for="gate_appointment_at">Appointment <span style="color: var(--danger-500);">*</span></label>
                                <input type="datetime-local" class="form-control @error('appointment_at') is-invalid @enderror" id="gate_appointment_at" name="appointment_at" value="{{ old('appointment_at') }}">
                                <div class="custom-control custom-checkbox mt-2">
                                    <input type="checkbox" class="custom-control-input" id="gate_walk_in" name="walk_in" value="1" {{ old('walk_in') ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="gate_walk_in">Walk-in (already in the store)</label>
                                </div>
                                @error('appointment_at')<span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>@enderror
                            </div>
                        </div>
                        <div class="col-md-6" data-gate="trial_product">
                            <div class="form-group">
                                <label for="gate_trial_product">Trial product <span style="color: var(--danger-500);">*</span></label>
                                <input type="text" class="form-control @error('trial_product') is-invalid @enderror" id="gate_trial_product" name="trial_product" maxlength="150" value="{{ old('trial_product') }}" placeholder="e.g. Phonak Audeo L50">
                                @error('trial_product')<span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>@enderror
                                <select name="trial_result" class="form-control mt-2" aria-label="How the trial went">
                                    <option value="">How did the trial go? (optional)</option>
                                    @foreach (\App\Models\DealTrial::RESULTS as $rk => $rv)<option value="{{ $rk }}" @selected(old('trial_result') === $rk)>{{ $rv }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6" data-gate="trial_return_date">
                            <div class="form-group">
                                <label for="gate_trial_return_date">Trial return date <span style="color: var(--danger-500);">*</span></label>
                                <input type="date" class="form-control @error('trial_return_date') is-invalid @enderror" id="gate_trial_return_date" name="trial_return_date" value="{{ old('trial_return_date') }}" min="{{ now()->toDateString() }}">
                                @error('trial_return_date')<span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>@enderror
                            </div>
                        </div>
                        <div class="col-md-6" data-gate="value">
                            <div class="form-group">
                                <label for="gate_value">Quoted value (৳) <span style="color: var(--danger-500);">*</span></label>
                                <input type="number" class="form-control @error('value') is-invalid @enderror" id="gate_value" name="value" min="1" step="0.01" value="{{ old('value') }}" placeholder="Leave empty to use Approx Budget">
                                @error('value')<span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Notes --}}
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="notes">Consultation Notes</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3" placeholder="Write consultation notes...">{{ old('notes') }}</textarea>
                            @error('notes')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- SECTION 5: First activity (same activity types as the Deals Board) --}}
        <div class="crm-form-section crm-animate-in delay-4">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--success-500);"><i class="fas fa-calendar-check"></i></div>
                <h6>First Activity</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="form-group">
                    <label>What's next? <span style="color: var(--danger-500);">*</span></label>
                    <div class="lf-types" role="radiogroup" aria-label="Activity type">
                        @foreach($activityTypes as $type)
                            <label class="lf-type" for="activity_type_{{ $type->value }}">
                                <input type="radio" name="activity_type" id="activity_type_{{ $type->value }}" value="{{ $type->value }}" {{ old('activity_type', 'call') === $type->value ? 'checked' : '' }}>
                                <span><i class="{{ $type->icon() }}"></i> {{ $type->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('activity_type')<span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>@enderror
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="due_at">When <span style="color: var(--danger-500);">*</span></label>
                            <input type="datetime-local" class="form-control @error('due_at') is-invalid @enderror" id="due_at" name="due_at" value="{{ old('due_at', now()->addDay()->setTime(11, 0)->format('Y-m-d\TH:i')) }}" required>
                            <small class="text-muted">Default: tomorrow 11:00 AM</small>
                            @error('due_at')<span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>@enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="assigned_user">Assigned to</label>
                            <select class="form-control select @error('assigned_user') is-invalid @enderror" name="assigned_user" id="assigned_user">
                                <option value="">Deal owner</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" {{ old('assigned_user') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                @endforeach
                            </select>
                            @error('assigned_user')<span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>@enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="activity_note">About</label>
                            <textarea class="form-control @error('activity_note') is-invalid @enderror" id="activity_note" name="activity_note" rows="1" maxlength="1000" placeholder="e.g. Call to book hearing test">{{ old('activity_note') }}</textarea>
                            @error('activity_note')<span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>@enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Submit Bar --}}
        <div class="crm-form-submit-bar crm-animate-in delay-4">
            <a href="{{ url('admin/leads') }}" class="btn btn-secondary">
                <i class="fas fa-times mr-1"></i>Cancel
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save mr-1"></i>Save Lead
            </button>
        </div>
    </form>

</div>
@endsection

@push('scripts')
  <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
  <script>
    $(document).ready(function() {
      $('.select').select2({
        width: '100%'
      });

      // Hearing ear toggle
      toggleHearingEar = function(ear) {
        var checked = document.getElementById('hearing_' + ear + '_enabled').checked;
        var degreeSelect = document.getElementById('hearing_' + ear + '_degree');
        var typeSelect = document.getElementById('hearing_' + ear + '_type');
        degreeSelect.disabled = !checked;
        typeSelect.disabled = !checked;
        if (!checked) {
          $(degreeSelect).val('').trigger('change');
          $(typeSelect).val('').trigger('change');
        }
      };

      // Show only what the chosen stage needs (rules come from Pipeline Settings)
      function syncGate() {
        var opt = $('#stage_id option:selected');
        var req = (opt.data('required') || '').toString().split(',').filter(Boolean);
        var shown = 0;
        $('#stage-gate [data-gate]').each(function () {
          var on = req.indexOf($(this).data('gate')) !== -1;
          $(this).toggle(on);
          if (on) { shown++; }
        });
        $('#stage-gate-title').text('"' + opt.text().trim() + '" stage needs');
        $('#stage-gate').css('--gate-color', opt.data('color') || '#6366f1').toggle(shown > 0);
      }
      $('#stage_id').on('change', syncGate);
      syncGate();
      $('#gate_walk_in').on('change', function () { $('#gate_appointment_at').prop('disabled', this.checked); }).trigger('change');

      // Manual Focus Spy for Step Progress
      var steps = $('.crm-form-step');
      var connectors = $('.step-connector');
      var visibleSections = $('.crm-form-section:visible');

      if (visibleSections.length > 0 && steps.length > 0) {
        
        function updateSteps(currentSectionIndex) {
          if (currentSectionIndex > 5) currentSectionIndex = 5;

          steps.each(function() {
            var stepNum = parseInt($(this).attr('data-step'));
            $(this).removeClass('active completed');
            
            if (stepNum < currentSectionIndex) {
              $(this).addClass('completed');
            } else if (stepNum === currentSectionIndex) {
              $(this).addClass('active');
            }
          });

          connectors.each(function(index) {
            if (index < currentSectionIndex - 1) {
              $(this).addClass('active');
            } else {
              $(this).removeClass('active');
            }
          });
        }

        // Initialize at step 1
        updateSteps(1);

        // Bind focus events on all inputs, selects, and textareas within each section
        visibleSections.each(function(index) {
          var sectionIndex = index + 1;
          $(this).find('input, select, textarea').on('focus', function() {
            updateSteps(sectionIndex);
          });
        });

        // Specific handling for Select2 dropdowns (they don't always trigger standard focus)
        visibleSections.on('select2:open', 'select', function(e) {
          // Find which section this select belongs to
          var sectionIndex = visibleSections.index($(this).closest('.crm-form-section')) + 1;
          updateSteps(sectionIndex);
        });
      }
    });
  </script>
@endpush

