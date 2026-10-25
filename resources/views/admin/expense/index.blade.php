@extends('admin.layouts.master')
@section('page_title', 'Debit Vouchers')
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
            <h1><i class="fas fa-money-bill-wave" style="color: var(--danger-500); margin-right: 8px;"></i>Debit Vouchers</h1>
            <p>Expenses and payments made, with voucher numbers</p>
        </div>
        <div class="header-actions">
            @can('permission', 'expense.create')
                <a href="{{ url('admin/expense/create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus mr-1"></i>New Debit Voucher
                </a>
            @endcan
        </div>
    </div>

    @include('admin.alert_message')
    @include('admin.partials.finance-quick-actions')

    {{-- Filters --}}
    <form method="GET" action="{{ url('admin/expense') }}" class="crm-table-card crm-animate-in delay-1" style="padding: 14px 16px; margin-bottom: 14px;">
        <div class="row">
            <div class="col-md-2"><label class="mb-1" style="font-size:12px;">From</label><input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="mb-1" style="font-size:12px;">To</label><input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="mb-1" style="font-size:12px;">Head</label>
                <select name="head" class="form-control form-control-sm">
                    <option value="">All</option>
                    @foreach ($heads as $h)
                        <option value="{{ $h->id }}" @selected(request('head') == $h->id)>{{ $h->head_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><label class="mb-1" style="font-size:12px;">Mode</label>
                <select name="method" class="form-control form-control-sm">
                    <option value="">All</option>
                    @foreach ($methods as $m)
                        <option value="{{ $m }}" @selected(request('method') === $m)>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><label class="mb-1" style="font-size:12px;">Search</label><input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Voucher, payee, ref"></div>
            <div class="col-md-2 d-flex align-items-end" style="gap:6px;">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter mr-1"></i>Filter</button>
                <a href="{{ url('admin/expense') }}" class="btn btn-secondary btn-sm">Reset</a>
            </div>
        </div>
    </form>

    {{-- Data Table --}}
    <div class="crm-table-card crm-animate-in delay-1">
        <div class="crm-table-header">
            <div class="table-title">
                <i class="fas fa-receipt" style="color: var(--page-text-muted);"></i>
                All Vouchers
                <span class="table-count">{{ count($expenses) }}</span>
            </div>
            <div style="font-weight:600;">Total: <span style="color: var(--danger-600);">৳{{ number_format($total, 2) }}</span></div>
        </div>

        <div style="overflow-x: auto;">
            <table class="crm-table" data-datatable="expense-list">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Date</th>
                        <th>Voucher / Ref No</th>
                        <th>Head</th>
                        <th>Paid To</th>
                        <th>Mode</th>
                        <th>Amount</th>
                        <th>Details</th>
                        <th>By</th>
                        <th>Status</th>
                        <th style="width: 150px; text-align: center;">Actions</th>
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
                                <a href="{{ url('admin/expense/' . $val->id) }}" style="font-weight: 600; color: var(--accent-600); font-size: 13px; text-decoration: none;" title="View / print voucher">
                                    {{ $val->voucher_no ?: $val->invoice_no }}
                                </a>
                            </td>
                            <td style="font-size:12px;">{{ $val->head->head_name ?? '—' }}</td>
                            <td style="font-size:12px;">{{ $val->payee_name ?: '—' }}</td>
                            <td style="font-size:12px;">
                                {{ $val->payment_method ?: '—' }}
                                @if ($val->transaction_no)<div style="color: var(--page-text-muted);">{{ $val->transaction_no }}</div>@endif
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
                            <td style="font-size:12px;">{{ $val->created_by ? ($creators[$val->created_by] ?? '—') : '—' }}</td>
                            <td style="font-size:12px;">
                                @if ($val->voucher_no && $val->type === null)
                                    @if ($val->status === 'Approved')
                                        <span style="color: var(--success-600); font-weight:600;">Approved</span>
                                    @else
                                        <span style="color: var(--warning-600, #b45309); font-weight:600;">Pending</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                <div class="crm-actions" style="justify-content: center;">
                                    <a href="{{ url('admin/expense/' . $val->id) }}" class="action-btn btn-view" title="View / print voucher"><i class="fas fa-print"></i></a>
                                    @if ($val->attachment_path)
                                        <a href="{{ route('expense.attachment', $val->id) }}" target="_blank" class="action-btn" title="Bill / receipt"><i class="fas fa-paperclip"></i></a>
                                    @endif
                                    @if ($val->voucher_no && $val->type === null && $val->ref_invoice_no === null && $val->status !== 'Approved')
                                        @can('permission', 'expense.edit')
                                            <a href="{{ url('admin/expense/' . $val->id . '/edit') }}" class="action-btn btn-edit" title="Edit voucher"><i class="fas fa-edit"></i></a>
                                        @endcan
                                        @can('permission', 'expense.approve')
                                            <form action="{{ route('expense.approve', $val->id) }}" method="POST" style="display:inline;">
                                                @csrf
                                                <button type="submit" class="action-btn" title="Approve voucher" onclick="return confirm('Approve this voucher?')"><i class="fas fa-check-circle"></i></button>
                                            </form>
                                        @endcan
                                    @endif
                                    {{-- Only manual expenses (no type, no linked invoice) can be cancelled here, by Super Admin, with a reason --}}
                                    @if(auth()->user()->isSuperAdmin() && $val->type === null && $val->ref_invoice_no === null)
                                        <form action="{{ route('expense.destroy', $val->id) }}" method="POST" style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="action-btn btn-delete" title="Cancel expense"
                                                onclick="confirmCancelWithReason(event, 'this expense')">
                                                <i class="far fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" style="text-align: center; padding: 40px !important; color: var(--page-text-muted);">
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
