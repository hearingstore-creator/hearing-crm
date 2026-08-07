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
                                        <a href="#" class="btn-success btn-sm"><i
                                                class="fas fa-align-justify mr-1 print"></i>Print</a>
                                    </div>
                                </div>
                        </div>

                        {{-- Search Filters --}}
                        <div class="row pb-3 mt-3">
                            <div class="col-md-12">
                                <form method="GET"
                                 data-product-search="{{ url('/admin/product-search') }}"
                                 >
                                    <div class="row">
                                        {{-- Product Dropdown --}}
                                        <div class="col-md-4">
                                            <select class="form-control product" name="product_id">
                                                <option value="">All Products</option>
                                                   @isset($selectedProduct)
                                                        <option value="{{ $selectedProduct->id }}" selected>
                                                            {{ $selectedProduct->category->category_name }} - {{ $selectedProduct->model }}
                                                        </option>
                                                   @endisset
                                            </select>
                                        </div>

                                        <div class="col-md-2">
                                            <button class="btn btn-info" type="submit">
                                                <i class="fas fa-search"></i> 
                                            </button>
                                            <a href="{{ url('admin/report/stock') }}" class="btn btn-primary">
                                                <i class="fas fa-sync-alt"></i>
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                    </div>

                    @include('admin.alert_message')

                    <div class="card-body table-responsive" id="print-area">
                        <h2 class="text-center pt-3 title report-title"> {{ $setting->company_name }}</h2>
                        <h3 class="text-center pt-1 title report-title"> Stock Report</h3>
                        <h5 class="text-center title pb-2 report-title"><b>Date:</b> {{ date('F jS, Y') }}</h5>
                        <table class="table table-bordered w-100">
                            <thead>
                                <tr>
                                    <th>SL</th>
                                    <th>Product Model</th>
                                    <th>Category</th>
                                    <th>In Qty</th>
                                    <th>Out Qty</th>
                                    <th>Stock</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($stockReports ?? [] as $key => $item)
                                <tr>
                                    <td>{{ ++$key }}</td>
                                    <td>{{ $item->model ?? null }}</td>
                                    <td>{{ $item->category_name ?? null }}</td>
                                    <td>{{ number_format($item->in_qty, 2) }}</td>
                                    <td>{{ number_format($item->out_qty, 2) }}</td>
                                    <td>{{ number_format($item->stock, 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>

                        </table>

                        @if(empty($stockReports))
                            <div class="text-center py-4">
                                <p class="text-muted">No stock data found</p>
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
