@extends('admin.layouts.master')

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
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
                                    <h5>Purchase & Stock Report</h5>
                                </div>
                                <div class="col-md-6 text-right">
                                    <a href="{{ route('report.purchase-stock.export', ['type' => 'raw', 'format' => 'xlsx']) }}?{{ http_build_query(request()->query()) }}" class="btn btn-success">
                                        <i class="fas fa-file-excel"></i> Export Excel
                                    </a>
                                </div>
                            </div>
                            <div class="filter-form pt-2">
                                <form class="form" action="{{ route('report.purchase-stock') }}" method="get" id="filterForm">
                                    <div class="row">
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="">Vendor</label>
                                                <select name="vendor_id" class="form-control select" id="vendor_id">
                                                    <option value="">All Vendors</option>
                                                    @foreach ($vendors ?? [] as $vendor)
                                                        <option value="{{ $vendor->id }}"
                                                            @isset($vendorId) {{ $vendorId == $vendor->id ? 'selected' : '' }} @endisset>
                                                            {{ $vendor->supplier_name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="">Product</label>
                                                <select name="product_id" class="form-control select" id="product_id">
                                                    <option value="">All Products</option>
                                                    @foreach ($products ?? [] as $product)
                                                        <option value="{{ $product->id }}"
                                                            @isset($productId) {{ $productId == $product->id ? 'selected' : '' }} @endisset>
                                                            {{ $product->model ?? 'N/A' }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="">From</label>
                                                <input type="date" autocomplete="off" name="start_date"
                                                    value="{{ $startDate ?? '' }}" class="form-control input-sm">
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="">To</label>
                                                <input type="date" autocomplete="OFF" name="end_date"
                                                    value="{{ $endDate ?? '' }}" class="form-control input-sm">
                                            </div>
                                        </div>
                                        <div class="col-md-2 submit">
                                            <div class="form-group">
                                                <input type="submit" class="btn btn-success btn-sm" value="Submit">
                                                <a href="{{ url('admin/report/purchase-stock') }}" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-sync-alt"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="card-body" id="print-area">
                            <div class="text-center pb-2">
                                <h3>Purchase & Stock Report</h3>
                                @if(isset($startDate) && isset($endDate))
                                    <p class="text-muted">
                                        <strong>Date Range:</strong> {{ $startDate }} To {{ $endDate }}
                                        @if(isset($vendorId))
                                            @php $selectedVendor = $vendors->firstWhere('id', $vendorId); @endphp
                                            @if($selectedVendor)
                                                | <strong>Vendor:</strong> {{ $selectedVendor->supplier_name }}
                                            @endif
                                        @endif
                                        @if(isset($productId))
                                            @php $selectedProduct = $products->firstWhere('id', $productId); @endphp
                                            @if($selectedProduct)
                                                | <strong>Product:</strong> {{ $selectedProduct->model }}
                                            @endif
                                        @endif
                                    </p>
                                @endif
                            </div>

                            @php
                                $totalQtyPurchased = 0;
                                $totalPurchaseValue = 0;
                                $totalQtySold = 0;
                                $totalStockBalance = 0;
                            @endphp

                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th class="text-center">SL</th>
                                            <th class="text-center">Vendor</th>
                                            <th class="text-center">Product</th>
                                            <th class="text-center">Category</th>
                                            <th class="text-center">Brand</th>
                                            <th class="text-center">Qty Purchased</th>
                                            <th class="text-center">Purchase Value</th>
                                            <th class="text-center">Qty Sold</th>
                                            <th class="text-center">Stock Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if(isset($reportData) && count($reportData) > 0)
                                            @foreach ($reportData as $key => $row)
                                                <tr>
                                                    <td class="text-center">{{ ++$key }}</td>
                                                    <td>{{ $row['vendor'] }}</td>
                                                    <td>{{ $row['product'] }}</td>
                                                    <td>{{ $row['category'] }}</td>
                                                    <td>{{ $row['brand'] }}</td>
                                                    <td class="text-center">{{ $row['qty_purchased'] }}</td>
                                                    <td class="text-right">{{ $row['purchase_value'] }}</td>
                                                    <td class="text-center">{{ $row['qty_sold'] }}</td>
                                                    <td class="text-center">{{ $row['stock_balance'] }}</td>
                                                </tr>

                                                @php
                                                    $totalQtyPurchased += $row['qty_purchased'];
                                                    $totalPurchaseValue += floatval(str_replace(',', '', $row['purchase_value']));
                                                    $totalQtySold += $row['qty_sold'];
                                                    $totalStockBalance += $row['stock_balance'];
                                                @endphp
                                            @endforeach
                                        @else
                                            <tr>
                                                <td colspan="9" class="text-center">No data found</td>
                                            </tr>
                                        @endif
                                    </tbody>
                                    @if(isset($reportData) && count($reportData) > 0)
                                    <tfoot>
                                        <tr class="font-weight-bold">
                                            <td colspan="5" class="text-right">Total:</td>
                                            <td class="text-center">{{ $totalQtyPurchased }}</td>
                                            <td class="text-right">{{ number_format($totalPurchaseValue, 2) }}</td>
                                            <td class="text-center">{{ $totalQtySold }}</td>
                                            <td class="text-center">{{ $totalStockBalance }}</td>
                                        </tr>
                                    </tfoot>
                                    @endif
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /.container-fluid -->
    </section>
    <!-- /.content -->
@endsection

@push('scripts')
    <!-- Select2 -->
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            $('.select').select2({
                placeholder: 'Select an option',
                allowClear: true
            });
        });
    </script>
@endpush
