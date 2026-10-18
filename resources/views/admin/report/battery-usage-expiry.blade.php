@extends('admin.layouts.master')

@push('css')
    <style>
        .alert-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        .alert-approaching {
            background-color: #fff3cd;
            color: #856404;
        }
        .alert-expired {
            background-color: #f8d7da;
            color: #721c24;
        }
        .stock-positive {
            color: #28a745;
            font-weight: 600;
        }
    </style>
@endpush

@section('content')
    <!-- Main content -->
    <section class="content pt-3">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h5 class="mb-0">Battery Usage Expiry Alert Report</h5>
                            <small class="text-muted">
                                Shows sold products approaching battery usage expiry
                                ({{ $alertDaysBefore ?? 15 }} days before usage period ends)
                            </small>
                        </div>

                        <div class="card-body">
                            @if(isset($alertProducts) && count($alertProducts) > 0)
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped" id="batteryUsageTable">
                                        <thead>
                                            <tr>
                                                <th>SL</th>
                                                <th>Type</th>
                                                <th>Identifier</th>
                                                <th>Product</th>
                                                <th>Category</th>
                                                <th>Brand</th>
                                                <th>Sale Invoice</th>
                                                <th>Sale Date</th>
                                                <th>Usage Months</th>
                                                <th>Usage Expiry</th>
                                                <th>Alert Date</th>
                                                <th>Status</th>
                                                <th>Days Left</th>
                                                <th>Qty</th>
                                                {{-- <th>Unit Price</th> --}}
                                                <th>Customer</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($alertProducts as $index => $item)
                                                <tr>
                                                    <td>{{ $index + 1 }}</td>
                                                    <td>
                                                        <span class="badge badge-{{ $item['type'] == 'batch' ? 'info' : ($item['type'] == 'serial' ? 'success' : 'secondary') }}">
                                                            {{ ucfirst($item['type']) }}
                                                        </span>
                                                    </td>
                                                    <td>{{ $item['identifier'] }}</td>
                                                    <td>
                                                        <strong>{{ $item['product']->model ?? 'N/A' }}</strong>
                                                        @if($item['product']->battery_size)
                                                            <br><small class="text-muted">Battery: {{ $item['product']->battery_size }}</small>
                                                        @endif
                                                    </td>
                                                    <td>{{ $item['product']->category->category_name ?? 'N/A' }}</td>
                                                    <td>{{ $item['product']->brand->brand_name ?? 'N/A' }}</td>
                                                    <td>{{ $item['sale_invoice'] }}</td>
                                                    <td>{{ $item['sale_date']->format('d M Y') }}</td>
                                                    <td>{{ $item['usage_months'] }} Months</td>
                                                    <td>{{ $item['usage_expiry_date']->format('d M Y') }}</td>
                                                    <td>{{ $item['alert_date']->format('d M Y') }}</td>
                                                    <td>
                                                        <span class="alert-badge {{ $item['status'] == 'Expired' ? 'alert-expired' : 'alert-approaching' }}">
                                                            {{ $item['status'] }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="{{ $item['days_until_expiry'] < 0 ? 'text-danger' : ($item['days_until_expiry'] <= 30 ? 'text-warning' : 'text-success') }}">
                                                            {{ $item['days_until_expiry'] }} days
                                                        </span>
                                                    </td>
                                                    <td>{{ $item['qty'] }}</td>
                                                    {{-- <td>{{ number_format($item['unit_price'], 2) }}</td> --}}
                                                    <td>{{ $item['customer'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-3">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="alert alert-info">
                                                <strong>Summary:</strong><br>
                                                <ul class="mb-0 pl-3">
                                                    <li>Total Sold Products Approaching Usage Expiry: <strong>{{ count($alertProducts) }}</strong></li>
                                                    <li>Alert Shown: {{ $alertDaysBefore ?? 30 }} days before usage period ends</li>
                                                    <li>Usage period calculated from: Sale Date + Usage Months</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="alert alert-success">
                                    <h5><i class="icon fa fa-check"></i> No Alerts!</h5>
                                    No sold products are approaching battery usage expiry alert period at this time.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
