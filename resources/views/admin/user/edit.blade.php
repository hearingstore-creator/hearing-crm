@extends('admin.layouts.master')
@section('page_title', 'Edit User')
@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
@endpush
@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-user-edit" style="color: var(--accent-500); margin-right: 8px;"></i>Edit User</h1>
            <p>Update user account details</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/user') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>User List</a>
        </div>
    </div>
    @include('admin.alert_message')
    <form action="{{ route('user.update', $user->id) }}" enctype="multipart/form-data" method="POST">
        @csrf
        @method('PUT')
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
                            <input type="text" class="form-control" name="name" id="name" value="{{ old('name', $user->name) }}" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email">Email <span style="color: var(--danger-500);">*</span></label>
                            <input type="email" class="form-control" name="email" id="email" value="{{ old('email', $user->email) }}" required>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" class="form-control" name="password" id="password" placeholder="Leave blank if unchanged" minlength="8" autocomplete="new-password">
                            <small class="form-text text-muted">At least 8 characters.</small>
                        </div>
                        <div class="form-group">
                            <label for="password_confirmation">Confirm New Password</label>
                            <input type="password" class="form-control" name="password_confirmation" id="password_confirmation" minlength="8" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="col-md-6">
                        @if(! $user->isVendor())
                        <div class="form-group">
                            <label for="roles">Roles <span style="color: var(--danger-500);">*</span></label>
                            <select name="roles[]" class="form-control select" id="roles" multiple required>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}" 
                                        {{ in_array($role->id, $userRoles) ? 'selected' : '' }}>
                                        {{ $role->name }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">You can assign multiple roles to a user.</small>
                        </div>
                        @endif
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="details">Remarks</label>
                            <textarea class="form-control" name="details" id="details" rows="3">{{ old('details', $user->details) }}</textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Status <span style="color: var(--danger-500);">*</span></label>
                            <div class="mt-1">
                                <label class="radio-inline my-2"><input type="radio" name="status" value="1" {{ $user->status == 1 ? 'checked' : '' }}> Active</label>
                                <label class="radio-inline my-2 ml-3"><input type="radio" name="status" value="0" {{ $user->status == 0 ? 'checked' : '' }}> Inactive</label>
                            </div>
                        </div>
                    </div>
                </div>
                @if (! $user->isVendor() && ! $user->isSuperAdmin())
                <div class="row">
                    <div class="col-md-6">
                        @include('admin.partials.branch_select', ['selected' => $user->vendor_id])
                    </div>
                </div>
                @endif
            </div>
        </div>
        <div class="crm-form-submit-bar">
            <a href="{{ url('admin/user') }}" class="btn btn-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Update</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
@endpush
