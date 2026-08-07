@extends('admin.layouts.master')

@push('css')
    <!-- DataTables -->
    @include('admin.partials.datatables-css')
@endpush

@section('content')
    <!-- Main content -->
    <section class="content medicine-stock pt-3">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5> Stock Report</h5>

                                </div>
                                <div class="col-md-6">
                                    <div class="text-right pb-2 print-btn">
                                        <a href="#" class="btn-success btn-sm"><i
                                                class="fas fa-align-justify mr-1 print"></i>Print</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @include('admin.alert_message')

                        <div class="card-body" id="print-area">
                            <h2 class="text-center pt-3 title report-title"> {{ $setting->company_name }}</h2>
                            <h3 class="text-center pt-1 title report-title"> Stock Report</h3>
                            <h5 class="text-center title pb-2 report-title"><b>Date:</b> {{ date('F jS, Y') }}</h5>
                            <table class="table table-bordered table-striped yajra-datatable"
                                data-datatable="stock-report-list"
                                data-ajax-url="{{ url('admin/report/stock-list?stockType=') }}
                                 {{ request('stockType', '') }}">
                                <thead>
                                    <tr>
                                        <th>SL</th>
                                        <th>Medicine Name</th>
                                        <th>Manufacturer</th>
                                        <th>In Qty</th>
                                        <th>Out Qty</th>
                                        <th>Stock</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /.container-fluid -->
    </section>
    <!-- /.content -->
    @push('scripts')
        @include('admin.partials.datatables-scripts')
    @endpush
@endsection
