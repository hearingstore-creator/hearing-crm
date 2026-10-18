@extends('admin.layouts.master')
@section('page_title', 'Doctor Payments')
@section('breadcrumb_parent', 'Finance')
@section('breadcrumb_parent_url', url('admin/doctors-payment'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/kpi-cards.css') }}">
<style>
.badge-cash { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; font-weight: 700; }
.badge-bank { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; font-weight: 700; }
.badge-cheque { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; font-weight: 700; }
.badge-mfs { background: #fce7f3; color: #9d174d; border: 1px solid #fbcfe8; font-weight: 700; }
.badge-other { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-weight: 700; }
.period-pill { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; padding: 2px 8px; border-radius: 6px; background: rgba(99,102,241,.08); color: #4338ca; font-weight: 600; }
</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-file-invoice-dollar" style="color: var(--accent-500); margin-right: 8px;"></i>Doctor Payments & Settlements</h1>
            <p>Settle monthly or custom period commissions and disburse doctor payments</p>
        </div>
        <div class="header-actions">
            @can('permission','doctor_payment.create')
                <a href="{{ route('doctors.payment.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-hand-holding-usd mr-1"></i>Settle & Pay Doctor</a>
            @endcan
            <a href="{{ route('report.doctor-referral') }}" class="btn btn-secondary btn-sm"><i class="fas fa-chart-line mr-1"></i>Referral Report</a>
        </div>
    </div>

    {{-- Top KPI Cards --}}
    <div class="kpi-row crm-animate-in">
        @include('admin.partials.kpi-card', [
            'color' => 'indigo',
            'icon' => 'fas fa-calendar-check',
            'label' => 'Paid This Month',
            'value' => '৳' . number_format($monthPaid, 2),
            'sub' => Carbon\Carbon::now()->format('F Y')
        ])
        @include('admin.partials.kpi-card', [
            'color' => 'teal',
            'icon' => 'fas fa-money-bill-wave',
            'label' => 'Total Disbursed',
            'value' => '৳' . number_format($totalPaid, 2),
            'sub' => 'All-time doctor payouts'
        ])
        @include('admin.partials.kpi-card', [
            'color' => 'rose',
            'icon' => 'fas fa-balance-scale',
            'label' => 'Outstanding Due',
            'value' => '৳' . number_format($totalPayable, 2),
            'sub' => $doctorsWithDue . ' doctor(s) with unpaid commission'
        ])
        @include('admin.partials.kpi-card', [
            'color' => 'orange',
            'icon' => 'fas fa-user-md',
            'label' => 'Active Doctors',
            'value' => number_format($doctors->count()),
            'sub' => 'Referral network'
        ])
    </div>

    @include('admin.alert_message')

    {{-- Filter Card --}}
    <div class="card shadow-sm border-0 mb-3 crm-animate-in delay-1" style="border-radius: 12px;">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('doctors.payment.index') }}" class="form-row align-items-end">
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">Filter by Doctor</label>
                    <select name="doctor_id" class="form-control form-control-sm select2">
                        <option value="">All Doctors</option>
                        @foreach($doctors as $d)
                            <option value="{{ $d->id }}" {{ request('doctor_id') == $d->id ? 'selected' : '' }}>
                                {{ $d->name }} ({{ $d->qualification ?? 'MBBS' }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">Payment Method</label>
                    <select name="payment_method" class="form-control form-control-sm">
                        <option value="">All Methods</option>
                        <option value="Cash" {{ request('payment_method') === 'Cash' ? 'selected' : '' }}>Cash</option>
                        <option value="Bank Transfer" {{ request('payment_method') === 'Bank Transfer' ? 'selected' : '' }}>Bank Transfer</option>
                        <option value="Cheque" {{ request('payment_method') === 'Cheque' ? 'selected' : '' }}>Cheque</option>
                        <option value="bKash" {{ request('payment_method') === 'bKash' ? 'selected' : '' }}>bKash</option>
                        <option value="Nagad" {{ request('payment_method') === 'Nagad' ? 'selected' : '' }}>Nagad</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">From Date</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ request('start_date') }}">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">To Date</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ request('end_date') }}">
                </div>
                <div class="col-md-3 col-sm-6 mb-2 d-flex">
                    <button type="submit" class="btn btn-primary btn-sm mr-2 flex-grow-1">
                        <i class="fas fa-filter mr-1"></i>Filter
                    </button>
                    <a href="{{ route('doctors.payment.index') }}" class="btn btn-outline-secondary btn-sm" title="Reset Filters">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Payments Table Card --}}
    <div class="crm-table-card crm-animate-in delay-2">
        <div class="crm-table-header d-flex justify-content-between align-items-center">
            <div class="table-title">
                <i class="fas fa-list mr-1" style="color: var(--page-text-muted);"></i>
                Payment & Settlement Records ({{ $payments->total() }})
            </div>
            <div>
                <small class="text-muted"><i class="fas fa-shield-alt text-success mr-1"></i>All payments are auto-posted to General Ledger</small>
            </div>
        </div>
        <div style="overflow-x: auto;">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th style="width: 45px;">#</th>
                        <th style="width: 100px;">Date</th>
                        <th>Doctor</th>
                        <th>Settlement Period</th>
                        <th class="num" style="width: 130px; text-align: right;">Amount Paid</th>
                        <th>Method & Reference</th>
                        <th>Voucher #</th>
                        <th style="width: 120px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $idx => $payment)
                    @php
                        $method = $payment->payment_method ?: 'Cash';
                        $methodClass = match($method) {
                            'Cash' => 'badge-cash',
                            'Bank Transfer' => 'badge-bank',
                            'Cheque' => 'badge-cheque',
                            'bKash', 'Nagad' => 'badge-mfs',
                            default => 'badge-other'
                        };
                    @endphp
                    <tr>
                        <td style="color: var(--page-text-muted); font-size: 12px;">{{ $payments->firstItem() + $idx }}</td>
                        <td style="font-size: 13px; font-weight: 500;">
                            {{ $payment->date ? $payment->date->format('d M Y') : '—' }}
                        </td>
                        <td>
                            <div class="crm-name-cell">
                                <div class="crm-avatar" style="background: var(--info-50); color: var(--info-600); font-weight: 700;">
                                    {{ strtoupper(substr($payment->doctor->name ?? 'D', 0, 1)) }}
                                </div>
                                <div class="crm-name-text">
                                    <span class="name font-weight-bold" style="color: #0f172a;">{{ $payment->doctor->name ?? 'Doctor Deleted' }}</span>
                                    @if(!empty($payment->doctor->qualification))
                                        <small class="text-muted d-block" style="font-size: 11px;">{{ $payment->doctor->qualification }}</small>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($payment->settlement_period_start && $payment->settlement_period_end)
                                <span class="period-pill">
                                    <i class="far fa-calendar-alt"></i>
                                    {{ $payment->settlement_period_start->format('d M Y') }} — {{ $payment->settlement_period_end->format('d M Y') }}
                                </span>
                            @else
                                <span class="text-muted" style="font-size: 12px;">General Payment</span>
                            @endif
                            @if(!empty($payment->details))
                                <div class="text-muted" style="font-size: 11px; margin-top: 2px;">{{ $payment->details }}</div>
                            @endif
                        </td>
                        <td style="text-align: right;">
                            <span style="font-weight: 800; color: #166534; font-size: 14px;">
                                ৳{{ number_format($payment->debit, 2) }}
                            </span>
                        </td>
                        <td>
                            <span class="badge {{ $methodClass }} px-2 py-1" style="font-size: 11px;">
                                {{ $method }}
                            </span>
                            @if(!empty($payment->bank_name))
                                <small class="text-muted d-block" style="font-size: 11px;">{{ $payment->bank_name }}</small>
                            @endif
                            @if(!empty($payment->transaction_no))
                                <small class="text-muted d-block" style="font-size: 11px;">Ref: {{ $payment->transaction_no }}</small>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('doctors.payment.slip', $payment->id) }}" class="font-weight-600 text-primary" style="font-size: 12px;">
                                <i class="fas fa-file-invoice mr-1"></i>{{ $payment->ref_invoice_no }}
                            </a>
                        </td>
                        <td style="text-align: center;">
                            <div class="crm-actions justify-content-center">
                                <a href="{{ route('doctors.payment.slip', $payment->id) }}" class="action-btn" title="Print Payment Voucher" style="color: #4f46e5;">
                                    <i class="fas fa-print"></i>
                                </a>
                                @if(auth()->user()->isSuperAdmin())
                                <form action="{{ route('doctors.payment.destroy', $payment->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="action-btn btn-delete" title="Cancel payment" onclick="confirmCancelWithReason(event, 'this doctor payment')">
                                        <i class="far fa-trash-alt"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px !important; color: var(--page-text-muted);">
                            <i class="fas fa-inbox" style="font-size: 32px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>
                            No doctor payment records found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($payments->hasPages())
        <div class="crm-table-footer p-3">{{ $payments->links() }}</div>
        @endif
    </div>
</div>
@endsection
