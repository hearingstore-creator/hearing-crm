@extends('admin.layouts.master')

@push('css')
  <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
  <style>
    .section-card {
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      padding: 20px;
      margin-bottom: 20px;
      background: #fff;
    }
    .section-header {
      display: flex;
      align-items: center;
      margin-bottom: 15px;
      padding-bottom: 10px;
      border-bottom: 2px solid;
    }
    .section-header .section-icon {
      width: 32px;
      height: 32px;
      border-radius: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 10px;
      color: #fff;
      font-size: 14px;
    }
    .section-header h6 {
      margin: 0;
      font-weight: 600;
      font-size: 14px;
    }
  </style>
@endpush

@section('content')

<!-- Main content -->
<section class="content pt-3">
  <div class="container-fluid">
    <div class="row">
      <div class="col-md-12">

        <div class="card card-outline card-primary">
          <div class="card-header">
            <div class="row">
              <div class="col-md-6">
                <h5>Edit Lead - <span class="badge bg-gray">{{ $lead->lead_uid }}</span></h5>
              </div>
            </div>
          </div>

          @include('admin.alert_message')

          <div class="card-body">
            <form action="{{ url('admin/leads/' . $lead->id) }}" method="post" enctype="multipart/form-data">
              @csrf
              @method('PUT')

              <!-- SECTION 1: Basic Information -->
              <div class="section-card">
                <div class="section-header" style="border-color: #2563EB;">
                  <div class="section-icon" style="background: #2563EB;">
                    <i class="fas fa-user"></i>
                  </div>
                  <h6 style="color: #2563EB;">Basic Information</h6>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group row">
                      <label for="full_name" class="col-sm-4 col-form-label">Full Name <i class="text-danger">*</i>:</label>
                      <div class="col-sm-8">
                        <input type="text" class="form-control @error('full_name') is-invalid @enderror" id="full_name" name="full_name" placeholder="Lead full name" value="{{ old('full_name', $lead->full_name) }}" required>
                        @error('full_name')
                          <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                      </div>
                    </div>
                    <div class="form-group row">
                      <label for="phone_number" class="col-sm-4 col-form-label">Phone Number <i class="text-danger">*</i>:</label>
                      <div class="col-sm-8">
                        <input type="text" class="form-control @error('phone_number') is-invalid @enderror" id="phone_number" name="phone_number" placeholder="Phone number" value="{{ old('phone_number', $lead->phone_number) }}" required>
                        @error('phone_number')
                          <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                      </div>
                    </div>
                    <div class="form-group row">
                      <label for="age" class="col-sm-4 col-form-label">Age <i class="text-danger">*</i>:</label>
                      <div class="col-sm-8">
                        <input type="number" class="form-control @error('age') is-invalid @enderror" id="age" name="age" placeholder="Age" value="{{ old('age', $lead->age) }}" required>
                        @error('age')
                          <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group row">
                      <label for="email" class="col-sm-4 col-form-label">Email:</label>
                      <div class="col-sm-8">
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" placeholder="Email address" value="{{ old('email', $lead->email) }}">
                        @error('email')
                          <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                      </div>
                    </div>
                    <div class="form-group row">
                      <label for="gender" class="col-sm-4 col-form-label">Gender:</label>
                      <div class="col-sm-8">
                        <select class="form-control select @error('gender') is-invalid @enderror" name="gender" id="gender">
                          <option value="">Select</option>
                          @foreach($genders as $gender)
                            <option value="{{ $gender->value }}" {{ $lead->gender == $gender->value ? 'selected' : '' }}>
                              {{ ucfirst($gender->value) }}
                            </option>
                          @endforeach
                        </select>
                        @error('gender')
                          <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- SECTION 2: Contact Person -->
              <div class="section-card">
                <div class="section-header" style="border-color: #6B7280;">
                  <div class="section-icon" style="background: #6B7280;">
                    <i class="fas fa-users"></i>
                  </div>
                  <h6 style="color: #6B7280;">Contact Person</h6>
                </div>
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
                          <option value="{{ $relation->value }}" {{ $lead->relation == $relation->value ? 'selected' : '' }}>
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

              <!-- SECTION 3: Location & Identity -->
              <div class="section-card">
                <div class="section-header" style="border-color: #0891B2;">
                  <div class="section-icon" style="background: #0891B2;">
                    <i class="fas fa-map-marker-alt"></i>
                  </div>
                  <h6 style="color: #0891B2;">Location & Identity</h6>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group row">
                      <label for="district_id" class="col-sm-4 col-form-label">District:</label>
                      <div class="col-sm-8">
                        <select class="form-control select @error('district_id') is-invalid @enderror" name="district_id" id="district_id">
                          <option value="">Select</option>
                          @foreach($districts as $val)
                            <option value="{{ $val->id }}" {{ $lead->district_id == $val->id ? 'selected' : '' }}>
                              {{ $val->name }}
                            </option>
                          @endforeach
                        </select>
                        @error('district_id')
                          <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group row">
                      <label for="lead_type" class="col-sm-4 col-form-label">Lead Type:</label>
                      <div class="col-sm-8">
                        <select class="form-control select @error('lead_type') is-invalid @enderror" name="lead_type" id="lead_type">
                          <option value="">Select</option>
                          @foreach($leadTypes as $type)
                            <option value="{{ $type->value }}" {{ $lead->lead_type == $type->value ? 'selected' : '' }}>
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
                </div>
                <div class="row">
                  <div class="col-md-12">
                    <div class="form-group">
                      <label for="address">Address</label>
                      <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="2" placeholder="Write address...">{{ $lead->address }}</textarea>
                      @error('address')
                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                      @enderror
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6">
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

              <!-- SECTION 4: Hearing & Requirement -->
              <div class="section-card">
                <div class="section-header" style="border-color: #7C3AED;">
                  <div class="section-icon" style="background: #7C3AED;">
                    <i class="fas fa-assistive-listening-systems"></i>
                  </div>
                  <h6 style="color: #7C3AED;">Hearing & Requirement</h6>
                </div>
                <div class="row">
                  <div class="col-md-3">
                    <div class="form-group row">
                      <label for="inquiry_for" class="col-sm-4 col-form-label">Inquiry For <i class="text-danger">*</i>:</label>
                      <div class="col-sm-8">
                        <select class="form-control select @error('inquiry_for') is-invalid @enderror" name="inquiry_for[]" multiple id="inquiry_for" required>
                          <option value="">Select</option>
                          @foreach($inquiries as $inquiry)
                            <option value="{{ $inquiry->value }}"
                              @if(is_array($lead->inquiry_for) && in_array($inquiry->value, $lead->inquiry_for)) selected @endif>
                              {{ $inquiry->label() }}
                            </option>
                          @endforeach
                        </select>
                        @error('inquiry_for')
                          <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                      </div>
                    </div>
                  </div>
                  <div class="col-md-5">
                    <div class="form-group row">
                      <label for="interest_brand_product" class="col-sm-4 col-form-label">Interested Product:</label>
                      <div class="col-sm-8">
                        <input type="text" class="form-control @error('interest_brand_product') is-invalid @enderror" id="interest_brand_product" name="interest_brand_product" placeholder="Brand or product name" value="{{ old('interest_brand_product', $lead->interest_brand_product) }}">
                        @error('interest_brand_product')
                          <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                      </div>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group row">
                      <label for="approx_budget" class="col-sm-4 col-form-label">Approx Budget:</label>
                      <div class="col-sm-8">
                        <input type="number" step="0.01" class="form-control @error('approx_budget') is-invalid @enderror" id="approx_budget" name="approx_budget" placeholder="e.g. 5000" value="{{ old('approx_budget', $lead->approx_budget) }}">
                        @error('approx_budget')
                          <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                        @enderror
                      </div>
                    </div>
                  </div>
                </div>

                @php
                  $hearingLoss = $lead->hearing_loss ?? [];
                  $rightEnabled = isset($hearingLoss['right']['enabled']) && $hearingLoss['right']['enabled'];
                  $leftEnabled = isset($hearingLoss['left']['enabled']) && $hearingLoss['left']['enabled'];
                @endphp

                <!-- Hearing Loss -->
                <div class="row">
                  <div class="col-md-12">
                    <label class="font-weight-bold" style="color: #7C3AED;"><i class="fas fa-ear-listen mr-1"></i> Hearing Loss</label>
                  </div>
                  <div class="col-md-6 mt-2">
                    <div class="form-group">
                      <div class="d-flex align-items-center mb-2">
                        <div class="custom-control custom-checkbox mr-3">
                          <input type="checkbox" class="custom-control-input" id="hearing_right_enabled" name="hearing_loss[right][enabled]" value="1" {{ $rightEnabled ? 'checked' : '' }} onclick="toggleHearingEar('right')">
                          <label class="custom-control-label font-weight-bold" for="hearing_right_enabled">
                            <i class="fas fa-ear-listen mr-1" style="color: #7C3AED;"></i> Right
                          </label>
                        </div>
                      </div>
                      <div class="row">
                        <div class="col-md-6">
                          <label for="hearing_right_degree">Degree</label>
                          <select class="form-control select" name="hearing_loss[right][degree]" id="hearing_right_degree" {{ !$rightEnabled ? 'disabled' : '' }}>
                            <option value="">Select</option>
                            @foreach($hearingDegrees as $degree)
                              <option value="{{ $degree->value }}" {{ (old('hearing_loss.right.degree', $hearingLoss['right']['degree'] ?? '') == $degree->value) ? 'selected' : '' }}>
                                {{ $degree->label() }}
                              </option>
                            @endforeach
                          </select>
                        </div>
                        <div class="col-md-6">
                          <label for="hearing_right_type">Type</label>
                          <select class="form-control select" name="hearing_loss[right][type]" id="hearing_right_type" {{ !$rightEnabled ? 'disabled' : '' }}>
                            <option value="">Select</option>
                            @foreach($hearingTypes as $type)
                              <option value="{{ $type->value }}" {{ (old('hearing_loss.right.type', $hearingLoss['right']['type'] ?? '') == $type->value) ? 'selected' : '' }}>
                                {{ $type->label() }}
                              </option>
                            @endforeach
                          </select>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6 mt-2">
                    <div class="form-group">
                      <div class="d-flex align-items-center mb-2">
                        <div class="custom-control custom-checkbox mr-3">
                          <input type="checkbox" class="custom-control-input" id="hearing_left_enabled" name="hearing_loss[left][enabled]" value="1" {{ $leftEnabled ? 'checked' : '' }} onclick="toggleHearingEar('left')">
                          <label class="custom-control-label font-weight-bold" for="hearing_left_enabled">
                            <i class="fas fa-ear-listen mr-1" style="color: #7C3AED;"></i> Left
                          </label>
                        </div>
                      </div>
                      <div class="row">
                        <div class="col-md-6">
                          <label for="hearing_left_degree">Degree</label>
                          <select class="form-control select" name="hearing_loss[left][degree]" id="hearing_left_degree" {{ !$leftEnabled ? 'disabled' : '' }}>
                            <option value="">Select</option>
                            @foreach($hearingDegrees as $degree)
                              <option value="{{ $degree->value }}" {{ (old('hearing_loss.left.degree', $hearingLoss['left']['degree'] ?? '') == $degree->value) ? 'selected' : '' }}>
                                {{ $degree->label() }}
                              </option>
                            @endforeach
                          </select>
                        </div>
                        <div class="col-md-6">
                          <label for="hearing_left_type">Type</label>
                          <select class="form-control select" name="hearing_loss[left][type]" id="hearing_left_type" {{ !$leftEnabled ? 'disabled' : '' }}>
                            <option value="">Select</option>
                            @foreach($hearingTypes as $type)
                              <option value="{{ $type->value }}" {{ (old('hearing_loss.left.type', $hearingLoss['left']['type'] ?? '') == $type->value) ? 'selected' : '' }}>
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

              <!-- SECTION 5: Lead Management -->
              <div class="section-card">
                <div class="section-header" style="border-color: #F59E0B;">
                  <div class="section-icon" style="background: #F59E0B;">
                    <i class="fas fa-filter"></i>
                  </div>
                  <h6 style="color: #F59E0B;">Lead Management</h6>
                </div>
                <div class="row">
                  <div class="col-md-4">
                    <div class="form-group">
                      <label for="source_of_entry">Source of Entry</label>
                      <select class="form-control select @error('source_of_entry') is-invalid @enderror" name="source_of_entry" id="source_of_entry">
                        <option value="">Select</option>
                        @foreach($sourceOfEntries as $source)
                          <option value="{{ $source->value }}" {{ $lead->source_of_entry == $source->value ? 'selected' : '' }}>
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
                      <label for="lead_status">Lead Status <i class="text-danger">*</i></label>
                      <select class="form-control select @error('lead_status') is-invalid @enderror" name="lead_status" id="lead_status" required>
                        <option value="">Select</option>
                        @foreach($leadStatus as $status)
                          <option value="{{ $status->value }}" {{ $lead->lead_status == $status->value ? 'selected' : '' }}>
                            {{ $status->label() }}
                          </option>
                        @endforeach
                      </select>
                      @error('lead_status')
                        <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                      @enderror
                    </div>
                  </div>
                  @if(! auth()->user()->isVendor())
                  <div class="col-md-4">
                    <div class="form-group">
                      <label for="vendor_id">Assign to Vendor</label>
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
              </div>

              <!-- SECTION 6: Notes -->
              <div class="section-card">
                <div class="section-header" style="border-color: #6B7280;">
                  <div class="section-icon" style="background: #6B7280;">
                    <i class="fas fa-file-alt"></i>
                  </div>
                  <h6 style="color: #6B7280;">Notes</h6>
                </div>
                <div class="form-group">
                  <label for="notes">Consultation Notes</label>
                  <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3" placeholder="Write consultation notes...">{!! $lead->notes !!}</textarea>
                  @error('notes')
                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                  @enderror
                </div>
              </div>

              <!-- Loss Details (if status is lost) -->
              <div id="loss_details_section" style="display: {{ old('lead_status', $lead->lead_status) == 'lost' ? 'block' : 'none' }};">
                <div class="section-card" style="border-color: #ef4444;">
                  <div class="section-header" style="border-color: #ef4444;">
                    <div class="section-icon" style="background: #ef4444;">
                      <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h6 style="color: #ef4444;">Loss Details</h6>
                  </div>
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label for="loss_reason">Loss Reason <i class="text-danger">*</i></label>
                        <textarea class="form-control @error('loss_reason') is-invalid @enderror" id="loss_reason" name="loss_reason" rows="2" placeholder="Reason for loss">{{ old('loss_reason', $lead->loss_reason) }}</textarea>
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

              <!-- Submit -->
              <div class="form-group row mt-4">
                <div class="col-md-6"></div>
                <div class="col-md-6 text-right">
                  <a href="{{ url('admin/leads') }}" class="btn btn-secondary">
                    <i class="fas fa-times mr-1"></i> Cancel
                  </a>
                  <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save mr-1"></i> Update Lead
                  </button>
                </div>
              </div>
            </form>
          </div>

        </div>
      </div>
    </div>
  </div>
</section>

@endsection

@push('scripts')
  <!-- Select2 -->
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

      // Store original status
      var originalStatus = '{{ $lead->lead_status }}';

      // Show/hide status change reason field
      $('#lead_status').on('change', function() {
        var newStatus = $(this).val();

        if (newStatus !== originalStatus && newStatus !== '') {
          $('#status_change_reason_group').slideDown();
        } else {
          $('#status_change_reason_group').slideUp();
        }

        // Show/hide loss details
        if (newStatus === 'lost') {
          $('#loss_details_section').slideDown();
          $('#loss_reason').prop('required', true);
        } else {
          $('#loss_details_section').slideUp();
          $('#loss_reason').prop('required', false);
        }
      });

      // Trigger on page load if already lost
      if ($('#lead_status').val() === 'lost') {
        $('#loss_details_section').show();
        $('#loss_reason').prop('required', true);
      }
    });
  </script>
@endpush
