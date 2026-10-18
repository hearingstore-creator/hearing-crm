@extends('admin.layouts.master')
@section('page_title', 'Edit Type')
@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush
@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-layer-group" style="color: var(--accent-500); margin-right: 8px;"></i>Edit Type</h1>
            <p>Update type information</p>
        </div>
        <div class="header-actions">
            @can( 'permission', 'type.index')
            <a href="{{ url('admin/type') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>Type List</a>
            @endcan
        </div>
    </div>
    @include('admin.alert_message')
    <form action="{{ route('type.update', $type->id) }}" enctype="multipart/form-data" method="post">
        @csrf
        @method('put')
        <div class="crm-form-section crm-animate-in delay-1">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--accent-500);"><i class="fas fa-layer-group"></i></div>
                <h6>Type Information</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="type_name">Type Name <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" class="form-control" id="type_name" value="{{ $type->type_name }}" placeholder="Type Name" name="type_name" autocomplete="off" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Status <span style="color: var(--danger-500);">*</span></label>
                            <div class="mt-1">
                                <label class="radio-inline my-2"><input type="radio" name="status" value="1" {{ $type->status == 1 ? 'checked' : '' }}> Active</label>
                                <label class="radio-inline my-2 ml-3"><input type="radio" name="status" value="0" {{ $type->status == 0 ? 'checked' : '' }}> Inactive</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="crm-form-submit-bar">
            <a href="{{ url('admin/type') }}" class="btn btn-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Update</button>
        </div>
    </form>
</div>
@endsection