@extends('admin.layouts.master')
@section('page_title', 'Supplier Payments')
@section('breadcrumb_parent', 'Finance')
@section('breadcrumb_parent_url', url('admin/suppliers-payment'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-file-invoice-dollar" style="color: var(--warning-500); margin-right: 8px;"></i>Supplier Payments</h1>
            <p>Track payments made to suppliers</p>
        </div>
        <div class="header-actions">
            @can('permission','supplier_payment.create')
                <a href="{{ url('admin/suppliers-payment/create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>Add Payment</a>
            @endcan
        </div>
    </div>

    @include('admin.alert_message')

    <div class="crm-table-card crm-animate-in delay-1">
        <div class="crm-table-header d-flex justify-content-between align-items-center flex-wrap" style="gap: 10px; padding: 12px 16px;">
            <div class="table-title"><i class="fas fa-list" style="color: var(--page-text-muted);"></i> All Payments</div>
            <form action="{{ url('admin/suppliers-payment') }}" method="GET" class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                <input type="text" name="supplier_memo_no" class="form-control form-control-sm" style="width: 170px;" placeholder="Memo / MR Ref #" value="{{ request('supplier_memo_no') }}">
                <select name="supplier_id" class="form-control form-control-sm" style="width: 180px;">
                    <option value="">All Suppliers</option>
                    @foreach($suppliers ?? [] as $s)
                        <option value="{{ $s->id }}" {{ request('supplier_id') == $s->id ? 'selected' : '' }}>{{ $s->supplier_name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search mr-1"></i>Filter</button>
                @if(request()->hasAny(['supplier_memo_no', 'supplier_id']))
                    <a href="{{ url('admin/suppliers-payment') }}" class="btn btn-sm btn-outline-secondary" title="Clear Filters"><i class="fas fa-undo"></i></a>
                @endif
            </form>
        </div>
        <div style="overflow-x: auto;">
            <table class="crm-table">
                <thead><tr><th style="width: 50px;">#</th><th>Date</th><th>Supplier</th><th>Amount</th><th>Method</th><th>Supplier Memo / MR</th><th>Voucher No</th><th>Details</th><th style="width: 140px; text-align: center;">Actions</th></tr></thead>
                <tbody>
                    @php $i = method_exists($payments, 'firstItem') ? ($payments->firstItem() ?? 1) : 1; @endphp
                    @forelse($payments as $payment)
                    <tr>
                        <td style="color: var(--page-text-muted); font-size: 12px;">{{ $i++ }}</td>
                        <td style="font-size: 13px;">{{ $payment->date ? $payment->date->format('d M Y') : '' }}</td>
                        <td><div class="crm-name-cell"><div class="crm-avatar" style="background: var(--warning-50); color: var(--warning-600);">{{ strtoupper(substr($payment->supplier->supplier_name ?? 'S', 0, 1)) }}</div><div class="crm-name-text"><span class="name">{{ $payment->supplier->supplier_name ?? '' }}</span></div></div></td>
                        <td><span style="font-weight: 700; color: var(--danger-600);">৳{{ number_format($payment->debit, 2) }}</span></td>
                        <td>
                            @php
                                $m = $payment->payment_method ?? 'Cash';
                                $mBadge = $m === 'Bank Deposit' ? 'badge-primary' : ($m === 'Online Transfer' ? 'badge-info' : 'badge-success');
                            @endphp
                            <span class="badge {{ $mBadge }}" style="font-size: 11px; padding: 4px 8px;">
                                {{ $m }}
                            </span>
                        </td>
                        <td>
                            @if(!empty($payment->supplier_memo_no))
                                <span class="badge badge-light border" style="font-family: monospace; font-size: 11px; font-weight: 700; color: var(--danger-700); background: #fef2f2; border-color: #fecaca !important;">
                                    <i class="fas fa-receipt mr-1 text-muted"></i>{{ $payment->supplier_memo_no }}
                                </span>
                            @else
                                <span class="text-muted" style="font-size: 12px;">—</span>
                            @endif
                        </td>
                        <td style="font-size: 12px; font-family: monospace; font-weight: 600; color: var(--page-text-primary);">{{ $payment->ref_invoice_no ?? '—' }}</td>
                        <td style="font-size: 12px; color: var(--page-text-secondary);">{{ $payment->details ?? '—' }}</td>
                        <td>
                            <div class="crm-actions">
                                <a href="{{ route('suppliers.payment.slip', $payment->id) }}" class="action-btn btn-view" title="Print Payment Voucher" style="color: var(--accent-600);"><i class="fas fa-print"></i></a>
                                @can('permission','supplier_payment.edit')<a href="{{ route('suppliers.payment.edit', $payment->id) }}" class="action-btn btn-edit" title="Edit"><i class="fas fa-pen"></i></a>@endcan
                                @if(auth()->user()->isSuperAdmin())<form action="{{ route('suppliers.payment.destroy', $payment->id) }}" method="POST" style="display:inline;">@csrf @method('DELETE')<button type="submit" class="action-btn btn-delete" title="Cancel payment" onclick="confirmCancelWithReason(event, 'this supplier payment')"><i class="far fa-trash-alt"></i></button></form>@endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" style="text-align: center; padding: 40px !important; color: var(--page-text-muted);"><i class="fas fa-inbox" style="font-size: 32px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>No payments found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($payments->hasPages())
        <div class="crm-table-footer">{{ $payments->links() }}</div>
        @endif
    </div>
</div>
@endsection
