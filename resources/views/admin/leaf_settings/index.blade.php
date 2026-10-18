@extends('admin.layouts.master')
@section('page_title', 'Leaf Settings')
@section('breadcrumb_parent', 'Inventory')
@section('breadcrumb_parent_url', url('admin/leaf-settings'))

@push('css')
    @include('admin.partials.datatables-css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-leaf" style="color: var(--accent-500); margin-right: 8px;"></i>Leaf Settings</h1>
            <p>Manage hearing aid leaf configurations</p>
        </div>
        <div class="header-actions">
            @can('permission', 'leaf_setting.create')
                <a href="{{ url('admin/leaf-settings/create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>Add Leaf Type</a>
            @endcan
        </div>
    </div>

    @include('admin.alert_message')

    <div class="crm-table-card crm-animate-in delay-1">
        <div class="crm-table-header">
            <div class="table-title"><i class="fas fa-list" style="color: var(--page-text-muted);"></i> All Leaf Settings</div>
        </div>
        <div style="overflow-x: auto;">
            <table class="crm-table yajra-datatable" data-datatable="leaf-settings-list">
                <thead><tr><th style="width: 50px;">SL</th><th>Leaf Type</th><th>Total Number Per Box</th><th>Status</th><th style="width: 110px; text-align: center;">Action</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @include('admin.partials.datatables-scripts')
@endpush
