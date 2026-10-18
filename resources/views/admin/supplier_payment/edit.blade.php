@extends('admin.layouts.master')
@section('page_title', 'Edit Supplier Payment')
@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<link rel="stylesheet" href="{{ asset("assets/plugins/select2/css/select2.min.css") }}">
<style>
.method-pill {
    cursor: pointer;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    font-size: 13px;
    color: #475569;
    transition: all 0.2s ease;
    background: #fff;
    margin-bottom: 8px;
}
.method-pill:hover {
    border-color: #cbd5e1;
    background: #f8fafc;
}
.method-pill.active {
    border-color: #3b82f6;
    background: #eff6ff;
    color: #1d4ed8;
}
.method-pill input[type="radio"] {
    margin: 0;
}
</style>
@endpush
@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-truck" style="color: var(--accent-500); margin-right: 8px;"></i>Edit Supplier Payment</h1>
            <p>Update payment details for voucher #{{ $payment->ref_invoice_no }}</p>
        </div>
        <div class="header-actions">
            @can('permission','supplier_payment.index')
            <a href="{{ route('suppliers.payment.slip', $payment->id) }}" class="btn btn-outline-info btn-sm mr-1"><i class="fas fa-print mr-1"></i>View Voucher</a>
            <a href="{{ url('admin/suppliers-payment') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>Payment List</a>
            @endcan
        </div>
    </div>
    @include('admin.alert_message')
    <form action="{{ url('admin/suppliers-payment/'.$payment->id) }}" method="POST" id="supplier-payment-form"
        data-supplier-due-url="{{ route('suppliers.due', ':id') }}">
        @csrf
        @method('PUT')
        <div class="crm-form-section crm-animate-in delay-1">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--accent-500);"><i class="fas fa-money-bill-wave"></i></div>
                <h6>Payment Details</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-5">
                        <div class="form-group">
                            <label for="supplier_id">Supplier <span style="color: var(--danger-500);">*</span></label>
                            <select class="form-control select" name="supplier_id" id="supplier_id" required>
                                <option value="">Select Supplier</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" {{ $payment->supplier_id == $supplier->id ? 'selected' : '' }}>
                                        {{ $supplier->supplier_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="due_amount">Outstanding Due (Excl. this)</label>
                            <input type="text" name="due_amount" value="৳{{ number_format($due_amount ?? 0, 2) }}" class="form-control font-weight-bold text-danger" id="due_amount" readonly>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="date">Payment Date <span style="color: var(--danger-500);">*</span></label>
                            <input type="date" name="date" value="{{ $payment->date ? $payment->date->format('Y-m-d') : '' }}" class="form-control" id="date" required>
                        </div>
                    </div>
                </div>

                {{-- Payment Method Selection --}}
                @php
                    $currentMethod = $payment->payment_method ?? 'Cash';
                @endphp
                <div class="form-group mt-2">
                    <label style="font-weight: 700; color: #1e293b;">Disbursement Method <span style="color: var(--danger-500);">*</span></label>
                    <div class="row">
                        <div class="col-md-4">
                            <label class="method-pill {{ $currentMethod === 'Cash' ? 'active' : '' }}">
                                <input type="radio" name="payment_method" value="Cash" {{ $currentMethod === 'Cash' ? 'checked' : '' }}>
                                <span><i class="fas fa-money-bill-alt mr-1 text-success"></i>Cash in Hand</span>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <label class="method-pill {{ $currentMethod === 'Bank Deposit' ? 'active' : '' }}">
                                <input type="radio" name="payment_method" value="Bank Deposit" {{ $currentMethod === 'Bank Deposit' ? 'checked' : '' }}>
                                <span><i class="fas fa-university mr-1 text-primary"></i>Bank Deposit / Cheque</span>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <label class="method-pill {{ $currentMethod === 'Online Transfer' ? 'active' : '' }}">
                                <input type="radio" name="payment_method" value="Online Transfer" {{ $currentMethod === 'Online Transfer' ? 'checked' : '' }}>
                                <span><i class="fas fa-mobile-alt mr-1 text-info"></i>Online Transfer (EFT/MFS)</span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Bank / Cheque / Transaction Details --}}
                <div class="row" id="bank_ref_fields" style="{{ $currentMethod === 'Cash' ? 'display: none;' : '' }} background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 14px 10px 4px 10px; margin: 0 0 16px 0;">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="bank_name" id="lbl-bank-name">Bank / Platform Name</label>
                            <input type="text" name="bank_name" class="form-control" id="bank_name" value="{{ old('bank_name', $payment->bank_name) }}" placeholder="e.g. City Bank / bKash">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="transaction_no" id="lbl-txn-no">Cheque No / Transaction ID / Slip No</label>
                            <input type="text" name="transaction_no" class="form-control" id="transaction_no" value="{{ old('transaction_no', $payment->transaction_no) }}" placeholder="e.g. Chq #098412 or Txn ID">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="debit">Payment Amount (৳) <span style="color: var(--danger-500);">*</span></label>
                            <input type="number" name="debit" class="form-control font-weight-bold" id="debit" step="0.01" min="0.01" value="{{ old('debit', $payment->debit) }}" placeholder="Enter payment amount" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="supplier_memo_no">Supplier Cash Memo / Receipt No</label>
                            <input type="text" name="supplier_memo_no" class="form-control" id="supplier_memo_no" value="{{ old('supplier_memo_no', $payment->supplier_memo_no) }}" placeholder="e.g. Memo # / Money Receipt #">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="details">Remarks / Note</label>
                            <input type="text" class="form-control" id="details" name="details" value="{{ old('details', $payment->details) }}" placeholder="Payment remarks">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="crm-form-submit-bar">
            <a href="{{url('admin/suppliers-payment')}}" class="btn btn-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Update & View Voucher</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset("assets/plugins/select2/js/select2.min.js") }}"></script>
<script>
$(document).ready(function() {
    $('.select').select2({
        placeholder: "Select Supplier",
        width: '100%'
    });

    $('input[name="payment_method"]').on('change', function() {
        $('.method-pill').removeClass('active');
        $(this).closest('.method-pill').addClass('active');

        var val = $(this).val();
        if (val === 'Bank Deposit') {
            $('#bank_ref_fields').slideDown(200);
            $('#lbl-bank-name').text('Bank Name & Branch');
            $('#lbl-txn-no').text('Cheque No / Deposit Slip No');
        } else if (val === 'Online Transfer') {
            $('#bank_ref_fields').slideDown(200);
            $('#lbl-bank-name').text('Bank / MFS Platform');
            $('#lbl-txn-no').text('Transaction / EFT / Transfer Reference');
        } else {
            $('#bank_ref_fields').slideUp(200);
        }
    });
});
</script>
@endpush
