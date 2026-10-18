@extends('admin.layouts.master')
@section('page_title', 'Edit Leaf Settings')
@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush
@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-leaf" style="color: var(--accent-500); margin-right: 8px;"></i>Edit Leaf Settings</h1>
            <p>Update leaf configuration</p>
        </div>
        <div class="header-actions">
            @can('permission', 'leaf_settings.index')
            <a href="{{ url('admin/leaf-settings') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>Leaf Settings List</a>
            @endcan
        </div>
    </div>
    @include('admin.alert_message')
    <form action="{{ route('leaf-settings.update', $leafSettings->id) }}" enctype="multipart/form-data" method="post">
        @csrf
        @method('put')
        <div class="crm-form-section crm-animate-in delay-1">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--accent-500);"><i class="fas fa-leaf"></i></div>
                <h6>Leaf Settings Information</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="leaf_type">Leaf Type <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" class="form-control" id="leaf_type" value="{{ $leafSettings->leaf_type }}" placeholder="Leaf Type" name="leaf_type" autocomplete="off" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="total_number_per_box">Total Number Per Box <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" class="form-control" id="total_number_per_box" value="{{ $leafSettings->total_number_per_box }}" placeholder="Total Number Per Box" name="total_number_per_box" autocomplete="off" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Status <span style="color: var(--danger-500);">*</span></label>
                            <div class="mt-1">
                                <label class="radio-inline my-2"><input type="radio" name="status" value="1" {{ $leafSettings->status == 1 ? 'checked' : '' }}> Active</label>
                                <label class="radio-inline my-2 ml-3"><input type="radio" name="status" value="0" {{ $leafSettings->status == 0 ? 'checked' : '' }}> Inactive</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="crm-form-submit-bar">
            <a href="{{ url('admin/leaf-settings') }}" class="btn btn-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Update</button>
        </div>
    </form>
</div>
@endsection
