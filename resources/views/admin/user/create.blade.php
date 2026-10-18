@extends('admin.layouts.master')
@section('page_title', 'Add User')
@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
@endpush
@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-user-plus" style="color: var(--accent-500); margin-right: 8px;"></i>Add User</h1>
            <p>Create a new system user</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/user') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>User List</a>
        </div>
    </div>
    @include('admin.alert_message')
    <form action="{{ url('admin/user') }}" enctype="multipart/form-data" method="POST">
        @csrf
        <div class="crm-form-section crm-animate-in delay-1">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--accent-500);"><i class="fas fa-user"></i></div>
                <h6>User Information</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name">Name <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" class="form-control" name="name" id="name" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email">Email <span style="color: var(--danger-500);">*</span></label>
                            <input type="email" class="form-control" name="email" id="email" required>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="password">Password <span style="color: var(--danger-500);">*</span></label>
                            <input type="password" class="form-control" name="password" id="password" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="roles">Roles <span style="color: var(--danger-500);">*</span></label>
                            <select name="roles[]" class="form-control select" id="roles" multiple required>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">You can assign multiple roles to a user.</small>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        @include('admin.partials.branch_select', ['selected' => null])
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="details">Remarks</label>
                            <textarea class="form-control" name="details" id="details" rows="3"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="crm-form-submit-bar">
            <a href="{{ url('admin/user') }}" class="btn btn-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Save</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
@endpush
