@extends('admin.layouts.master')
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
                                    <h5>Edit Employee </h5>
                                </div>
                                <div class="col-md-6 text-right">
                                    <a href="{{ url('admin/employee') }}" class="btn-primary btn-cs-primary  btn-sm"><i
                                            class="fas fa-align-justify mr-1"></i>Employee List</a>
                                </div>
                            </div>
                        </div>

                        @include('admin.alert_message')

                        <div class="card-body">
                            <form action="{{ route('employee.update', $employee) }}" enctype="multipart/form-data"
                                method="post">
                                @csrf
                                @method('put')
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group row">
                                            <label for="name" class="col-sm-3 col-form-label">Name: <i
                                                    class="text-danger"> * </i>:</label>
                                            <div class="col-sm-9">
                                                <input type="text" class="form-control" id="name"
                                                    value="{{ $employee->name }}" placeholder="Name" name="name" required
                                                    autocomplete="off">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="phone" class="col-sm-3 col-form-label">Phone: </label>
                                            <div class="col-sm-9">
                                                <input type="text" class="form-control" id="phone"
                                                    value="{{ $employee->phone }}" placeholder="Phone" name="phone">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="address" class="col-sm-3 col-form-label">Address:</label>
                                            <div class="col-sm-9">
                                                <textarea class="form-control" id="address" name="address">{{ $employee->address }} </textarea>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="image" class="col-sm-3 col-form-label">Image:</label>
                                            <div class="col-sm-9">
                                                <input type="file" class="form-control" id="image"
                                                    placeholder="image" name="image">
                                                @if (!empty($employee->image))
                                                    <img src="{{ asset($employee->image) }}" width="50%">
                                                @endif
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="joining_date" class="col-sm-3 col-form-label">Joining Date:</label>
                                            <div class="col-sm-9">
                                                <input type="date" class="form-control" id="joining_date"
                                                    value="{{ $employee->joining_date }}" placeholder=""
                                                    name="joining_date">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group row">
                                            <label for="mobile_no" class="col-sm-3 col-form-label">Mobile No <i
                                                    class="text-danger"> * </i>:</label>
                                            <div class="col-sm-9">
                                                <input type="text" class="form-control" id="mobile_no"
                                                    value="{{ $employee->mobile_no }}" placeholder="Mobile No" required
                                                    name="mobile_no" autocomplete="off">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="email" class="col-sm-3 col-form-label">Email:</label>
                                            <div class="col-sm-9">
                                                <input type="email" class="form-control" id="email"
                                                    value="{{ $employee->email }}" placeholder="Email" name="email">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="designation_id" class="col-sm-3 col-form-label">Designation:</label>
                                            <div class="col-sm-9">
                                                <select class="form-control" name="designation_id" id="designation_id">
                                                    <option value=""> Salary Designation</option>
                                                    @foreach ($designations as $val)
                                                        <option value="{{ $val->id }}"
                                                            {{ $val->id == $employee->designation_id ? 'selected' : '' }}>
                                                            {{ $val->designation_name }} </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="salary_type" class="col-sm-3 col-form-label">Salary Type <i
                                                    class="text-danger"> * </i>:</label>
                                            <div class="col-sm-9">
                                                <select class="form-control" name="salary_type" id="salary_type"
                                                    required>
                                                    <option value=""> Salary Type</option>
                                                    @foreach ($salaryType as $val)
                                                        <option value="{{ $val }}"
                                                            {{ $val == $employee->salary_type ? 'selected' : '' }}>
                                                            {{ $val }} </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="salary" class="col-sm-3 col-form-label">Salary:</label>
                                            <div class="col-sm-9">
                                                <input type="text" class="form-control" id="salary"
                                                    value="{{ $employee->salary }}" placeholder="Salary" name="salary">
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="blood_group" class="col-sm-3 col-form-label">Blood Group:</label>
                                            <div class="col-sm-9">
                                                <select class="form-control" name="blood_group" id="blood_group">
                                                    <option value=""> Select Blood Group</option>
                                                    @foreach ($bloodGroup as $val)
                                                        <option value="{{ $val }}"
                                                            {{ $val == $employee->blood_group ? 'selected' : '' }}>
                                                            {{ $val }} </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group row">
                                            <label for="status" class="col-md-3 col-form-label">Status:</label>
                                            <div class="col-md-9">
                                                <div class="">
                                                    <label class="radio-inline my-2">
                                                        <input type="radio" name="status" value="1"
                                                            checked="checked"
                                                            {{ $employee->status == 1 ? 'checked' : '' }}>
                                                        Active
                                                    </label>
                                                    <label class="radio-inline my-2">
                                                        <input type="radio" name="status" value="0"
                                                            {{ $employee->status == 0 ? 'checked' : '' }}>
                                                        Inactive
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <div class="col-md-6 text-right">
                                    </div>
                                    <div class="col-md-6 text-right">
                                        <div class="">
                                            <button type="submit" class="btn btn-success">
                                                Update</button>
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
