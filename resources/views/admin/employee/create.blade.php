@extends('admin.layouts.master')
@section('page_title', 'Add Employee')
@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush
@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-user-tie" style="color: var(--accent-500); margin-right: 8px;"></i>Add Employee</h1>
            <p>Register a new employee</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/employee') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>Employee List</a>
        </div>
    </div>
    @include('admin.alert_message')
    <form action="{{ url('admin/employee') }}" enctype="multipart/form-data" method="post">
        @csrf
        <div class="crm-form-section crm-animate-in delay-1">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--accent-500);"><i class="fas fa-user-tie"></i></div>
                <h6>Personal Information</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name">Name <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" class="form-control" id="name" placeholder="Name" name="name" required autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="mobile_no">Mobile No <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" class="form-control" id="mobile_no" placeholder="Mobile No" required name="mobile_no" autocomplete="off">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="text" class="form-control" id="phone" placeholder="Phone" name="phone">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" class="form-control" id="email" placeholder="Email" name="email">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="blood_group">Blood Group</label>
                            <select class="form-control" name="blood_group" id="blood_group">
                                <option value="">Select Blood Group</option>
                                @foreach ($bloodGroup as $val)
                                    <option value="{{ $val }}">{{ $val }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="image">Image</label>
                            <input type="file" class="form-control" id="image" name="image">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="crm-form-section crm-animate-in delay-2">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--success-500);"><i class="fas fa-money-check-alt"></i></div>
                <h6>Employment Details</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="designation_id">Designation</label>
                            <select class="form-control" name="designation_id" id="designation_id">
                                <option value="">Salary Designation</option>
                                @foreach ($designations as $val)
                                    <option value="{{ $val->id }}">{{ $val->designation_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="salary_type">Salary Type <span style="color: var(--danger-500);">*</span></label>
                            <select class="form-control" name="salary_type" id="salary_type" required>
                                <option value="">Salary Type</option>
                                @foreach ($salaryType as $val)
                                    <option value="{{ $val }}">{{ $val }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="salary">Salary</label>
                            <input type="text" class="form-control" id="salary" placeholder="Salary" name="salary">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="joining_date">Joining Date</label>
                            <input type="date" class="form-control" id="joining_date" name="joining_date">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="crm-form-submit-bar">
            <a href="{{ url('admin/employee') }}" class="btn btn-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Save</button>
        </div>
    </form>
</div>
@endsection
