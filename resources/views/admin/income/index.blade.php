@extends('admin.layouts.master')
@section('page_title', 'Income')
@section('breadcrumb_parent', 'Finance')
@section('breadcrumb_parent_url', url('admin/income'))

@push('css')
    @include('admin.partials.datatables-css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-hand-holding-usd" style="color: var(--success-500); margin-right: 8px;"></i>Income</h1>
            <p>Track and manage business income records</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/income/create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus mr-1"></i>Add Income
            </a>
        </div>
    </div>

    @include('admin.alert_message')

    {{-- Data Table --}}
    <div class="crm-table-card crm-animate-in delay-1">
        <div class="crm-table-header">
            <div class="table-title">
                <i class="fas fa-list" style="color: var(--page-text-muted);"></i>
                All Income Records
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table class="crm-table yajra-datatable" data-datatable="income-list"
                data-ajax-url="{{ route('income.list') }}">
                <thead>
                    <tr>
                        <th style="width: 50px;">SL</th>
                        <th>Date</th>
                        <th>Invoice No</th>
                        <th>Details</th>
                        <th>Amount</th>
                        <th style="width: 90px; text-align: center;">Action</th>
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
