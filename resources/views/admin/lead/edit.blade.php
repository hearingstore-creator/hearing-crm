@extends('admin.layouts.master')
@section('page_title', 'Edit Lead')
@section('breadcrumb_parent', 'Leads')
@section('breadcrumb_parent_url', url('admin/leads'))

@push('css')
  <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-pen-to-square" style="color: var(--accent-500); margin-right: 8px;"></i>Edit Lead</h1>
            <p>Update details for <strong>{{ $lead->full_name }}</strong> · {{ $lead->lead_uid ?? 'No UID' }}</p>
        </div>
        <div class="header-actions">
            <a href="{{ route('leads.show', $lead->id) }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-eye mr-1"></i>View Lead
            </a>
            <a href="{{ url('admin/leads') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i>Back to Leads
            </a>
        </div>
    </div>

    @include('admin.alert_message')

    <form action="{{ url('admin/leads/' . $lead->id) }}" method="post" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        {{-- SECTION 1: Basic Information --}}
        <div class="crm-form-section crm-animate-in delay-1">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--accent-500);"><i class="fas fa-user"></i></div>
                <h6>Basic Information</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="full_name">Full Name <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" class="form-control @error('full_name') is-invalid @enderror" id="full_name" name="full_name" placeholder="Lead full name" value="{{ old('full_name', $lead->full_name) }}" required>
                            @error('full_name')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="phone_number">Phone Number <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" class="form-control @error('phone_number') is-invalid @enderror" id="phone_number" name="phone_number" placeholder="Phone number" value="{{ old('phone_number', $lead->phone_number) }}" required>
                            @error('phone_number')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" placeholder="Email address" value="{{ old('email', $lead->email) }}">
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
                                    <input type="number" class="form-control @error('age') is-invalid @enderror" id="age" name="age" placeholder="Age" value="{{ old('age', $lead->age) }}" min="1" max="150">
                                    @error('age')
                                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-7 pl-1">
                                <div class="form-group">
                                    <label for="date_of_birth">Date of Birth</label>
                                    <input type="date" class="form-control @error('date_of_birth') is-invalid @enderror" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth', $lead->date_of_birth?->format('Y-m-d')) }}" max="{{ date('Y-m-d') }}" onchange="(function(v){if(!v)return;var d=new Date(v),n=new Date(),a=n.getFullYear()-d.getFullYear();if(n.getMonth()<d.getMonth()||(n.getMonth()==d.getMonth()&&n.getDate()<d.getDate()))a--;if(a>0)document.getElementById('age').value=a;})(this.value)">
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
                                    <option value="{{ $gender->value }}" {{ old('gender', $lead->gender) == $gender->value ? 'selected' : '' }}>
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
                                    <option value="{{ $val->id }}" {{ old('district_id', $lead->district_id) == $val->id ? 'selected' : '' }}>
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
                                    <option value="{{ $type->value }}" {{ old('lead_type', $lead->lead_type) == $type->value ? 'selected' : '' }}>
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
                            <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="2" placeholder="Write address...">{{ old('address', $lead->address) }}</textarea>
                            @error('address')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="ref_by">Referred By</label>
                            <input type="text" class="form-control @error('ref_by') is-invalid @enderror" id="ref_by" name="ref_by" placeholder="Referrer name" value="{{ old('ref_by', $lead->ref_by) }}">
                            @error('ref_by')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- SECTION 2: Contact Person --}}
        <div class="crm-form-section crm-animate-in delay-1">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--gray-500);"><i class="fas fa-users"></i></div>
                <h6>Contact Person</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="contact_person_name">Contact Name</label>
                            <input type="text" class="form-control @error('contact_person_name') is-invalid @enderror" id="contact_person_name" name="contact_person_name" placeholder="Name of contact person" value="{{ old('contact_person_name', $lead->contact_person_name) }}">
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
                                    <option value="{{ $relation->value }}" {{ old('relation', $lead->relation) == $relation->value ? 'selected' : '' }}>
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
                            <input type="text" class="form-control @error('contact_person_phone') is-invalid @enderror" id="contact_person_phone" name="contact_person_phone" placeholder="Phone of contact person" value="{{ old('contact_person_phone', $lead->contact_person_phone) }}">
                            @error('contact_person_phone')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- SECTION 3: Hearing & Requirement --}}
        @php
            $hl = $lead->hearing_loss ?? [];
            if (!is_array($hl)) $hl = json_decode($hl, true) ?? [];
            $rightEnabled = !empty($hl['right']['enabled']);
            $leftEnabled  = !empty($hl['left']['enabled']);
        @endphp
        <div class="crm-form-section crm-animate-in delay-2">
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
                                @php $selectedInquiries = old('inquiry_for', $lead->inquiry_for ?? []); @endphp
                                @foreach($inquiries as $inquiry)
                                    <option value="{{ $inquiry->value }}" {{ in_array($inquiry->value, (array)$selectedInquiries) ? 'selected' : '' }}>
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
                            <input type="text" class="form-control @error('interest_brand_product') is-invalid @enderror" id="interest_brand_product" name="interest_brand_product" placeholder="Brand or product name" value="{{ old('interest_brand_product', $lead->interest_brand_product) }}">
                            @error('interest_brand_product')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="approx_budget">Approx Budget (৳)</label>
                            <input type="number" step="0.01" class="form-control @error('approx_budget') is-invalid @enderror" id="approx_budget" name="approx_budget" placeholder="e.g. 5000" value="{{ old('approx_budget', $lead->approx_budget) }}">
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
                                    <input type="checkbox" class="custom-control-input" id="hearing_right_enabled" name="hearing_loss[right][enabled]" value="1" onclick="toggleHearingEar('right')" {{ $rightEnabled ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="hearing_right_enabled" style="font-weight: 600 !important;">
                                        <i class="fas fa-ear-listen mr-1" style="color: #7C3AED;"></i> Right Ear
                                    </label>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="hearing_right_degree" style="font-size: 11px !important;">Degree</label>
                                            <select class="form-control select" name="hearing_loss[right][degree]" id="hearing_right_degree" {{ !$rightEnabled ? 'disabled' : '' }}>
                                                <option value="">Select</option>
                                                @foreach($hearingDegrees as $degree)
                                                    <option value="{{ $degree->value }}" {{ old('hearing_loss.right.degree', $hl['right']['degree'] ?? '') == $degree->value ? 'selected' : '' }}>
                                                        {{ $degree->label() }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="hearing_right_type" style="font-size: 11px !important;">Type</label>
                                            <select class="form-control select" name="hearing_loss[right][type]" id="hearing_right_type" {{ !$rightEnabled ? 'disabled' : '' }}>
                                                <option value="">Select</option>
                                                @foreach($hearingTypes as $type)
                                                    <option value="{{ $type->value }}" {{ old('hearing_loss.right.type', $hl['right']['type'] ?? '') == $type->value ? 'selected' : '' }}>
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
                                    <input type="checkbox" class="custom-control-input" id="hearing_left_enabled" name="hearing_loss[left][enabled]" value="1" onclick="toggleHearingEar('left')" {{ $leftEnabled ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="hearing_left_enabled" style="font-weight: 600 !important;">
                                        <i class="fas fa-ear-listen mr-1" style="color: #7C3AED;"></i> Left Ear
                                    </label>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="hearing_left_degree" style="font-size: 11px !important;">Degree</label>
                                            <select class="form-control select" name="hearing_loss[left][degree]" id="hearing_left_degree" {{ !$leftEnabled ? 'disabled' : '' }}>
                                                <option value="">Select</option>
                                                @foreach($hearingDegrees as $degree)
                                                    <option value="{{ $degree->value }}" {{ old('hearing_loss.left.degree', $hl['left']['degree'] ?? '') == $degree->value ? 'selected' : '' }}>
                                                        {{ $degree->label() }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="hearing_left_type" style="font-size: 11px !important;">Type</label>
                                            <select class="form-control select" name="hearing_loss[left][type]" id="hearing_left_type" {{ !$leftEnabled ? 'disabled' : '' }}>
                                                <option value="">Select</option>
                                                @foreach($hearingTypes as $type)
                                                    <option value="{{ $type->value }}" {{ old('hearing_loss.left.type', $hl['left']['type'] ?? '') == $type->value ? 'selected' : '' }}>
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
        <div class="crm-form-section crm-animate-in delay-2">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--warning-500);"><i class="fas fa-filter"></i></div>
                <h6>Lead Management</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="source_of_entry">Source of Entry</label>
                            <select class="form-control select @error('source_of_entry') is-invalid @enderror" name="source_of_entry" id="source_of_entry">
                                <option value="">Select</option>
                                @foreach($sourceOfEntries as $source)
                                    <option value="{{ $source->value }}" {{ old('source_of_entry', $lead->source_of_entry) == $source->value ? 'selected' : '' }}>
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
                            <label>Stage</label>
                            @php
                                $stageLabel = !$deal ? 'No deal yet' : match ($deal->status) {
                                    'won'   => 'Won',
                                    'lost'  => 'Lost',
                                    default => $deal->stage->name ?? 'Open',
                                };
                                $stageColor = !$deal ? '#94a3b8' : match ($deal->status) {
                                    'won'   => '#10b981',
                                    'lost'  => '#ef4444',
                                    default => $deal->stage->color ?? '#6366f1',
                                };
                            @endphp
                            <div class="form-control" id="lead-stage" style="display: flex; align-items: center; gap: 8px; background: var(--gray-50);">
                                <span style="width: 10px; height: 10px; border-radius: 50%; background: {{ $stageColor }};"></span>
                                <b style="font-weight: 600;">{{ $stageLabel }}</b>
                                @if ($deal && auth()->user()->hasAccess('deal_board.index'))
                                    <a href="{{ url('admin/deals?deal=' . $deal->id) }}" id="lead-stage-open" style="margin-left: auto; font-size: 12px; font-weight: 600;">Change on Deals Board <i class="fas fa-arrow-right"></i></a>
                                @endif
                            </div>
                            <small class="text-muted">Stage, Won and Lost are managed on the Deals Board.</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        @include('admin.lead.partials.temperature-picker', ['selected' => old('lead_temperature', $lead->lead_temperature ?: 'warm')])
                    </div>
                    @if(! auth()->user()->isVendor())
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="vendor_id">Assign to Branch</label>
                            <select class="form-control select @error('vendor_id') is-invalid @enderror" name="vendor_id" id="vendor_id">
                                <option value="">Select</option>
                                @foreach($vendors as $vendor)
                                    <option value="{{ $vendor->id }}" {{ old('vendor_id', $lead->vendor_id) == $vendor->id ? 'selected' : '' }}>
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
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="notes">Consultation Notes</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3" placeholder="Write consultation notes...">{{ old('notes', $lead->notes) }}</textarea>
                            @error('notes')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Loss Details (lost leads only) --}}
        @if ($lead->lead_status === 'lost')
        <div id="loss_details_section">
            <div class="crm-form-section" style="border-color: var(--danger-500);">
                <div class="crm-form-section-header" style="background: var(--danger-50);">
                    <div class="section-icon" style="background: var(--danger-500);"><i class="fas fa-exclamation-triangle"></i></div>
                    <h6 style="color: var(--danger-600) !important;">Loss Details</h6>
                </div>
                <div class="crm-form-section-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="loss_reason">Loss Reason <span style="color: var(--danger-500);">*</span></label>
                                <div class="mb-2" style="display:flex;flex-wrap:wrap;gap:6px;">
                                    @foreach(\App\Services\DashboardAnalyticsService::LOST_REASONS as $reasonKey => $reasonMeta)
                                        <button type="button" class="btn btn-sm" style="border:1px solid var(--danger-200, #fecdd3);background:var(--danger-50, #fff1f2);color:var(--danger-600, #e11d48);border-radius:999px;padding:2px 10px;font-size:12px;"
                                                onclick="document.getElementById('loss_reason').value='{{ $reasonMeta['label'] }}';">
                                            <i class="{{ $reasonMeta['icon'] }} mr-1"></i>{{ $reasonMeta['label'] }}
                                        </button>
                                    @endforeach
                                </div>
                                <textarea class="form-control @error('loss_reason') is-invalid @enderror" id="loss_reason" name="loss_reason" rows="2" placeholder="Pick a reason above or write your own">{{ old('loss_reason', $lead->loss_reason) }}</textarea>
                                @error('loss_reason')
                                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="loss_to_whom">Lost to Whom</label>
                                <input type="text" class="form-control @error('loss_to_whom') is-invalid @enderror" id="loss_to_whom" name="loss_to_whom" placeholder="Competitor name" value="{{ old('loss_to_whom', $lead->loss_to_whom) }}">
                                @error('loss_to_whom')
                                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Submit Bar --}}
        <div class="crm-form-submit-bar crm-animate-in delay-3">
            <a href="{{ url('admin/leads') }}" class="btn btn-secondary">
                <i class="fas fa-times mr-1"></i>Cancel
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save mr-1"></i>Update Lead
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
    });
  </script>
@endpush

