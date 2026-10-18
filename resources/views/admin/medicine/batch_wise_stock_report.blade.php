@extends('admin.layouts.master')

@push('css')
    <!-- DataTables -->
    @include('admin.partials.datatables-css')
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
                                    <h5>Batch Wise Stock Report</h5>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-right pb-2">
                                        <a href="#" class="btn-success btn-sm print-btn"><i
                                                class="fas fa-align-justify mr-1 print"></i>Print</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @include('admin.alert_message')

                        <div class="card-body" id="print-area">
                            <h2 class="text-center pt-3 title report-title"> {{ $setting->company_name }}</h2>
                            <h3 class="text-center pt-1 title report-title"> Batch Wise Stock Report</h3>
                            <h5 class="text-center title pb-2 report-title"><b>Date:</b> {{ date('F jS, Y') }}</h5>
                            <table class="table table-bordered table-striped yajra-datatable"
                                data-datatable="batch-wise-stock-report">
                                <thead>
                                    <tr>
                                        <th class="text-center">Product Name</th>
                                        <th class="text-center">Brand</th>
                                        <th class="text-center">Batch No</th>
                                        <th class="text-center">Stock</th>
                                        <th class="text-center">Expiry Date</th>
                                    </tr>
                                </thead>
                                <tbody>

                                    @foreach ($batchWiseStock as $item)
                                        @php
                                            $product = $item->product ?? $item->medicine ?? null;
                                            $brand = $product?->brand ?? $product?->manufacturer ?? null;
                                        @endphp
                                        @if (!empty($product))
                                            <tr>
                                                <td class="text-center">{{ $product->product_name ?? $product->medicine_name ?? '-' }}</td>
                                                <td class="text-center">{{ $brand->brand_name ?? $brand->manufacturer_name ?? '-' }}</td>
                                                <td class="text-center">{{ $item->batch_no }}</td>
                                                <td class="text-center">{{ $item->stock }}</td>
                                                <td class="text-center">{{ $item->expiry_date ?? '-' }}</td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>

                            {{ $batchWiseStock->appends(request()->input())->links() }}
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
    @include('admin.partials.datatables-scripts')
@endpush
