@extends('admin.layouts.master')
@section('page_title', 'Edit Professional')
@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
<style>
    /* Day card */
    .day-card {
        border: 1px solid var(--card-border, #e3e6ea);
        border-radius: 10px;
        overflow: hidden;
        transition: all 0.2s ease;
    }
    .day-card:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .day-card.has-slots {
        border-color: var(--accent-400, #80bdff);
    }
    .day-card.has-slots .card-header {
        background: var(--accent-50, #eef6ff);
    }

    /* Slot chip */
    .slot-chip {
        display: inline-flex;
        align-items: center;
        cursor: pointer;
        margin: 2px;
        border-radius: 6px;
        border: 1px solid #dee2e6;
        background: #fff;
        transition: all 0.15s ease;
        user-select: none;
    }
    .slot-chip:hover {
        border-color: var(--accent-400, #80bdff);
        background: #f8fbff;
    }
    .slot-chip input[type="checkbox"] {
        display: none;
    }
    .slot-chip-label {
        display: inline-block;
        padding: 4px 10px;
        font-size: 0.78rem;
        color: #495057;
        white-space: nowrap;
    }
    .slot-chip input:checked + .slot-chip-label {
        background: var(--accent-500, #007bff);
        color: #fff;
        border-radius: 5px;
    }

    .day-indicator { transition: background 0.2s ease; }
    .day-indicator.active { background: var(--success-500, #28a745) !important; }
    .card .card-header { transition: background 0.2s ease; }
    .schedule-summary { background: #f8f9fa; border-radius: 8px; padding: 0.75rem 1rem; }
</style>
@endpush
@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-user-md" style="color: var(--accent-500); margin-right: 8px;"></i>Edit Professional</h1>
            <p>Update professional information and schedule</p>
        </div>
        <div class="header-actions">
            @can('permission', 'professional_list.index')
            <a href="{{ url('admin/professionals') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>Professional List</a>
            @endcan
        </div>
    </div>
    @include('admin.alert_message')
    <form action="{{ route('professionals.update', $professional->id) }}" method="post" id="professional-form">
        @csrf
        @method('PUT')
        <div class="row">
            <div class="col-lg-7">
                <div class="crm-form-section crm-animate-in delay-1">
                    <div class="crm-form-section-header">
                        <div class="section-icon" style="background: var(--accent-500);"><i class="fas fa-user-md"></i></div>
                        <h6>Basic Information</h6>
                    </div>
                    <div class="crm-form-section-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Full Name <span style="color: var(--danger-500);">*</span></label>
                                    <input type="text" class="form-control" name="name" value="{{ $professional->name }}" placeholder="Enter full name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Email <span style="color: var(--danger-500);">*</span></label>
                                    <input type="email" class="form-control" name="email" value="{{ $professional->email }}" placeholder="email@example.com" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="password" placeholder="Leave blank to keep current">
                                        <div class="input-group-append">
                                            <span class="input-group-text" style="cursor:pointer" onclick="togglePassword(this)">
                                                <i class="fas fa-eye text-muted"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">Leave blank to keep current password</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Phone Number</label>
                                    <input type="text" class="form-control" name="mobile_no" value="{{ $professional->mobile_no }}" placeholder="Phone number">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                @include('admin.partials.branch_select', ['selected' => $professional->vendor_id])
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Designation <span style="color: var(--danger-500);">*</span></label>
                                    <select class="form-control" name="designation" required>
                                        <option value="">Select Designation</option>
                                        @foreach ($designations as $designation)
                                            <option value="{{ $designation->value }}" {{ $professional->designation?->value === $designation->value ? 'selected' : '' }}>{{ $designation->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Status <span style="color: var(--danger-500);">*</span></label>
                                    <div class="mt-1">
                                        <label class="radio-inline my-2"><input type="radio" name="status" value="1" {{ $professional->status ? 'checked' : '' }}> Active</label>
                                        <label class="radio-inline my-2 ml-3"><input type="radio" name="status" value="0" {{ !$professional->status ? 'checked' : '' }}> Inactive</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="crm-form-section crm-animate-in delay-2">
                    <div class="crm-form-section-header">
                        <div class="section-icon" style="background: var(--info-500, #17a2b8);"><i class="fas fa-briefcase-medical"></i></div>
                        <h6>Services</h6>
                    </div>
                    <div class="crm-form-section-body">
                        <div class="form-group">
                            <select class="form-control select2-services" name="services[]" multiple required>
                                @foreach ($appointmentServices as $service)
                                    <option value="{{ $service->id }}" {{ $professional->appointmentServices->contains($service->id) ? 'selected' : '' }}>{{ $service->name }} ({{ $service->category->label() }})</option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">Hold <kbd>Ctrl</kbd> / <kbd>Cmd</kbd> to select multiple</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schedule Section -->
        <div class="crm-form-section crm-animate-in delay-3">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--success-500);"><i class="fas fa-calendar-alt"></i></div>
                <h6>Working Shifts & Clinic Rosters</h6>
                <div style="margin-left: auto;">
                    <a href="{{ url('admin/roster') }}" target="_blank" class="btn btn-outline-primary btn-xs">
                        <i class="fas fa-user-clock mr-1"></i>Open Staff Rosters & Leave
                    </a>
                </div>
            </div>
            <div class="crm-form-section-body">
                @php
                    $schedules = $professional->staffSchedules()->with('branch')->get();
                @endphp
                @if($schedules->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0" style="font-size: 13px;">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width: 150px;">Day of Week</th>
                                    <th>Clinic Branch</th>
                                    <th>Shift Hours</th>
                                    <th style="width: 100px; text-align: center;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($schedules as $shift)
                                <tr>
                                    <td><strong>{{ \App\Models\BranchSchedule::isoDayName($shift->day_of_week) }}</strong></td>
                                    <td>{{ $shift->branch?->company_name ?? $shift->branch?->name ?? 'Default Branch' }}</td>
                                    <td>
                                        <span class="text-primary font-weight-600">
                                            <i class="far fa-clock mr-1"></i>{{ date('g:i A', strtotime($shift->start_time)) }} - {{ date('g:i A', strtotime($shift->end_time)) }}
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        @if($shift->is_active)
                                            <span class="badge badge-success px-2 py-1">Active Shift</span>
                                        @else
                                            <span class="badge badge-secondary px-2 py-1">Inactive</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-light border mb-0 text-muted d-flex align-items-center" style="border-radius: 8px; background: #f8fafc;">
                        <i class="fas fa-calendar-times mr-3 text-warning" style="font-size: 20px;"></i>
                        <div>
                            No weekly roster shifts assigned yet. 
                            <a href="{{ url('admin/roster') }}" target="_blank" class="font-weight-600 text-primary">Assign weekly shifts in Staff Rosters & Leave &rarr;</a>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="crm-form-submit-bar">
            <a href="{{ url('admin/professionals') }}" class="btn btn-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Update Professional</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            $('.select2-services').select2({
                width: '100%',
                placeholder: 'Select services...',
                allowClear: true
            });

            $(document).on('change', '.day-slot', function() {
                updateDayCount($(this).data('day'));
            });

            // Initialize existing slot states on load
            $('.day-slot:checked').each(function() {
                const dayId = $(this).data('day');
                const $card = $(`#day-card-${dayId}`);
                $card.addClass('has-slots');
            });

            updateSummary();
        });

        function updateDayCount(dayId) {
            const count = $(`.day-slot[data-day="${dayId}"]:checked`).length;
            $(`#day-count-${dayId}`).text(count);
            const $card = $(`#day-card-${dayId}`);
            const $indicator = $(`#day-indicator-${dayId}`);
            if (count > 0) {
                $card.addClass('has-slots');
                $indicator.addClass('active');
                $(`#day-count-${dayId}`).removeClass('badge-light').addClass('badge-primary');
            } else {
                $card.removeClass('has-slots');
                $indicator.removeClass('active');
                $(`#day-count-${dayId}`).removeClass('badge-primary').addClass('badge-light');
            }
            updateSummary();
        }

        function toggleDaySlots(dayId) {
            const $checkboxes = $(`.day-slot[data-day="${dayId}"]`);
            const allChecked = $checkboxes.length === $checkboxes.filter(':checked').length;
            $checkboxes.prop('checked', !allChecked);
            updateDayCount(dayId);
        }

        function selectAllSlots() {
            $('.day-slot').prop('checked', true);
            const dayIds = [];
            $('.day-slot').each(function() {
                const dayId = $(this).data('day');
                if (!dayIds.includes(dayId)) { dayIds.push(dayId); updateDayCount(dayId); }
            });
        }

        function clearAllSlots() {
            $('.day-slot').prop('checked', false);
            const dayIds = [];
            $('.day-slot').each(function() {
                const dayId = $(this).data('day');
                if (!dayIds.includes(dayId)) { dayIds.push(dayId); updateDayCount(dayId); }
            });
        }

        function updateSummary() {
            const activeDays = new Set();
            let totalSlots = 0;
            $('.day-slot:checked').each(function() { activeDays.add($(this).data('day')); totalSlots++; });
            if (activeDays.size > 0) {
                if (!$('#schedule-summary').length) {
                    $('#schedule-container').html(`
                        <div class="schedule-summary d-flex align-items-center mb-3" id="schedule-summary">
                            <i class="fas fa-info-circle text-primary mr-2"></i>
                            <span class="text-muted" style="font-size:0.85rem;">
                                <strong class="text-dark" id="summary-days">${activeDays.size}</strong> working day(s) with
                                <strong class="text-dark" id="summary-slots">${totalSlots}</strong> time slot(s)
                            </span>
                        </div>`);
                } else {
                    $('#summary-days').text(activeDays.size);
                    $('#summary-slots').text(totalSlots);
                }
            } else { $('#schedule-summary').remove(); }
        }

        function togglePassword(el) {
            const input = $(el).closest('.input-group').find('input');
            const icon = $(el).find('i');
            if (input.attr('type') === 'password') { input.attr('type', 'text'); icon.removeClass('fa-eye').addClass('fa-eye-slash'); }
            else { input.attr('type', 'password'); icon.removeClass('fa-eye-slash').addClass('fa-eye'); }
        }
    </script>
@endpush
