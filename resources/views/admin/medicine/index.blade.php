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
                                    <h5>Medicine List</h5>
                                </div>
                                @can('permission', 'medicine.create')
                                <div class="col-md-6 text-right">
                                    <a href="{{ url('admin/medicine/create') }}" class="btn-primary btn-cs-primary btn-sm">
                                        <i class="fas fa-plus mr-1"></i>Add Medicine
                                    </a>
                                </div>
                                @endcan
                            </div>
                        </div>

                        @include('admin.alert_message')

                        <div class="card-body">
                            <table class="table table-bordered table-striped yajra-datatable"
                                   data-datatable="medicine-list"
                                   data-ajax-url="{{ route('medicine.list') }}">
                                <thead>
                                    <tr>
                                        <th>SL</th>
                                        <th>Medicine Name</th>
                                        <th>Generic Name</th>
                                        <th>Category</th>
                                        <th>Manufacturer</th>
                                        <th>Shelf</th>
                                        <th>Price</th>
                                        <th>Cost</th>
                                        <th>Strength</th>
                                        <th>Image</th>
                                        <th>Status</th>
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
@endsection

@push('scripts')
@include('admin.partials.datatables-scripts')
@endpush
