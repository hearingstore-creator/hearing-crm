@extends('admin.layouts.master')
@section('page_title', 'Add Branch')
@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush
@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-store" style="color: var(--accent-500); margin-right: 8px;"></i>Add Branch</h1>
            <p>Register a new branch (outlet)</p>
        </div>
        <div class="header-actions">
            @can('permission', 'vendor_list.index')
            <a href="{{ url('admin/vendors') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>Branch List</a>
            @endcan
        </div>
    </div>
    @include('admin.alert_message')
    <form action="{{ url('admin/vendors') }}" enctype="multipart/form-data" method="post">
        @csrf
        <div class="crm-form-section crm-animate-in delay-1">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--accent-500);"><i class="fas fa-store"></i></div>
                <h6>Branch Information</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name">Name <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" placeholder="Branch Name" autocomplete="off" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="mobile_no">Mobile No <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" class="form-control" id="mobile_no" name="mobile_no" placeholder="Mobile No" autocomplete="off" required>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email">Email <span style="color: var(--danger-500);">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Email Address" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="password">Password <span style="color: var(--danger-500);">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @include('admin.vendor._branding')
        <div class="crm-form-submit-bar">
            <a href="{{ url('admin/vendors') }}" class="btn btn-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Save</button>
        </div>
    </form>
</div>
@endsection
