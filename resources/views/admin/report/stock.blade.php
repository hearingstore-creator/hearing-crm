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
                                <h5>Stock Report</h5>
                            </div>
                            <div class="col-md-6">
                                    <div class="text-right pb-2 print-btn">
                                        @include('admin.report.partials.export-buttons', ['formats' => ['csv']])
                                        <a href="#" class="btn-success btn-sm"><i
                                                class="fas fa-align-justify mr-1 print"></i>Print</a>
                                    </div>
                                </div>
                        </div>

                        {{-- Search Filters --}}
                        <div class="row pb-3 mt-3">
                            <div class="col-md-12">
                                <form method="GET" data-product-search="{{ url('/admin/product-search') }}">
                                    <div class="row">
                                        {{-- Product Dropdown --}}
                                        <div class="col-md-3">
                                            <label class="form-label small">Product</label>
                                            <select class="form-control product select" name="product_id">
                                                <option value="">All Products</option>
                                                @isset($selectedProduct)
                                                    <option value="{{ $selectedProduct->id }}" selected>
                                                        {{ $selectedProduct->category->category_name ?? 'N/A' }} - {{ $selectedProduct->model }}
                                                    </option>
                                                @endisset
                                            </select>
                                        </div>

                                        {{-- Category Dropdown --}}
                                        <div class="col-md-3">
                                            <label class="form-label small">Category</label>
                                            <select class="form-control select" name="category_id">
                                                <option value="">All Categories</option>
                                                @foreach ($categories ?? [] as $category)
                                                    <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                                                        {{ $category->category_name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Brand Dropdown --}}
                                        <div class="col-md-3">
                                            <label class="form-label small">Brand</label>
                                            <select class="form-control select" name="brand_id">
                                                <option value="">All Brands</option>
                                                @foreach ($brands ?? [] as $brand)
                                                    <option value="{{ $brand->id }}" {{ request('brand_id') == $brand->id ? 'selected' : '' }}>
                                                        {{ $brand->brand_name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Search & Reset Buttons --}}
                                        <div class="col-md-3">
                                            <label class="form-label small">&nbsp;</label>
                                            <div>
                                                <button class="btn btn-info" type="submit">
                                                    <i class="fas fa-search"></i> Search
                                                </button>
                                                <a href="{{ url('admin/report/stock') }}" class="btn btn-primary">
                                                    <i class="fas fa-sync-alt"></i> Reset
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                    </div>

                    @include('admin.alert_message')

                    <div class="card-body table-responsive" id="print-area">
                        <h2 class="text-center pt-3 title report-title">{{ $setting->company_name ?? 'Company Name' }}</h2>
                        <h3 class="text-center pt-1 title report-title">Stock Report</h3>
                        <h5 class="text-center title pb-2 report-title">
                            <b>Date:</b> {{ date('F jS, Y') }}
                            @if(request()->filled('product_id') || request()->filled('category_id') || request()->filled('brand_id'))
                                <br>
                                <small class="text-muted">
                                    @if(request()->filled('product_id')) Product Filtered | @endif
                                    @if(request()->filled('category_id')) Category Filtered | @endif
                                    @if(request()->filled('brand_id')) Brand Filtered @endif
                                </small>
                            @endif
                        </h5>

                        <table class="table table-bordered w-100">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width: 50px;">SL</th>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Brand</th>
                                    <th>Type</th>
                                    <th class="text-center">In Qty</th>
                                    <th class="text-center">Out Qty</th>
                                    <th class="text-center">Stock</th>
                                </tr>
                            </thead>

                            <tbody>
                                @isset($stockReports)
                                    @foreach ($stockReports as $key => $item)
                                        <tr>
                                            <td class="text-center">{{ ++$key }}</td>
                                            <td>{{ $item['product'] ?? 'N/A' }}</td>
                                            <td>{{ $item['category'] ?? 'N/A' }}</td>
                                            <td>{{ $item['brand'] ?? 'N/A' }}</td>
                                            <td>{{ $item['type'] ?? 'N/A' }}</td>
                                            <td class="text-center">{{ number_format($item['total_in']) }}</td>
                                            <td class="text-center">{{ number_format($item['total_out']) }}</td>
                                            <td class="text-center">
                                                <strong>{{ number_format($item['stock_balance']) }}</strong>
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <p class="text-muted mb-0">No stock data found</p>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>

                            @isset($stockReports)
                            <tfoot>
                                <tr class="font-weight-bold bg-light">
                                    <td colspan="5" class="text-right">Total:</td>
                                    <td class="text-center">{{ number_format($totalIn ?? 0) }}</td>
                                    <td class="text-center">{{ number_format($totalOut ?? 0) }}</td>
                                    <td class="text-center">{{ number_format($totalBalance ?? 0) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                            @endif
                        </table>

                        @isset($stockReports)
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="alert alert-info mb-0">
                                    <h6 class="mb-2"><i class="fas fa-info-circle"></i> Summary</h6>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <strong>Total Products:</strong> {{ $stockReports->count() }}
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Total In:</strong> {{ number_format($totalIn ?? 0) }}
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Total Out:</strong> {{ number_format($totalOut ?? 0) }}
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Total Balance:</strong> {{ number_format($totalBalance ?? 0) }}
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

        // Product search with AJAX
        $('.product').select2({
            placeholder: "Search Product...",
            allowClear: true,
            ajax: {
                url: $('form[data-product-search]').data('product-search'),
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        search: params.term
                    };
                },
                processResults: function (data) {
                    return {
                        results: data
                    };
                },
                cache: true
            }
        });
    });
    </script>
@endpush
@endsection
