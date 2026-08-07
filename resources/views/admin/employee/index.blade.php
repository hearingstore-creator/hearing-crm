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
                                    <h5> Employee List</h5>
                                </div>
                                <div class="col-md-6 text-right">
                                    <a href="{{ url('admin/employee/create') }}" class="btn-primary btn-cs-primary btn-sm"><i
                                            class="fas fa-plus mr-1"></i>Add Employee</a>
                                </div>
                            </div>
                        </div>

                        @include('admin.alert_message')

                        <div class="card-body">
                            <table class="table table-bordered table-striped yajra-datatable" data-datatable="employee-list"
                                data-ajax-url="{{ route('employee.list') }}">
                                <thead>
                                    <tr>
                                        <th>SL</th>
                                        <th>Name</th>
                                        <th>Designation</th>
                                        <th>Email</th>
                                        <th>Mobile No</th>
                                        <th>Phone</th>
                                        <th>Blood Group</th>
                                        <th>Salary</th>
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
