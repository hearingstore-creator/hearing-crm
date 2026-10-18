@extends('admin.layouts.master')
@section('page_title', 'Payment Voucher: ' . $payment->ref_invoice_no)
@section('breadcrumb_parent', 'Supplier Payments')
@section('breadcrumb_parent_url', route('suppliers.payment.index'))

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
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
    max-width: 900px;
    margin: 0 auto;
    padding: 36px 42px;
}

.voucher-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin: 24px 0;
    padding: 16px 20px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}

.voucher-field {
    margin-bottom: 8px;
    font-size: 13px;
    color: #334155;
}

.voucher-field strong {
    color: #0f172a;
    display: inline-block;
    min-width: 120px;
}

.balance-strip {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 16px;
    margin: 24px 0;
}

.balance-box {
    padding: 16px;
    border-radius: 8px;
    text-align: center;
    border: 1px solid #e2e8f0;
}

.balance-box.prev {
    background: #f1f5f9;
}

.balance-box.paid {
    background: #ecfdf5;
    border-color: #a7f3d0;
}

.balance-box.rem {
    background: #fef2f2;
    border-color: #fecaca;
}

.balance-box .b-label {
    font-size: 11px;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

.balance-box.prev .b-label { color: #475569; }
.balance-box.paid .b-label { color: #047857; }
.balance-box.rem .b-label { color: #b91c1c; }

.balance-box .b-amount {
    font-size: 20px;
    font-weight: 800;
}

.balance-box.prev .b-amount { color: #1e293b; }
.balance-box.paid .b-amount { color: #059669; }
.balance-box.rem .b-amount { color: #dc2626; }

.words-box {
    background: #f8fafc;
    border-left: 4px solid #3b82f6;
    padding: 14px 18px;
    border-radius: 4px;
    margin: 20px 0;
    font-size: 14px;
}

.words-box .w-title {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 2px;
}

.words-box .w-text {
    font-weight: 700;
    color: #1e293b;
    font-style: italic;
}

.signatures-row {
    display: flex;
    justify-content: space-between;
    margin-top: 70px;
    padding-top: 20px;
}

.sign-block {
    text-align: center;
    width: 200px;
}

.sign-line {
    border-top: 1px dashed #94a3b8;
    margin-bottom: 6px;
}

.sign-title {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
}
</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Top Action Bar (No Print) --}}
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div>
            <a href="{{ route('suppliers.payment.index') }}" class="btn btn-outline-secondary btn-sm mr-1">
                <i class="fas fa-arrow-left mr-1"></i>Back to Payments
            </a>
            <a href="{{ route('suppliers.payment.create') }}" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-plus mr-1"></i>New Payment
            </a>
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-primary btn-sm">
                <i class="fas fa-print mr-1"></i>Print Payment Voucher
            </button>
        </div>
    </div>

    @include('admin.alert_message')

    {{-- Printable Payment Voucher --}}
    <div id="print-voucher-area">
        <div class="voucher-card">

            {{-- Dual-Wing Document Header (Hearing Store HQ + Branch) --}}
            @include('admin.partials.document_header', [
                'vendor'       => $payment->supplier?->vendor,
                'setting'      => $setting,
                'documentType' => 'PAYMENT VOUCHER',
                'documentNo'   => $payment->ref_invoice_no,
                'date'         => $payment->date,
                'status'       => 'Paid / Disbursed',
                'statusClass'  => 'completed',
            ])

            {{-- Voucher Details Grid --}}
            <div class="voucher-grid">
                <div>
                    <h6 style="font-size: 12px; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 12px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px;">
                        <i class="fas fa-truck mr-1 text-primary"></i>Paid To (Supplier)
                    </h6>
                    <div class="voucher-field">
                        <strong>Supplier Name:</strong>
                        <span style="font-weight: 700; color: #0f172a;">{{ $payment->supplier->supplier_name ?? '—' }}</span>
                    </div>
                    @if(!empty($payment->supplier->contact_person))
                    <div class="voucher-field">
                        <strong>Contact Person:</strong>
                        <span>{{ $payment->supplier->contact_person }}</span>
                    </div>
                    @endif
                    @if(!empty($payment->supplier->phone))
                    <div class="voucher-field">
                        <strong>Phone / Mobile:</strong>
                        <span>{{ $payment->supplier->phone }}</span>
                    </div>
                    @endif
                    @if(!empty($payment->supplier->address))
                    <div class="voucher-field">
                        <strong>Address:</strong>
                        <span>{{ $payment->supplier->address }}</span>
                    </div>
                    @endif
                </div>

                <div>
                    <h6 style="font-size: 12px; font-weight: 800; text-transform: uppercase; color: #64748b; margin-bottom: 12px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px;">
                        <i class="fas fa-money-check-alt mr-1 text-primary"></i>Payment Disbursement
                    </h6>
                    <div class="voucher-field">
                        <strong>Payment Method:</strong>
                        <span class="badge badge-primary px-2 py-1" style="font-size: 11px; text-transform: uppercase;">
                            {{ $payment->payment_method ?? 'Cash' }}
                        </span>
                    </div>
                    @if(!empty($payment->supplier_memo_no))
                    <div class="voucher-field">
                        <strong>Supplier Memo / MR:</strong>
                        <span style="font-family: monospace; font-weight: 700; color: #b91c1c; background: #fef2f2; padding: 2px 6px; border-radius: 4px; border: 1px solid #fecaca;">{{ $payment->supplier_memo_no }}</span>
                    </div>
                    @endif
                    @if(!empty($payment->bank_name))
                    <div class="voucher-field">
                        <strong>Bank Name:</strong>
                        <span style="font-weight: 600;">{{ $payment->bank_name }}</span>
                    </div>
                    @endif
                    @if(!empty($payment->transaction_no))
                    <div class="voucher-field">
                        <strong>Ref / Cheque / Txn:</strong>
                        <span style="font-family: monospace; font-weight: 700; color: #0369a1;">{{ $payment->transaction_no }}</span>
                    </div>
                    @endif
                    @if($payment->linkedPurchase)
                    <div class="voucher-field">
                        <strong>Against Purchase:</strong>
                        <span>Inv #{{ $payment->linkedPurchase->purchase_invoice_no }} ({{ $payment->linkedPurchase->date ? $payment->linkedPurchase->date->format('d M Y') : '' }})</span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- 3-Box Balance Strip --}}
            <div class="balance-strip">
                <div class="balance-box prev">
                    <div class="b-label">Previous Due Balance</div>
                    <div class="b-amount">৳{{ number_format((float)$previousDue, 2) }}</div>
                </div>
                <div class="balance-box paid">
                    <div class="b-label">Amount Paid This Slip</div>
                    <div class="b-amount">৳{{ number_format((float)$payment->debit, 2) }}</div>
                </div>
                <div class="balance-box rem">
                    <div class="b-label">Remaining Due Balance</div>
                    <div class="b-amount">৳{{ number_format((float)$remainingDue, 2) }}</div>
                </div>
            </div>

            {{-- Amount in Words --}}
            <div class="words-box">
                <div class="w-title">Amount In Words:</div>
                <div class="w-text">{{ $amountInWords }}</div>
            </div>

            @if(!empty($payment->details))
            <div style="font-size: 12px; color: #475569; margin: 12px 0; background: #fff; border: 1px dashed #cbd5e1; padding: 10px 14px; border-radius: 4px;">
                <strong style="color: #1e293b;">Remarks / Note:</strong> {{ $payment->details }}
            </div>
            @endif

            {{-- Signatures Area --}}
            <div class="signatures-row">
                <div class="sign-block">
                    <div class="sign-line"></div>
                    <div class="sign-title">Prepared By (Accounts)</div>
                </div>
                <div class="sign-block">
                    <div class="sign-line"></div>
                    <div class="sign-title">Authorized Signatory</div>
                </div>
                <div class="sign-block">
                    <div class="sign-line"></div>
                    <div class="sign-title">Supplier / Payee Signature</div>
                </div>
            </div>

        </div>
    </div>

</div>
@endsection
