@extends('admin.layouts.master')

@section('content')
<section class="content pt-3">
    <div class="container-fluid card card-outline card-primary p-3">
        <!-- Page Header -->
        <div class="row mb-3">
            <div class="col-md-8">
                <div class="d-flex align-items-center">
                    <div>
                        <h4 class="mb-0">Add New Professional</h4>
                        <small class="text-muted">Fill in the details to register a new professional</small>
                    </div>
                </div>
            </div>
            @can('permission', 'professional_list.index')
            <div class="col-md-4 text-right">
                <a href="{{ url('admin/professionals') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-list-ul mr-1"></i> Professional List
                </a>
            </div>
            @endcan
        </div>

        @include('admin.alert_message')

        <form action="{{ url('admin/professionals') }}" method="post" id="professional-form">
            @csrf

            <!-- Row 1: Basic Info (left) + Services (right) -->
            <div class="row">

                <!-- Basic Information Card -->
                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-header bg-white border-bottom">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mr-3" style="width:36px;height:36px;font-size:14px;">
                                    <i class="fas fa-user-md"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 font-weight-bold">Basic Information</h6>
                                    <small class="text-muted">Personal & account details</small>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="small font-weight-bold text-muted mb-1">Full Name <span class="text-danger">*</span></label>
                                        <div class="input-group input-group-sm">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text bg-light border-right-0"><i class="fas fa-user text-muted"></i></span>
                                            </div>
                                            <input type="text" class="form-control border-left-0 pl-0" name="name" placeholder="Enter full name" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="small font-weight-bold text-muted mb-1">Email <span class="text-danger">*</span></label>
                                        <div class="input-group input-group-sm">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text bg-light border-right-0"><i class="fas fa-envelope text-muted"></i></span>
                                            </div>
                                            <input type="email" class="form-control border-left-0 pl-0" name="email" placeholder="email@example.com" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="small font-weight-bold text-muted mb-1">Password <span class="text-danger">*</span></label>
                                        <div class="input-group input-group-sm">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text bg-light border-right-0"><i class="fas fa-lock text-muted"></i></span>
                                            </div>
                                            <input type="password" class="form-control border-left-0 pl-0" name="password" placeholder="Min 6 characters" required>
                                            <div class="input-group-append">
                                                <span class="input-group-text bg-light border-left-0" style="cursor:pointer" onclick="togglePassword(this)">
                                                    <i class="fas fa-eye text-muted"></i>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="small font-weight-bold text-muted mb-1">Phone Number</label>
                                        <div class="input-group input-group-sm">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text bg-light border-right-0"><i class="fas fa-phone text-muted"></i></span>
                                            </div>
                                            <input type="text" class="form-control border-left-0 pl-0" name="mobile_no" placeholder="Phone number">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="small font-weight-bold text-muted mb-1">Designation <span class="text-danger">*</span></label>
                                        <select class="form-control form-control-sm" name="designation" required>
                                            <option value="">Select Designation</option>
                                            @foreach ($designations as $designation)
                                                <option value="{{ $designation->value }}">{{ $designation->label() }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="small font-weight-bold text-muted mb-1">Status <span class="text-danger">*</span></label>
                                        <div class="d-flex pt-1">
                                            <div class="custom-control custom-radio custom-control-inline mr-4">
                                                <input type="radio" id="status_active" name="status" value="1" class="custom-control-input" checked>
                                                <label class="custom-control-label" for="status_active">
                                                    <span class="badge badge-success mr-1">Active</span>
                                                </label>
                                            </div>
                                            <div class="custom-control custom-radio custom-control-inline">
                                                <input type="radio" id="status_inactive" name="status" value="0" class="custom-control-input">
                                                <label class="custom-control-label" for="status_inactive">
                                                    <span class="badge badge-secondary mr-1">Inactive</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Services Card -->
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-header bg-white border-bottom">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-info text-white d-flex align-items-center justify-content-center mr-3" style="width:36px;height:36px;font-size:14px;">
                                    <i class="fas fa-briefcase-medical"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 font-weight-bold">Services</h6>
                                    <small class="text-muted">Select offered services</small>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <select class="form-control form-control-sm select2-services" name="services[]" multiple required>
                                    @foreach ($appointmentServices as $service)
                                        <option value="{{ $service->id }}">{{ $service->name }} ({{ $service->category->label() }})</option>
                                    @endforeach
                                </select>
                                <small class="text-muted mt-1 d-block">Hold <kbd>Ctrl</kbd> / <kbd>Cmd</kbd> to select multiple</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Schedule Section -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-white border-bottom">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center mr-3" style="width:36px;height:36px;font-size:14px;">
                                        <i class="fas fa-calendar-week"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 font-weight-bold">Weekly Schedule</h6>
                                        <small class="text-muted">Assign time slots for each working day</small>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center">
                                    <button type="button" class="btn btn-outline-secondary btn-xs mr-2" onclick="selectAllSlots()">
                                        <i class="fas fa-check-double mr-1"></i> Select All Slots
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-xs" onclick="clearAllSlots()">
                                        <i class="fas fa-times mr-1"></i> Clear All
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="schedule-container">
                                <!-- Summary bar appears dynamically -->
                            </div>
                            <div class="row">
                                @foreach ($days as $day)
                                <div class="col-lg-6 col-xl-4 mb-3">
                                    <div class="card day-card mb-0" id="day-card-{{ $day->id }}">
                                        <div class="card-header py-2 d-flex align-items-center justify-content-between" id="day-header-{{ $day->id }}">
                                            <div class="d-flex align-items-center">
                                                <div class="day-indicator mr-2" id="day-indicator-{{ $day->id }}" style="width:8px;height:8px;border-radius:50%;background:#d1d5db;"></div>
                                                <strong class="mb-0" style="font-size:0.9rem;">{{ $day->label() }}</strong>
                                                <span class="badge badge-light ml-2 day-count" id="day-count-{{ $day->id }}" style="font-size:0.7rem;">0</span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <small>
                                                    <a href="javascript:void(0)" onclick="toggleDaySlots({{ $day->id }})" class="text-primary font-weight-bold" style="font-size:0.75rem;">Toggle</a>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="card-body py-2">
                                            <div class="d-flex flex-wrap">
                                                @foreach ($timeSlots as $slot)
                                                <label class="slot-chip" id="chip-{{ $day->id }}-{{ $slot->id }}">
                                                    <input type="checkbox" name="time_slots[{{ $day->id }}][]" value="{{ $slot->id }}" class="day-slot" data-day="{{ $day->id }}" onchange="updateDayCount({{ $day->id }})">
                                                    <span class="slot-chip-label">{{ $slot->start_time->format('g:i A') }} - {{ $slot->end_time->format('g:i A') }}</span>
                                                </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Section -->
            <div class="row mt-3 mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-end">
                        <a href="{{ url('admin/professionals') }}" class="btn btn-light mr-2">
                            <i class="fas fa-times mr-1"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save mr-1"></i> Save Professional
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
    <style>
        /* Day card */
        .day-card {
            border: 1px solid #e3e6ea;
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.2s ease;
        }
        .day-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .day-card.has-slots {
            border-color: #80bdff;
        }
        .day-card.has-slots .card-header {
            background: #eef6ff;
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
            border-color: #80bdff;
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
            background: #007bff;
            color: #fff;
            border-radius: 5px;
        }

        /* Day indicator dot */
        .day-indicator {
            transition: background 0.2s ease;
        }
        .day-indicator.active {
            background: #28a745 !important;
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
