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
                                    <h5> Add Salary Setup</h5>
                                </div>
                                <div class="col-md-6 text-right">
                                    <a href="{{ url('admin/salary') }}" class="btn-primary btn-cs-primary  btn-sm"><i
                                            class="fas fa-align-justify mr-1"></i>Salary List</a>
                                </div>
                            </div>
                        </div>

                        @include('admin.alert_message')

                        <div class="card-body">
                            <form action="{{ url('admin/salary') }}" enctype="multipart/form-data" method="post">
                                @csrf
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group row">
                                            <label for="employee_id" class="col-lg-3 col-form-label">Employee <i
                                                    class="text-danger">*</i>:</label>
                                            <div class="col-md-5">
                                                <select class="form-control employee" name="employee_id"
                                                    id="employee_id"
                                                    data-employee-search="{{ route('employee.search') }}"
                                                    data-employee-salary="{{ url('admin/employee-salary') }}" required>
                                                    <option value="">Select</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="month_year" class="col-md-3 col-form-label">Month Year <i
                                                    class="text-danger">*</i>:</label>
                                            <div class="col-md-5">
                                                <input type="month" name="month_year" value=""
                                                    class="form-control" id="month_year" required>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="salary_type" class="col-sm-3 col-form-label">Salary Type:</label>
                                            <div class="col-sm-5">
                                                <input type="text" name="salary_type" value="" class="form-control"
                                                    id="salary_type" readonly>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="basic_salary" class="col-sm-3 col-form-label">Basic <i
                                                    class="text-danger">*</i>:</label>
                                            <div class="col-sm-5">
                                                <input type="text" name="basic_salary" value=""
                                                    class="form-control" id="basic_salary" readonly>
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="earning" class="col-sm-3 col-form-label">Earning/Bonus:</label>
                                            <div class="col-sm-5">
                                                <input type="text" name="earning" value="" class="form-control"
                                                    id="earning">
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="deduction" class="col-sm-3 col-form-label">Deduction:</label>
                                            <div class="col-sm-5">
                                                <input type="text" name="deduction" value=""
                                                    class="form-control" id="deduction">
                                            </div>
                                        </div>

                                        <div class="form-group row">
                                            <label for="gross_salary" class="col-sm-3 col-form-label">Gross Salary:</label>
                                            <div class="col-sm-5">
                                                <input type="text" class="form-control" name="gross_salary" value=""
                                                    id="gross_salary" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group row">
                                    <div class="col-md-6 text-right">
                                    </div>
                                    <div class="col-md-2 text-right">
                                        <button type="submit" class="btn btn-success">Save</button>
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
