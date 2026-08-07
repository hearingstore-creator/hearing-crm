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
                                    <h5>Return To Manufacturer List</h5>
                                </div>
                                <div class="col-md-6 text-right">
                                    <a href="{{ url('admin/return/add-return') }}" class="btn-primary btn-cs-primary btn-sm">
                                        <i class="fas fa-plus mr-1"></i>Add Return
                                    </a>
                                </div>
                            </div>
                        </div>

                        @include('admin.alert_message')

                        <div class="card-body">
                            <table class="table table-bordered table-striped yajra-datatable"
                                   data-datatable="manufacturer-return-list"
                                   data-ajax-url="{{ route('manufacturer-return.list') }}">
                                <thead>
                                    <tr>
                                        <th>SL</th>
                                        <th>Purchase Invoice No</th>
                                        <th>Manufacturer Name</th>
                                        <th>Date</th>
                                        <th>Total Amount</th>
                                        <th>Action</th>
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
