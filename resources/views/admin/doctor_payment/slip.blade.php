@extends('admin.layouts.master')
@section('page_title', 'Doctor Payment Voucher: ' . $payment->ref_invoice_no)
@section('breadcrumb_parent', 'Doctor Payments')
@section('breadcrumb_parent_url', route('doctors.payment.index'))

@push('css')
<style>
@media print {
    body * {
        visibility: hidden;
    }
    #print-voucher-area, #print-voucher-area * {
        visibility: visible;
    }
    #print-voucher-area {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 20px;
    }
    .no-print {
        display: none !important;
    }
    .voucher-card {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
}

.voucher-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
    max-width: 860px;
    margin: 0 auto;
    padding: 36px 42px;
}

.voucher-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin: 20px 0;
    padding: 16px 20px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}

.voucher-field {
    margin-bottom: 6px;
    font-size: 13px;
    color: #334155;
}

.voucher-field strong {
    color: #0f172a;
    display: inline-block;
    min-width: 140px;
}

.amount-box {
    background: #f0fdf4;
    border: 2px solid #86efac;
    border-radius: 8px;
    padding: 16px 24px;
    text-align: center;
    margin: 20px 0;
}

.amount-box .amount-num {
    font-size: 26px;
    font-weight: 800;
    color: #15803d;
}

.amount-box .amount-words {
    font-size: 13px;
    color: #166534;
    font-style: italic;
    margin-top: 4px;
}

.sig-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 30px;
    margin-top: 60px;
    padding-top: 10px;
}

.sig-box {
    text-align: center;
    border-top: 1px dashed #94a3b8;
    padding-top: 6px;
    font-size: 12px;
    font-weight: 600;
    color: #475569;
}
</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    {{-- Header with action buttons --}}
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <div>
            <h5 class="mb-0 font-weight-bold text-dark">Payment Voucher</h5>
            <small class="text-muted">Voucher #{{ $payment->ref_invoice_no }}</small>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('doctors.payment.index') }}" class="btn btn-secondary btn-sm mr-2">
                <i class="fas fa-arrow-left mr-1"></i>Back to Payments
            </a>
            <a href="{{ route('doctors.payment.create') }}" class="btn btn-outline-primary btn-sm mr-2">
                <i class="fas fa-plus mr-1"></i>New Payment
            </a>
            <button type="button" onclick="window.print()" class="btn btn-primary btn-sm">
                <i class="fas fa-print mr-1"></i>Print Voucher
            </button>
        </div>
    </div>

    @include('admin.alert_message')

    {{-- Printable Area --}}
    <div class="voucher-card" id="print-voucher-area">
        {{-- Company Header --}}
        <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3">
            <div>
                <h4 class="font-weight-bold mb-1" style="color: #0f172a; letter-spacing: -0.5px;">
                    {{ $setting->company_name ?? 'HEARING STORE CRM' }}
                </h4>
                <div class="text-muted" style="font-size: 12px; line-height: 1.4;">
                    @if(!empty($setting->address)) {{ $setting->address }}<br> @endif
                    @if(!empty($setting->contact_no)) Phone: {{ $setting->contact_no }} @endif
                    @if(!empty($setting->email)) &nbsp;·&nbsp; Email: {{ $setting->email }} @endif
                </div>
            </div>
            <div class="text-right">
                <div class="badge badge-primary px-3 py-2 text-uppercase font-weight-bold" style="font-size: 13px; letter-spacing: 1px;">
                    DOCTOR PAYMENT VOUCHER
                </div>
                <div class="font-weight-bold mt-2" style="font-size: 14px; color: #0f172a;">
                    #{{ $payment->ref_invoice_no }}
                </div>
                <div class="text-muted" style="font-size: 12px;">
                    Date: {{ $payment->date ? $payment->date->format('d M Y') : '—' }}
                </div>
            </div>
        </div>

        {{-- Meta Grid: Doctor Info & Payment Particulars --}}
        <div class="voucher-grid">
            <div>
                <div class="text-uppercase font-weight-bold text-muted mb-2" style="font-size: 11px; letter-spacing: 0.5px;">
                    Payee (Doctor) Details
                </div>
                <div class="voucher-field">
                    <strong>Doctor Name:</strong> {{ $payment->doctor->name ?? '—' }}
                </div>
                @if(!empty($payment->doctor->qualification))
                <div class="voucher-field">
                    <strong>Qualification:</strong> {{ $payment->doctor->qualification }}
                </div>
                @endif
                @if(!empty($payment->doctor->contact_number))
                <div class="voucher-field">
                    <strong>Contact Number:</strong> {{ $payment->doctor->contact_number }}
                </div>
                @endif
                <div class="voucher-field">
                    <strong>Branch:</strong> {{ $payment->doctor->vendor->supplier_name ?? ($payment->doctor->vendor->name ?? 'Head Office') }}
                </div>
            </div>
            <div>
                <div class="text-uppercase font-weight-bold text-muted mb-2" style="font-size: 11px; letter-spacing: 0.5px;">
                    Payment Particulars
                </div>
                <div class="voucher-field">
                    <strong>Payment Method:</strong> <span class="badge badge-light border font-weight-bold">{{ $payment->payment_method ?: 'Cash' }}</span>
                </div>
                @if(!empty($payment->bank_name))
                <div class="voucher-field">
                    <strong>Bank / Wallet:</strong> {{ $payment->bank_name }}
                </div>
                @endif
                @if(!empty($payment->transaction_no))
                <div class="voucher-field">
                    <strong>Transaction/Cheque #:</strong> {{ $payment->transaction_no }}
                </div>
                @endif
                @if($payment->settlement_period_start && $payment->settlement_period_end)
                <div class="voucher-field">
                    <strong>Settlement Period:</strong> 
                    <span class="font-weight-bold text-primary">
                        {{ $payment->settlement_period_start->format('d M Y') }} — {{ $payment->settlement_period_end->format('d M Y') }}
                    </span>
                </div>
                @endif
                <div class="voucher-field">
                    <strong>Remaining Due:</strong> ৳{{ number_format($remainingDue, 2) }}
                </div>
            </div>
        </div>

        {{-- Amount Box --}}
        <div class="amount-box">
            <div class="text-uppercase font-weight-bold" style="font-size: 11px; letter-spacing: 1px; color: #15803d;">
                AMOUNT PAID
            </div>
            <div class="amount-num">
                ৳{{ number_format($payment->debit, 2) }}
            </div>
            @if(!empty($payment->details))
                <div class="text-muted mt-1" style="font-size: 12px;">{{ $payment->details }}</div>
            @endif
        </div>

        {{-- Settled Invoices Breakdown (If available) --}}
        @if($settledSales->isNotEmpty())
        <div class="mt-4">
            <h6 class="font-weight-bold text-dark mb-2" style="font-size: 12.5px;">
                <i class="fas fa-list-alt text-primary mr-1"></i> Patient Sales Settled in this Period ({{ $settledSales->count() }})
            </h6>
            <table class="table table-sm table-bordered" style="font-size: 11.5px;">
                <thead class="bg-light">
                    <tr>
                        <th style="width: 35px;">#</th>
                        <th>Date</th>
                        <th>Patient Name</th>
                        <th>Invoice #</th>
                        <th style="text-align: right;">Sale Amount (৳)</th>
                        <th style="text-align: right;">Commission (৳)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($settledSales as $sIdx => $sale)
                    <tr>
                        <td class="text-center text-muted">{{ $sIdx + 1 }}</td>
                        <td>{{ \Carbon\Carbon::parse($sale->date)->format('d M Y') }}</td>
                        <td class="font-weight-600">{{ $sale->patient_name }}</td>
                        <td>#{{ $sale->sale_invoice_no }}</td>
                        <td style="text-align: right;">৳{{ number_format($sale->total_amount, 2) }}</td>
                        <td style="text-align: right; font-weight: 700; color: #15803d;">৳{{ number_format($sale->reference_amount, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-light font-weight-bold">
                    <tr>
                        <td colspan="4">Total Commission Earned in Period</td>
                        <td style="text-align: right;">৳{{ number_format($settledSales->sum('total_amount'), 2) }}</td>
                        <td style="text-align: right; color: #15803d;">৳{{ number_format($settledSales->sum('reference_amount'), 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @endif

        {{-- Signatures --}}
        <div class="sig-grid">
            <div class="sig-box">
                Prepared By<br>
                <small class="text-muted">{{ auth()->user()->name ?? 'Accounts' }}</small>
            </div>
            <div class="sig-box">
                Checked & Verified<br>
                <small class="text-muted">Accounts Department</small>
            </div>
            <div class="sig-box">
                Doctor / Payee Signature<br>
                <small class="text-muted">{{ $payment->doctor->name ?? 'Doctor' }}</small>
            </div>
        </div>

        {{-- Footer note --}}
        <div class="text-center text-muted mt-4 pt-2 border-top" style="font-size: 10.5px;">
            System-generated payment voucher on {{ now()->format('d M Y, h:i A') }} · Hearing Store CRM
        </div>
    </div>
</div>
@endsection
