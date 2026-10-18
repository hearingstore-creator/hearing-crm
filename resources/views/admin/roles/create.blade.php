@extends('admin.layouts.master')
@section('page_title', 'Add Role')
@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush
@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-user-shield" style="color: var(--accent-500); margin-right: 8px;"></i>Add Role</h1>
            <p>Create a new user role</p>
        </div>
        <div class="header-actions">
            <a href="{{url('admin/roles')}}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>Role List</a>
        </div>
    </div>
    @include('admin.alert_message')
    <form action="{{url('admin/roles')}}" enctype="multipart/form-data" method="post">
        @csrf
        <div class="crm-form-section crm-animate-in delay-1">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--accent-500);"><i class="fas fa-user-shield"></i></div>
                <h6>Role Information</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name">Role Name <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" class="form-control" id="name" placeholder="Role Name" name="name" autocomplete="off" required>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="crm-form-submit-bar">
            <a href="{{url('admin/roles')}}" class="btn btn-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Save</button>
        </div>
    </form>
</div>
@endsection
