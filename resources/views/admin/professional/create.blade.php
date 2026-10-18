@extends('admin.layouts.master')
@section('page_title', 'Add Professional')
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

    /* Day indicator dot */
    .day-indicator {
        transition: background 0.2s ease;
    }
    .day-indicator.active {
        background: var(--success-500, #28a745) !important;
    }

    /* Card headers */
    .card .card-header {
        transition: background 0.2s ease;
    }

    /* Summary bar */
    .schedule-summary {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 0.75rem 1rem;
    }
</style>
@endpush
@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-user-md" style="color: var(--accent-500); margin-right: 8px;"></i>Add Professional</h1>
            <p>Register a new professional with schedule</p>
        </div>
        <div class="header-actions">
            @can('permission', 'professional_list.index')
            <a href="{{ url('admin/professionals') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>Professional List</a>
            @endcan
        </div>
    </div>
    @include('admin.alert_message')
    <form action="{{ url('admin/professionals') }}" method="post" id="professional-form">
        @csrf
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
                                    <input type="text" class="form-control" name="name" placeholder="Enter full name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Email <span style="color: var(--danger-500);">*</span></label>
                                    <input type="email" class="form-control" name="email" placeholder="email@example.com" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Password <span style="color: var(--danger-500);">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="password" placeholder="Min 6 characters" required>
                                        <div class="input-group-append">
                                            <span class="input-group-text" style="cursor:pointer" onclick="togglePassword(this)">
                                                <i class="fas fa-eye text-muted"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Phone Number</label>
                                    <input type="text" class="form-control" name="mobile_no" placeholder="Phone number">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                @include('admin.partials.branch_select', ['selected' => null])
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Designation <span style="color: var(--danger-500);">*</span></label>
                                    <select class="form-control" name="designation" required>
                                        <option value="">Select Designation</option>
                                        @foreach ($designations as $designation)
                                            <option value="{{ $designation->value }}">{{ $designation->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Status <span style="color: var(--danger-500);">*</span></label>
                                    <div class="mt-1">
                                        <label class="radio-inline my-2"><input type="radio" name="status" value="1" checked> Active</label>
                                        <label class="radio-inline my-2 ml-3"><input type="radio" name="status" value="0"> Inactive</label>
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
                                    <option value="{{ $service->id }}">{{ $service->name }} ({{ $service->category->label() }})</option>
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
            </div>
            <div class="crm-form-section-body">
                <div class="alert alert-light border d-flex align-items-center mb-0" style="border-radius: 8px; background: #f8fafc;">
                    <i class="fas fa-info-circle mr-3 text-primary" style="font-size: 24px;"></i>
                    <div style="font-size: 13.5px; line-height: 1.5;">
                        <strong>Dynamic Rostering System Active:</strong>
                        This specialist's weekly working hours, branch rotations, and clinic breaks are configured in 
                        <a href="{{ url('admin/roster') }}" target="_blank" class="font-weight-600 text-primary"><u>Staff Rosters & Leave</u></a>. 
                        Once you save this professional, their shifts can be added directly to any branch schedule.
                    </div>
                </div>
            </div>
        </div>

        <div class="crm-form-submit-bar">
            <a href="{{ url('admin/professionals') }}" class="btn btn-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Save Professional</button>
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

            // Update count on change
            $(document).on('change', '.day-slot', function() {
                updateDayCount($(this).data('day'));
            });
        });

        function updateDayCount(dayId) {
            const count = $(`.day-slot[data-day="${dayId}"]:checked`).length;
            const total = $(`.day-slot[data-day="${dayId}"]`).length;

            $(`#day-count-${dayId}`).text(count);

            // Update visual states
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
                if (!dayIds.includes(dayId)) {
                    dayIds.push(dayId);
                    updateDayCount(dayId);
                }
            });
        }

        function clearAllSlots() {
            $('.day-slot').prop('checked', false);
            const dayIds = [];
            $('.day-slot').each(function() {
                const dayId = $(this).data('day');
                if (!dayIds.includes(dayId)) {
                    dayIds.push(dayId);
                    updateDayCount(dayId);
                }
            });
        }

        function updateSummary() {
            const activeDays = new Set();
            let totalSlots = 0;
            $('.day-slot:checked').each(function() {
                activeDays.add($(this).data('day'));
                totalSlots++;
            });

            if (activeDays.size > 0) {
                if (!$('#schedule-summary').length) {
                    $('#schedule-container').html(`
                        <div class="schedule-summary d-flex align-items-center justify-content-between mb-3" id="schedule-summary">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-info-circle text-primary mr-2"></i>
                                <span class="text-muted" style="font-size:0.85rem;">
                                    <strong class="text-dark" id="summary-days">${activeDays.size}</strong> working day(s) configured with
                                    <strong class="text-dark" id="summary-slots">${totalSlots}</strong> time slot(s)
                                </span>
                            </div>
                        </div>
                    `);
                } else {
                    $('#summary-days').text(activeDays.size);
                    $('#summary-slots').text(totalSlots);
                }
            } else {
                $('#schedule-summary').remove();
            }
        }

        function togglePassword(el) {
            const input = $(el).closest('.input-group').find('input');
            const icon = $(el).find('i');
            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                icon.removeClass('fa-eye').addClass('fa-eye-slash');
            } else {
                input.attr('type', 'password');
                icon.removeClass('fa-eye-slash').addClass('fa-eye');
            }
        }
    </script>
@endpush
