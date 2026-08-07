@extends('admin.layouts.master')
@push('css')
  <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
  <style>
    .info-label {
      font-weight: 600;
      color: #495057;
    }
    .info-value {
      color: #212529;
    }
    .section-title {
      border-bottom: 2px solid #007bff;
      padding-bottom: 8px;
      margin-bottom: 15px;
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
                <h5>Lead Details - {{ $lead->lead_uid }} </h5>
              </div>
              <div class="col-md-6 text-right">
                @if ($lead->lead_status != 'won')
                  @can('permission', 'lead_list.edit')
                      <a href="{{ route('leads.edit', $lead->id) }}"
                          class="btn btn-success btn-sm"> 
                          <i class="fas fa-edit"></i> Edit
                      </a>
                  @endcan
                @endif  
              </div>
            </div>
          </div>

          <div class="m-3">
              <label class="font-weight-bold">Profile Completion</label>
              
              <div class="progress" style="height: 22px;border-radius: 20px;">
                  <div class="progress-bar" role="progressbar"
                      style="
                        width: {{ $lead->profileCompletion() }}%;
                        border-radius: 20px;
                        background: linear-gradient(45deg, #007bff, #00d4ff);
                      ">
                      <strong>{{ $lead->profileCompletion() }}%</strong>
                  </div>
              </div>
          </div>


          @include('admin.alert_message')

          <div class="card-body">
            <!-- Lead Status Badge -->
            <div class="row mb-3">
              <div class="col-md-12">
                <div class="alert alert-info">
                  <div class="row">
                    <div class="col-md-6">
                      <strong>Status:</strong> 
                      <span class="badge badge-{{ $lead->status_color }} badge-lg">
                        {{ $lead->status_label }}
                      </span>
                    </div>
                    <div class="col-md-6 text-right">
                      <strong>Created:</strong> {{ $lead->created_at->format('d M Y, h:i A') }}<br>
                      <strong>Last Updated:</strong> {{ $lead->updated_at->diffForHumans() }}
                    </div>
                  </div>
                </div>
              </div>
            </div>

             <!-- Lead Journey Section -->
            <h6 class="section-title text-primary"><i class="fas fa-route mr-2"></i>Lead Journey</h6>

            <!-- Status Pipeline -->
            <div style="overflow-x: auto;">
              <div style="display: flex; align-items: center; justify-content: flex-start; padding: 25px 15px; min-width: max-content; background: #f8f9fa; border-radius: 10px;">
                @foreach($leadJourney as $index => $step)
                  @php
                    $borderColor  = $step['is_current'] ? '#2563eb' : ($step['is_reached'] ? '#93c5fd' : '#e5e7eb');
                    $bgColor      = $step['is_current'] ? '#2563eb' : ($step['is_reached'] ? '#eff6ff' : '#ffffff');
                    $iconColor    = $step['is_current'] ? '#ffffff' : ($step['is_reached'] ? '#2563eb' : '#d1d5db');
                    $textColor    = $step['is_current'] ? '#ffffff' : ($step['is_reached'] ? '#1e40af' : '#9ca3af');
                    $fontWeight   = $step['is_current'] ? '700' : ($step['is_reached'] ? '600' : '400');
                    $boxShadow    = $step['is_current'] ? '0 4px 15px rgba(37,99,235,0.3)' : '0 1px 4px rgba(0,0,0,0.05)';
                    $opacity      = (!$step['is_reached'] && !$step['is_current']) ? '0.5' : '1';
                  @endphp
                  <div style="flex-shrink: 0; display: flex; align-items: center; opacity: {{ $opacity }};">
                    <div style="
                      border: 3px solid {{ $borderColor }};
                      border-radius: 12px;
                      padding: 15px 20px;
                      text-align: center;
                      background: {{ $bgColor }};
                      min-width: 120px;
                      box-shadow: {{ $boxShadow }};
                      position: relative;
                    ">
                      <div style="font-size: 20px; color: {{ $iconColor }}; margin-bottom: 6px;">
                        <i class="{{ $step['icon'] }}"></i>
                      </div>
                      <div style="
                        font-weight: {{ $fontWeight }};
                        font-size: 12px;
                        color: {{ $textColor }};
                        line-height: 1.3;
                      ">
                        {{ $step['label'] }}
                      </div>
                      @if($step['is_reached'] && !$step['is_current'])
                      <div style="margin-top: 6px;">
                        <i class="fas fa-check-circle" style="color: #10b981; font-size: 14px;"></i>
                      </div>
                      @endif
                    </div>
                    @if($index < $leadJourney->count() - 1)
                    <div style="flex-shrink: 0; margin: 0 2px;">
                      <i class="fas fa-chevron-right" style="color: {{ $step['is_reached'] ? '#93c5fd' : '#d1d5db' }}; font-size: 12px;"></i>
                    </div>
                    @endif
                  </div>
                @endforeach
              </div>
            </div>

            <!-- Journey History Table -->
            @if($lead->statusHistories->count() > 0)
              <div class="mt-4">
                <h6 class="text-muted"><i class="fas fa-list mr-2"></i>Journey History</h6>
                <div class="table-responsive">
                  <table class="table table-sm table-bordered">
                    <thead class="thead-light">
                      <tr>
                        <th width="5%">#</th>
                        <th width="20%">Date & Time</th>
                        <th width="15%">From</th>
                        <th width="15%">To</th>
                        <th width="15%">Changed By</th>
                        <th width="30%">Notes</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($lead->statusHistories as $index => $history)
                      <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                          <strong>{{ $history->created_at->format('d M Y, h:i A') }}</strong>
                        </td>
                        <td>
                          @if($history->from_status)
                            <span class="badge badge-secondary">{{ $history->from_status_label }}</span>
                          @else
                            <span class="text-muted">-</span>
                          @endif
                        </td>
                        <td>
                          <span class="badge badge-{{ $history->to_status === 'won' ? 'success' : ($history->to_status === 'lost' ? 'danger' : 'primary') }}">
                            {{ $history->to_status_label }}
                          </span>
                        </td>
                        <td>
                          @if($history->creator)
                            <small>{{ $history->creator->name }}</small>
                          @else
                            <span class="text-muted">-</span>
                          @endif
                        </td>
                        <td>
                          <small>{{ $history->notes ?? '-' }}</small>
                        </td>
                      </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
            @else
              <div class="alert alert-warning mt-3">
                <i class="fas fa-info-circle mr-2"></i> No journey history available for this lead.
              </div>
            @endif

            <!-- Personal Information -->
            <h6 class="section-title text-primary"><i class="fas fa-user mr-2"></i>Personal Information</h6>
            <div class="row mb-4">
              <div class="col-md-6">
                <table class="table table-sm table-borderless">
                  <tr>
                    <td width="40%" class="info-label">Full Name:</td>
                    <td class="info-value">{{ $lead->full_name }}</td>
                  </tr>
                  <tr>
                    <td class="info-label">Email:</td>
                    <td class="info-value">{{ $lead->email ?? 'N/A' }}</td>
                  </tr>
                  <tr>
                    <td class="info-label">Phone Number:</td>
                    <td class="info-value">{{ $lead->phone_number }}</td>
                  </tr>
                  <tr>
                    <td class="info-label">Age:</td>
                    <td class="info-value">{{ $lead->age ?? 'N/A' }}</td>
                  </tr>
                  <tr>
                    <td class="info-label">Gender:</td>
                    <td class="info-value">{{ $lead->gender ? ucfirst($lead->gender) : 'N/A' }}</td>
                  </tr>
                  <tr>
                    <td class="info-label">District:</td>
                    <td class="info-value">{{ $lead->district->name ?? 'N/A' }}</td>
                  </tr>
                  <tr>
                    <td class="info-label">Address:</td>
                    <td class="info-value">{{ $lead->address ?? 'N/A' }}</td>
                  </tr>
                </table>
              </div>
              <div class="col-md-6">
                <table class="table table-sm table-borderless">
                  <tr>
                    <td width="40%" class="info-label">Contact Person:</td>
                    <td class="info-value">{{ $lead->contact_person_name ?? 'N/A' }}</td>
                  </tr>
                  <tr>
                    <td class="info-label">Relation:</td>
                    <td class="info-value">{{ $lead->relation ? ucfirst($lead->relation) : 'N/A' }}</td>
                  </tr>
                  <tr>
                    <td class="info-label">Contact Person Phone:</td>
                    <td class="info-value">{{ $lead->contact_person_phone ?? 'N/A' }}</td>
                  </tr>
                  <tr>
                    <td class="info-label">Referred By:</td>
                    <td class="info-value">{{ $lead->ref_by ?? 'N/A' }}</td>
                  </tr>
                </table>
              </div>
            </div>

            <!-- Lead Details -->
            <h6 class="section-title text-primary"><i class="fas fa-clipboard mr-2"></i>Lead Details</h6>
            <div class="row mb-4">
              <div class="col-md-6">
                <table class="table table-sm table-borderless">
                  <tr>
                    <td width="40%" class="info-label">Lead Status:</td>
                    <td class="info-value">
                      <span class="badge bg-{{ $lead->status_color }}">{{ $lead->status_label }}</span>
                    </td>
                  </tr>
                  <tr>
                    <td class="info-label">Inquiry For:</td>
                    <td class="info-value">
                      {{ $lead->inquiry_for_label }}
                    </td>
                  </tr>
                  <tr>
                    <td class="info-label">Interested Brand/Product:</td>
                    <td class="info-value">{{ $lead->interest_brand_product ?? 'N/A' }}</td>
                  </tr>
                  <tr>
                    <td class="info-label" style="vertical-align: top; padding-top: 12px;">Hearing Loss:</td>
                    <td class="info-value">
                      @if($hearingLoss)
                        <div style="display: flex; gap: 14px; flex-wrap: wrap;">
                          @foreach($hearingLoss as $item)
                            <div style="
                              flex: 1; min-width: 180px; max-width: 240px;
                              border: 1px solid #dee2e6;
                              border-radius: 10px; overflow: hidden;
                              background: #f8f9fa;
                            ">
                              <div style="
                                background: #6c757d; color: #fff;
                                padding: 7px 12px; font-weight: 700; font-size: 13px;
                                display: flex; align-items: center; gap: 6px;
                              ">
                                <i class="fas fa-ear-listen"></i>
                                {{ $item['ear'] }} Ear
                              </div>
                              <div style="padding: 10px 12px;">
                                <div style="font-size: 13px; margin-bottom: 4px;">
                                  <span class="text-muted">Degree:</span>
                                  <strong>{{ $item['degree'] }}</strong>
                                </div>
                                <div style="font-size: 13px;">
                                  <span class="text-muted">Type:</span>
                                  <strong>{{ $item['type'] }}</strong>
                                </div>
                              </div>
                            </div>
                          @endforeach
                        </div>
                      @else
                        <span class="text-muted font-italic">No hearing loss data</span>
                      @endif
                    </td>
                  </tr>
                  <tr>
                    <td class="info-label">Approx Budget:</td>
                    <td class="info-value">{{ $lead->approx_budget ? 'BDT ' . number_format($lead->approx_budget, 2) : 'N/A' }}</td>
                  </tr>
                </table>
              </div>
              <div class="col-md-6">
                <table class="table table-sm table-borderless">
                  <tr>
                    <td width="40%" class="info-label">Source of Entry:</td>
                    <td class="info-value">
                      {{ $lead->source_of_entry_label }}
                    </td>
                  </tr>
                  <tr>
                    <td class="info-label">Lead Type:</td>
                    <td class="info-value">
                      {{ $lead->lead_type_label ?? 'N/A' }}
                    </td>
                  </tr>
                </table>
              </div>
            </div>

            <!-- Notes -->
            @if($lead->notes)
              <h6 class="section-title text-primary"><i class="fas fa-sticky-note mr-2"></i>Notes</h6>
              <div class="alert alert-light">
                {{ $lead->notes }}
              </div>
            @endif

            <!-- Loss Details (if applicable) -->
            @if($lead->lead_status === 'lost' && ($lead->loss_reason || $lead->loss_to_whom))
              <div class="alert alert-danger">
                <h6 class="text-danger"><i class="fas fa-exclamation-triangle mr-2"></i>Loss Details</h6>
                <div class="row">
                  <div class="col-md-6">
                    <strong>Loss Reason:</strong><br>
                    {{ $lead->loss_reason ?? 'N/A' }}
                  </div>
                  <div class="col-md-6">
                    <strong>Lost to Whom:</strong><br>
                    {{ $lead->loss_to_whom ?? 'N/A' }}
                  </div>
                </div>
              </div>
            @endif

            <!-- Follow-ups Section -->
            <h6 class="section-title text-primary mt-4"><i class="fas fa-calendar-check mr-2"></i>Follow-ups</h6>
            
            <!-- Add New Follow-up Button -->
            <div class="mb-3">
              <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#addFollowUpModal">
                <i class="fas fa-plus mr-1"></i> Add New Follow-up
              </button>
            </div>

            @if($lead->followUps->count() > 0)
              <div class="table-responsive">
                <table class="table table-bordered table-hover">
                  <thead class="thead-light">
                    <tr>
                      <th width="5%">#</th>
                      <th width="15%">Date & Time</th>
                      <th width="15%">Mode</th>
                      <th width="30%">Remarks</th>
                      <th width="15%">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($lead->followUps as $index => $followUp)
                      <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                          <strong>{{ \Carbon\Carbon::parse($followUp->follow_up_date)->format('d M Y, h:i A') }}</strong><br>
                        </td>
                        <td>
                         {{ $followUp->follow_up_mode_label }}
                        </td>
                        <td>
                          <small>{{ $followUp->follow_up_notes ?? 'No remarks' }}</small>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#editFollowUpModal{{ $followUp->id }}">
                              <i class="fas fa-edit"></i>
                            </button>
                        </td>
                      </tr>

                      <!-- Edit Follow-up Modal -->
                      <div class="modal fade" id="editFollowUpModal{{ $followUp->id }}" tabindex="-1">
                        <div class="modal-dialog">
                          <div class="modal-content">
                            <div class="modal-header">
                              <h5 class="modal-title">Edit Follow-up</h5>
                              <button type="button" class="close" data-dismiss="modal">&times;</button>
                            </div>
                            <form action="{{ url('admin/leads/follow-ups/' . $followUp->id) }}" method="POST">
                              @csrf
                              @method('PUT')
                              <div class="modal-body">
                                <div class="form-group">
                                  <label>Follow-up Date <span class="text-danger">*</span></label>
                                  <input type="datetime-local" name="follow_up_date" class="form-control" value="{{ \Carbon\Carbon::parse($followUp->follow_up_date)->format('Y-m-d H:i:s') }}" required>
                                </div>
                                <div class="form-group">
                                  <label>Follow-up Mode <span class="text-danger">*</span></label>
                                  <select name="follow_up_mode[]" class="form-control select" multiple required>
                                    @foreach($followUpModes as $mode)
                                      <option value="{{ $mode->value }}" {{ is_array($followUp->follow_up_mode) && in_array($mode->value, $followUp->follow_up_mode) ? 'selected' : '' }}>
                                        {{ $mode->label() }}
                                      </option>
                                    @endforeach
                                  </select>
                                </div>
                                <label for="assigned_user" class="col-sm-4 col-form-label">Assigned User:</label>
                                <div class="form-group">
                                    <select class="form-control select @error('assigned_user') is-invalid @enderror" name="assigned_user" id="assigned_user">
                                        <option value="">Select</option>
                                        @foreach($users as $user)
                                            <option value="{{ $user->id }}" {{ $followUp->assigned_user == $user->id ? 'selected' : '' }}>
                                            {{ $user->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('assigned_user')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                    @enderror
                                </div>
                                <div class="form-group">
                                  <label>Remarks</label>
                                  <textarea name="follow_up_notes" class="form-control" rows="3">{{ $followUp->follow_up_notes }}</textarea>
                                </div>
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-primary">Update Follow-up</button>
                              </div>
                            </form>
                          </div>
                        </div>
                      </div>
                    @endforeach
                  </tbody>
                </table>
              </div>
            @else
              <div class="alert alert-warning">
                <i class="fas fa-info-circle mr-2"></i> No follow-ups scheduled yet.
              </div>
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Add Follow-up Modal -->
<div class="modal fade" id="addFollowUpModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add New Follow-up</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <form action="{{ url('admin/leads/' . $lead->id . '/follow-ups') }}" method="POST">
        @csrf
        <div class="modal-body">
          <div class="form-group">
            <label>Follow-up Date <span class="text-danger">*</span></label>
            <input type="datetime-local" name="follow_up_date" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Follow-up Mode <span class="text-danger">*</span></label>
            <select name="follow_up_mode[]" class="form-control select" multiple required>
              @foreach($followUpModes as $mode)
                <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
              @endforeach
            </select>
          </div>

          <label for="assigned_user" class="col-sm-4 col-form-label">Assigned User:</label>
          <div class="form-group">
            <select class="form-control select @error('assigned_user') is-invalid @enderror" name="assigned_user" id="assigned_user">
                <option value="">Select</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" {{ old('assigned_user') == $user->id ? 'selected' : '' }}>
                    {{ $user->name }}
                    </option>
                @endforeach
            </select>
            @error('assigned_user')
            <span class="invalid-feedback" role="alert">
                <strong>{{ $message }}</strong>
            </span>
            @enderror
          </div>
          
          <div class="form-group">
            <label>Remarks</label>
            <textarea name="follow_up_notes" class="form-control" rows="3"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-success">Add Follow-up</button>
        </div>
      </form>
    </div>
  </div>
</div>

@endsection

@push('scripts')
  <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>

<script>
    $('.select').select2({
        width: '100%'
      });
  function completeFollowUp(followUpId) {
    if (confirm('Mark this follow-up as completed?')) {
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = `/admin/follow-ups/${followUpId}/complete`;
      
      const csrfToken = document.createElement('input');
      csrfToken.type = 'hidden';
      csrfToken.name = '_token';
      csrfToken.value = '{{ csrf_token() }}';
      
      const methodInput = document.createElement('input');
      methodInput.type = 'hidden';
      methodInput.name = '_method';
      methodInput.value = 'PATCH';
      
      form.appendChild(csrfToken);
      form.appendChild(methodInput);
      document.body.appendChild(form);
      form.submit();
    }
  }
</script>
@endpush