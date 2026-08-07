@extends('admin.layouts.master')
@push('css')
    <!-- Select2 -->
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
                                    <h5>Salary Payment</h5>
                                </div>
                                <div class="col-md-6 text-right">
                                    <a href="{{ url('admin/salary-payment') }}"
                                        class="btn-primary btn-cs-primary  btn-sm"><i
                                            class="fas fa-align-justify mr-1"></i>Salary Payment List</a>
                                </div>
                            </div>
                        </div>

                        @include('admin.alert_message')

                        <div class="card-body">
                            <form action="{{ url('admin/salary-payment') }}" enctype="multipart/form-data" method="post">
                                @csrf
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group row">
                                            <label for="employee_id" class="col-lg-3 col-form-label">Employee <i
                                                    class="text-danger"> * </i>:</label>
                                            <div class="col-md-5">
                                                <div class="">
                                                    <select class="form-control employee" name="employee_id"
                                                        id="employee_id"
                                                        data-employee-search="{{ route('employee.search') }}"
                                                        data-employee-salary="{{ url('admin/employee-salary') }}" required>
                                                        <option value="">Select</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="month_year" class="col-md-3 col-form-label">Month Year <i
                                                    class="text-danger"> * </i>:</label>
                                            <div class="col-md-5">
                                                <div class="">
                                                    <input type="month" name="month_year" value=""
                                                        class="form-control" id="month_year" required>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="due" class="col-sm-3 col-form-label">Due:</label>
                                            <div class="col-sm-5">
                                                <input type="text" name="due" value="" class="form-control"
                                                    id="due" readonly>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="paid_amount" class="col-sm-3 col-form-label">Payment:</label>
                                            <div class="col-sm-5">
                                                <input type="text" name="paid_amount" value="" class="form-control"
                                                    id="paid_amount" required>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="details" class="col-sm-3 col-form-label">Details:</label>
                                            <div class="col-sm-5">
                                                <textarea name="details" class="form-control" id="details"> </textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group row">
                                    <div class="col-md-6 text-right">
                                    </div>
                                    <div class="col-md-2 text-right">
                                        <div class="">
                                            <button type="submit" class="btn btn-success">
                                                Save</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
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
    <script src="{{ asset('assets/salary.js') }}"></script>
@endpush
