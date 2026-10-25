@extends('admin.layouts.master')
@section('page_title', 'Credit Vouchers')
@section('breadcrumb_parent', 'Finance')
@section('breadcrumb_parent_url', url('admin/income'))

@push('css')
    @include('admin.partials.datatables-css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-hand-holding-usd" style="color: var(--success-500); margin-right: 8px;"></i>Credit Vouchers</h1>
            <p>Money received, with voucher numbers</p>
        </div>
        <div class="header-actions">
            @can('permission', 'income.create')
            <a href="{{ url('admin/income/create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus mr-1"></i>New Credit Voucher
            </a>
            @endcan
        </div>
    </div>

    @include('admin.alert_message')
    @include('admin.partials.finance-quick-actions')

    <form method="GET" action="{{ url('admin/income') }}" class="crm-table-card crm-animate-in delay-1" style="padding: 14px 16px; margin-bottom: 14px;">
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
            <div class="col-md-2"><label class="mb-1" style="font-size:12px;">Search</label><input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Voucher, payer, ref"></div>
            <div class="col-md-2 d-flex align-items-end" style="gap:6px;">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter mr-1"></i>Filter</button>
                <a href="{{ url('admin/income') }}" class="btn btn-secondary btn-sm">Reset</a>
            </div>
        </div>
    </form>

    <div class="crm-table-card crm-animate-in delay-1">
        <div class="crm-table-header">
            <div class="table-title">
                <i class="fas fa-list" style="color: var(--page-text-muted);"></i>
                All Income Records
                <span class="table-count">{{ count($incomes) }}</span>
            </div>
            <div style="font-weight:600;">Total: <span style="color: var(--success-600);">৳{{ number_format($total, 2) }}</span></div>
        </div>

        <div style="overflow-x: auto;">
            <table class="crm-table" data-datatable="income-list">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Date</th>
                        <th>Voucher / Ref No</th>
                        <th>Head</th>
                        <th>Received From</th>
                        <th>Mode</th>
                        <th>Amount</th>
                        <th>Details</th>
                        <th>Status</th>
                        <th style="width: 130px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($incomes as $key => $val)
                        <tr>
                            <td style="color: var(--page-text-muted); font-size: 12px;">{{ ++$key }}</td>
                            <td><span style="font-size: 13px; font-weight: 500;">{{ $val->date }}</span></td>
                            <td>
                                <a href="{{ url('admin/income/' . $val->id) }}" style="font-weight: 600; color: var(--accent-600); font-size: 13px; text-decoration: none;" title="View / print voucher">
                                    {{ $val->voucher_no ?: $val->invoice_no }}
                                </a>
                            </td>
                            <td style="font-size:12px;">{{ $val->head->head_name ?? '—' }}</td>
                            <td style="font-size:12px;">{{ $val->payee_name ?: '—' }}</td>
                            <td style="font-size:12px;">
                                {{ $val->payment_method ?: '—' }}
                                @if ($val->transaction_no)<div style="color: var(--page-text-muted);">{{ $val->transaction_no }}</div>@endif
                            </td>
                            <td><span style="font-weight: 600; color: var(--success-600);">৳{{ number_format($val->income_amount, 2) }}</span></td>
                            <td>
                                <span style="font-size: 12px; color: var(--page-text-secondary);">
                                    @if ($val->ref_invoice_no !== null)
                                        {{ $val->details }} <span style="color: var(--page-text-muted);">({{ $val->type }} - invoice no-{{ $val->ref_invoice_no }})</span>
                                    @else
                                        {{ $val->details }}
                                    @endif
                                </span>
                            </td>
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
                                    <a href="{{ url('admin/income/' . $val->id) }}" class="action-btn btn-view" title="View / print voucher"><i class="fas fa-print"></i></a>
                                    @if ($val->attachment_path)
                                        <a href="{{ route('income.attachment', $val->id) }}" target="_blank" class="action-btn" title="Document"><i class="fas fa-paperclip"></i></a>
                                    @endif
                                    @if (auth()->user()->isSuperAdmin() && $val->type === null && $val->ref_invoice_no === null && $val->income_expense_head_id)
                                        <form action="{{ route('income.destroy', $val->id) }}" method="POST" style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="action-btn btn-delete" title="Cancel income"
                                                onclick="confirmCancelWithReason(event, 'this income')">
                                                <i class="far fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 40px !important; color: var(--page-text-muted);">
                                <i class="fas fa-inbox" style="font-size: 32px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>
                                No income found.
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
