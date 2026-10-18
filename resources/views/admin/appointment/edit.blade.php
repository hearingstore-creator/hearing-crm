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

                {{-- Advance Payment & Refund Panel (Parts C & D) --}}
                <div class="card card-outline {{ $appointment->customerAdvance ? 'card-success' : 'card-secondary' }} mt-3">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-hand-holding-usd mr-1 text-success"></i> Advance Payment &amp; Refund Status
                            </h5>
                            @if (!$appointment->customerAdvance && $appointment->status?->value === 'scheduled')
                                <button type="button" class="btn btn-sm btn-success" data-toggle="modal" data-target="#modal-take-advance">
                                    <i class="fas fa-plus-circle mr-1"></i> Take Advance Payment
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        @if ($appointment->customerAdvance)
                            @php $adv = $appointment->customerAdvance; @endphp
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <div class="text-muted" style="font-size: 11px; text-transform: uppercase;">Receipt No</div>
                                    <div class="font-weight-bold" style="font-size: 15px;">{{ $adv->receipt_no }}</div>
                                    <small class="text-muted">{{ $adv->date?->format('d M Y') }} &bull; {{ strtoupper($adv->payment_method) }}</small>
                                </div>
                                <div class="col-md-2">
                                    <div class="text-muted" style="font-size: 11px; text-transform: uppercase;">Advance Paid</div>
                                    <div class="font-weight-bold text-success" style="font-size: 18px;">৳{{ number_format($adv->amount, 2) }}</div>
                                </div>
                                <div class="col-md-2">
                                    <div class="text-muted" style="font-size: 11px; text-transform: uppercase;">Unused Balance</div>
                                    <div class="font-weight-bold text-primary" style="font-size: 18px;">৳{{ number_format($adv->balance, 2) }}</div>
                                </div>
                                <div class="col-md-2">
                                    <div class="text-muted" style="font-size: 11px; text-transform: uppercase;">Status</div>
                                    <span class="{{ $adv->statusBadgeClass() }}" style="font-size: 12px; padding: 5px 10px;">
                                        {{ $adv->statusLabel() }}
                                    </span>
                                </div>
                                <div class="col-md-3 text-right">
                                    <div class="d-flex justify-content-end align-items-center" style="gap: 8px;">
                                        <a href="{{ route('appointments.advance-receipt', $appointment->id) }}" target="_blank" class="btn btn-sm btn-outline-success" title="Print Money Receipt">
                                            <i class="fas fa-print mr-1"></i>Money Receipt
                                        </a>

                                        {{-- Refund button: visible only for users with advance.refund permission --}}
                                        @if ($adv->status === 'active' && $adv->balance > 0)
                                            @if(auth()->user()->can('advance.refund') || \App\Classes\Helper::hasPermission('advance.refund') || auth()->user()->type === 'super_admin' || auth()->user()->type === 'admin')
                                                <button type="button" class="btn btn-sm btn-danger btn-open-refund" 
                                                    data-id="{{ $adv->id }}" 
                                                    data-receipt="{{ $adv->receipt_no }}" 
                                                    data-balance="{{ $adv->balance }}">
                                                    <i class="fas fa-undo mr-1"></i>Refund Advance
                                                </button>
                                            @endif
                                        @endif

                                        @if ($adv->status === 'refunded' && $adv->refund_receipt_no)
                                            <a href="{{ route('advances.refund-receipt', $adv->id) }}" target="_blank" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-file-invoice mr-1"></i>Refund Receipt
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @if ($adv->notes)
                                <div class="mt-2 text-muted" style="font-size: 12px;"><strong>Note:</strong> {{ $adv->notes }}</div>
                            @endif
                        @else
                            <div class="text-muted" style="font-size: 13px;">
                                <i class="fas fa-info-circle mr-1 text-info"></i> No advance deposit has been taken for this appointment yet. Staff can collect an advance payment before visit to secure the booking.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Modal: Take Advance Payment --}}
<div class="modal fade" id="modal-take-advance" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; border: none;">
            <div class="modal-header" style="background: #059669; color: #ffffff;">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-hand-holding-usd mr-1"></i> Take Advance Payment</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form action="{{ route('appointments.record-advance', $appointment->id) }}" method="POST">
                @csrf
                <div class="modal-body p-3">
                    <div class="form-group mb-2">
                        <label class="font-weight-bold" style="font-size: 12.5px;">Advance Amount (৳) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="1" name="amount" class="form-control" placeholder="e.g. 500" required>
                    </div>
                    <div class="form-group mb-2">
                        <label class="font-weight-bold" style="font-size: 12.5px;">Payment Method <span class="text-danger">*</span></label>
                        <select name="payment_method" class="form-control" required>
                            <option value="cash">Cash</option>
                            <option value="bkash">bKash</option>
                            <option value="nagad">Nagad</option>
                            <option value="card">Card / POS</option>
                            <option value="bank">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="form-group mb-2">
                        <label class="font-weight-bold" style="font-size: 12.5px;">Reference / Trx ID</label>
                        <input type="text" name="reference" class="form-control" placeholder="Cheque / TrxID">
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold" style="font-size: 12.5px;">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional remarks">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm px-4"><i class="fas fa-check mr-1"></i> Record &amp; Issue Receipt</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal: Refund Advance (Part D) --}}
<div class="modal fade" id="modal-refund-advance" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div class="modal-header" style="background: #dc2626; color: #ffffff;">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-undo mr-1"></i> Refund Advance / Keep as Credit</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="form-advance-refund">
                @csrf
                <input type="hidden" id="rf-advance-id" name="advance_id" value="">
                <div class="modal-body p-3">
                    <div class="alert alert-light border mb-3" style="font-size: 13px;">
                        <div><strong>Advance Receipt:</strong> <span id="rf-receipt-no">-</span></div>
                        <div><strong>Available Balance:</strong> <span id="rf-balance-display" class="font-weight-bold text-primary">৳0.00</span></div>
                    </div>

                    {{-- Action Type Switch --}}
                    <div class="form-group mb-3">
                        <label class="font-weight-bold" style="font-size: 12.5px;">Resolution Option <span class="text-danger">*</span></label>
                        <div class="d-flex gap-3" style="gap: 16px;">
                            <label style="cursor: pointer; font-size: 13px;">
                                <input type="radio" name="action_type" value="refund" checked id="opt-action-refund">
                                <strong>Refund to Patient</strong> (Cash/Bank Out)
                            </label>
                            <label style="cursor: pointer; font-size: 13px;">
                                <input type="radio" name="action_type" value="credit_kept" id="opt-action-credit">
                                <strong>Keep as Customer Credit</strong>
                            </label>
                        </div>
                    </div>

                    <div id="wrap-refund-fields">
                        <div class="row">
                            <div class="col-md-6 form-group mb-2">
                                <label style="font-size: 12px; font-weight: 700;">Refund Amount (৳) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" name="refund_amount" id="rf-refund-amount" class="form-control" placeholder="0.00" required>
                                <small class="text-muted">Partial refund allowed</small>
                            </div>
                            <div class="col-md-6 form-group mb-2">
                                <label style="font-size: 12px; font-weight: 700;">Deduction / Cancellation Fee (৳)</label>
                                <input type="number" step="0.01" min="0" name="deduction_fee" id="rf-deduction-fee" class="form-control" value="0.00" placeholder="0.00">
                                <small class="text-muted">Recorded as cancellation income</small>
                            </div>
                            <div class="col-md-6 form-group mb-2">
                                <label style="font-size: 12px; font-weight: 700;">Refund Method <span class="text-danger">*</span></label>
                                <select name="refund_method" id="rf-refund-method" class="form-control">
                                    <option value="cash">Cash</option>
                                    <option value="bkash">bKash</option>
                                    <option value="nagad">Nagad</option>
                                    <option value="card">Card</option>
                                    <option value="bank">Bank Transfer</option>
                                </select>
                            </div>
                            <div class="col-md-6 form-group mb-2">
                                <label style="font-size: 12px; font-weight: 700;">Refund Reference / Trx</label>
                                <input type="text" name="reference" id="rf-reference" class="form-control" placeholder="Cheque / TrxID">
                            </div>
                        </div>
                    </div>

                    <div id="wrap-credit-hint" style="display: none;" class="alert alert-info py-2 px-3 mb-2" style="font-size: 12px;">
                        <i class="fas fa-info-circle mr-1"></i> Patient cannot attend. The advance will remain on the customer's account balance as credit for future sales or appointments.
                    </div>

                    <div class="form-group mb-0">
                        <label style="font-size: 12px; font-weight: 700;">Reason for Cancellation / Refund <span class="text-danger">*</span></label>
                        <textarea name="reason" id="rf-reason" class="form-control" rows="2" placeholder="e.g. Patient emergency / out of station" required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm px-4" id="btn-submit-refund">
                        <i class="fas fa-check-circle mr-1"></i> Confirm Process
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
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

            // Open Refund Modal (Part D)
            $(document).on('click', '.btn-open-refund', function() {
                var advId = $(this).data('id');
                var receipt = $(this).data('receipt');
                var balance = parseFloat($(this).data('balance') || 0);

                $('#rf-advance-id').val(advId);
                $('#rf-receipt-no').text(receipt);
                $('#rf-balance-display').text('৳' + balance.toFixed(2));
                $('#rf-refund-amount').val(balance.toFixed(2)).attr('max', balance);
                $('#rf-deduction-fee').val('0.00');
                $('#opt-action-refund').prop('checked', true).trigger('change');
                $('#modal-refund-advance').modal('show');
            });

            // Action type toggle
            $('input[name=action_type]').on('change', function() {
                if ($(this).val() === 'credit_kept') {
                    $('#wrap-refund-fields').slideUp(150);
                    $('#wrap-credit-hint').slideDown(150);
                    $('#rf-refund-amount').prop('required', false);
                    $('#rf-refund-method').prop('required', false);
                    $('#btn-submit-refund').removeClass('btn-danger').addClass('btn-secondary').html('<i class="fas fa-wallet mr-1"></i> Confirm Retain Credit');
                } else {
                    $('#wrap-refund-fields').slideDown(150);
                    $('#wrap-credit-hint').slideUp(150);
                    $('#rf-refund-amount').prop('required', true);
                    $('#rf-refund-method').prop('required', true);
                    $('#btn-submit-refund').removeClass('btn-secondary').addClass('btn-danger').html('<i class="fas fa-check-circle mr-1"></i> Confirm Process');
                }
            });

            // Submit Refund / Credit Kept
            $('#form-advance-refund').on('submit', function(e) {
                e.preventDefault();
                var advId = $('#rf-advance-id').val();
                var $btn = $('#btn-submit-refund');
                $btn.prop('disabled', true).prepend('<i class="fas fa-spinner fa-spin mr-1"></i>');

                $.ajax({
                    url: '{{ url("admin/advances") }}/' + advId + '/refund',
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(res) {
                        $('#modal-refund-advance').modal('hide');
                        if (res.success) {
                            if (res.refund_receipt_url) {
                                window.open(res.refund_receipt_url, '_blank');
                            }
                            alert(res.message);
                            location.reload();
                        }
                    },
                    error: function(xhr) {
                        $btn.prop('disabled', false).find('.fa-spinner').remove();
                        var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Error processing refund.';
                        alert(msg);
                    }
                });
            });
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
