@extends('admin.layouts.master')
@section('page_title', 'Add Supplier')
@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush
@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-truck" style="color: var(--accent-500); margin-right: 8px;"></i>Add Supplier</h1>
            <p>Register a new supplier</p>
        </div>
        <div class="header-actions">
            @can('permission', 'supplier_list.index')
            <a href="{{ url('admin/suppliers') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>Supplier List</a>
            @endcan
        </div>
    </div>
    @include('admin.alert_message')
    <form action="{{ url('admin/suppliers') }}" enctype="multipart/form-data" method="post">
        @csrf
        <div class="crm-form-section crm-animate-in delay-1">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--accent-500);"><i class="fas fa-truck"></i></div>
                <h6>Supplier Information</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="supplier_name">Supplier Name <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" class="form-control" id="supplier_name" name="supplier_name" placeholder="Supplier Name" autocomplete="off" required>
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
                            <label for="phone">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone" placeholder="Phone">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="fax">Fax</label>
                            <input type="text" class="form-control" id="fax" name="fax" placeholder="Fax">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email1">Email Address 1</label>
                            <input type="email" class="form-control" id="email1" name="email1" placeholder="Email Address 1">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email2">Email Address 2</label>
                            <input type="email" class="form-control" id="email2" name="email2" placeholder="Email Address 2">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="address1">Address 1</label>
                            <textarea class="form-control" id="address1" name="address1" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="address2">Address 2</label>
                            <textarea class="form-control" id="address2" name="address2" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="city">City</label>
                            <input type="text" class="form-control" id="city" name="city" placeholder="City">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="state">State</label>
                            <input type="text" class="form-control" id="state" name="state" placeholder="State">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="country">Country</label>
                            <input type="text" class="form-control" id="country" name="country" placeholder="Country">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="zip">Zip</label>
                            <input type="text" class="form-control" id="zip" name="zip" placeholder="Zip">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="previous_balance">Previous Balance</label>
                            <input type="text" class="form-control" id="previous_balance" name="previous_balance" placeholder="Previous Balance">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="crm-form-submit-bar">
            <a href="{{ url('admin/supplier') }}" class="btn btn-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Save</button>
        </div>
    </form>
</div>
@endsection
