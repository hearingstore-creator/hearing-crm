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
                     <div class="col-md-6">
                        <h5><i class="fas fa-history mr-2"></i>SMS Send Log</h5>
                     </div>
                     <div class="col-md-6 text-right">
                        <a href="{{ url('admin/sms') }}" class="btn-primary btn-cs-primary btn-sm">
                           <i class="fas fa-comment-dots mr-1"></i>SMS Centre
                        </a>
                     </div>
                  </div>

                  <!-- Search Form -->
                  <div class="row pb-3">
                     <div class="col-md-12">
                        <form method="GET">
                           <div class="row">
                              <div class="col-md-2">
                                 <input type="text" name="search" class="form-control" placeholder="Phone / Message"
                                    value="{{ isset($_GET['search']) ? $_GET['search'] : '' }}">
                              </div>
                              <div class="col-md-2">
                                 <select name="status" class="form-control">
                                    <option value="">All Status</option>
                                    @foreach(\App\Enums\SmsStatus::cases() as $status)
                                    <option value="{{ $status->value }}" {{ isset($_GET['status']) && $_GET['status'] == $status->value ? 'selected' : '' }}>
                                       {{ $status->label() }}
                                    </option>
                                    @endforeach
                                 </select>
                              </div>
                              <div class="col-md-2">
                                 <select name="type" class="form-control">
                                    <option value="">All Types</option>
                                    @foreach(\App\Enums\SmsType::cases() as $type)
                                    <option value="{{ $type->value }}" {{ isset($_GET['type']) && $_GET['type'] == $type->value ? 'selected' : '' }}>
                                       {{ $type->label() }}
                                    </option>
                                    @endforeach
                                 </select>
                              </div>
                              <div class="col-md-2">
                                 <input type="date" name="date_from" class="form-control"
                                    value="{{ isset($_GET['date_from']) ? $_GET['date_from'] : '' }}">
                              </div>
                              <div class="col-md-2">
                                 <input type="date" name="date_to" class="form-control"
                                    value="{{ isset($_GET['date_to']) ? $_GET['date_to'] : '' }}">
                              </div>
                              <div class="col-md-2">
                                 <button class="btn btn-info" type="submit"><i class="fas fa-search"></i> </button>
                                 <a href="{{ url('admin/sms-log') }}" class="btn btn-primary"><i class="fas fa-sync-alt"></i> </a>
                              </div>
                           </div>
                        </form>
                     </div>
                  </div>
               </div>

               @include('admin.alert_message')

               <div class="card-body table-responsive">
                  <table class="table table-bordered table-striped">
                     <thead>
                        <tr>
                           <th>SL</th>
                           <th>Phone</th>
                           <th>Message</th>
                           <th>Type</th>
                           <th>Status</th>
                           <th>Recipient</th>
                           <th>Source</th>
                           <th>Sent At</th>
                        </tr>
                     </thead>
                     <tbody>
                        @foreach ($logs as $key => $log)
                        <tr>
                           <td>{{ ($logs->currentPage() - 1) * $logs->perPage() + ++$key }}</td>
                           <td><code>{{ $log->phone_number }}</code></td>
                           <td>{{ $log->message }}</td>
                           <td>
                              <span class="sms-badge sms-badge-{{ $log->type_color }}">
                                 {{ $log->type_label }}
                              </span>
                           </td>
                           <td>
                              <span class="sms-badge sms-badge-{{ $log->status_color ?? 'gray' }}">
                                 {{ $log->status_label }}
                              </span>
                           </td>
                           <td>
                              <span class="text-capitalize">{{ ucfirst($log->recipient_type) }}</span>
                           </td>
                           <td>
                              @if($log->campaign)
                              <span class="text-primary">{{ $log->campaign->name }}</span>
                              @else
                              <span class="text-muted">-</span>
                              @endif
                           </td>
                           <td>{{ $log->sent_at ? $log->sent_at->format('d M Y, h:i A') : '-' }}</td>
                        </tr>
                        @endforeach

                        @if($logs->isEmpty())
                        <tr>
                           <td colspan="8" class="text-center text-muted py-4">
                              <i class="fas fa-inbox fa-2x mb-2"></i>
                              <p class="mb-0">No logs found.</p>
                           </td>
                        </tr>
                        @endif
                     </tbody>
                  </table>
               </div>

               <div class="card-footer clearfix">
                  {{ $logs->withQueryString()->links() }}
               </div>
            </div>
         </div>
      </div>
   </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
<script>$('.select').select2({ width: '100%' });</script>
@endpush
