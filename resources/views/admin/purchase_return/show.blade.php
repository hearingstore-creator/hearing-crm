@extends('admin.layouts.master')
@section('page_title', 'Purchase Return: ' . $return->return_no)
@section('breadcrumb_parent', 'Purchase Returns')
@section('breadcrumb_parent_url', route('purchase-returns.index'))

@push('css')
<style>
/* ─── Screen Styling ─── */
.debit-note-card {
    background: #fff;
    border-radius: 8px;
    border: 1px solid var(--gray-200);
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    max-width: 900px;
    margin: 0 auto;
    padding: 32px 36px;
    position: relative;
}
.dn-meta-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 24px;
}
.dn-party-box {
    background: #f8fafc;
    border-radius: 6px;
    padding: 14px 16px;
    border: 1px solid #e2e8f0;
}
.dn-party-label {
    font-size: 11px;
    font-weight: 700;
    color: var(--page-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}
.dn-party-name {
    font-size: 15px;
    font-weight: 700;
    color: var(--page-text-main);
    margin-bottom: 3px;
}
.dn-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}
.dn-table th {
    background: #f1f5f9;
    color: #1e293b;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    padding: 9px 10px;
    border-top: 1px solid #cbd5e1;
    border-bottom: 2px solid #cbd5e1;
}
.dn-table td {
    padding: 8px 10px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 12px;
    vertical-align: middle;
}
.dn-total-row td {
    font-weight: 700;
    font-size: 13px;
    border-top: 2px solid #cbd5e1;
    border-bottom: 2px solid #cbd5e1;
    background: #f8fafc;
}
.dn-signatures {
    display: flex;
    justify-content: space-between;
    margin-top: 50px;
    padding-top: 12px;
}
.dn-sig-line {
    text-align: center;
    width: 180px;
    border-top: 1px dashed #94a3b8;
    padding-top: 6px;
    font-size: 11px;
    color: #64748b;
}

/* Watermark banner for Pending */
.pending-watermark-banner {
    background: #fffbeb;
    border: 2px dashed #f59e0b;
    color: #b45309;
    border-radius: 6px;
    padding: 10px 14px;
    text-align: center;
    font-weight: 800;
    font-size: 14px;
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-bottom: 20px;
}

.confirmed-watermark-banner {
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #065f46;
    border-radius: 6px;
    padding: 12px 16px;
    margin-bottom: 20px;
}

/* ─── A4 Portrait Print Layout ─── */
@media print {
    @page {
        size: A4 portrait;
        margin: 10mm 12mm 12mm 12mm;
    }
    body {
        background: #fff !important;
        color: #000 !important;
        font-size: 11pt;
    }
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
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
        box-shadow: none !important;
    }
    .no-print {
        display: none !important;
    }
    .pending-watermark-banner {
        border: 2px solid #d97706 !important;
        color: #b45309 !important;
        background: #fef3c7 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .dn-signatures {
        page-break-inside: avoid;
        margin-top: 40px;
    }
    .print-footer-info {
        display: flex !important;
        justify-content: space-between;
        font-size: 9pt;
        color: #64748b;
        border-top: 1px solid #cbd5e1;
        margin-top: 24px;
        padding-top: 6px;
    }
}
.print-footer-info {
    display: none;
}
</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px 50px 24px;">

    {{-- Top Action Bar (No-Print) --}}
    <div class="d-flex justify-content-between align-items-center mb-3 no-print" style="max-width: 900px; margin: 0 auto 16px auto;">
        <div>
            <a href="{{ route('purchase-returns.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i>Back to Returns
            </a>
        </div>
        <div class="d-flex align-items-center" style="gap: 8px;">
            {{-- Confirm with supplier document button (Pending only) --}}
            @if($return->isPending())
                <button type="button" class="btn btn-success btn-sm font-weight-bold" data-toggle="modal" data-target="#confirmDocModal">
                    <i class="fas fa-file-check mr-1"></i>Confirm with supplier document
                </button>
            @endif

            {{-- Super Admin: Unconfirm button (Confirmed only) --}}
            @if($return->isConfirmed() && $isSuperAdmin)
                <button type="button" class="btn btn-warning btn-sm" data-toggle="modal" data-target="#unconfirmModal">
                    <i class="fas fa-undo-alt mr-1"></i>Unconfirm
                </button>
            @endif

            {{-- Super Admin: Edit button (Pending only) --}}
            @if($return->isPending() && $isSuperAdmin)
                <a href="{{ route('purchase-returns.edit', $return->id) }}" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-edit mr-1"></i>Edit
                </a>
            @endif

            {{-- Super Admin: Delete button (Pending only) --}}
            @if($return->isPending() && $isSuperAdmin)
                <button type="button" class="btn btn-outline-danger btn-sm" data-toggle="modal" data-target="#deleteReturnModal">
                    <i class="fas fa-trash-alt mr-1"></i>Delete
                </button>
            @endif

            {{-- Print Button --}}
            <button type="button" onclick="window.print()" class="btn btn-primary btn-sm">
                <i class="fas fa-print mr-1"></i>Print A4
            </button>
        </div>
    </div>

    @include('admin.alert_message')

    {{-- On-Screen Status Alert Banner (No-Print) --}}
    <div class="no-print" style="max-width: 900px; margin: 0 auto 16px auto;">
        @if($return->isPending())
            <div class="alert alert-warning d-flex align-items-center justify-content-between p-3" style="border-radius: 8px;">
                <div>
                    <h6 class="mb-1 font-weight-bold"><i class="fas fa-clock mr-2"></i>Status: Pending Supplier Confirmation</h6>
                    <div style="font-size: 13px;">
                        The goods have physically left inventory (Stock deducted). <b>No accounting entry has been posted yet</b> — supplier payable balance remains unchanged until confirmation.
                        @if($return->days_pending > 0)
                            <span class="badge badge-danger ml-1">{{ $return->days_pending }} days pending</span>
                        @endif
                    </div>
                </div>
                <div>
                    <button type="button" class="btn btn-success btn-sm font-weight-bold text-nowrap" data-toggle="modal" data-target="#confirmDocModal">
                        <i class="fas fa-clipboard-check mr-1"></i>Confirm Now
                    </button>
                </div>
            </div>
        @elseif($return->isConfirmed())
            <div class="alert alert-success p-3" style="border-radius: 8px;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-1 font-weight-bold text-success">
                            <i class="fas fa-check-circle mr-2"></i>Reconciled & Confirmed with Supplier Document
                        </h6>
                        <div style="font-size: 13px; color: #166534;">
                            Supplier Return Memo / Credit Note No(s): <b>{{ $return->supplier_doc_no ?: '—' }}</b> &nbsp;|&nbsp;
                            Memo Date: <b>{{ $return->supplier_doc_date ? $return->supplier_doc_date->format('d M Y') : '—' }}</b> &nbsp;|&nbsp;
                            Accepted: <b>৳{{ number_format($return->accepted_amount, 2) }}</b>
                            @if((float)$return->loss_amount > 0)
                                &nbsp;|&nbsp; <span class="badge badge-danger font-weight-bold">Loss: ৳{{ number_format($return->loss_amount, 2) }}</span>
                            @endif
                        </div>
                        <div style="font-size: 11px; color: #4b5563; margin-top: 4px;">
                            Confirmed by: <b>{{ $return->confirmedBy->name ?? 'Staff' }}</b> on {{ $return->confirmed_at ? $return->confirmed_at->format('d M Y, h:i A') : '—' }}
                            @if(!empty($return->attachment))
                                &nbsp;|&nbsp; <a href="{{ asset($return->attachment) }}" target="_blank" class="text-primary font-weight-bold"><i class="fas fa-paperclip mr-1"></i>View Document Attachment</a>
                            @endif
                        </div>
                        @if($return->remarks)
                            <div style="font-size: 12px; color: #374151; margin-top: 4px; font-style: italic;">
                                Remarks: {{ $return->remarks }}
                            </div>
                        @endif
                    </div>
                    @if($isSuperAdmin)
                        <div>
                            <button type="button" class="btn btn-outline-warning btn-sm" data-toggle="modal" data-target="#unconfirmModal">
                                <i class="fas fa-undo mr-1"></i>Unconfirm
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        @elseif($return->isCancelled())
            <div class="alert alert-secondary p-3" style="border-radius: 8px;">
                <h6 class="mb-1 font-weight-bold text-danger"><i class="fas fa-ban mr-2"></i>Status: Voided / Cancelled</h6>
                <div style="font-size: 13px;">
                    This return has been voided and inventory reversed.
                    @if($return->delete_reason)
                        <br><i>Reason: {{ $return->delete_reason }}</i>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- Printable A4 Sheet --}}
    <div class="debit-note-card" id="print-area">

        {{-- Dual-Branding Document Header (Hearing Store HQ + Branch) --}}
        @include('admin.partials.document_header', [
            'vendor'       => $return->vendor,
            'setting'      => $setting,
            'documentType' => 'PURCHASE RETURN',
            'documentNo'   => $return->return_no,
            'date'         => $return->date,
            'status'       => $return->isConfirmed() ? 'Confirmed' : ($return->isPending() ? 'Pending Confirmation' : 'Cancelled'),
            'statusClass'  => $return->isConfirmed() ? 'completed' : ($return->isPending() ? 'partial' : 'unpaid'),
        ])

        {{-- Watermark Banner for Pending on Print and Screen --}}
        @if($return->isPending())
            <div class="pending-watermark-banner">
                <i class="fas fa-clock mr-1"></i> PENDING SUPPLIER CONFIRMATION — NO ACCOUNTING ENTRY POSTED YET
            </div>
        @endif

        {{-- Meta Grid: Supplier & Reference Information --}}
        <div class="dn-meta-grid">
            <div class="dn-party-box">
                <div class="dn-party-label">Supplier Information</div>
                <div class="dn-party-name">{{ $return->supplier->supplier_name ?? '—' }}</div>
                <div style="font-size: 12px; color: var(--page-text-muted); line-height: 1.5;">
                    @if($return->supplier && $return->supplier->address1)
                        {{ $return->supplier->address1 }}<br>
                    @endif
                    @if($return->supplier && $return->supplier->mobile_no)
                        Mobile: {{ $return->supplier->mobile_no }}<br>
                    @endif
                    @if($return->supplier && $return->supplier->email1)
                        Email: {{ $return->supplier->email1 }}
                    @endif
                </div>
            </div>

            <div class="dn-party-box">
                <div class="dn-party-label">Reference Information</div>
                <div style="font-size: 12px; color: var(--page-text-main); line-height: 1.6;">
                    @php
                        $linkedPurchases = $return->linked_purchases;
                        $memoNos = $return->supplier_memo_nos_array;
                        $docNos = $return->supplier_doc_nos_array;
                    @endphp

                    {{-- Original Purchase Invoice Nos --}}
                    <div>
                        <b>Our Purchase Invoice:</b>
                        @if($linkedPurchases->count() > 1)
                            <span>{{ $linkedPurchases->pluck('purchase_invoice_no')->implode(', ') }}</span>
                        @elseif($linkedPurchases->count() === 1)
                            <span>{{ $linkedPurchases->first()->purchase_invoice_no }}</span>
                        @else
                            <span>Direct Stock Return</span>
                        @endif
                    </div>

                    {{-- Supplier Original Memo / Bill Nos (supports multiple) --}}
                    <div>
                        <b>Supplier Memo No(s):</b>
                        @if(count($memoNos) > 0)
                            <span style="font-family: monospace; font-weight: 700; color: #0369a1;">
                                {{ implode(', ', $memoNos) }}
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </div>

                    {{-- Supplier Confirmation Document Nos & Date (when Confirmed) --}}
                    @if($return->isConfirmed())
                        <div>
                            <b>Supplier Memo / Credit Note No(s):</b>
                            <span style="font-family: monospace; font-weight: 700; color: #15803d;">
                                {{ count($docNos) > 0 ? implode(', ', $docNos) : ($return->supplier_doc_no ?: '—') }}
                            </span>
                        </div>
                        <div>
                            <b>Supplier Memo Date:</b>
                            <span>{{ $return->supplier_doc_date ? $return->supplier_doc_date->format('d M Y') : '—' }}</span>
                        </div>
                    @endif

                    <div>
                        <b>Created By:</b>
                        <span>{{ $return->creator->name ?? 'Staff' }} ({{ $return->created_at ? $return->created_at->format('d M Y') : '—' }})</span>
                    </div>

                    @if(!empty($return->reason))
                        <div><b>Return Reason:</b> <i>{{ $return->reason }}</i></div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Returned Items Table --}}
        <table class="dn-table">
            <thead>
                <tr>
                    <th style="width: 35px;">SL</th>
                    <th>Product & Model</th>
                    <th>Serial No(s)</th>
                    <th style="text-align: center; width: 65px;">Qty</th>
                    <th style="text-align: right; width: 105px;">Rate (৳)</th>
                    <th style="text-align: right; width: 115px;">Total (৳)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($return->details as $idx => $d)
                    <tr>
                        <td style="color: var(--page-text-muted); text-align: center;">{{ $idx + 1 }}</td>
                        <td>
                            <strong>{{ $d->product->product_name ?? 'Product' }}</strong>
                            @if($d->product && $d->product->model_name)
                                <div style="font-size: 11px; color: var(--page-text-muted);">Model: {{ $d->product->model_name }}</div>
                            @endif
                            @if($d->purchaseMaster)
                                <div style="font-size: 11px; color: #0284c7; font-family: monospace;">
                                    Bill: {{ $d->purchaseMaster->purchase_invoice_no }}
                                </div>
                            @endif
                        </td>
                        <td>
                            @if(!empty($d->serial_no) && is_array($d->serial_no))
                                @foreach($d->serial_no as $sn)
                                    <span style="display: inline-block; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 3px; padding: 1px 5px; font-size: 10px; font-family: monospace; margin: 1px;">
                                        {{ $sn }}
                                    </span>
                                @endforeach
                            @else
                                <span style="color: var(--page-text-muted); font-size: 11px;">—</span>
                            @endif
                        </td>
                        <td style="text-align: center; font-weight: 600;">{{ number_format($d->qty, 0) }}</td>
                        <td style="text-align: right;">৳{{ number_format($d->purchase_price, 2) }}</td>
                        <td style="text-align: right; font-weight: 600;">৳{{ number_format($d->total_price, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="dn-total-row">
                    <td colspan="3" style="text-align: right; text-transform: uppercase;">Total Return Amount:</td>
                    <td style="text-align: center;">{{ number_format($return->details->sum('qty'), 0) }}</td>
                    <td></td>
                    <td style="text-align: right; color: #b91c1c; font-size: 13px;">৳{{ number_format($return->total_amount, 2) }}</td>
                </tr>

                @if($return->isConfirmed())
                    <tr style="background: #f0fdf4; border-bottom: 1px solid #bbf7d0;">
                        <td colspan="5" style="text-align: right; font-weight: 700; color: #166534;">
                            Amount Accepted by Supplier:
                        </td>
                        <td style="text-align: right; font-weight: 800; color: #166534; font-size: 13px;">
                            ৳{{ number_format($return->accepted_amount, 2) }}
                        </td>
                    </tr>
                    @if((float) $return->loss_amount > 0)
                        <tr style="background: #fef2f2; border-bottom: 2px solid #fecaca;">
                            <td colspan="5" style="text-align: right; font-weight: 700; color: #991b1b;">
                                Purchase Return Loss (Short/Disallowed Difference):
                            </td>
                            <td style="text-align: right; font-weight: 800; color: #991b1b; font-size: 13px;">
                                ৳{{ number_format($return->loss_amount, 2) }}
                            </td>
                        </tr>
                    @endif
                @endif
            </tfoot>
        </table>

        {{-- Amount in words --}}
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; margin-bottom: 18px; font-size: 12px;">
            <b>Amount in Words:</b>
            <span style="text-transform: capitalize; color: #1e293b;">
                @if($return->isConfirmed())
                    {{ \App\Classes\Helper::numberToWords($return->accepted_amount) }} (Accepted by Supplier)
                @else
                    {{ \App\Classes\Helper::numberToWords($return->total_amount) }}
                @endif
            </span>
        </div>

        {{-- Accounting / Status Note --}}
        <div style="background: {{ $return->isConfirmed() ? '#f0fdf4' : '#fffbeb' }}; border: 1px solid {{ $return->isConfirmed() ? '#bbf7d0' : '#fde68a' }}; border-radius: 6px; padding: 10px 14px; font-size: 11px; color: {{ $return->isConfirmed() ? '#166534' : '#92400e' }}; margin-bottom: 24px; line-height: 1.5;">
            @if($return->isConfirmed())
                <strong>Accounting Note:</strong> This debit note has been confirmed and posted to accounts. Our payable balance to <b>{{ $return->supplier->supplier_name ?? 'the supplier' }}</b> has been debited by <b>৳{{ number_format($return->accepted_amount, 2) }}</b> as per Supplier Document #<b>{{ $return->supplier_doc_no }}</b> dated <b>{{ $return->supplier_doc_date ? $return->supplier_doc_date->format('d M Y') : '—' }}</b>.
                @if((float) $return->loss_amount > 0)
                    The difference of <b>৳{{ number_format($return->loss_amount, 2) }}</b> was posted to the corporate expense account <i>Purchase Return Loss</i>.
                @endif
            @else
                <strong>Pending Supplier Reconciliation:</strong> This document reflects goods dispatched from our premises. Physical inventory has been deducted. Accounting postings will be recorded upon receipt and verification of the supplier's credit memo.
            @endif
        </div>

        {{-- Remarks if any --}}
        @if(!empty($return->remarks))
            <div style="font-size: 11px; color: #475569; margin-bottom: 20px;">
                <strong>Remarks / Notes:</strong> {{ $return->remarks }}
            </div>
        @endif

        {{-- Signatures --}}
        <div class="dn-signatures">
            <div class="dn-sig-line">
                Prepared By<br>
                <b>{{ $return->creator->name ?? 'Staff' }}</b>
            </div>
            <div class="dn-sig-line">
                Authorized By<br>
                <b>Management</b>
            </div>
            <div class="dn-sig-line">
                Received by Supplier<br>
                <b>(Name, Sign & Date)</b>
            </div>
        </div>

        {{-- Print footer info --}}
        <div class="print-footer-info">
            <div>Printed on: {{ date('d M Y, h:i A') }}</div>
            <div>Return #{{ $return->return_no }}</div>
            <div>Page 1 of 1</div>
        </div>

    </div>

</div>

{{-- ─── Modal 1: Confirm with Supplier Document ─── --}}
@if($return->isPending())
<div class="modal fade" id="confirmDocModal" tabindex="-1" role="dialog" aria-labelledby="confirmDocModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <form method="POST" action="{{ route('purchase-returns.confirm', $return->id) }}" enctype="multipart/form-data" id="confirmReturnForm">
            @csrf
            <div class="modal-content" style="border-radius: 8px;">
                <div class="modal-header bg-success text-white" style="border-radius: 8px 8px 0 0;">
                    <h5 class="modal-title font-weight-bold" id="confirmDocModalLabel" style="font-size: 16px;">
                        <i class="fas fa-clipboard-check mr-2"></i>Confirm Purchase Return with Supplier Document
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body" style="padding: 24px;">
                    <div class="alert alert-info py-2 px-3 mb-3" style="font-size: 12px;">
                        <i class="fas fa-info-circle mr-1"></i>
                        Confirming will post this debit note to the <b>Supplier Ledger</b> dated with the supplier's document date. If the accepted amount is less than the return value, the difference will be automatically recorded as <b>Purchase Return Loss</b>.
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="supplier_doc_no" style="font-size: 12px; font-weight: 700;">
                                    Supplier Return MEMO / Credit Note Number(s) <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="supplier_doc_no" id="supplier_doc_no" class="form-control" required
                                       placeholder="e.g. CM-8841, CR-9901 (comma-separated for multiple)"
                                       value="{{ old('supplier_doc_no', $return->supplier_doc_no ?? $return->supplier_memo_no) }}">
                                <small class="text-muted">Enter the supplier's return memo or credit note number(s).</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="supplier_doc_date" style="font-size: 12px; font-weight: 700;">
                                    Supplier Document Date <span class="text-danger">*</span>
                                </label>
                                <input type="date" name="supplier_doc_date" id="supplier_doc_date" class="form-control" required
                                       value="{{ old('supplier_doc_date', date('Y-m-d')) }}">
                                <small class="text-muted">Ledger entries will be dated with this date to match supplier statements.</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="modal_return_amount" style="font-size: 12px; font-weight: 700;">
                                    Original Return Value (৳)
                                </label>
                                <input type="text" id="modal_return_amount" class="form-control" readonly
                                       value="৳{{ number_format($return->total_amount, 2) }}" style="background: #f1f5f9; font-weight: 700;">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="accepted_amount" style="font-size: 12px; font-weight: 700;">
                                    Amount Accepted by Supplier (৳) <span class="text-danger">*</span>
                                </label>
                                <input type="number" step="0.01" min="0" max="{{ $return->total_amount }}"
                                       name="accepted_amount" id="accepted_amount" class="form-control font-weight-bold text-success" required
                                       value="{{ old('accepted_amount', $return->total_amount) }}"
                                       oninput="calcLossPreview()">
                                <small class="text-muted">Defaults to full return amount. Change if supplier deducted or refused items.</small>
                            </div>
                        </div>
                    </div>

                    {{-- Live Loss / Difference Preview Card --}}
                    <div id="lossPreviewBox" class="p-3 mb-3" style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700;">Reconciliation Outcome</div>
                            <div id="reconciliationSummaryText" style="font-size: 13px; font-weight: 600; color: #166534;">
                                Full amount accepted. No loss recorded.
                            </div>
                        </div>
                        <div class="text-right">
                            <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700;">Calculated Loss</div>
                            <div id="calculatedLossText" style="font-size: 15px; font-weight: 800; color: #0f172a;">
                                ৳0.00
                            </div>
                        </div>
                    </div>

                    <div id="overAmountAlert" class="alert alert-danger py-2 px-3 mb-3" style="display: none; font-size: 12px;">
                        <i class="fas fa-ban mr-1"></i>
                        <strong>Error:</strong> Accepted amount cannot be greater than the original return amount (৳{{ number_format($return->total_amount, 2) }}).
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="attachment" style="font-size: 12px; font-weight: 700;">
                                    Attach Scan / Photo of Document <span class="text-muted font-weight-normal">(Optional — can be left blank)</span>
                                </label>
                                <input type="file" name="attachment" id="attachment" class="form-control-file" accept=".pdf,.png,.jpg,.jpeg">
                                <small class="text-muted">Optional. You can confirm without uploading any document.</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="remarks" style="font-size: 12px; font-weight: 700;">
                                    Remarks / Notes (Optional)
                                </label>
                                <textarea name="remarks" id="remarks" rows="2" class="form-control" placeholder="Any internal notes or supplier memo details..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer" style="background: #f8fafc; border-radius: 0 0 8px 8px;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" id="submitConfirmBtn" class="btn btn-success btn-sm font-weight-bold">
                        <i class="fas fa-check mr-1"></i>Confirm & Post to Accounts
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

{{-- ─── Modal 2: Unconfirm Return (Super Admin only) ─── --}}
@if($return->isConfirmed() && $isSuperAdmin)
<div class="modal fade" id="unconfirmModal" tabindex="-1" role="dialog" aria-labelledby="unconfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form method="POST" action="{{ route('purchase-returns.unconfirm', $return->id) }}">
            @csrf
            <div class="modal-content" style="border-radius: 8px;">
                <div class="modal-header bg-warning text-dark" style="border-radius: 8px 8px 0 0;">
                    <h5 class="modal-title font-weight-bold" id="unconfirmModalLabel" style="font-size: 16px;">
                        <i class="fas fa-undo-alt mr-2"></i>Unconfirm Purchase Return
                    </h5>
                    <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" style="padding: 20px;">
                    <p style="font-size: 13px;">
                        Are you sure you want to unconfirm return <strong>{{ $return->return_no }}</strong>?
                    </p>
                    <div class="alert alert-warning py-2 px-3 mb-3" style="font-size: 12px;">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <b>Reversal effect:</b> The supplier credit (৳{{ number_format($return->accepted_amount, 2) }}) and any Purchase Return Loss (৳{{ number_format($return->loss_amount, 2) }}) will be <b>completely reversed</b> from the accounts. The return will go back to <b>Pending</b> status.
                    </div>
                    <div class="form-group mb-0">
                        <label for="unconfirm_reason" style="font-size: 12px; font-weight: 700;">
                            Reason for Unconfirming <span class="text-danger">*</span>
                        </label>
                        <textarea name="reason" id="unconfirm_reason" class="form-control" rows="3" required
                                  placeholder="Explain why this confirmed return needs to be reversed..."></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8fafc; border-radius: 0 0 8px 8px;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm font-weight-bold">
                        <i class="fas fa-undo mr-1"></i>Confirm Unconfirm
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

{{-- ─── Modal 3: Delete Pending Return (Super Admin only) ─── --}}
@if($return->isPending() && $isSuperAdmin)
<div class="modal fade" id="deleteReturnModal" tabindex="-1" role="dialog" aria-labelledby="deleteReturnModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form method="POST" action="{{ route('purchase-returns.destroy', $return->id) }}">
            @csrf
            @method('DELETE')
            <div class="modal-content" style="border-radius: 8px;">
                <div class="modal-header bg-danger text-white" style="border-radius: 8px 8px 0 0;">
                    <h5 class="modal-title font-weight-bold" id="deleteReturnModalLabel" style="font-size: 16px;">
                        <i class="fas fa-trash-alt mr-2"></i>Void Purchase Return
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" style="padding: 20px;">
                    <p style="font-size: 13px;">
                        Are you sure you want to void return <strong>{{ $return->return_no }}</strong>?
                    </p>
                    <div class="alert alert-warning py-2 px-3 mb-3" style="font-size: 12px;">
                        <i class="fas fa-boxes mr-1"></i>
                        Physical inventory will be restored back to warehouse stock. The record will be soft-deleted with an audit trail.
                    </div>
                    <div class="form-group mb-0">
                        <label for="delete_reason" style="font-size: 12px; font-weight: 700;">
                            Reason for Voiding <span class="text-danger">*</span>
                        </label>
                        <textarea name="delete_reason" id="delete_reason" class="form-control" rows="3" required
                                  placeholder="State why this return is being voided..."></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8fafc; border-radius: 0 0 8px 8px;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm font-weight-bold">
                        <i class="fas fa-trash mr-1"></i>Void & Restore Stock
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

@push('js')
<script>
var totalReturnAmount = {{ (float) $return->total_amount }};

function calcLossPreview() {
    var acceptedInput = document.getElementById('accepted_amount');
    var acceptedVal = parseFloat(acceptedInput.value) || 0;
    var submitBtn = document.getElementById('submitConfirmBtn');
    var alertBox = document.getElementById('overAmountAlert');
    var summaryText = document.getElementById('reconciliationSummaryText');
    var lossText = document.getElementById('calculatedLossText');
    var previewBox = document.getElementById('lossPreviewBox');

    if (acceptedVal > totalReturnAmount) {
        alertBox.style.display = 'block';
        submitBtn.disabled = true;
        previewBox.style.background = '#fef2f2';
        summaryText.innerHTML = '<span class="text-danger font-weight-bold">Amount exceeds return value!</span>';
        lossText.innerText = 'Invalid';
        lossText.style.color = '#dc2626';
    } else {
        alertBox.style.display = 'none';
        submitBtn.disabled = false;
        var diff = totalReturnAmount - acceptedVal;

        if (diff > 0.001) {
            previewBox.style.background = '#fff1f2';
            previewBox.style.borderColor = '#fecdd3';
            summaryText.innerHTML = '<span class="text-danger font-weight-bold">Accepted less than return amount.</span> Difference will be recorded as Purchase Return Loss.';
            lossText.innerText = '৳' + diff.toFixed(2);
            lossText.style.color = '#e11d48';
        } else {
            previewBox.style.background = '#f0fdf4';
            previewBox.style.borderColor = '#bbf7d0';
            summaryText.innerHTML = '<span class="text-success font-weight-bold">Full amount accepted.</span> No loss will be recorded.';
            lossText.innerText = '৳0.00';
            lossText.style.color = '#166534';
        }
    }
}

// Double-click protection on confirm form
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('confirmReturnForm');
    if (form) {
        form.addEventListener('submit', function() {
            var btn = document.getElementById('submitConfirmBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Posting to accounts...';
            }
        });
    }
});
</script>
@endpush
@endsection
