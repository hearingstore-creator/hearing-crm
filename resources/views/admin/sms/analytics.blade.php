@extends('admin.layouts.master')

@section('content')
<section class="content pt-3">
   <div class="container-fluid">
      <div class="row">
         <div class="col-md-12">
            <div class="card card-outline card-primary">
               <div class="card-header">
                  <div class="row">
                     <div class="col-md-6">
                        <h5><i class="fas fa-chart-bar mr-2"></i>SMS Analytics</h5>
                     </div>
                     <div class="col-md-6 text-right">
                        <a href="{{ url('admin/sms') }}" class="btn-primary btn-cs-primary btn-sm">
                           <i class="fas fa-comment-dots mr-1"></i>SMS Centre
                        </a>
                     </div>
                  </div>

                  <!-- Period Filter -->
                  <div class="row pb-3">
                     <div class="col-md-12">
                        <form method="GET">
                           <div class="row align-items-center">
                              <div class="col-md-2">
                                 <label class="mb-0 small font-weight-bold">Period:</label>
                              </div>
                              <div class="col-md-4">
                                 <select name="period" class="form-control" onchange="this.form.submit()">
                                    <option value="7_days" {{ $period == '7_days' ? 'selected' : '' }}>Last 7 Days</option>
                                    <option value="30_days" {{ $period == '30_days' ? 'selected' : '' }}>Last 30 Days</option>
                                    <option value="90_days" {{ $period == '90_days' ? 'selected' : '' }}>Last 90 Days</option>
                                    <option value="12_months" {{ $period == '12_months' ? 'selected' : '' }}>Last 12 Months</option>
                                 </select>
                              </div>
                           </div>
                        </form>
                     </div>
                  </div>
               </div>

               <div class="card-body">
                  <!-- Stats Strip -->
                  <div class="row mb-4">
                     <div class="col-md-3 col-sm-6">
                        <div class="sms-stat-card sms-stat-blue">
                           <div class="sms-stat-icon"><i class="fas fa-paper-plane"></i></div>
                           <div class="sms-stat-value">{{ $data['total_sent'] }}</div>
                           <div class="sms-stat-label">Total Sent</div>
                        </div>
                     </div>
                     <div class="col-md-3 col-sm-6">
                        <div class="sms-stat-card sms-stat-green">
                           <div class="sms-stat-icon"><i class="fas fa-check-circle"></i></div>
                           <div class="sms-stat-value">{{ $data['successful_sent'] }}</div>
                           <div class="sms-stat-label">Delivered</div>
                        </div>
                     </div>
                     <div class="col-md-3 col-sm-6">
                        <div class="sms-stat-card sms-stat-red">
                           <div class="sms-stat-icon"><i class="fas fa-times-circle"></i></div>
                           <div class="sms-stat-value">{{ $data['failed_sent'] }}</div>
                           <div class="sms-stat-label">Failed</div>
                        </div>
                     </div>
                     <div class="col-md-3 col-sm-6">
                        <div class="sms-stat-card sms-stat-purple">
                           <div class="sms-stat-icon"><i class="fas fa-percentage"></i></div>
                           <div class="sms-stat-value">{{ $data['delivery_rate'] }}%</div>
                           <div class="sms-stat-label">Delivery Rate</div>
                        </div>
                     </div>
                  </div>

                  <div class="row">
                     <!-- Messages by Type -->
                     <div class="col-md-6 mb-4">
                        <div class="card card-outline card-info">
                           <div class="card-header py-2">
                              <h6 class="mb-0"><i class="fas fa-chart-pie mr-1"></i> Messages by Type</h6>
                           </div>
                           <div class="card-body">
                              @php
                                 $total = array_sum($data['messages_by_type']) ?: 1;
                              @endphp
                              @foreach($data['messages_by_type'] as $type => $count)
                              <div class="mb-3">
                                 <div class="d-flex justify-content-between mb-1">
                                    <small><strong>{{ ucfirst($type) }}</strong></small>
                                    <small>{{ $count }} ({{ round(($count / $total) * 100, 1) }}%)</small>
                                 </div>
                                 <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-primary" style="width: {{ ($count / $total) * 100 }}%"></div>
                                 </div>
                              </div>
                              @endforeach

                              @if(empty($data['messages_by_type']))
                              <p class="text-muted text-center">No data for this period.</p>
                              @endif
                           </div>
                        </div>
                     </div>

                     <!-- Top Campaigns -->
                     <div class="col-md-6 mb-4">
                        <div class="card card-outline card-warning">
                           <div class="card-header py-2">
                              <h6 class="mb-0"><i class="fas fa-trophy mr-1"></i> Campaign Summary</h6>
                           </div>
                           <div class="card-body">
                              <p class="text-center text-muted">Total bulk campaigns sent: <strong>{{ $data['bulk_campaigns'] }}</strong></p>
                           </div>
                        </div>
                     </div>
                  </div>

                  <!-- Campaign Summary -->
                  <div class="card card-outline card-success">
                     <div class="card-header py-2">
                        <h6 class="mb-0"><i class="fas fa-list-alt mr-1"></i> Recent Campaigns</h6>
                     </div>
                     <div class="card-body">
                        @if($campaigns->isNotEmpty())
                        <table class="table table-bordered table-striped">
                           <thead>
                              <tr>
                                 <th>Campaign</th>
                                 <th>Recipients</th>
                                 <th>Sent</th>
                                 <th>Failed</th>
                                 <th>Status</th>
                                 <th>Date</th>
                              </tr>
                           </thead>
                           <tbody>
                              @foreach($campaigns as $campaign)
                              <tr>
                                 <td>{{ $campaign->name }}</td>
                                 <td>{{ $campaign->total_recipients }}</td>
                                 <td class="text-success">{{ $campaign->sent_count }}</td>
                                 <td class="text-danger">{{ $campaign->failed_count }}</td>
                                 <td>
                                    <span class="sms-badge sms-badge-{{ $campaign->status_color ?? 'gray' }}">
                                       {{ $campaign->status_label }}
                                    </span>
                                 </td>
                                 <td>{{ $campaign->created_at->format('d M Y, h:i A') }}</td>
                              </tr>
                              @endforeach
                           </tbody>
                        </table>
                        @else
                        <p class="text-muted text-center">No campaigns yet.</p>
                        @endif
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </div>
</section>
@endsection
