@extends('admin.layouts.master')
@section('page_title', 'Medicines')
@section('breadcrumb_parent', 'Inventory')
@section('breadcrumb_parent_url', url('admin/medicine'))

@push('css')
    @include('admin.partials.datatables-css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-pills" style="color: var(--accent-500); margin-right: 8px;"></i>Medicines</h1>
            <p>Manage medicine catalog</p>
        </div>
        <div class="header-actions">
            @can('permission', 'medicine.create')
                <a href="{{ url('admin/medicine/create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>Add Medicine</a>
            @endcan
        </div>
    </div>

    @include('admin.alert_message')

    <div class="crm-table-card crm-animate-in delay-1">
        <div class="crm-table-header">
            <div class="table-title"><i class="fas fa-list" style="color: var(--page-text-muted);"></i> All Medicines</div>
        </div>
        <div style="overflow-x: auto;">
            <table class="crm-table yajra-datatable" data-datatable="medicine-list" data-ajax-url="{{ route('medicine.list') }}">
                <thead><tr><th style="width: 50px;">SL</th><th>Name</th><th>Generic</th><th>Category</th><th>Manufacturer</th><th>Shelf</th><th>Price</th><th>Cost</th><th>Strength</th><th>Image</th><th>Status</th><th style="width: 90px; text-align: center;">Action</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @include('admin.partials.datatables-scripts')
@endpush
