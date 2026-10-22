@extends('admin.layouts.master')
@section('page_title', 'Cash & Bank Book')
@section('breadcrumb_parent', 'Finance')
@section('breadcrumb_parent_url', url('admin/voucher-book'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-book" style="color: var(--accent-500); margin-right: 8px;"></i>Cash &amp; Bank Book</h1>
            <p>Money in and out by payment mode, from Credit and Debit Vouchers</p>
        </div>
    </div>

    @include('admin.alert_message')

    <div style="display:flex; gap:8px; margin-bottom:12px; flex-wrap:wrap;">
        @foreach ($books as $key => $b)
            <a href="{{ url('admin/voucher-book?book=' . $key . '&from=' . $from . '&to=' . $to) }}"
               class="btn btn-sm {{ $book === $key ? 'btn-primary' : 'btn-secondary' }}">{{ $b['label'] }}</a>
        @endforeach
    </div>

    <form method="GET" action="{{ url('admin/voucher-book') }}" class="crm-table-card crm-animate-in delay-1" style="padding: 14px 16px; margin-bottom: 14px;">
        <input type="hidden" name="book" value="{{ $book }}">
        <div class="row">
            <div class="col-md-3"><label class="mb-1" style="font-size:12px;">From</label><input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm"></div>
            <div class="col-md-3"><label class="mb-1" style="font-size:12px;">To</label><input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm"></div>
            <div class="col-md-3 d-flex align-items-end"><button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter mr-1"></i>Show</button></div>
        </div>
    </form>

    <div class="row" style="margin-bottom: 14px;">
        <div class="col-md-3"><div class="crm-table-card" style="padding:14px 16px;"><div style="font-size:12px;color:var(--page-text-muted);">Opening balance</div><div style="font-size:20px;font-weight:700;">৳{{ number_format($opening, 2) }}</div></div></div>
        <div class="col-md-3"><div class="crm-table-card" style="padding:14px 16px;"><div style="font-size:12px;color:var(--page-text-muted);">Money in</div><div style="font-size:20px;font-weight:700;color:var(--success-600);">৳{{ number_format($receipts, 2) }}</div></div></div>
        <div class="col-md-3"><div class="crm-table-card" style="padding:14px 16px;"><div style="font-size:12px;color:var(--page-text-muted);">Money out</div><div style="font-size:20px;font-weight:700;color:var(--danger-600);">৳{{ number_format($payments, 2) }}</div></div></div>
        <div class="col-md-3"><div class="crm-table-card" style="padding:14px 16px;"><div style="font-size:12px;color:var(--page-text-muted);">Closing balance</div><div style="font-size:20px;font-weight:700;">৳{{ number_format($closing, 2) }}</div></div></div>
    </div>

    <div class="crm-table-card crm-animate-in delay-1">
        <div class="crm-table-header">
            <div class="table-title">
                <i class="fas fa-list" style="color: var(--page-text-muted);"></i>
                {{ $books[$book]['label'] }}
                <span class="table-count">{{ count($entries) }}</span>
            </div>
        </div>
        <div style="overflow-x: auto;">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Voucher / Ref No</th>
                        <th>Head</th>
                        <th>Party</th>
                        <th>Mode</th>
                        <th style="text-align:right;">Money in</th>
                        <th style="text-align:right;">Money out</th>
                        <th style="text-align:right;">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="background: var(--gray-50, #f8fafc);">
                        <td colspan="7" style="font-weight:600;">Opening balance</td>
                        <td style="text-align:right; font-weight:600;">৳{{ number_format($opening, 2) }}</td>
                    </tr>
                    @forelse ($entries as $e)
                        <tr>
                            <td style="font-size:13px;">{{ \Carbon\Carbon::parse($e->date)->format('d M Y') }}</td>
                            <td style="font-weight:600; color: var(--accent-500); font-size:13px;">
                                {{ $e->voucher_no ?: $e->invoice_no }}
                                @if (str_contains((string) $e->type, 'Reversed') || str_contains((string) $e->type, 'Reversal'))
                                    <span style="color: var(--danger-600); font-size:11px;">cancelled</span>
                                @endif
                            </td>
                            <td style="font-size:12px;">{{ $e->head->head_name ?? '—' }}</td>
                            <td style="font-size:12px;">{{ $e->payee_name ?: '—' }}</td>
                            <td style="font-size:12px;">{{ $e->payment_method }}</td>
                            <td style="text-align:right; color: var(--success-600);">{{ (float) $e->income_amount != 0.0 ? '৳' . number_format($e->income_amount, 2) : '' }}</td>
                            <td style="text-align:right; color: var(--danger-600);">{{ (float) $e->expense_amount != 0.0 ? '৳' . number_format($e->expense_amount, 2) : '' }}</td>
                            <td style="text-align:right; font-weight:600;">৳{{ number_format($e->running_balance, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align:center; padding:32px !important; color: var(--page-text-muted);">No voucher entries in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr style="font-weight:700;">
                        <td colspan="5">Totals</td>
                        <td style="text-align:right;">৳{{ number_format($receipts, 2) }}</td>
                        <td style="text-align:right;">৳{{ number_format($payments, 2) }}</td>
                        <td style="text-align:right;">৳{{ number_format($closing, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p style="padding: 10px 16px; font-size:12px; color: var(--page-text-muted);">
            Built from Credit and Debit Vouchers only. Sale receipts and payments made from other screens are not included.
        </p>
    </div>
</div>
@endsection
