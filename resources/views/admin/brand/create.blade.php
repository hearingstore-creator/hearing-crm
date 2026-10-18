@extends('admin.layouts.master')
@section('page_title', 'Add Brand')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-tag" style="color: var(--accent-500); margin-right: 8px;"></i>Add Brand</h1>
            <p>Create a new product brand</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/brand') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i>Brand List
            </a>
        </div>
    </div>

    @include('admin.alert_message')

    <form action="{{ url('admin/brand') }}" enctype="multipart/form-data" method="post">
        @csrf
        <div class="crm-form-section crm-animate-in delay-1">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--accent-500);"><i class="fas fa-tag"></i></div>
                <h6>Brand Information</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="brand_name">Brand Name <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" class="form-control" id="brand_name" placeholder="Brand Name" name="brand_name" autocomplete="off" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Status <span style="color: var(--danger-500);">*</span></label>
                            <div class="mt-1">
                                <label class="radio-inline my-2">
                                    <input type="radio" name="status" value="1" checked="checked" id="status"> Active
                                </label>
                                <label class="radio-inline my-2 ml-3">
                                    <input type="radio" name="status" value="0"> Inactive
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="crm-form-submit-bar">
            <a href="{{ url('admin/brand') }}" class="btn btn-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Save</button>
        </div>
    </form>
</div>
@endsection
