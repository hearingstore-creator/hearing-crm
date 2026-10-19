@extends('admin.layouts.master')
@section('page_title', 'Doctor & Referral Payments')
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
.nav-pills-custom .nav-link {
    border-radius: 10px;
    padding: 10px 20px;
    font-weight: 700;
    color: #64748b;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    margin-right: 8px;
    transition: all 0.2s ease;
}
.nav-pills-custom .nav-link.active {
    background: #4f46e5;
    color: #ffffff;
    border-color: #4f46e5;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
}
.btn-quick-pct {
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 700;
    padding: 3px 10px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s ease;
}
.btn-quick-pct:hover {
    background: #eef2ff;
    border-color: #6366f1;
    color: #4f46e5;
}
</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-file-invoice-dollar" style="color: var(--accent-500); margin-right: 8px;"></i>Doctor & Referral Settlements</h1>
            <p>Negotiate and settle referral commissions for Doctors, Quacks, and RF Agents, and disburse payments</p>
        </div>
        <div class="header-actions">
            @can('permission','doctor_payment.create')
                <a href="{{ route('doctors.payment.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-hand-holding-usd mr-1"></i>Settle Monthly Period</a>
            @endcan
            <a href="{{ route('report.doctor-referral') }}" class="btn btn-secondary btn-sm"><i class="fas fa-chart-line mr-1"></i>Referral Report</a>
        </div>
    </div>

    @include('admin.partials.finance-quick-actions')

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
            'sub' => 'All-time payouts'
        ])
        @include('admin.partials.kpi-card', [
            'color' => 'rose',
            'icon' => 'fas fa-balance-scale',
            'label' => 'Outstanding Due',
            'value' => '৳' . number_format($totalPayable, 2),
            'sub' => $doctorsWithDue . ' referrer(s) with unpaid commission'
        ])
        @include('admin.partials.kpi-card', [
            'color' => $pendingCount > 0 ? 'orange' : 'teal',
            'icon' => 'fas fa-handshake',
            'label' => 'Pending Negotiations',
            'value' => number_format($pendingCount),
            'sub' => $pendingCount > 0 ? 'Sales awaiting day-end agreement' : 'All sales settled'
        ])
    </div>

    @include('admin.alert_message')

    {{-- Nav Tabs --}}
    <ul class="nav nav-pills nav-pills-custom mb-3 crm-animate-in delay-1" id="settlementTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link {{ request('tab', $pendingCount > 0 ? 'pending' : 'history') === 'pending' ? 'active' : '' }}" 
               id="pending-tab" data-toggle="pill" href="#pending-panel" role="tab">
                <i class="fas fa-handshake mr-1.5"></i>Pending Negotiations
                @if($pendingCount > 0)
                    <span class="badge badge-danger ml-1" style="border-radius: 10px; font-size: 11px; padding: 2px 7px;">{{ $pendingCount }}</span>
                @else
                    <span class="badge badge-light ml-1" style="border-radius: 10px; font-size: 11px; padding: 2px 7px;">0</span>
                @endif
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request('tab', $pendingCount > 0 ? 'pending' : 'history') === 'history' ? 'active' : '' }}" 
               id="history-tab" data-toggle="pill" href="#history-panel" role="tab">
                <i class="fas fa-receipt mr-1.5"></i>Payment Vouchers & Disbursed ({{ $payments->total() }})
            </a>
        </li>
    </ul>

    <div class="tab-content" id="settlementTabsContent">
        {{-- TAB 1: PENDING NEGOTIATIONS --}}
        <div class="tab-pane fade {{ request('tab', $pendingCount > 0 ? 'pending' : 'history') === 'pending' ? 'show active' : '' }}" 
             id="pending-panel" role="tabpanel">
            <div class="crm-table-card crm-animate-in delay-2">
                <div class="crm-table-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="table-title">
                        <i class="fas fa-clock mr-1" style="color: var(--warning-500);"></i>
                        Sales Awaiting Referral Agreement & Negotiation (<span id="pending-count-display">{{ $pendingCount }}</span>)
                    </div>
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <div class="btn-group btn-group-sm" id="pendingCategoryFilters" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary active btn-filter-pending-cat" data-cat="all">All</button>
                            <button type="button" class="btn btn-sm btn-outline-primary btn-filter-pending-cat" data-cat="doctor"><i class="fas fa-user-md mr-1"></i>Doctors</button>
                            <button type="button" class="btn btn-sm btn-outline-info btn-filter-pending-cat" data-cat="quack"><i class="fas fa-stethoscope mr-1"></i>Quacks / RMP</button>
                            <button type="button" class="btn btn-sm btn-outline-warning btn-filter-pending-cat" data-cat="agent"><i class="fas fa-handshake mr-1"></i>RF Agents</button>
                        </div>
                    </div>
                </div>
                <div style="overflow-x: auto;">
                    <table class="crm-table">
                        <thead>
                            <tr>
                                <th style="width: 45px;">#</th>
                                <th style="width: 100px;">Sale Date</th>
                                <th>Invoice #</th>
                                <th>Branch</th>
                                <th>Patient</th>
                                <th>Items Sold</th>
                                <th class="num" style="width: 120px; text-align: right;">Sale Total</th>
                                <th>Referrer</th>
                                <th>Status</th>
                                <th style="width: 140px; text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pendingSales as $pidx => $sale)
                            @php
                                $patientName = $sale->customer->lead->full_name ?? ($sale->customer->name ?? 'Walk-in Patient');
                                $patientPhone = $sale->customer->lead->phone_number ?? ($sale->customer->mobile ?? '');
                                $doc = $sale->doctor;
                                $docName = $doc ? $doc->name : 'Unknown Referrer';
                                $docType = $doc ? $doc->referrer_type_label : 'Referrer';
                                $docBadge = $doc ? $doc->referrer_badge_class : 'badge-primary';
                                
                                $itemNames = [];
                                foreach ($sale->saleDetails as $sd) {
                                    if ($sd->product) {
                                        $itemNames[] = $sd->product->product_name ?? 'Product';
                                    }
                                }
                                $itemsSummary = !empty($itemNames) ? implode(', ', array_slice($itemNames, 0, 2)) : 'Sale Items';
                            @endphp
                            <tr class="pending-sale-row" data-referrer-type="{{ $doc->referrer_type ?? 'doctor' }}">
                                <td style="color: var(--page-text-muted); font-size: 12px;">{{ $pidx + 1 }}</td>
                                <td style="font-size: 13px; font-weight: 500;">
                                    {{ $sale->date ? \Carbon\Carbon::parse($sale->date)->format('d M Y') : '—' }}
                                </td>
                                <td>
                                    <span class="font-weight-bold text-dark">{{ $sale->sale_invoice_no }}</span>
                                </td>
                                <td>
                                    <span class="badge badge-light border text-muted" style="font-size: 11px;">
                                        {{ $sale->vendor->name ?? 'Head Office' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="font-weight-bold" style="color: #0f172a; font-size: 13px;">{{ $patientName }}</div>
                                    @if($patientPhone)
                                        <small class="text-muted" style="font-size: 11px;">{{ $patientPhone }}</small>
                                    @endif
                                </td>
                                <td>
                                    <div style="font-size: 12px; color: #334155; max-width: 200px; white-space: normal;" title="{{ implode(', ', $itemNames) }}">
                                        {{ Str::limit($itemsSummary, 40) }}
                                    </div>
                                </td>
                                <td style="text-align: right;">
                                    <span style="font-weight: 800; color: #0f172a; font-size: 14px;">
                                        ৳{{ number_format((float) $sale->total_amount, 2) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="font-weight-bold" style="color: #0f172a; font-size: 13px;">{{ $docName }}</span>
                                        <span class="badge {{ $docBadge }} ml-1.5" style="font-size: 10px; font-weight: 600;">{{ $docType }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-warning px-2 py-1" style="font-size: 11px; font-weight: 700;">
                                        <i class="fas fa-clock mr-1"></i>Awaiting Negotiation
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    @can('permission','doctor_payment.create')
                                    <button type="button" class="btn btn-sm btn-success px-2.5 py-1 btn-settle-modal" 
                                            style="border-radius: 6px; font-weight: 700; font-size: 12px;"
                                            data-id="{{ $sale->id }}"
                                            data-invoice="{{ $sale->sale_invoice_no }}"
                                            data-patient="{{ $patientName }}"
                                            data-total="{{ (float) $sale->total_amount }}"
                                            data-referrer="{{ $docName }}"
                                            data-referrer-type="{{ $docType }}"
                                            data-badge-class="{{ $docBadge }}">
                                        <i class="fas fa-handshake mr-1"></i>Settle & Pay
                                    </button>
                                    @else
                                    <span class="text-muted small">No permission</span>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="10" style="text-align: center; padding: 48px !important; color: var(--page-text-muted);">
                                    <i class="fas fa-check-circle text-success" style="font-size: 36px; display: block; margin-bottom: 12px;"></i>
                                    <h6 class="font-weight-bold text-dark mb-1">All Referral Sales Are Settled!</h6>
                                    <p class="small text-muted mb-0">When a sale is made with a doctor, quack, or RF agent and commission is pending, it will appear here for negotiation.</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- TAB 2: PAYMENT HISTORY & DISBURSED --}}
        <div class="tab-pane fade {{ request('tab', $pendingCount > 0 ? 'pending' : 'history') === 'history' ? 'show active' : '' }}" 
             id="history-panel" role="tabpanel">
            {{-- Filter Card --}}
            <div class="card shadow-sm border-0 mb-3 crm-animate-in delay-1" style="border-radius: 12px;">
                <div class="card-body p-3">
                    <form method="GET" action="{{ route('doctors.payment.index') }}" class="form-row align-items-end">
                        <input type="hidden" name="tab" value="history">
                        <div class="col-md-2 col-sm-6 mb-2">
                            <label class="small font-weight-bold text-muted mb-1"><i class="fas fa-tags mr-1"></i>Category</label>
                            <select name="referrer_type" id="filter_payment_referrer_type" class="form-control form-control-sm">
                                <option value="">All Categories</option>
                                <option value="doctor" {{ request('referrer_type') === 'doctor' ? 'selected' : '' }}>Qualified Doctor</option>
                                <option value="quack" {{ request('referrer_type') === 'quack' ? 'selected' : '' }}>Village Doctor / Quack</option>
                                <option value="agent" {{ request('referrer_type') === 'agent' ? 'selected' : '' }}>RF / Referral Agent</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2">
                            <label class="small font-weight-bold text-muted mb-1"><i class="fas fa-user-md mr-1"></i>Referrer Name</label>
                            <select name="doctor_id" id="filter_payment_doctor_id" class="form-control form-control-sm select2">
                                <option value="" data-category="">All Referrers</option>
                                @foreach($doctors as $d)
                                    <option value="{{ $d->id }}" data-category="{{ $d->referrer_type ?? 'doctor' }}" {{ request('doctor_id') == $d->id ? 'selected' : '' }}>
                                        [{{ $d->referrer_type_label }}] {{ $d->name }}
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
                            <a href="{{ route('doctors.payment.index', ['tab' => 'history']) }}" class="btn btn-outline-secondary btn-sm" title="Reset Filters">
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
                                <th>Doctor / Referrer</th>
                                <th>Settlement Scope</th>
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
                                            <span class="name font-weight-bold" style="color: #0f172a;">{{ $payment->doctor->name ?? 'Referrer Deleted' }}</span>
                                            @if($payment->doctor)
                                                <small class="text-muted d-block" style="font-size: 11px;">
                                                    <span class="badge {{ $payment->doctor->referrer_badge_class }}" style="font-size: 9.5px;">{{ $payment->doctor->referrer_type_label }}</span>
                                                    {{ $payment->doctor->qualification ?? '' }}
                                                </small>
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
                                        <span class="text-muted" style="font-size: 12px;">Direct / Sale Settlement</span>
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
                                    No payment records found.
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
    </div>
</div>

{{-- Settle & Pay Negotiated Modal --}}
<div class="modal fade" id="settleModal" tabindex="-1" role="dialog" aria-labelledby="settleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
            <form id="settleForm" method="POST" action="">
                @csrf
                <div class="modal-header" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; border-radius: 12px 12px 0 0;">
                    <h5 class="modal-title font-weight-bold" id="settleModalLabel" style="font-size: 16px; color: #0f172a;">
                        <i class="fas fa-handshake text-success mr-2"></i>Negotiate & Settle Referral
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    {{-- Sale summary card --}}
                    <div class="p-3 mb-3" style="background: #f1f5f9; border-radius: 8px; border-left: 4px solid var(--accent-500);">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="font-weight-bold text-dark" id="modal-invoice-no">INV-...</span>
                            <span class="font-weight-bold text-primary" id="modal-total-amount" style="font-size: 15px;">৳0.00</span>
                        </div>
                        <div class="small text-muted" id="modal-patient-info">Patient: ...</div>
                        <div class="small text-muted mt-1">
                            Referrer: <strong id="modal-referrer-name" class="text-dark">...</strong>
                            <span id="modal-referrer-badge" class="badge badge-primary ml-1" style="font-size: 10px;">Doctor</span>
                        </div>
                    </div>

                    {{-- Negotiated Amount Input --}}
                    <div class="form-group">
                        <label class="font-weight-bold text-dark mb-1">
                            Negotiated Commission Amount (৳ BDT) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white font-weight-bold text-muted">৳</span>
                            </div>
                            <input type="number" step="0.01" min="1" class="form-control font-weight-bold" 
                                   id="modal-reference-amount" name="reference_amount" placeholder="e.g. 5000" required
                                   style="font-size: 16px; color: #166534;">
                        </div>
                        {{-- Quick % helper buttons --}}
                        <div class="d-flex align-items-center mt-2" style="gap: 6px;">
                            <small class="text-muted mr-1">Quick % of sale:</small>
                            <button type="button" class="btn-quick-pct" data-pct="10">10%</button>
                            <button type="button" class="btn-quick-pct" data-pct="15">15%</button>
                            <button type="button" class="btn-quick-pct" data-pct="20">20%</button>
                            <button type="button" class="btn-quick-pct" data-pct="25">25%</button>
                        </div>
                    </div>

                    {{-- Action Selection (Pay Cash Now vs Add to Due) --}}
                    <div class="form-group">
                        <label class="font-weight-bold text-dark mb-2">Settlement Action <span class="text-danger">*</span></label>
                        <div class="custom-control custom-radio mb-2">
                            <input type="radio" id="action-pay-now" name="action" value="pay_now" class="custom-control-input" checked>
                            <label class="custom-control-label font-weight-bold text-success" for="action-pay-now">
                                <i class="fas fa-money-bill-wave mr-1"></i>Pay Now (Disburse cash / bKash immediately)
                            </label>
                            <small class="form-text text-muted ml-4" style="margin-top: 0;">Auto-deducts from branch cash drawer and generates payment voucher.</small>
                        </div>
                        <div class="custom-control custom-radio">
                            <input type="radio" id="action-add-due" name="action" value="add_to_due" class="custom-control-input">
                            <label class="custom-control-label font-weight-bold text-indigo" for="action-add-due">
                                <i class="fas fa-clock mr-1"></i>Add to Due (Referrer will collect later)
                            </label>
                            <small class="form-text text-muted ml-4" style="margin-top: 0;">Saves to referrer's account balance without immediate cash outflow.</small>
                        </div>
                    </div>

                    {{-- Payment Details (shown only if Pay Now) --}}
                    <div id="pay-now-fields" class="p-3 mb-3" style="background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1;">
                        <div class="form-row">
                            <div class="col-md-6 mb-2">
                                <label class="small font-weight-bold text-muted mb-1">Payment Method</label>
                                <select name="payment_method" id="modal-payment-method" class="form-control form-control-sm">
                                    <option value="Cash" selected>Cash Register</option>
                                    <option value="bKash">bKash</option>
                                    <option value="Nagad">Nagad</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Cheque">Cheque</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="small font-weight-bold text-muted mb-1">Trx / Ref No (Optional)</label>
                                <input type="text" name="transaction_no" class="form-control form-control-sm" placeholder="e.g. Trx ID or Cheque #">
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label class="small font-weight-bold text-muted mb-1">Notes / Remarks (Optional)</label>
                        <input type="text" name="details" class="form-control form-control-sm" placeholder="e.g. Agreed at closing with Dr. Motin">
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; border-radius: 0 0 12px 12px;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm font-weight-bold">
                        <i class="fas fa-check-circle mr-1"></i>Confirm & Settle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Open Settle Modal
    $('.btn-settle-modal').on('click', function() {
        var btn = $(this);
        var saleId = btn.data('id');
        var invoice = btn.data('invoice');
        var patient = btn.data('patient');
        var total = parseFloat(btn.data('total') || 0);
        var referrer = btn.data('referrer');
        var referrerType = btn.data('referrer-type');
        var badgeClass = btn.data('badge-class') || 'badge-primary';

        $('#settleForm').attr('action', '{{ url("admin/doctors-payment/settle") }}/' + saleId);
        $('#modal-invoice-no').text(invoice);
        $('#modal-total-amount').text('৳' + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#modal-patient-info').text('Patient: ' + patient);
        $('#modal-referrer-name').text(referrer);
        $('#modal-referrer-badge').text(referrerType).attr('class', 'badge ml-1 ' + badgeClass);

        $('#modal-reference-amount').val('').focus();
        $('#settleForm').data('sale-total', total);
        $('#action-pay-now').prop('checked', true).trigger('change');

        $('#settleModal').modal('show');
    });

    // Quick % buttons
    $('.btn-quick-pct').on('click', function() {
        var pct = parseFloat($(this).data('pct') || 0);
        var total = parseFloat($('#settleForm').data('sale-total') || 0);
        if (total > 0 && pct > 0) {
            var calc = (total * pct / 100).toFixed(2);
            $('#modal-reference-amount').val(calc);
        }
    });

    // Toggle Pay Now fields
    $('input[name="action"]').on('change', function() {
        if ($('#action-pay-now').is(':checked')) {
            $('#pay-now-fields').slideDown(150);
            $('#modal-payment-method').prop('required', true);
        } else {
            $('#pay-now-fields').slideUp(150);
            $('#modal-payment-method').prop('required', false);
        }
    });

    // Tab 1 Quick Category Filter
    $('.btn-filter-pending-cat').on('click', function() {
        var cat = $(this).data('cat');
        $('.btn-filter-pending-cat').removeClass('active');
        $(this).addClass('active');

        var visibleCount = 0;
        $('.pending-sale-row').each(function() {
            var rowCat = $(this).data('referrer-type') || 'doctor';
            if (cat === 'all' || rowCat === cat) {
                $(this).show();
                visibleCount++;
            } else {
                $(this).hide();
            }
        });
        $('#pending-count-display').text(visibleCount);
    });

    // Tab 2 History Filter: Category -> Referrer Name Sync
    var $histCat = $('#filter_payment_referrer_type');
    var $histDoc = $('#filter_payment_doctor_id');

    if ($histDoc.length && $histCat.length) {
        var allHistOptions = [];
        $histDoc.find('option').each(function() {
            allHistOptions.push({
                value: $(this).val(),
                text: $(this).text(),
                category: $(this).data('category') || ''
            });
        });

        function filterHistoryDoctors(cat, keepVal) {
            var currentVal = keepVal !== undefined ? keepVal : $histDoc.val();
            $histDoc.empty();

            var summaryText = 'All Referrers';
            if (cat === 'doctor') summaryText = 'All Qualified Doctors';
            else if (cat === 'quack') summaryText = 'All Village Doctors / Quacks';
            else if (cat === 'agent') summaryText = 'All RF / Referral Agents';

            $histDoc.append(new Option(summaryText, '', true, false));

            var found = false;
            allHistOptions.forEach(function(opt) {
                if (!opt.value) return;
                if (!cat || opt.category === cat) {
                    var isSelected = (opt.value == currentVal);
                    if (isSelected) found = true;
                    $histDoc.append(new Option(opt.text, opt.value, isSelected, isSelected));
                }
            });

            if (!found && currentVal) {
                $histDoc.val('');
            }

            if ($.fn.select2 && $histDoc.hasClass('select2')) {
                $histDoc.trigger('change.select2');
            }
        }

        if ($.fn.select2) {
            $('.select2').select2({ width: '100%' });
        }

        $histCat.on('change', function() {
            filterHistoryDoctors($(this).val());
        });

        if ($histCat.val()) {
            filterHistoryDoctors($histCat.val(), '{{ request("doctor_id") }}');
        }
    }
});
</script>
@endpush
