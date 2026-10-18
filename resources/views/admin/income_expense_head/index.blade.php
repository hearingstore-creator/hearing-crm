@extends('admin.layouts.master')
@section('page_title', 'Income Expense Heads')
@section('breadcrumb_parent', 'Finance')
@section('breadcrumb_parent_url', url('admin/income-expense-head'))

@push('css')
@include('admin.partials.datatables-css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-sitemap" style="color: var(--accent-500); margin-right: 8px;"></i>Income/Expense Heads</h1>
            <p>Manage accounting categories</p>
        </div>
        <div class="header-actions">
            @can('permission', 'income_expense_head.create')
                <a href="{{ url('admin/income-expense-head/create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>Add Head</a>
            @endcan
        </div>
    </div>

    @include('admin.alert_message')

    <div class="crm-table-card crm-animate-in delay-1">
        <div class="crm-table-header">
            <div class="table-title"><i class="fas fa-list" style="color: var(--page-text-muted);"></i> All Heads <span class="table-count">{{ count($incomeExpenseHeads) }}</span></div>
        </div>
        <div style="overflow-x: auto;">
            <table class="crm-table" data-datatable="income-expense-head-list">
                <thead><tr><th style="width: 50px;">#</th><th>Head Type</th><th>Head Name</th><th>Status</th><th style="width: 110px; text-align: center;">Actions</th></tr></thead>
                <tbody>
                    @foreach ($incomeExpenseHeads as $key => $val)
                    <tr>
                        <td style="color: var(--page-text-muted); font-size: 12px;">{{ ++$key }}</td>
                        <td><span class="crm-status-badge badge-status-{{ strtolower($val->head_type) }}"><span class="dot"></span>{{ $val->head_type }}</span></td>
                        <td><span style="font-weight: 500;">{{ $val->head_name }}</span></td>
                        <td>@if($val->status == 1)<span class="crm-status-badge badge-status-won"><span class="dot"></span>Active</span>@else<span class="crm-status-badge badge-status-lost"><span class="dot"></span>Inactive</span>@endif</td>
                        <td><div class="crm-actions">@can('permission', 'income_expense_head.edit')<a href="{{ route('income-expense-head.edit', $val->id) }}" class="action-btn btn-edit" title="Edit"><i class="fas fa-pen"></i></a>@endcan @can('permission', 'income_expense_head.delete')<form action="{{ route('income-expense-head.destroy', $val->id) }}" method="POST" style="display:inline;">@csrf @method('DELETE')<button type="submit" class="action-btn btn-delete" title="Delete" onclick="confirmDelete(event)"><i class="far fa-trash-alt"></i></button></form>@endcan</div></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
