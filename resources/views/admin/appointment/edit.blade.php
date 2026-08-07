@extends('admin.layouts.master')

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
@endpush

@section('content')
<section class="content pt-3">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card card-outline card-primary">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-6"><h5>Edit Appointment</h5></div>
                            <div class="col-md-6 text-right">
                                <a href="{{ url('admin/appointments') }}" class="btn-primary btn-cs-primary btn-sm">
                                    <i class="fas fa-list mr-1"></i> Appointment List
                                </a>
                            </div>
                        </div>
                    </div>

                    @include('admin.alert_message')

                    <div class="card-body">
                        <form action="{{ route('appointments.update', $appointment->id) }}" method="post">
                            @csrf
                            @method('PUT')
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Patient Type:</label>
                                        <div class="col-sm-3">
                                            <div class="d-flex gap-3">
                                                <label class="radio-inline">
                                                    <input type="radio" name="patient_type" value="lead" {{ $appointment->lead_id ? 'checked' : '' }} id="pt_lead"> Lead
                                                </label>
                                                <label class="radio-inline">
                                                    <input type="radio" name="patient_type" value="customer" {{ $appointment->customer_id ? 'checked' : '' }} id="pt_customer"> Customer
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group row" id="lead-group" style="{{ $appointment->customer_id ? 'display:none' : '' }}">
                                        <label class="col-sm-3 col-form-label">Lead <span class="text-danger">*</span>:</label>
                                        <div class="col-sm-6">
                                            <select class="form-control select" name="lead_id" id="lead_id">
                                                <option value="">Search or select lead...</option>
                                                @foreach ($leads as $lead)
                                                    <option value="{{ $lead->id }}" {{ $appointment->lead_id == $lead->id ? 'selected' : '' }}>{{ $lead->full_name }} — {{ $lead->phone_number }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group row" id="customer-group" style="{{ $appointment->lead_id ? 'display:none' : '' }}">
                                        <label class="col-sm-3 col-form-label">Customer <span class="text-danger">*</span>:</label>
                                        <div class="col-sm-6">
                                            <select class="form-control select" name="customer_id" id="customer_id">
                                                <option value="">Search or select customer...</option>
                                                @foreach ($customers as $customer)
                                                    <option value="{{ $customer->id }}" {{ $appointment->customer_id == $customer->id ? 'selected' : '' }}>{{ $customer->lead?->full_name ?? 'Customer #'.$customer->id }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Professional <span class="text-danger">*</span>:</label>
                                        <div class="col-sm-6">
                                            <select class="form-control select" name="user_id" id="edit_professional" onchange="loadEditSlots()">
                                                <option value="">Select Professional</option>
                                                @foreach ($professionals as $prof)
                                                    <option value="{{ $prof->id }}" {{ $appointment->user_id == $prof->id ? 'selected' : '' }}>{{ $prof->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Service <span class="text-danger">*</span>:</label>
                                        <div class="col-sm-6">
                                            <select class="form-control select" name="service_id">
                                                <option value="">Select Service</option>
                                                @foreach ($appointmentServices as $service)
                                                    <option value="{{ $service->id }}" {{ $appointment->service_id == $service->id ? 'selected' : '' }}>{{ $service->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Date:</label>
                                        <div class="col-sm-6">
                                            <input type="date" class="form-control" name="appointment_date" value="{{ $appointment->appointment_date ? $appointment->appointment_date->format('Y-m-d') : '' }}" onchange="loadEditSlots()">
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Time Slot:</label>
                                        <div class="col-sm-6">
                                            <select class="form-control" name="professional_time_slot_id" id="edit_slot_select">
                                                <option value="">Select Slot</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Status:</label>
                                        <div class="col-sm-6">
                                            <select class="form-control" name="status">
                                                @foreach ($appointmentStatuses as $status)
                                                    <option value="{{ $status->value }}" {{ $appointment->status?->value === $status->value ? 'selected' : '' }}>{{ $status->label() }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Notes:</label>
                                        <div class="col-sm-6">
                                            <textarea class="form-control" name="notes" rows="2">{{ $appointment->notes }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group row">
                                <div class="col-md-6 text-right">
                                    <button type="submit" class="btn btn-success">Update</button>
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
        $(document).ready(function() {
            $('.select').select2({ width: '100%', allowClear: true });

            // Patient type toggle
            $('input[name=patient_type]').on('change', function() {
                if ($(this).val() === 'lead') {
                    $('#lead-group').show();
                    $('#customer-group').hide();
                    $('#customer_id').val(null).trigger('change');
                } else {
                    $('#lead-group').hide();
                    $('#customer-group').show();
                    $('#lead_id').val(null).trigger('change');
                }
            });

            loadEditSlots();
        });

        function loadEditSlots() {
            const userId = $('#edit_professional').val();
            const date = $('input[name=appointment_date]').val();
            const excludeId = '{{ $appointment->id }}';
            if (!userId || !date) {
                $('#edit_slot_select').html('<option value="">Select Professional and Date first</option>');
                return;
            }

            $.get(`{{ url('admin/appointments/professional-slots') }}/${userId}/${date}?exclude_appointment_id=${excludeId}`, function(slots) {
                let html = '<option value="">Select Slot</option>';
                const currentSlotId = '{{ $appointment->professional_time_slot_id }}';
                slots.forEach(s => {
                    const sel = s.professional_time_slot_id == currentSlotId ? 'selected' : '';
                    const disabled = s.available ? '' : 'disabled';
                    html += `<option value="${s.professional_time_slot_id}" ${sel} ${disabled}>${s.start_time} - ${s.end_time} ${!s.available ? '(Booked)' : ''}</option>`;
                });
                $('#edit_slot_select').html(html);
            });
        }
    </script>
@endpush
