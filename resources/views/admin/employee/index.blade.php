@extends('admin.layouts.master')
@section('page_title', 'Employees')
@section('breadcrumb_parent', 'HR')
@section('breadcrumb_parent_url', url('admin/employee'))

@push('css')
    @include('admin.partials.datatables-css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-id-badge" style="color: var(--accent-500); margin-right: 8px;"></i>Employees</h1>
            <p>Manage employee records and information</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/employee/create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus mr-1"></i>Add Employee
            </a>
        </div>
    </div>

    @include('admin.alert_message')

    {{-- Data Table --}}
    <div class="crm-table-card crm-animate-in delay-1">
        <div class="crm-table-header">
            <div class="table-title">
                <i class="fas fa-list" style="color: var(--page-text-muted);"></i>
                All Employees
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table class="crm-table yajra-datatable" data-datatable="employee-list"
                data-ajax-url="{{ route('employee.list') }}">
                <thead>
                    <tr>
                        <th style="width: 50px;">SL</th>
                        <th>Name</th>
                        <th>Designation</th>
                        <th>Email</th>
                        <th>Mobile No</th>
                        <th>Phone</th>
                        <th>Blood Group</th>
                        <th>Salary</th>
                        <th>Status</th>
                        <th style="width: 110px; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@push('scripts')
    @include('admin.partials.datatables-scripts')
@endpush
