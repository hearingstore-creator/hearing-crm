@extends('admin.layouts.master')

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
    <style>
        /* Step Indicator */
        .step-item { display:flex; align-items:center; gap:10px; }
        .step-icon {
            width:42px; height:42px; border-radius:50%; display:flex; align-items:center; justify-content:center;
            font-size:15px; font-weight:700; border:2px solid #dee2e6; color:#6c757d; background:#fff;
            transition:all .25s ease; flex-shrink:0;
        }
        .step-icon.active { border-color:#007bff; color:#fff; background:#007bff; box-shadow:0 0 0 4px rgba(0,123,255,.15); }
        .step-icon.done { border-color:#28a745; color:#fff; background:#28a745; }
        .step-text { font-size:13px; color:#6c757d; font-weight:500; transition:color .25s; }
        .step-text.active { color:#007bff; font-weight:700; }
        .step-text.done { color:#28a745; }
        .step-connector { flex:1; height:2px; background:#dee2e6; margin:0 12px; transition:background .25s; }
        .step-connector.done { background:#28a745; }

        /* Service Card */
        .svc-card {
            border:2px solid #e9ecef; border-radius:12px; padding:16px 18px; cursor:pointer;
            background:#fff; transition:all .2s ease; position:relative; overflow:hidden;
        }
        .svc-card:hover { border-color:#80bdff; transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,.06); }
        .svc-card.selected { border-color:#007bff; background:linear-gradient(135deg, #e7f1ff 0%, #f0f7ff 100%); }
        .svc-card.selected::before {
            content:''; position:absolute; top:0; right:0; width:0; height:0;
            border-top:28px solid #007bff; border-left:28px solid transparent;
        }
        .svc-card.selected::after {
            content:'✓'; position:absolute; top:2px; right:3px; color:#fff; font-size:11px; font-weight:700;
        }

        /* Professional Card */
        .prof-card {
            border:2px solid #e9ecef; border-radius:12px; padding:16px 18px; cursor:pointer;
            background:#fff; transition:all .2s ease; position:relative; overflow:hidden;
        }
        .prof-card:hover { border-color:#80bdff; transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,.06); }
        .prof-card.selected { border-color:#007bff; background:linear-gradient(135deg, #e7f1ff 0%, #f0f7ff 100%); }
        .prof-card.selected::before {
            content:''; position:absolute; top:0; right:0; width:0; height:0;
            border-top:28px solid #007bff; border-left:28px solid transparent;
        }
        .prof-card.selected::after {
            content:'✓'; position:absolute; top:2px; right:3px; color:#fff; font-size:11px; font-weight:700;
        }

        /* Slot Button */
        .slot-btn {
            border:2px solid #e9ecef; border-radius:10px; padding:12px 8px; text-align:center;
            cursor:pointer; background:#fff; transition:all .2s ease; font-weight:500; font-size:0.85rem;
        }
        .slot-btn:hover:not(.taken) { border-color:#80bdff; background:#f8fbff; }
        .slot-btn.selected { background:#007bff; border-color:#007bff; color:#fff; box-shadow:0 3px 8px rgba(0,123,255,.3); }
        .slot-btn.taken {
            background:#f8f9fa; border-color:#e9ecef; color:#adb5bd; cursor:not-allowed;
        }
        .slot-btn.taken span { text-decoration:line-through; }

        /* Patient Section */
        .patient-type-btn {
            border:2px solid #e9ecef; border-radius:10px; padding:14px 20px; cursor:pointer;
            background:#fff; transition:all .2s ease; text-align:center; font-weight:600;
        }
        .patient-type-btn:hover { border-color:#80bdff; }
        .patient-type-btn.active { border-color:#007bff; background:#e7f1ff; color:#007bff; }

        /* Summary */
        .summary-box {
            background:linear-gradient(135deg, #0C1B2E 0%, #162d50 100%);
            border-radius:12px; color:rgba(255,255,255,.85); overflow:hidden;
        }
        .summary-row {
            display:flex; justify-content:space-between; align-items:center;
            padding:10px 0; border-bottom:1px solid rgba(255,255,255,.08);
        }
        .summary-row:last-child { border-bottom:none; }
        .summary-label { color:rgba(255,255,255,.5); font-size:0.85rem; }
        .summary-value { font-weight:700; font-size:0.9rem; }

        /* Form labels */
        .form-label-sm {
            font-size:0.8rem; font-weight:700; color:#495057; text-transform:uppercase;
            letter-spacing:0.5px; margin-bottom:6px; display:block;
        }
    </style>
@endpush

@section('content')
<section class="content pt-3">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8">
                <div class="card card-outline card-primary">
                    <div class="card-header bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h5 class="mb-0">Book Appointment</h5>
                                <small class="text-muted">Follow the steps to schedule a new appointment</small>
                            </div>
                            <div class="d-flex" style="gap:8px">
                                @can('permission', 'appointment_list.index')
                                <a href="{{ url('admin/appointments') }}" class="btn btn-primary btn-sm">
                                    <i class="fas fa-list-ul mr-1"></i> Appointment List
                                </a>
                                @endcan
                                @can('permission', 'calendar_view.index')
                                <a href="{{ url('admin/appointments/calendar') }}" class="btn btn-success btn-sm">
                                    <i class="fas fa-calendar mr-1"></i> Calendar View
                                </a>
                                @endcan
                            </div>
                        </div>
                    </div>

                    @include('admin.alert_message')

                    <div class="card-body">
                        <form action="{{ url('admin/appointments') }}" method="post" id="appointment-form">
                            @csrf

                            <!-- Step Indicator -->
                            <div class="d-flex align-items-center mb-4 pb-4 border-bottom">
                                <div class="step-item">
                                    <div class="step-icon active" id="sc1">1</div>
                                    <span class="step-text active" id="sl1">Service</span>
                                </div>
                                <div class="step-connector" id="ln1"></div>
                                <div class="step-item">
                                    <div class="step-icon" id="sc2">2</div>
                                    <span class="step-text" id="sl2">Professional</span>
                                </div>
                                <div class="step-connector" id="ln2"></div>
                                <div class="step-item">
                                    <div class="step-icon" id="sc3">3</div>
                                    <span class="step-text" id="sl3">Date & Slot</span>
                                </div>
                                <div class="step-connector" id="ln3"></div>
                                <div class="step-item">
                                    <div class="step-icon" id="sc4">4</div>
                                    <span class="step-text" id="sl4">Patient</span>
                                </div>
                            </div>

                            <!-- Step 1: Service -->
                            <div id="step1">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mr-2" style="width:28px;height:28px;font-size:12px;">
                                        <i class="fas fa-briefcase-medical"></i>
                                    </div>
                                    <h6 class="mb-0 font-weight-bold">Choose a Service</h6>
                                </div>
                                <div class="row">
                                    @foreach ($appointmentServices as $service)
                                    <div class="col-md-4 mb-3">
                                        <label class="d-block svc-card" data-service-id="{{ $service->id }}">
                                            <input type="radio" name="service_id" value="{{ $service->id }}" class="d-none service-radio" required>
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle bg-light text-primary d-flex align-items-center justify-content-center mr-3" style="width:40px;height:40px;flex-shrink:0;">
                                                    <i class="fas fa-stethoscope"></i>
                                                </div>
                                                <div>
                                                    <strong class="d-block" style="font-size:0.9rem;">{{ $service->name }}</strong>
                                                    <small class="text-muted">{{ $service->category->label() }}</small>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                    @endforeach
                                </div>
                                <div class="text-right mt-3">
                                    <button type="button" class="btn btn-primary btn-sm" onclick="goStep(2)">Next <i class="fas fa-arrow-right ml-1"></i></button>
                                </div>
                            </div>

                            <!-- Step 2: Professional -->
                            <div id="step2" style="display:none">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mr-2" style="width:28px;height:28px;font-size:12px;">
                                        <i class="fas fa-user-md"></i>
                                    </div>
                                    <h6 class="mb-0 font-weight-bold">Choose a Professional</h6>
                                </div>
                                <div id="professionals-list">
                                    <p class="text-muted">Select a service first.</p>
                                </div>
                                <input type="hidden" name="user_id" id="selected_user_id" required>
                                <div class="text-right mt-3">
                                    <button type="button" class="btn btn-outline-secondary btn-sm mr-2" onclick="goStep(1)"><i class="fas fa-arrow-left mr-1"></i> Back</button>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="goStep(3)">Next <i class="fas fa-arrow-right ml-1"></i></button>
                                </div>
                            </div>

                            <!-- Step 3: Date & Slot -->
                            <div id="step3" style="display:none">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mr-2" style="width:28px;height:28px;font-size:12px;">
                                        <i class="fas fa-calendar-check"></i>
                                    </div>
                                    <h6 class="mb-0 font-weight-bold">Pick Date & Time</h6>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <span class="form-label-sm">Appointment Date <span class="text-danger">*</span></span>
                                        <input type="date" class="form-control form-control-sm" name="appointment_date" id="appointment_date" required>
                                    </div>
                                </div>
                                <div class="mt-3" id="slots-container" style="display:none">
                                    <span class="form-label-sm">Available Time Slots</span>
                                    <div class="row mt-1" id="slots-grid"></div>
                                    <input type="hidden" name="professional_time_slot_id" id="selected_slot_id" required>
                                </div>
                                <div class="text-right mt-3">
                                    <button type="button" class="btn btn-outline-secondary btn-sm mr-2" onclick="goStep(2)"><i class="fas fa-arrow-left mr-1"></i> Back</button>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="goStep(4)">Next <i class="fas fa-arrow-right ml-1"></i></button>
                                </div>
                            </div>

                            <!-- Step 4: Patient -->
                            <div id="step4" style="display:none">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mr-2" style="width:28px;height:28px;font-size:12px;">
                                        <i class="fas fa-user-injured"></i>
                                    </div>
                                    <h6 class="mb-0 font-weight-bold">Patient Details</h6>
                                </div>

                                <!-- Patient Type -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <span class="form-label-sm">Patient Type</span>
                                        <div class="d-flex gap-3 mt-1">
                                            <label class="patient-type-btn flex-fill d-block active" id="pt_lead_card">
                                                <input type="radio" name="patient_type" value="lead" checked id="pt_lead" class="d-none">
                                                <i class="fas fa-user mr-1"></i> Lead
                                            </label>
                                            <label class="patient-type-btn flex-fill d-block" id="pt_customer_card">
                                                <input type="radio" name="patient_type" value="customer" id="pt_customer" class="d-none">
                                                <i class="fas fa-user-check mr-1"></i> Customer
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group" id="lead-select-group">
                                            <span class="form-label-sm">Lead <span class="text-danger">*</span></span>
                                            <select class="form-control form-control-sm select" name="lead_id" id="lead_id">
                                                <option value="">Search or select lead...</option>
                                                @foreach ($leads as $lead)
                                                    <option value="{{ $lead->id }}">{{ $lead->full_name }} — {{ $lead->phone_number }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group" id="customer-select-group" style="display:none">
                                            <span class="form-label-sm">Customer <span class="text-danger">*</span></span>
                                            <select class="form-control form-control-sm select" name="customer_id" id="customer_id">
                                                <option value="">Search or select customer...</option>
                                                @foreach ($customers as $customer)
                                                    <option value="{{ $customer->id }}">{{ $customer->lead?->full_name ?? 'Customer #'.$customer->id }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <span class="form-label-sm">Notes</span>
                                            <textarea class="form-control form-control-sm" name="notes" rows="3" placeholder="Add any special notes..."></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Summary Box -->
                                <div class="summary-box p-3 mt-3 mb-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-clipboard-list text-warning mr-2"></i>
                                        <strong style="color:#ffc107; font-size:0.9rem;">Booking Summary</strong>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Service</span>
                                        <span class="summary-value" id="sum-service">—</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Professional</span>
                                        <span class="summary-value" id="sum-professional">—</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Date & Time</span>
                                        <span class="summary-value" id="sum-datetime">—</span>
                                    </div>
                                </div>

                                <div class="text-right mt-3">
                                    <button type="button" class="btn btn-outline-secondary btn-sm mr-2" onclick="goStep(3)"><i class="fas fa-arrow-left mr-1"></i> Back</button>
                                    <button type="submit" class="btn btn-success btn-sm px-3"><i class="fas fa-check-circle mr-1"></i> Book Appointment</button>
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
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>
        let currentStep = 1;
        let selectedServiceName = '';
        let selectedProfName = '';
        let selectedSlotLabel = '';

        $(document).ready(function() {
            // Init Select2
            $('.select').select2({ width: '100%', allowClear: true });

            // Service card click
            $('.svc-card').on('click', function() {
                $('.svc-card').removeClass('selected');
                $(this).addClass('selected');
                $(this).find('.service-radio').prop('checked', true);
                selectedServiceName = $(this).find('strong').text();
            });

            // Patient type toggle
            $('input[name=patient_type]').on('change', function() {
                if ($(this).val() === 'lead') {
                    $('#lead-select-group').show();
                    $('#customer-select-group').hide();
                    $('#customer_id').val(null).trigger('change');
                    $('#pt_lead_card').addClass('active');
                    $('#pt_customer_card').removeClass('active');
                } else {
                    $('#lead-select-group').hide();
                    $('#customer-select-group').show();
                    $('#lead_id').val(null).trigger('change');
                    $('#pt_customer_card').addClass('active');
                    $('#pt_lead_card').removeClass('active');
                }
            });

            // Date change -> load slots
            $('#appointment_date').on('change', function() {
                loadSlots();
            });
        });

        function goStep(n) {
            // Validate current step
            if (n > currentStep) {
                if (currentStep === 1 && !$('input[name=service_id]:checked').val()) {
                    alert('Please select a service.'); return;
                }
                if (currentStep === 2 && !$('#selected_user_id').val()) {
                    alert('Please select a professional.'); return;
                }
                if (currentStep === 3 && !$('#selected_slot_id').val()) {
                    alert('Please select a date and time slot.'); return;
                }
            }

            for (let i = 1; i <= 4; i++) {
                $('#step' + i).toggle(i === n);
                const sc = $('#sc' + i), sl = $('#sl' + i);
                sc.removeClass('active done');
                sl.removeClass('active done');
                if (i < n) { sc.addClass('done'); sc.html('<i class="fas fa-check"></i>'); sl.addClass('done'); }
                else if (i === n) { sc.addClass('active'); sc.text(i); sl.addClass('active'); }
                else { sc.text(i); }
                if (i < 4) $('#ln' + i).toggleClass('done', i < n);
            }
            currentStep = n;

            if (n === 2) loadProfessionals();
            if (n === 3 && $('#appointment_date').val()) loadSlots();
            if (n === 4) updateSummary();
        }

        function loadProfessionals() {
            const serviceId = $('input[name=service_id]:checked').val();
            if (!serviceId) return;

            $.get(`{{ url('admin/appointments/professionals-by-service') }}/${serviceId}`, function(data) {
                if (!data.length) {
                    $('#professionals-list').html('<p class="text-muted">No professionals available for this service.</p>');
                    return;
                }
                let html = '<div class="row">';
                data.forEach(p => {
                    const selected = $('#selected_user_id').val() == p.id;
                    html += `<div class="col-md-6 mb-3">
                        <label class="d-block prof-card ${selected ? 'selected' : ''}" onclick="selectProf(${p.id}, this)">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-light text-primary d-flex align-items-center justify-content-center mr-3" style="width:40px;height:40px;flex-shrink:0;">
                                    <i class="fas fa-user-md"></i>
                                </div>
                                <div>
                                    <strong class="d-block" style="font-size:0.9rem;">${p.name}</strong>
                                    <small class="text-muted">${p.designation ? p.designation : ''}</small>
                                </div>
                            </div>
                        </label>
                    </div>`;
                });
                html += '</div>';
                $('#professionals-list').html(html);
            });
        }

        function selectProf(userId, el) {
            $('#selected_user_id').val(userId);
            $('.prof-card').removeClass('selected');
            $(el).addClass('selected');
            selectedProfName = $(el).find('strong').text();
            updateSummary();
        }

        function updateSummary() {
            $('#sum-service').text(selectedServiceName || '—');
            $('#sum-professional').text(selectedProfName || '—');

            const dateVal = $('#appointment_date').val();
            if (dateVal && selectedSlotLabel) {
                const d = new Date(dateVal + 'T00:00:00');
                const formatted = d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
                $('#sum-datetime').text(formatted + ' at ' + selectedSlotLabel);
            } else if (dateVal) {
                const d = new Date(dateVal + 'T00:00:00');
                $('#sum-datetime').text(d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' }));
            } else {
                $('#sum-datetime').text('—');
            }
        }

        function loadSlots() {
            const userId = $('#selected_user_id').val();
            const date = $('#appointment_date').val();
            if (!userId || !date) {
                $('#slots-container').hide();
                return;
            }

            $('#slots-container').show();
            $('#slots-grid').html('<p class="text-muted"><i class="fas fa-spinner fa-spin mr-1"></i> Loading...</p>');

            $.get(`{{ url('admin/appointments/professional-slots') }}/${userId}/${date}`, function(slots) {
                if (!slots.length) {
                    $('#slots-grid').html('<p class="text-muted">No available slots for this date.</p>');
                    return;
                }
                let html = '';
                slots.forEach(s => {
                    let cls = s.available ? 'slot-btn' : 'slot-btn taken';
                    const selected = $('#selected_slot_id').val() == s.professional_time_slot_id;
                    if (selected) cls += ' selected';
                    const click = s.available ? `onclick="selectSlot(${s.professional_time_slot_id}, this)"` : 'disabled';
                    html += `<div class="col-md-3 mb-2">
                        <div class="${cls}" ${click}>
                            <i class="fas ${s.available ? 'fa-clock' : 'fa-ban'} mr-1" style="font-size:0.75rem;"></i>
                            <span>${s.start_time} - ${s.end_time}</span>
                            ${!s.available ? '<br><small class="text-muted" style="font-size:0.7rem;">Booked</small>' : ''}
                        </div>
                    </div>`;
                });
                $('#slots-grid').html(html);
            });
        }

        function selectSlot(slotId, el) {
            $('#selected_slot_id').val(slotId);
            $('.slot-btn').removeClass('selected');
            $(el).addClass('selected');
            selectedSlotLabel = $(el).find('span').text().trim();
            updateSummary();
        }
    </script>
@endpush
