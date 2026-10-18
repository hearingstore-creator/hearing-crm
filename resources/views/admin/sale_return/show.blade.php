@extends('admin.layouts.master')
@section('page_title', 'Credit Note: ' . $saleReturn->credit_note_no)
@section('breadcrumb_parent', 'Sales Returns')
@section('breadcrumb_parent_url', route('sale-returns.index'))

@push('css')
<style>
@media print {
    body * {
        visibility: hidden;
    }
    #print-area, #print-area * {
        visibility: visible;
    }
    #print-area {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 16px;
    }
    .no-print {
        display: none !important;
    }
    .credit-note-card {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
}

.credit-note-card {
    background: #fff;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    max-width: 900px;
    margin: 0 auto;
    padding: 36px 40px;
}
.cn-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 20px;
    margin-bottom: 24px;
}
.cn-title {
    font-size: 24px;
    font-weight: 800;
    color: #6366f1;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
    text-transform: uppercase;
}
.cn-badge {
    display: inline-block;
    background: #e0e7ff;
    color: #3730a3;
    padding: 4px 10px;
    border-radius: 4px;
    font-weight: 700;
    font-size: 13px;
    letter-spacing: 0.5px;
}
.info-section {
    display: flex;
    justify-content: space-between;
    margin-bottom: 24px;
    font-size: 13px;
}
.info-col {
    flex: 1;
}
.info-col h6 {
    font-size: 12px;
    text-transform: uppercase;
    font-weight: 700;
    color: #64748b;
    margin-bottom: 8px;
    letter-spacing: 0.5px;
}
.items-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 24px;
    font-size: 13px;
}
.items-table th {
    background: #f8fafc;
    border-top: 1px solid #cbd5e1;
    border-bottom: 2px solid #cbd5e1;
    padding: 10px 12px;
    text-align: left;
    font-weight: 700;
    color: #334155;
    font-size: 11px;
    text-transform: uppercase;
}
.items-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: top;
}
.serial-pill {
    display: inline-block;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    color: #334155;
    padding: 1px 6px;
    border-radius: 3px;
    font-family: monospace;
    font-size: 11px;
    font-weight: 600;
    margin: 2px 2px 2px 0;
}
.totals-area {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 30px;
}
.totals-table {
    width: 320px;
    font-size: 13px;
}
.totals-table td {
    padding: 6px 10px;
}
.totals-table .grand-total {
    font-size: 16px;
    font-weight: 800;
    color: #15803d;
    border-top: 2px solid #0f172a;
    border-bottom: 2px solid #0f172a;
}
.signatures-area {
    display: flex;
    justify-content: space-between;
    margin-top: 60px;
    padding-top: 20px;
}
.sign-box {
    text-align: center;
    width: 180px;
}
.sign-line {
    border-top: 1px dashed #94a3b8;
    margin-bottom: 6px;
}
.sign-label {
    font-size: 11px;
    color: #64748b;
    text-transform: uppercase;
    font-weight: 600;
}
</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Top Action Bar (No Print) --}}
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div>
            <a href="{{ route('sale-returns.index') }}" class="btn btn-outline-secondary btn-sm mr-1">
                <i class="fas fa-arrow-left mr-1"></i>Back to Returns
            </a>
            @if ($saleReturn->saleMaster)
                <a href="{{ route('sale.show', $saleReturn->saleMaster->id) }}" class="btn btn-outline-info btn-sm">
                    <i class="fas fa-external-link-alt mr-1"></i>View Original Sale
                </a>
            @endif
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-primary btn-sm">
                <i class="fas fa-print mr-1"></i>Print Credit Note Voucher
            </button>
        </div>
    </div>

    {{-- Printable Credit Note Document --}}
    <div id="print-area">
        <div class="credit-note-card">

            {{-- Dual-Branding Document Header (Hearing Store HQ + Branch) --}}
            @include('admin.partials.document_header', [
                'vendor'       => $saleReturn->vendor,
                'setting'      => $setting,
                'documentType' => 'CREDIT NOTE',
                'documentNo'   => $saleReturn->credit_note_no,
                'date'         => $saleReturn->date,
                'status'       => 'Credit Issued',
                'statusClass'  => 'completed',
            ])

            {{-- Patient & Settlement Details --}}
            <div class="info-section">
                <div class="info-col">
                    <h6>Customer / Patient Information</h6>
                    <div style="font-weight: 700; font-size: 15px; color: #0f172a;">
                        {{ $saleReturn->customer?->lead?->full_name ?? ($saleReturn->customer?->name ?? 'N/A') }}
                    </div>
                    <div style="color: #475569; margin-top: 2px;">
                        Phone: <strong>{{ $saleReturn->customer?->lead?->phone_number ?? ($saleReturn->customer?->phone ?? '—') }}</strong>
                    </div>
                    @if ($saleReturn->customer?->customer_uid)
                        <div style="color: #64748b; font-size: 12px;">
                            Patient UID: {{ $saleReturn->customer->customer_uid }}
                        </div>
                    @endif
                    @if ($saleReturn->customer?->lead?->address)
                        <div style="color: #64748b; font-size: 12px;">
                            {{ $saleReturn->customer->lead->address }}
                        </div>
                    @endif
                </div>
                <div class="info-col" style="text-align: right;">
                    <h6>Reference & Settlement Resolution</h6>
                    @if ($saleReturn->sale_invoice_no)
                        <div style="font-size: 13px; color: #0f172a; margin-bottom: 3px;">
                            Original Sale Invoice: <strong>#{{ $saleReturn->sale_invoice_no }}</strong>
                        </div>
                    @endif
                    <div style="color: #64748b; font-size: 12px;">
                        Processed by: {{ $saleReturn->creator?->name ?? 'System' }}
                    </div>
                    <div style="margin-top: 6px;">
                        <span style="font-size: 11px; text-transform: uppercase; color: #64748b;">Settlement Method:</span><br>
                        @if($saleReturn->settlement_type === 'due_reduction')
                            <strong style="color: #1e40af;"><i class="fas fa-file-invoice mr-1"></i>Due Reduction on Invoice</strong>
                        @elseif($saleReturn->settlement_type === 'store_credit')
                            <strong style="color: #15803d;"><i class="fas fa-wallet mr-1"></i>Store Credit / Patient Wallet</strong>
                        @elseif($saleReturn->settlement_type === 'cash_refund')
                            <strong style="color: #b91c1c;"><i class="fas fa-hand-holding-usd mr-1"></i>Cash / Bank Refund</strong>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Items Table --}}
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Item Description & Details</th>
                        <th style="width: 90px; text-align: center;">Sold Qty</th>
                        <th style="width: 90px; text-align: center;">Return Qty</th>
                        <th style="width: 110px; text-align: right;">Unit Price (৳)</th>
                        <th style="width: 100px; text-align: right;">Deduction (৳)</th>
                        <th style="width: 120px; text-align: right;">Net Credit (৳)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($saleReturn->details as $index => $detail)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>
                                @if ($detail->isService())
                                    <div style="font-weight: 700; color: #0f172a;">
                                        {{ $detail->serviceType?->service_name ?? 'Clinical Service' }}
                                    </div>
                                    <div style="margin-top: 3px;">
                                        <span style="display: inline-block; background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 700; text-transform: uppercase;">
                                            <i class="fas fa-stethoscope mr-1"></i>Clinical Service / Fee Cancellation (Non-Stock)
                                        </span>
                                    </div>
                                @else
                                    <div style="font-weight: 700; color: #0f172a;">
                                        {{ $detail->product?->product_name ?? ($detail->product?->model ?? 'Product #' . $detail->product_id) }}
                                    </div>
                                    <div style="margin-top: 3px;">
                                        <span style="display: inline-block; background: #dcfce7; color: #166534; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 700; text-transform: uppercase;">
                                            <i class="fas fa-boxes mr-1"></i>Physical Product (Stock Restocked)
                                        </span>
                                    </div>
                                    @if (!empty($detail->batch?->batch_no))
                                        <div style="font-size: 11px; color: #64748b; margin-top: 2px;">Batch: {{ $detail->batch->batch_no }}</div>
                                    @endif
                                    @if (!empty($detail->serial_no))
                                        <div style="margin-top: 4px;">
                                            <span style="font-size: 11px; color: #64748b; font-weight: 600;">Returned Serial(s):</span>
                                            @foreach ((array) $detail->serial_no as $sn)
                                                <span class="serial-pill">{{ $sn }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                @endif
                            </td>
                            <td style="text-align: center; color: #64748b;">{{ number_format($detail->sold_qty, 0) }}</td>
                            <td style="text-align: center; font-weight: 700; color: #0f172a;">{{ number_format($detail->return_qty, 0) }}</td>
                            <td style="text-align: right;">৳{{ number_format($detail->unit_price, 2) }}</td>
                            <td style="text-align: right; color: #d97706;">
                                @if($detail->deduction_amount > 0)
                                    -৳{{ number_format($detail->deduction_amount, 2) }}
                                    @if($detail->deduction_rate > 0)
                                        <div style="font-size: 10px; color: #94a3b8;">({{ $detail->deduction_rate }}%)</div>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td style="text-align: right; font-weight: 700; color: #15803d;">
                                ৳{{ number_format($detail->net_return_amount, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Totals Area --}}
            <div class="totals-area">
                <table class="totals-table">
                    <tr>
                        <td style="color: #64748b;">Gross Returned Value:</td>
                        <td style="text-align: right; font-weight: 600;">৳{{ number_format($saleReturn->return_subtotal, 2) }}</td>
                    </tr>
                    @if ($saleReturn->deduction_amount > 0)
                        <tr>
                            <td style="color: #d97706;">Usage / Restocking Deductions:</td>
                            <td style="text-align: right; font-weight: 600; color: #d97706;">-৳{{ number_format($saleReturn->deduction_amount, 2) }}</td>
                        </tr>
                    @endif
                    <tr class="grand-total">
                        <td>Net Credit Note Value:</td>
                        <td style="text-align: right;">৳{{ number_format($saleReturn->credit_note_amount, 2) }}</td>
                    </tr>
                    @if ($saleReturn->due_reduced_amount > 0)
                        <tr>
                            <td style="color: #1e40af; font-size: 12px; padding-top: 8px;">Applied to Clear Invoice Due:</td>
                            <td style="text-align: right; font-weight: 600; color: #1e40af; font-size: 12px; padding-top: 8px;">-৳{{ number_format($saleReturn->due_reduced_amount, 2) }}</td>
                        </tr>
                    @endif
                    @if ($saleReturn->patient_refund_amount > 0)
                        <tr>
                            <td style="color: #b91c1c; font-size: 12px;">Cash / Bank Refund to Patient:</td>
                            <td style="text-align: right; font-weight: 600; color: #b91c1c; font-size: 12px;">৳{{ number_format($saleReturn->patient_refund_amount, 2) }}</td>
                        </tr>
                    @endif
                    @php
                        $retainedStoreCredit = max(0, (float)$saleReturn->credit_note_amount - (float)$saleReturn->due_reduced_amount - (float)$saleReturn->patient_refund_amount);
                    @endphp
                    @if ($retainedStoreCredit > 0)
                        <tr>
                            <td style="color: #15803d; font-size: 12px;">Store Credit / Patient Wallet:</td>
                            <td style="text-align: right; font-weight: 600; color: #15803d; font-size: 12px;">৳{{ number_format($retainedStoreCredit, 2) }}</td>
                        </tr>
                    @endif
                </table>
            </div>

            {{-- Clinical Return Reason & Doctor Note --}}
            @if ($saleReturn->reason || $saleReturn->doctor_commission_adjusted > 0)
                <div style="background: #f8fafc; border-radius: 6px; padding: 12px 16px; margin-bottom: 30px; font-size: 12px; border-left: 3px solid #6366f1;">
                    @if ($saleReturn->reason)
                        <div>
                            <strong style="color: #334155;">Return Reason / Clinical Notes:</strong>
                            <span style="color: #64748b;">{{ $saleReturn->reason }}</span>
                        </div>
                    @endif
                    @if ($saleReturn->doctor_commission_adjusted > 0)
                        <div style="margin-top: 4px; color: #0284c7;">
                            <strong>Doctor Referral Commission Adjustment:</strong> ৳{{ number_format($saleReturn->doctor_commission_adjusted, 2) }} clawback posted to Dr. {{ $saleReturn->doctor?->name }}'s ledger.
                        </div>
                    @endif
                </div>
            @endif

            {{-- Signature Blocks --}}
            <div class="signatures-area">
                <div class="sign-box">
                    <div class="sign-line"></div>
                    <div class="sign-label">Customer / Patient</div>
                </div>
                <div class="sign-box">
                    <div class="sign-line"></div>
                    <div class="sign-label">Audiologist / Examiner</div>
                </div>
                <div class="sign-box">
                    <div class="sign-line"></div>
                    <div class="sign-label">Branch Manager / Accounts</div>
                </div>
            </div>

            {{-- Footer Note --}}
            <div style="text-align: center; font-size: 10px; color: #94a3b8; margin-top: 36px; border-top: 1px solid #f1f5f9; padding-top: 12px;">
                This is an official Credit Note Voucher generated by {{ config('app.name', 'ZenHear CRM') }}.
            </div>

        </div>
    </div>

</div>
@endsection
