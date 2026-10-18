@extends('admin.layouts.master')
@section('page_title', 'Salary')
@section('breadcrumb_parent', 'HR')
@section('breadcrumb_parent_url', url('admin/salary'))

@push('css')
    @include('admin.partials.datatables-css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-money-check-alt" style="color: var(--accent-500); margin-right: 8px;"></i>Salary</h1>
            <p>Manage employee salary records</p>
        </div>
        <div class="header-actions">
            @can('permission', 'salary.create')
                <a href="{{ url('admin/salary/create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>Generate Salary</a>
            @endcan
        </div>
    </div>

    @include('admin.alert_message')

    <div class="crm-table-card crm-animate-in delay-1">
        <div class="crm-table-header">
            <div class="table-title"><i class="fas fa-list" style="color: var(--page-text-muted);"></i> All Salary</div>
        </div>
        <div style="overflow-x: auto;">
            <table class="crm-table yajra-datatable" data-datatable="salary-list" data-ajax-url="{{ route('salary.list') }}">
                <thead><tr><th style="width: 50px;">SL</th><th>Name</th><th>Month Year</th><th>Setup Date</th><th>Gross Salary</th><th style="width: 90px; text-align: center;">Action</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @include('admin.partials.datatables-scripts')
@endpush
