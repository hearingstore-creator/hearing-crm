@extends('admin.layouts.master')
@section('page_title', 'Edit Purchase Return: ' . $return->return_no)
@section('breadcrumb_parent', 'Purchase Returns')
@section('breadcrumb_parent_url', route('purchase-returns.index'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
<style>
.serial-pill-checkbox {
    display: inline-flex;
    align-items: center;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    padding: 2px 7px;
    font-size: 11px;
    margin: 2px;
    cursor: pointer;
    font-family: monospace;
    user-select: none;
    transition: all 0.15s ease;
}
.serial-pill-checkbox:hover {
    background: #e2e8f0;
}
.serial-pill-checkbox.checked {
    background: #fee2e2;
    border-color: #ef4444;
    color: #b91c1c;
    font-weight: 700;
}
.origin-bill-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #166534;
    border-radius: 4px;
    padding: 2px 6px;
    font-size: 11px;
    font-family: monospace;
    font-weight: 600;
}
</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px 50px 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-edit" style="color: var(--warning-600); margin-right: 8px;"></i>Edit Purchase Return #{{ $return->return_no }}</h1>
            <p>Super Admin adjustment of pending return items, quantities, and prices. Inventory will be automatically adjusted by the difference.</p>
        </div>
        <div class="header-actions">
            <a href="{{ route('purchase-returns.show', $return->id) }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i>Back to Return Details
            </a>
        </div>
    </div>

    @include('admin.alert_message')

    <form action="{{ route('purchase-returns.update', $return->id) }}" method="POST" id="editPurchaseReturnForm">
        @csrf
        @method('PUT')

        {{-- Primary Info Card --}}
        <div class="crm-form-section crm-animate-in delay-1">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--warning-500);"><i class="fas fa-truck-loading"></i></div>
                <h6>Return & Supplier Information</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="supplier_id">Supplier <span class="text-danger">*</span></label>
                            <select name="supplier_id" id="supplier_id" class="form-control select" required>
                                <option value="">Select Supplier</option>
                                @foreach ($suppliers as $s)
                                    <option value="{{ $s->id }}" {{ (old('supplier_id', $return->supplier_id) == $s->id) ? 'selected' : '' }}>
                                        {{ $s->supplier_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="date">Return Date <span class="text-danger">*</span></label>
                            <input type="date" name="date" id="date" class="form-control" value="{{ old('date', $return->date ? $return->date->format('Y-m-d') : date('Y-m-d')) }}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="supplier_memo_no">Supplier Memo / Invoice Ref No(s)</label>
                            <input type="text" name="supplier_memo_no" id="supplier_memo_no" class="form-control" value="{{ old('supplier_memo_no', $return->supplier_memo_no) }}" placeholder="e.g. Memo #, Credit Note # (comma-separated)">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="reason">Reason for Return</label>
                            <input type="text" name="reason" id="reason" class="form-control" value="{{ old('reason', $return->reason) }}" placeholder="e.g. Defective batch, wrong items">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="edit_reason">Reason for Editing (Super Admin Audit Trail) <span class="text-danger">*</span></label>
                            <input type="text" name="edit_reason" id="edit_reason" class="form-control" required placeholder="State why this return is being modified (e.g. corrected quantity count)">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Items Table Card --}}
        <div class="crm-table-card crm-animate-in delay-2 mt-3">
            <div class="crm-table-header" style="justify-content: space-between;">
                <div class="table-title">
                    <i class="fas fa-boxes mr-1" style="color: var(--page-text-muted);"></i>
                    Returned Items & Quantities
                </div>
                <div>
                    <button type="button" class="btn btn-outline-secondary btn-xs" id="btnAddRow">
                        <i class="fas fa-plus mr-1"></i>Add Product Row
                    </button>
                </div>
            </div>

            <div style="padding: 16px;">
                <div style="overflow-x: auto;">
                    <table class="crm-table" id="itemsTable">
                        <thead>
                            <tr>
                                <th style="width: 35px;">#</th>
                                <th style="width: 30%;">Product</th>
                                <th style="width: 25%;">Serial Numbers (comma-separated)</th>
                                <th style="width: 12%; text-align: center;">Qty</th>
                                <th style="width: 15%; text-align: right;">Unit Price (৳)</th>
                                <th style="width: 15%; text-align: right;">Total (৳)</th>
                                <th style="width: 40px; text-align: center;"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsTbody">
                            @foreach ($return->details as $index => $detail)
                                <tr data-row="{{ $index }}">
                                    <td class="row-index text-center" style="color: var(--page-text-muted);">{{ $index + 1 }}</td>
                                    <td>
                                        <select name="items[{{ $index }}][product_id]" class="form-control form-control-sm select-product" required onchange="calculateRowTotal({{ $index }})">
                                            @foreach ($products as $p)
                                                <option value="{{ $p['id'] }}" {{ $detail->product_id == $p['id'] ? 'selected' : '' }}>
                                                    {{ $p['product_name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <input type="hidden" name="items[{{ $index }}][purchase_master_id]" value="{{ $detail->purchase_master_id }}">
                                        <input type="hidden" name="items[{{ $index }}][batch_id]" value="{{ $detail->batch_id }}">
                                    </td>
                                    <td>
                                        @php
                                            $serialsStr = '';
                                            if (is_array($detail->serial_no)) {
                                                $serialsStr = implode(', ', $detail->serial_no);
                                            } elseif (!empty($detail->serial_no)) {
                                                $decoded = json_decode($detail->serial_no, true);
                                                $serialsStr = is_array($decoded) ? implode(', ', $decoded) : (string)$detail->serial_no;
                                            }
                                        @endphp
                                        <input type="text" name="items[{{ $index }}][serial_no]" class="form-control form-control-sm item-serials" value="{{ $serialsStr }}" placeholder="SN-1, SN-2" onblur="handleSerialsChanged({{ $index }})">
                                    </td>
                                    <td style="text-align: center;">
                                        <input type="number" step="0.01" min="0.01" name="items[{{ $index }}][qty]" class="form-control form-control-sm text-center item-qty" value="{{ $detail->qty }}" required oninput="calculateRowTotal({{ $index }})">
                                    </td>
                                    <td style="text-align: right;">
                                        <input type="number" step="0.01" min="0" name="items[{{ $index }}][purchase_price]" class="form-control form-control-sm text-right item-price" value="{{ $detail->purchase_price }}" required oninput="calculateRowTotal({{ $index }})">
                                    </td>
                                    <td style="text-align: right;">
                                        <input type="text" class="form-control form-control-sm text-right item-line-total" readonly value="৳{{ number_format($detail->total_price, 2) }}" style="background: #f8fafc; font-weight: 700;">
                                    </td>
                                    <td style="text-align: center;">
                                        <button type="button" class="btn btn-outline-danger btn-xs" onclick="removeRow(this)">
                                            <i class="far fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr style="background: #f8fafc; font-weight: 700;">
                                <td colspan="3" style="text-align: right;">Total Return Value:</td>
                                <td id="totalQtySum" style="text-align: center;">{{ number_format($return->details->sum('qty'), 0) }}</td>
                                <td></td>
                                <td id="totalAmountSum" style="text-align: right; color: #b91c1c; font-size: 14px;">৳{{ number_format($return->total_amount, 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="d-flex justify-content-end mt-4" style="gap: 10px;">
                    <a href="{{ route('purchase-returns.show', $return->id) }}" class="btn btn-secondary btn-sm">Cancel</a>
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold">
                        <i class="fas fa-save mr-1"></i>Save Changes & Adjust Inventory
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

@push('js')
<script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
<script>
var productOptionsHtml = `@foreach ($products as $p)<option value="{{ $p['id'] }}">{{ addslashes($p['product_name']) }}</option>@endforeach`;
var rowIndex = {{ count($return->details) }};

function calculateRowTotal(idx) {
    var tr = document.querySelector('tr[data-row="' + idx + '"]');
    if (!tr) return;
    var qty = parseFloat(tr.querySelector('.item-qty').value) || 0;
    var price = parseFloat(tr.querySelector('.item-price').value) || 0;
    var lineTotal = qty * price;
    tr.querySelector('.item-line-total').value = '৳' + lineTotal.toFixed(2);
    calcGrandTotal();
}

function handleSerialsChanged(idx) {
    var tr = document.querySelector('tr[data-row="' + idx + '"]');
    if (!tr) return;
    var serialInput = tr.querySelector('.item-serials');
    var val = (serialInput.value || '').trim();
    if (val.length > 0) {
        var count = val.split(',').filter(function(s) { return s.trim().length > 0; }).length;
        if (count > 0) {
            tr.querySelector('.item-qty').value = count;
            calculateRowTotal(idx);
        }
    }
}

function calcGrandTotal() {
    var rows = document.querySelectorAll('#itemsTbody tr');
    var sumQty = 0;
    var sumAmt = 0;
    rows.forEach(function(tr) {
        var q = parseFloat(tr.querySelector('.item-qty').value) || 0;
        var p = parseFloat(tr.querySelector('.item-price').value) || 0;
        sumQty += q;
        sumAmt += (q * p);
    });
    document.getElementById('totalQtySum').innerText = sumQty.toFixed(0);
    document.getElementById('totalAmountSum').innerText = '৳' + sumAmt.toFixed(2);
}

function removeRow(btn) {
    var tr = btn.closest('tr');
    var tbody = document.getElementById('itemsTbody');
    if (tbody.children.length <= 1) {
        alert('Return must contain at least 1 item.');
        return;
    }
    tr.remove();
    calcGrandTotal();
    reindexRows();
}

function reindexRows() {
    var rows = document.querySelectorAll('#itemsTbody tr');
    rows.forEach(function(tr, i) {
        tr.querySelector('.row-index').innerText = i + 1;
    });
}

document.getElementById('btnAddRow').addEventListener('click', function() {
    var tbody = document.getElementById('itemsTbody');
    var tr = document.createElement('tr');
    tr.setAttribute('data-row', rowIndex);
    tr.innerHTML = `
        <td class="row-index text-center" style="color: var(--page-text-muted);">${tbody.children.length + 1}</td>
        <td>
            <select name="items[${rowIndex}][product_id]" class="form-control form-control-sm select-product" required onchange="calculateRowTotal(${rowIndex})">
                ${productOptionsHtml}
            </select>
        </td>
        <td>
            <input type="text" name="items[${rowIndex}][serial_no]" class="form-control form-control-sm item-serials" placeholder="SN-1, SN-2" onblur="handleSerialsChanged(${rowIndex})">
        </td>
        <td style="text-align: center;">
            <input type="number" step="0.01" min="0.01" name="items[${rowIndex}][qty]" class="form-control form-control-sm text-center item-qty" value="1" required oninput="calculateRowTotal(${rowIndex})">
        </td>
        <td style="text-align: right;">
            <input type="number" step="0.01" min="0" name="items[${rowIndex}][purchase_price]" class="form-control form-control-sm text-right item-price" value="0.00" required oninput="calculateRowTotal(${rowIndex})">
        </td>
        <td style="text-align: right;">
            <input type="text" class="form-control form-control-sm text-right item-line-total" readonly value="৳0.00" style="background: #f8fafc; font-weight: 700;">
        </td>
        <td style="text-align: center;">
            <button type="button" class="btn btn-outline-danger btn-xs" onclick="removeRow(this)">
                <i class="far fa-trash-alt"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    rowIndex++;
    calcGrandTotal();
});

$(document).ready(function() {
    $('.select').select2({ width: '100%' });
});
</script>
@endpush
@endsection
