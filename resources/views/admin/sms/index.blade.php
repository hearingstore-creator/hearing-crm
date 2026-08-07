@extends('admin.layouts.master')

@section('content')
<section class="content pt-3">
   <div class="container-fluid">
      <div class="row">
         <div class="col-md-12">
            <div class="card card-outline card-primary">
               <div class="card-header">
                  <h5><i class="fas fa-comment-dots mr-2"></i>SMS Centre</h5>
               </div>

               @include('admin.alert_message')

               <!-- Tabs -->
               <ul class="nav nav-tabs" id="smsTabs" role="tablist">
                  <li class="nav-item">
                     <a class="nav-link active" data-toggle="tab" href="#compose-tab">
                        <i class="fas fa-paper-plane mr-1"></i> Compose & Send
                     </a>
                  </li>
                  <li class="nav-item">
                     <a class="nav-link" data-toggle="tab" href="#templates-tab">
                        <i class="fas fa-file-alt mr-1"></i> Templates
                     </a>
                  </li>
               </ul>

               <div class="tab-content">
                  @include('admin.sms.partials.compose')
                  @include('admin.sms.partials.templates')
               </div>
            </div>
         </div>
      </div>
   </div>
</section>
@endsection

@push('css')
<link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
@endpush

@push('scripts')
<script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
<script>
   window.SMS_BASE_URL = '{{ url("admin") }}';
</script>
<script src="{{ asset('assets/sms.js') }}"></script>
@endpush
