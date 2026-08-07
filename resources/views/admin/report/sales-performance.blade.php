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
                                <h5>Sales Performance Report</h5>
                            </div>
                            <div class="col-md-6 text-right">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-success dropdown-toggle" data-toggle="dropdown">
                                        <i class="fas fa-file-excel"></i> Export Excel
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="{{ route('report.sales-performance.export', ['type' => 'full', 'format' => 'xlsx']) }}?{{ http_build_query(request()->query()) }}">
                                            <i class="fas fa-file-excel text-success"></i> Table Report
                                        </a>
                                        <a class="dropdown-item" href="{{ route('report.sales-performance.export', ['type' => 'summary', 'format' => 'xlsx']) }}?{{ http_build_query(request()->query()) }}">
                                            <i class="fas fa-file-excel text-primary"></i> Summary Report
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Search Filters --}}
                        <div class="row pb-3 mt-3">
                            <div class="col-md-12">
                                <form method="GET">
                                    <div class="row">
                                        {{-- Group By --}}
                                        <div class="col-md-2">
                                            <label class="form-label small">Group By</label>
                                            <select class="form-control select" name="group_by">
                                                <option value="vendor" {{ $group_by === 'vendor' ? 'selected' : '' }}>Vendor</option>
                                                <option value="staff" {{ $group_by === 'staff' ? 'selected' : '' }}>Staff</option>
                                                <option value="product" {{ $group_by === 'product' ? 'selected' : '' }}>Product Model</option>
                                                <option value="doctor" {{ $group_by === 'doctor' ? 'selected' : '' }}>Doctor</option>
                                                <option value="category" {{ $group_by === 'category' ? 'selected' : '' }}>Category</option>
                                                <option value="membership_tier" {{ $group_by === 'membership_tier' ? 'selected' : '' }}>Membership Tier</option>
                                            </select>
                                        </div>

                                        {{-- Product Filter --}}
                                        <div class="col-md-2">
                                            <label class="form-label small">Product</label>
                                            <select class="form-control select" name="product_id">
                                                <option value="">All Products</option>
                                                @foreach ($products ?? [] as $product)
                                                    <option value="{{ $product->id }}" {{ $product_id == $product->id ? 'selected' : '' }}>
                                                        {{ $product->model }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Doctor Filter --}}
                                        <div class="col-md-2">
                                            <label class="form-label small">Doctor</label>
                                            <select class="form-control select" name="doctor_id">
                                                <option value="">All Doctors</option>
                                                @foreach ($doctors ?? [] as $doctor)
                                                    <option value="{{ $doctor->id }}" {{ $doctor_id == $doctor->id ? 'selected' : '' }}>
                                                        {{ $doctor->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Vendor Filter --}}
                                        <div class="col-md-2">
                                            <label class="form-label small">Vendor</label>
                                            <select class="form-control select" name="vendor_id">
                                                <option value="">All Vendors</option>
                                                @foreach ($vendors ?? [] as $vendor)
                                                    <option value="{{ $vendor->id }}" {{ $vendor_id == $vendor->id ? 'selected' : '' }}>
                                                        {{ $vendor->supplier_name ?? $vendor->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- From Date --}}
                                        <div class="col-md-2">
                                            <label class="form-label small">From</label>
                                            <input type="date" class="form-control" name="start_date" value="{{ $start_date ?? '' }}">
                                        </div>

                                        {{-- To Date --}}
                                        <div class="col-md-2">
                                            <label class="form-label small">To</label>
                                            <div class="input-group">
                                                <input type="date" class="form-control" name="end_date" value="{{ $end_date ?? '' }}">
                                                <div class="input-group-append">
                                                    <button type="submit" class="btn btn-info">
                                                        <i class="fas fa-search"></i>
                                                    </button>
                                                    <a href="{{ url('admin/report/sales-performance') }}" class="btn btn-primary">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    @include('admin.alert_message')

                    <div class="card-body table-responsive" id="print-area">
                        @php
                            $groupLabel = match($group_by ?? 'vendor') {
                                'vendor' => 'Vendor',
                                'staff' => 'Staff',
                                'product' => 'Product Model',
                                'doctor' => 'Doctor',
                                'category' => 'Category',
                                'membership_tier' => 'Membership Tier',
                                default => 'Group'
                            };
                        @endphp

                        <h3 class="text-center pt-2 title report-title">Sales Performance Report</h3>
                        <h5 class="text-center title pb-2 report-title">
                            <strong>Grouped By:</strong> {{ $groupLabel }}
                            @if(isset($start_date) && isset($end_date))
                                | <strong>Date Range:</strong> {{ $start_date }} To {{ $end_date }}
                            @endif
                            @if(isset($product_id))
                                @php $selectedProduct = $products->firstWhere('id', $product_id); @endphp
                                @if($selectedProduct) | <strong>Product:</strong> {{ $selectedProduct->model }} @endif
                            @endif
                        </h5>

                        {{-- Summary Cards --}}
                        @isset($reportData)
                        <div class="row mb-3">
                            <div class="col-6 col-md-3 mb-2">
                                <div class="card bg-primary text-white">
                                    <div class="card-body py-2 text-center">
                                        <h6 class="card-title small mb-1">Total Revenue</h6>
                                        <h3 class="mb-0">{{ number_format($totalRevenue ?? 0, 2) }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-2">
                                <div class="card bg-warning text-white">
                                    <div class="card-body py-2 text-center">
                                        <h6 class="card-title small mb-1">Total Discount</h6>
                                        <h3 class="mb-0">{{ number_format($totalDiscount ?? 0, 2) }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-2">
                                <div class="card bg-success text-white">
                                    <div class="card-body py-2 text-center">
                                        <h6 class="card-title small mb-1">Net Revenue</h6>
                                        <h3 class="mb-0">{{ number_format($totalNetRevenue ?? 0, 2) }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-2">
                                <div class="card bg-info text-white">
                                    <div class="card-body py-2 text-center">
                                        <h6 class="card-title small mb-1">Avg Invoice</h6>
                                        <h3 class="mb-0">{{ number_format($avgInvoice ?? 0, 2) }}</h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 50px;">SL</th>
                                    <th>{{ $groupLabel }}</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-right">Revenue</th>
                                    <th class="text-right">Discount</th>
                                    <th class="text-right">Net Revenue</th>
                                    <th class="text-right">Commission</th>
                                    <th class="text-center">Invoice Count</th>
                                    <th class="text-right">Avg Invoice</th>
                                </tr>
                            </thead>

                            <tbody>
                                @isset($reportData)
                                    @foreach ($reportData as $key => $row)
                                        <tr>
                                            <td class="text-center">{{ ++$key }}</td>
                                            <td><strong>{{ $row['group_name'] ?? 'N/A' }}</strong></td>
                                            <td class="text-center">{{ number_format($row['qty']) }}</td>
                                            <td class="text-right">{{ number_format($row['revenue'], 2) }}</td>
                                            <td class="text-right">{{ number_format($row['discount'], 2) }}</td>
                                            <td class="text-right"><strong>{{ number_format($row['net_revenue'], 2) }}</strong></td>
                                            <td class="text-right">{{ number_format($row['commission'], 2) }}</td>
                                            <td class="text-center">{{ $row['invoice_count'] }}</td>
                                            <td class="text-right">{{ number_format($row['avg_invoice'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <p class="text-muted mb-0">No sales data found</p>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>

                            @isset($reportData)
                            <tfoot>
                                <tr class="font-weight-bold bg-light">
                                    <td colspan="2" class="text-right">Total:</td>
                                    <td class="text-center">{{ number_format($totalQty ?? 0) }}</td>
                                    <td class="text-right">{{ number_format($totalRevenue ?? 0, 2) }}</td>
                                    <td class="text-right">{{ number_format($totalDiscount ?? 0, 2) }}</td>
                                    <td class="text-right">{{ number_format($totalNetRevenue ?? 0, 2) }}</td>
                                    <td class="text-right">{{ number_format($totalCommission ?? 0, 2) }}</td>
                                    <td></td>
                                    <td class="text-right">{{ number_format($avgInvoice ?? 0, 2) }}</td>
                                </tr>
                            </tfoot>
                            @endif
                        </table>

                        @isset($reportData)
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="alert alert-info mb-0">
                                    <h6 class="mb-2"><i class="fas fa-info-circle"></i> Summary</h6>
                                    <div class="row">
                                        <div class="col-md-2">
                                            <strong>Total Groups:</strong> {{ $reportData->count() }}
                                        </div>
                                        <div class="col-md-2">
                                            <strong>Total Qty Sold:</strong> {{ number_format($totalQty ?? 0) }}
                                        </div>
                                        <div class="col-md-2">
                                            <strong>Total Revenue:</strong> {{ number_format($totalRevenue ?? 0, 2) }}
                                        </div>
                                        <div class="col-md-2">
                                            <strong>Total Discount:</strong> {{ number_format($totalDiscount ?? 0, 2) }}
                                        </div>
                                        <div class="col-md-2">
                                            <strong>Total Commission:</strong> {{ number_format($totalCommission ?? 0, 2) }}
                                        </div>
                                        <div class="col-md-2">
                                            <strong>Net Revenue:</strong> {{ number_format($totalNetRevenue ?? 0, 2) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@push('scripts')
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>
    $(document).ready(function() {
        // Initialize Select2
        $('.select').select2({
            placeholder: "Select an option",
            allowClear: true,
            width: '100%'
        });

        // Print functionality
        $('.print-btn').on('click', function(e) {
            e.preventDefault();
            var printArea = $('#print-area').html();
            var originalContents = $('body').html();

            $('body').html('<div class="container">' + printArea + '</div>');
            window.print();
            $('body').html(originalContents);
            location.reload();
        });
    });
    </script>
@endpush
@endsection
