@extends('admin.layouts.master')

@push('css')
    <!-- Select2 -->
  <link rel="stylesheet" href="{{ asset("assets/plugins/select2/css/select2.min.css") }}">
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
                        <h5>Edit Doctor Payment</h5>
                     </div>
                     @can('permission','doctor_payment.index')
                     <div class="col-md-6 text-right">
                        <a href="{{url('admin/doctors-payment')}}" class="btn-primary btn-cs-primary  btn-sm"><i class="fas fa-align-justify mr-1"></i>Payment List</a>
                     </div>
                     @endcan
                  </div>
               </div>
               @include('admin.alert_message')
               <div class="card-body">
                  <form action="{{ url('admin/doctors-payment/'.$payment->id) }}" method="POST"
                     data-doctor-due-url="{{ route('doctors.due', ':id') }}">
                     @csrf
                     @method('PUT')
                     <div class="form-group row">
                        <label for="doctor_id" class="col-md-3 col-form-label">Doctor <i class="text-danger">*</i>:</label>
                        <div class="col-md-4">
                           <div class="">
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
                        <label for="due_amount" class="col-md-2 col-form-label">Due Amount:</label>
                        <div class="col-md-3">
                           <div class="">
                              <input type="text" name="due_amount" value="{{ number_format($due_amount ?? 0, 2) }}" class="form-control" id="due_amount" readonly>
                           </div>
                        </div>
                     </div>
                     <div class="form-group row">
                        <label for="date" class="col-md-3 col-form-label">Date <i class="text-danger">*</i>:</label>
                        <div class="col-md-4">
                           <div class="">
                              <input type="date" name="date" value="{{ $payment->date ? $payment->date->format('Y-m-d') : '' }}" class="form-control" id="date" required>
                           </div>
                        </div>
                     </div>
                     <div class="form-group row">
                        <label for="debit" class="col-md-3 col-form-label">Payment Amount <i class="text-danger">*</i>:</label>
                        <div class="col-md-4">
                           <div class="">
                              <input type="number" name="debit" class="form-control" id="debit" step="0.01" min="0" value="{{ $payment->debit }}" placeholder="Enter payment amount" required>
                           </div>
                        </div>
                     </div>
                     <div class="form-group row">
                        <label for="details" class="col-md-3 col-form-label">Details:</label>
                        <div class="col-md-9">
                           <div class="">
                              <textarea class="form-control" id="details" name="details" rows="3" placeholder="Enter payment details (optional)">{{ $payment->details ?? '' }}</textarea>
                           </div>
                        </div>
                     </div>
                     <div class="form-group row">
                        <div class="col-md-3"></div>
                        <div class="col-md-9">
                           <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Update</button>
                           <a href="{{url('admin/doctors-payment')}}" class="btn btn-danger"><i class="fas fa-times"></i> Cancel</a>
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
