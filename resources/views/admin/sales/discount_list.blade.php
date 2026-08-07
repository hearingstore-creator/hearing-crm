@extends('admin.layouts.master')

@push('css')
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
                                    <h5>Discount List</h5>
                                </div>
                                @can('permission', 'add_sale.create')
                                <div class="col-md-6 text-right">
                                    <a href="{{ url('admin/sale/create') }}" class="btn-primary btn-cs-primary btn-sm">
                                        <i class="fas fa-plus mr-1"></i>Add Sale
                                    </a>
                                </div>
                                @endcan
                            </div>
                        </div>

                        @include('admin.alert_message')

                        <div class="card-body">
                            <table class="table table-bordered table-striped yajra-datatable" 
                                   data-datatable="discount-list" 
                                   data-ajax-url="{{ route('sale.discountList') }}">
                                <thead>
                                    <tr>
                                        <th>SL</th>
                                        <th>Sale Invoice No</th>
                                        <th>Customer Name</th>
                                        <th>Date</th>
                                        <th>Total Amount</th>
                                        <th>Discount</th>
                                        <th>Paid</th>
                                        <th>Due</th>
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
    </section>
@endsection

@push('scripts')
    @include('admin.partials.datatables-scripts')
@endpush
