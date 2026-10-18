@extends('admin.layouts.master')
@section('page_title', 'Edit Doctor Payment')
@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<link rel="stylesheet" href="{{ asset("assets/plugins/select2/css/select2.min.css") }}">
@endpush
@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-user-md" style="color: var(--accent-500); margin-right: 8px;"></i>Edit Doctor Payment</h1>
            <p>Update doctor payment details</p>
        </div>
        <div class="header-actions">
            @can('permission','doctor_payment.index')
            <a href="{{url('admin/doctors-payment')}}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>Payment List</a>
            @endcan
        </div>
    </div>
    @include('admin.alert_message')
    <form action="{{ url('admin/doctors-payment/'.$payment->id) }}" method="POST"
        data-doctor-due-url="{{ route('doctors.due', ':id') }}">
        @csrf
        @method('PUT')
        <div class="crm-form-section crm-animate-in delay-1">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--accent-500);"><i class="fas fa-money-bill-wave"></i></div>
                <h6>Payment Details</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-5">
                        <div class="form-group">
                            <label for="doctor_id">Doctor <span style="color: var(--danger-500);">*</span></label>
                            <select class="form-control select" name="doctor_id" id="doctor_id" required>
                                <option value="">Select Doctor</option>
                                @foreach ($doctors as $doctor)
                                    <option value="{{ $doctor->id }}" {{ $payment->doctor_id == $doctor->id ? 'selected' : '' }}>
                                        {{ $doctor->name }} ({{ $doctor->qualification ?? 'N/A' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="due_amount">Due Amount</label>
                            <input type="text" name="due_amount" value="{{ number_format($due_amount ?? 0, 2) }}" class="form-control" id="due_amount" readonly>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="date">Date <span style="color: var(--danger-500);">*</span></label>
                            <input type="date" name="date" value="{{ $payment->date ? $payment->date->format('Y-m-d') : '' }}" class="form-control" id="date" required>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="debit">Payment Amount <span style="color: var(--danger-500);">*</span></label>
                            <input type="number" name="debit" class="form-control" id="debit" step="0.01" min="0" value="{{ $payment->debit }}" placeholder="Enter payment amount" required>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="form-group">
                            <label for="details">Details</label>
                            <textarea class="form-control" id="details" name="details" rows="3" placeholder="Enter payment details (optional)">{{ $payment->details ?? '' }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="crm-form-submit-bar">
            <a href="{{url('admin/doctors-payment')}}" class="btn btn-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Update</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset("assets/plugins/select2/js/select2.min.js") }}"></script>
<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select').select2({
        placeholder: "Select Doctor",
        width: '100%'
    });

    // Load doctor due amount when doctor is selected using Select2 event
     $('.select').on('select2:select', function(e) {
         // 1. Use $(this).closest('form') to find the specific form wrapping this element
         var $form = $(this).closest('form'); 
         var doctorId = e.params.data.id;
         
         // 2. Get URL from that specific form
         var dueUrl = $form.data('doctor-due-url'); 

         // 3. Fallback for older jQuery versions (optional, see note below)
         if (!dueUrl && $form.data('doctorDueUrl')) {
            dueUrl = $form.data('doctorDueUrl');
         }

         // 4. Construct the URL safely
         // We replace ':id' only if it exists. If your URL is already absolute, just remove the replace line.
         var finalUrl = dueUrl.replace(':id', doctorId);

         if (doctorId) {
            $.ajax({
                  url: finalUrl,
                  type: 'GET',
                  success: function(response) {
                     // 5. Safety check: ensure response exists and has 'due'
                     if (response && response.due !== undefined) {
                        $('#due_amount').val(response.due.toFixed(2));
                     } else {
                        console.error("Invalid response format", response);
                        $('#due_amount').val('');
                     }
                  },
                  error: function(xhr) {
                     console.error("Error fetching due amount:", xhr.status);
                     $('#due_amount').val('');
                  }
            });
         }
      });

    // Also clear due amount when doctor is cleared
    $('.select').on('select2:clear', function(e) {
        $('#due_amount').val('');
    });
});
</script>
@endpush
