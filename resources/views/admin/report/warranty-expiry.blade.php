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
                            <h5 class="mb-0">Warranty Expiry Alert Report</h5>
                            <small class="text-muted">
                                Shows unsold products approaching inventory warranty expiry
                                ({{ $alertDaysBefore ?? 10 }} days before warranty difference ends)
                            </small>
                        </div>

                        <div class="card-body">
                            @if(isset($alertProducts) && count($alertProducts) > 0)
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped" id="warrantyTable">
                                        <thead>
                                            <tr>
                                                <th>SL</th>
                                                <th>Type</th>
                                                <th>Identifier</th>
                                                <th>Product</th>
                                                <th>Category</th>
                                                <th>Brand</th>
                                                <th>Purchase Date</th>
                                                <th>Inventory Warranty</th>
                                                <th>Sales Warranty</th>
                                                <th>Month Diff</th>
                                                <th>Warranty Expiry</th>
                                                <th>Alert Date</th>
                                                <th>Status</th>
                                                <th>Days Left</th>
                                                <th>Stock</th>
                                                {{-- <th>Purchase Price</th> --}}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($alertProducts as $index => $item)
                                                <tr>
                                                    <td>{{ $index + 1 }}</td>
                                                    <td>
                                                        <span class="badge badge-{{ $item['type'] == 'batch' ? 'info' : 'success' }}">
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
                                                    <td>{{ $item['purchase_date']->format('d M Y') }}</td>
                                                    <td>{{ $item['inventory_warranty_months'] }} Months</td>
                                                    <td>{{ $item['sales_warranty_months'] }} Months</td>
                                                    <td>{{ $item['month_difference'] }} Months</td>
                                                    <td>{{ $item['warranty_expiry_date']->format('d M Y') }}</td>
                                                    <td>{{ $item['alert_date']->format('d M Y') }}</td>
                                                    <td>
                                                        <span class="alert-badge {{ $item['status'] == 'Alert Expired' ? 'alert-expired' : 'alert-approaching' }}">
                                                            {{ $item['status'] }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="{{ $item['days_until_alert'] < 0 ? 'text-danger' : ($item['days_until_alert'] <= 10 ? 'text-warning' : 'text-success') }}">
                                                            {{ $item['days_until_alert'] }} days
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="stock-positive">{{ $item['remaining_stock'] }}</span>
                                                    </td>
                                                    {{-- <td>{{ number_format($item['purchase_price'], 2) }}</td> --}}
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
                                                    <li>Total Products Approaching Warranty Expiry: <strong>{{ count($alertProducts) }}</strong></li>
                                                    <li>Alert Shown: {{ $alertDaysBefore ?? 10 }} days before warranty difference (Inventory Warranty - Sales Warranty) ends</li>
                                                    <li>Products shown are within ±30 days of alert date</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="alert alert-success">
                                    <h5><i class="icon fa fa-check"></i> No Alerts!</h5>
                                    No products are approaching warranty expiry alert period at this time.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
