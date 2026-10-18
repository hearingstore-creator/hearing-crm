@extends('admin.layouts.master')
@section('page_title', 'Customer Returns')
@section('breadcrumb_parent', 'Sales')
@section('breadcrumb_parent_url', url('admin/return'))

@push('css')
    @include('admin.partials.datatables-css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-undo-alt" style="color: var(--accent-500); margin-right: 8px;"></i>Customer Returns</h1>
            <p>Track products returned from customers</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/return/add-return') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>Add Return</a>
        </div>
    </div>

    @include('admin.alert_message')

    <div class="crm-table-card crm-animate-in delay-1">
        <div class="crm-table-header">
            <div class="table-title"><i class="fas fa-list" style="color: var(--page-text-muted);"></i> All Customer Returns</div>
        </div>
        <div style="overflow-x: auto;">
            <table class="crm-table yajra-datatable" data-datatable="customer-return-list" data-ajax-url="{{ route('customer-return.list') }}">
                <thead><tr><th style="width: 50px;">SL</th><th>Sale Invoice No</th><th>Customer Name</th><th>Date</th><th>Total Amount</th><th style="width: 90px; text-align: center;">Action</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @include('admin.partials.datatables-scripts')
@endpush
