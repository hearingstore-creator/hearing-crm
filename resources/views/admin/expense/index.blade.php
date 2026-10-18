@extends('admin.layouts.master')
@section('page_title', 'Expenses')
@section('breadcrumb_parent', 'Finance')
@section('breadcrumb_parent_url', url('admin/expense'))

@push('css')
    @include('admin.partials.datatables-css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-money-bill-wave" style="color: var(--danger-500); margin-right: 8px;"></i>Expenses</h1>
            <p>Track and manage business expenditures</p>
        </div>
        <div class="header-actions">
            @can('permission', 'expense.create')
                <a href="{{ url('admin/expense/create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus mr-1"></i>Add Expense
                </a>
            @endcan
        </div>
    </div>

    @include('admin.alert_message')

    {{-- Data Table --}}
    <div class="crm-table-card crm-animate-in delay-1">
        <div class="crm-table-header">
            <div class="table-title">
                <i class="fas fa-receipt" style="color: var(--page-text-muted);"></i>
                All Expenses
                <span class="table-count">{{ count($expenses) }}</span>
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table class="crm-table" data-datatable="expense-list">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Date</th>
                        <th>Invoice No</th>
                        <th>Amount</th>
                        <th>Details</th>
                        <th style="width: 90px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($expenses as $key => $val)
                        <tr>
                            <td style="color: var(--page-text-muted); font-size: 12px;">{{ ++$key }}</td>
                            <td>
                                <span style="font-size: 13px; font-weight: 500;">{{ $val->date }}</span>
                            </td>
                            <td>
                                <span style="font-weight: 600; color: var(--accent-500); font-size: 13px;">{{ $val->invoice_no }}</span>
                            </td>
                            <td>
                                <span style="font-weight: 600; color: var(--danger-600);">৳{{ number_format($val->expense_amount, 2) }}</span>
                            </td>
                            <td>
                                <span style="font-size: 12px; color: var(--page-text-secondary);">
                                    @if ($val->ref_invoice_no !== null)
                                        {{ $val->details }} <span style="color: var(--page-text-muted);">({{ $val->type }} - invoice no-{{ $val->ref_invoice_no }})</span>
                                    @else
                                        {{ $val->details }}
                                    @endif
                                </span>
                            </td>
                            <td>
                                <div class="crm-actions">
                                    @can('permission', 'expense.delete')
                                        @if(!empty($val->income_expense_head_id))
                                            <form action="{{ route('expense.destroy', $val->id) }}" method="POST" style="display:inline;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="action-btn btn-delete" title="Delete"
                                                    onclick="confirmDelete(event)">
                                                    <i class="far fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px !important; color: var(--page-text-muted);">
                                <i class="fas fa-inbox" style="font-size: 32px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>
                                No expenses found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@push('scripts')
@include('admin.partials.datatables-scripts')
@endpush
