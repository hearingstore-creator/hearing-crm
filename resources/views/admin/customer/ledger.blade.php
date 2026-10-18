@extends('admin.layouts.master')
@section('page_title', 'Customer Ledger')
@section('breadcrumb_parent', 'Customers')
@section('breadcrumb_parent_url', url('admin/customers'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid customer-ledger" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-book" style="color: var(--accent-500); margin-right: 8px;"></i>Customer Ledger</h1>
            <p>View transaction history for a customer</p>
        </div>
    </div>

    {{-- Filter Panel --}}
    <div class="crm-table-card crm-animate-in delay-1" style="padding: 0;">
        <div class="crm-table-header">
            <div class="table-title"><i class="fas fa-filter" style="color: var(--page-text-muted);"></i> Select Customer & Date Range</div>
        </div>
        <div style="padding: 20px 24px;">
            <form class="form" action="{{ route('customer.ledger') }}" method="post">
                @csrf
                <div class="filter-row" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 200px;">
                        <label class="crm-form-label">Customer <span style="color: var(--danger-500);">*</span></label>
                        <select name="customer_id" class="form-control select" id="customer_id" required>
                            <option selected value="">Select Customer</option>
                            @foreach ($customers as $val)
                                <option value="{{ $val->id }}" @isset($customerId) {{ $customerId == $val->id ? 'selected' : '' }} @endisset>
                                    {{ $val->lead->full_name ?? $val->name }} ({{ $val->lead->phone_number ?? '' }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div style="min-width: 160px;">
                        <label class="crm-form-label">From <span style="color: var(--danger-500);">*</span></label>
                        <input type="date" name="start_date" value="{{ $startDate ?? '' }}" class="form-control" autocomplete="off" required>
                    </div>
                    <div style="min-width: 160px;">
                        <label class="crm-form-label">To <span style="color: var(--danger-500);">*</span></label>
                        <input type="date" name="end_date" value="{{ $endDate ?? '' }}" class="form-control" autocomplete="off" required>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search mr-1"></i>Search</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Ledger Table --}}
    @if(isset($ledgers))
    <div class="crm-table-card crm-animate-in delay-2" style="padding: 0; margin-top: 20px;">
        <div class="crm-table-header">
            <div class="table-title"><i class="fas fa-list" style="color: var(--page-text-muted);"></i> {{ $customerName }} — {{ $startDate }} to {{ $endDate }}</div>
        </div>
        <div style="overflow-x: auto;">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">#</th>
                        <th style="text-align: center;">Date</th>
                        <th style="text-align: center;">Description</th>
                        <th style="text-align: right;">Debit</th>
                        <th style="text-align: right;">Credit</th>
                        <th style="text-align: right;">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @php $totalDebit = 0; $totalCredit = 0; @endphp
                    @forelse ($ledgers as $key => $val)
                    <tr>
                        <td style="text-align: center; color: var(--page-text-muted); font-size: 12px;">{{ ++$key }}</td>
                        <td style="text-align: center; font-size: 13px;">{{ $val->date }}</td>
                        <td style="text-align: center; font-size: 13px;">{{ $val->details }}</td>
                        <td style="text-align: right; color: var(--danger-600); font-weight: 500;">{{ $val->debit }}</td>
                        <td style="text-align: right; color: var(--success-600); font-weight: 500;">{{ $val->credit }}</td>
                        <td style="text-align: right; font-weight: 600;">{{ number_format($val->debit - $val->credit, 2) }}</td>
                    </tr>
                    @php $totalDebit += $val->debit; $totalCredit += $val->credit; @endphp
                    @empty
                    <tr><td colspan="6" style="text-align: center; padding: 40px !important; color: var(--page-text-muted);"><i class="fas fa-book" style="font-size: 32px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>No ledger entries found.</td></tr>
                    @endforelse
                </tbody>
                @if(count($ledgers) > 0)
                <tfoot>
                    <tr style="background: var(--gray-50); font-weight: 600;">
                        <td colspan="3" style="text-align: right;">Total</td>
                        <td style="text-align: right; color: var(--danger-600);">{{ number_format($totalDebit, 2) }}</td>
                        <td style="text-align: right; color: var(--success-600);">{{ number_format($totalCredit, 2) }}</td>
                        <td style="text-align: right;">{{ number_format($totalDebit - $totalCredit, 2) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
    @endif

</div>

@push('scripts')
<script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
<script>$(document).ready(function() { $('.select').select2({ width: '100%' }); });</script>
@endpush

@endsection
