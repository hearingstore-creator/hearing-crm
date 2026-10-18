@extends('admin.layouts.master')
@section('page_title', 'Manufacturer Returns')
@section('breadcrumb_parent', 'Inventory')
@section('breadcrumb_parent_url', url('admin/manufacturer-return'))

@push('css')
    @include('admin.partials.datatables-css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-exchange-alt" style="color: var(--accent-500); margin-right: 8px;"></i>Manufacturer Returns</h1>
            <p>Track returns sent to manufacturers</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/manufacturer-return/add-return') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>Add Return</a>
        </div>
    </div>

    @include('admin.alert_message')

    <div class="crm-table-card crm-animate-in delay-1">
        <div class="crm-table-header">
            <div class="table-title"><i class="fas fa-list" style="color: var(--page-text-muted);"></i> All Manufacturer Returns</div>
        </div>
        <div style="overflow-x: auto;">
            <table class="crm-table yajra-datatable" data-datatable="manufacturer-return-list" data-ajax-url="{{ route('manufacturer-return.list') }}">
                <thead><tr><th style="width: 50px;">SL</th><th>Purchase Invoice No</th><th>Manufacturer</th><th>Date</th><th>Total Amount</th><th style="width: 90px; text-align: center;">Action</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @include('admin.partials.datatables-scripts')
@endpush
