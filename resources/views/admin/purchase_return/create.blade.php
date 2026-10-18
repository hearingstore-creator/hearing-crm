@extends('admin.layouts.master')
@section('page_title', 'New Purchase Return (Debit Note)')
@section('breadcrumb_parent', 'Purchases')
@section('breadcrumb_parent_url', url('admin/purchase'))

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
.serial-pill-checkbox input {
    margin-right: 5px;
}
.multi-bill-picker-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 14px 16px;
    margin-bottom: 16px;
}
.bill-preview-table th {
    background: #e2e8f0;
    font-size: 11px;
    text-transform: uppercase;
    padding: 7px 10px;
    color: #475569;
}
.bill-preview-table td {
    padding: 8px 10px;
    font-size: 12px;
    vertical-align: middle;
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
.origin-bill-badge.direct {
    background: #f8fafc;
    border-color: #e2e8f0;
    color: #64748b;
}
</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-undo" style="color: var(--danger-500); margin-right: 8px;"></i>New Purchase Return (Debit Note)</h1>
            <p>Return defective or unsold items from single or multiple purchase bills and issue a unified debit note</p>
        </div>
        <div class="header-actions">
            <a href="{{ route('purchase-returns.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i>Returns List
            </a>
        </div>
    </div>

    @include('admin.alert_message')

    <form action="{{ route('purchase-returns.store') }}" method="POST" id="purchaseReturnForm">
        @csrf

        {{-- Primary Info Card --}}
        <div class="crm-form-section crm-animate-in delay-1">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--danger-500);"><i class="fas fa-truck-loading"></i></div>
                <h6>Return & Supplier Information</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="supplier_id">Supplier <span style="color: var(--danger-500);">*</span></label>
                            <select name="supplier_id" id="supplier_id" class="form-control select" required>
                                <option value="">Select Supplier</option>
                                @foreach ($suppliers as $s)
                                    <option value="{{ $s->id }}" {{ (old('supplier_id', $supplierId ?? request('supplier_id')) == $s->id) ? 'selected' : '' }}>
                                        {{ $s->supplier_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="date">Return Date <span style="color: var(--danger-500);">*</span></label>
                            <input type="date" name="date" id="date" class="form-control" value="{{ old('date', date('Y-m-d')) }}" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group mb-0">
                            <label for="reason">Reason for Return</label>
                            <input type="text" name="reason" id="reason" class="form-control" value="{{ old('reason') }}" placeholder="e.g. Damaged in transit, Defective unit, Overstock, Wrong model">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Multi-Bill & Serial Item Selection Tool --}}
        <div class="crm-table-card crm-animate-in delay-2 mt-3">
            <div class="crm-table-header" style="justify-content: space-between;">
                <div class="table-title">
                    <i class="fas fa-search-plus" style="color: var(--page-text-muted);"></i>
                    Select Items to Return
                </div>
                <div class="text-muted" style="font-size: 12px;">
                    <i class="fas fa-info-circle mr-1 text-primary"></i>You can add items from multiple purchase bills or scan serial numbers into this single return.
                </div>
            </div>

            <div style="padding: 16px;">
                <div class="multi-bill-picker-box">
                    <div class="row align-items-center">
                        <div class="col-md-7">
                            <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #475569;">
                                <i class="fas fa-file-invoice text-primary mr-1"></i>Add Items From Purchase Bill
                            </label>
                            <div class="d-flex" style="gap: 8px;">
                                <select id="bill_selector" class="form-control select">
                                    <option value="">-- Choose a Purchase Bill --</option>
                                    @foreach ($purchases as $p)
                                        <option value="{{ $p->id }}" data-memo="{{ $p->supplier_memo_no }}" {{ (old('purchase_id', request('purchase_id')) == $p->id) ? 'selected' : '' }}>
                                            Bill #{{ $p->purchase_invoice_no }} ({{ $p->date ? $p->date->format('d M Y') : '' }} · ৳{{ number_format($p->total_amount, 2) }})
                                            {{ $p->supplier_memo_no ? '— Memo: ' . $p->supplier_memo_no : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="button" class="btn btn-primary btn-sm px-3" id="btnLoadBill" style="white-space: nowrap;">
                                    <i class="fas fa-download mr-1"></i>Fetch Items
                                </button>
                            </div>
                        </div>

                        <div class="col-md-5 mt-2 mt-md-0">
                            <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #475569;">
                                <i class="fas fa-barcode text-danger mr-1"></i>Or Scan / Quick Add Serial Number
                            </label>
                            <div class="input-group">
                                <input type="text" id="serial_lookup_input" class="form-control form-control-sm" placeholder="Type or scan serial (e.g. SN-88201)...">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-danger btn-sm px-3" id="btnLookupSerial">
                                        <i class="fas fa-plus mr-1"></i>Add
                                    </button>
                                </div>
                            </div>
                            <div id="serial_feedback" class="mt-1" style="font-size: 11px; display: none;"></div>
                        </div>
                    </div>

                    {{-- Bill Items Preview (when a bill is fetched) --}}
                    <div id="bill_preview_wrapper" class="mt-3" style="display: none; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong id="preview_bill_title" style="font-size: 13px; color: #0f172a;"></strong>
                                <span id="preview_bill_memo" class="badge badge-light border ml-2"></span>
                            </div>
                            <div>
                                <button type="button" class="btn btn-xs btn-outline-secondary" id="btnClosePreview">Close</button>
                                <button type="button" class="btn btn-xs btn-success ml-1 font-weight-bold" id="btnAddCheckedToReturn">
                                    <i class="fas fa-check mr-1"></i>Add Selected to Return List
                                </button>
                            </div>
                        </div>
                        <div style="overflow-x: auto;">
                            <table class="table table-bordered table-sm bill-preview-table mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 40px; text-align: center;"><input type="checkbox" id="chkSelectAllBillItems"></th>
                                        <th>Product</th>
                                        <th>Available Serials in Stock</th>
                                        <th style="width: 100px; text-align: center;">Return Qty</th>
                                        <th style="width: 120px; text-align: right;">Purchase Rate</th>
                                    </tr>
                                </thead>
                                <tbody id="bill_preview_tbody">
                                    {{-- Dynamically populated --}}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Unified Return Table --}}
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 style="font-size: 13px; font-weight: 800; text-transform: uppercase; color: #1e293b; margin: 0;">
                        <i class="fas fa-boxes mr-1 text-danger"></i>Items Included in this Return (Debit Note)
                    </h6>
                    <button type="button" class="btn btn-outline-secondary btn-xs" id="btnAddCustomRow">
                        <i class="fas fa-plus mr-1"></i>Add Manual / Non-Serialized Product
                    </button>
                </div>

                <div style="overflow-x: auto;">
                    <table class="crm-table" id="itemsTable">
                        <thead>
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th style="width: 25%;">Product</th>
                                <th style="width: 18%;">Origin Bill</th>
                                <th style="width: 25%;">Returned Serial Numbers</th>
                                <th style="width: 10%; text-align: center;">Qty</th>
                                <th style="width: 11%; text-align: right;">Purchase Rate (৳)</th>
                                <th style="width: 11%; text-align: right;">Debit Total (৳)</th>
                                <th style="width: 40px; text-align: center;"></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            {{-- Rows added dynamically --}}
                        </tbody>
                        <tfoot>
                            <tr style="font-weight: 700; background: var(--gray-50);">
                                <td colspan="6" style="text-align: right; padding-right: 16px;">
                                    Total Debit Note Value:
                                </td>
                                <td style="text-align: right; font-size: 16px; color: var(--danger-600);" id="grandTotalDisplay">
                                    ৳0.00
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- Submit Bar --}}
        <div class="crm-form-submit-bar mt-4">
            <a href="{{ route('purchase-returns.index') }}" class="btn btn-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-danger btn-sm" id="btnSubmit">
                <i class="fas fa-check mr-1"></i>Confirm & Issue Debit Note
            </button>
        </div>
    </form>

</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/plugins/select2/js/select2.min.js') }}"></script>
<script>
$(document).ready(function() {
    $('.select').select2({ width: '100%' });

    var rowIndex = 0;
    var allProducts = @json($products);
    var addedSerialsSet = new Set();

    // 1. Fetch supplier purchase bills helper
    function fetchSupplierBills(supplierId, selectedBillId) {
        var selector = $('#bill_selector');
        if (!supplierId) {
            selector.empty().append($('<option>').val('').text('-- Choose a Purchase Bill --')).trigger('change.select2');
            return;
        }

        var btn = $('#btnLoadBill');
        var origBtnHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Loading...');

        $.ajax({
            url: "{{ route('purchase-returns.supplier-bills') }}",
            type: 'GET',
            data: { supplier_id: supplierId },
            success: function(res) {
                selector.empty().append($('<option>').val('').text('-- Choose a Purchase Bill --'));
                if (res.success && res.purchases && res.purchases.length > 0) {
                    res.purchases.forEach(function(p) {
                        var text = 'Bill #' + p.purchase_invoice_no + ' (' + p.date + ' · ৳' + Number(p.total_amount).toLocaleString('en-IN', {minimumFractionDigits: 2}) + ')';
                        if (p.supplier_memo_no) {
                            text += ' — Memo: ' + p.supplier_memo_no;
                        }
                        var opt = $('<option>').val(p.id).text(text).attr('data-memo', p.supplier_memo_no);
                        if (selectedBillId && p.id == selectedBillId) {
                            opt.prop('selected', true);
                        }
                        selector.append(opt);
                    });
                } else {
                    selector.append($('<option>').val('').text('-- No purchase bills found for this supplier --').prop('disabled', true));
                }
                selector.trigger('change.select2');
            },
            error: function() {
                console.error('Failed to load supplier bills');
            },
            complete: function() {
                btn.prop('disabled', false).html(origBtnHtml);
            }
        });
    }

    // Supplier change -> fetch historical bills via AJAX
    $('#supplier_id').on('change', function() {
        var supplierId = $(this).val();
        fetchSupplierBills(supplierId);
    });

    // When the bill selector is opened or clicked, if options are empty and supplier is selected, fetch bills immediately
    $(document).on('select2:opening', '#bill_selector', function() {
        var supplierId = $('#supplier_id').val();
        if (supplierId && $('#bill_selector option').length <= 1) {
            fetchSupplierBills(supplierId);
        }
    });
    $('#bill_selector').on('focus click', function() {
        var supplierId = $('#supplier_id').val();
        if (supplierId && $(this).find('option').length <= 1) {
            fetchSupplierBills(supplierId);
        }
    });

    // Check on page load: if supplier is selected (e.g. from validation error old('supplier_id') or URL query)
    var initialSupplierId = $('#supplier_id').val();
    if (initialSupplierId) {
        var currentBillCount = $('#bill_selector option').length;
        if (currentBillCount <= 1) {
            fetchSupplierBills(initialSupplierId, "{{ old('purchase_id', request('purchase_id')) }}");
        }
    }

    // 2. Fetch Items from Selected Bill for Preview
    function loadBillPreview(billId) {
        if (!billId) return;

        $.ajax({
            url: "{{ route('purchase-returns.items') }}",
            type: 'GET',
            data: { purchase_id: billId },
            beforeSend: function() {
                $('#preview_bill_title').html('<i class="fas fa-spinner fa-spin mr-1"></i>Loading bill items...');
                $('#bill_preview_tbody').empty();
                $('#bill_preview_wrapper').show();
            },
            success: function(res) {
                if (res.success && res.items.length > 0) {
                    $('#preview_bill_title').text('Bill #' + res.purchase_invoice_no);
                    if (res.supplier_id && !$('#supplier_id').val()) {
                        $('#supplier_id').val(res.supplier_id).trigger('change.select2');
                    }

                    if (res.supplier_memo_no) {
                        $('#preview_bill_memo').text('Memo: ' + res.supplier_memo_no).show();
                    } else {
                        $('#preview_bill_memo').hide();
                    }

                    var tbody = $('#bill_preview_tbody');
                    tbody.empty();

                    res.items.forEach(function(item, idx) {
                        var tr = $('<tr>').attr('data-item', JSON.stringify(item));

                        // Checkbox
                        var tdChk = $('<td>').addClass('text-center');
                        tdChk.append($('<input type="checkbox">').addClass('chk-bill-item').prop('checked', true));

                        // Product
                        var tdProd = $('<td>');
                        tdProd.append($('<strong>').text(item.product_name));

                        // Serials (if any)
                        var tdSerials = $('<td>');
                        if (item.serials && item.serials.length > 0) {
                            var wrap = $('<div>').css('max-height', '90px').css('overflow-y', 'auto');
                            item.serials.forEach(function(s) {
                                var isAlreadyAdded = addedSerialsSet.has(s);
                                var lbl = $('<label>').addClass('serial-pill-checkbox' + (isAlreadyAdded ? ' text-muted disabled' : ' checked'));
                                var chk = $('<input type="checkbox">').addClass('preview-serial-chk').val(s).prop('checked', !isAlreadyAdded);
                                if (isAlreadyAdded) chk.prop('disabled', true);
                                lbl.append(chk).append(document.createTextNode(' ' + s));
                                wrap.append(lbl);
                            });
                            tdSerials.append(wrap);
                            tdSerials.append($('<small>').addClass('text-muted d-block').text('Checked serials will be returned'));
                        } else {
                            tdSerials.html('<span class="text-muted">Non-serialized product</span>');
                        }

                        // Qty input
                        var tdQty = $('<td>').addClass('text-center');
                        var qtyInput = $('<input type="number" step="1" min="1">')
                            .addClass('form-control form-control-sm preview-qty text-center')
                            .val(item.serials && item.serials.length > 0 ? item.serials.length : 1);
                        if (item.serials && item.serials.length > 0) {
                            qtyInput.prop('readonly', true);
                        }
                        tdQty.append(qtyInput);

                        // Price
                        var tdPrice = $('<td>').addClass('text-right font-weight-bold').text('৳' + Number(item.purchase_price).toFixed(2));

                        tr.append(tdChk, tdProd, tdSerials, tdQty, tdPrice);
                        tbody.append(tr);
                    });
                } else {
                    $('#bill_preview_tbody').html('<tr><td colspan="5" class="text-center py-3 text-muted">No items found for this bill.</td></tr>');
                }
            },
            error: function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to load bill items.';
                $('#bill_preview_tbody').html('<tr><td colspan="5" class="text-center py-3 text-danger"><i class="fas fa-exclamation-triangle mr-1"></i>' + msg + '</td></tr>');
            }
        });
    }

    $('#btnLoadBill').on('click', function() {
        var supplierId = $('#supplier_id').val();
        if (!supplierId) {
            alert('Please select a supplier first.');
            $('#supplier_id').select2('open');
            return;
        }

        var billId = $('#bill_selector').val();
        if (!billId) {
            if ($('#bill_selector option').length <= 1) {
                fetchSupplierBills(supplierId);
                alert('Loading purchase bills for this supplier. Please select a bill from the dropdown once loaded.');
            } else {
                alert('Please select a purchase bill from the dropdown first.');
                $('#bill_selector').select2('open');
            }
            return;
        }
        loadBillPreview(billId);
    });

    $('#btnClosePreview').on('click', function() {
        $('#bill_preview_wrapper').slideUp();
    });

    $('#chkSelectAllBillItems').on('change', function() {
        var checked = $(this).is(':checked');
        $('.chk-bill-item').prop('checked', checked);
    });

    $(document).on('change', '.preview-serial-chk', function() {
        var lbl = $(this).closest('label');
        if ($(this).is(':checked')) {
            lbl.addClass('checked');
        } else {
            lbl.removeClass('checked');
        }
        var row = $(this).closest('tr');
        var checkedCount = row.find('.preview-serial-chk:checked').length;
        row.find('.preview-qty').val(checkedCount);
        if (checkedCount === 0) {
            row.find('.chk-bill-item').prop('checked', false);
        } else {
            row.find('.chk-bill-item').prop('checked', true);
        }
    });

    // 3. Add Checked Preview Items into the Master Return Table
    $('#btnAddCheckedToReturn').on('click', function() {
        var checkedRows = $('#bill_preview_tbody tr').filter(function() {
            return $(this).find('.chk-bill-item').is(':checked');
        });

        if (checkedRows.length === 0) {
            alert('Please select at least one item to add.');
            return;
        }

        checkedRows.each(function() {
            var rawData = $(this).data('item');
            if (!rawData) return;

            var serialsToReturn = [];
            $(this).find('.preview-serial-chk:checked').each(function() {
                serialsToReturn.push($(this).val());
                addedSerialsSet.add($(this).val());
            });

            var qty = serialsToReturn.length > 0 ? serialsToReturn.length : (parseFloat($(this).find('.preview-qty').val()) || 1);

            addRow(
                rawData.product_id,
                rawData.product_name,
                rawData.purchase_price,
                rawData.purchase_master_id,
                rawData.purchase_invoice_no,
                serialsToReturn,
                qty
            );
        });

        $('#bill_preview_wrapper').slideUp();
        $('#bill_selector').val('').trigger('change');
    });

    // 4. Quick Serial Lookup and Add
    function handleSerialLookup() {
        var serial = $('#serial_lookup_input').val().trim();
        var supplierId = $('#supplier_id').val();

        if (!serial) {
            $('#serial_feedback').text('Please enter a serial number.').css('color', '#dc2626').show();
            return;
        }

        if (addedSerialsSet.has(serial)) {
            $('#serial_feedback').text('Serial ' + serial + ' is already in the return table.').css('color', '#d97706').show();
            return;
        }

        $('#serial_feedback').html('<i class="fas fa-spinner fa-spin mr-1"></i>Searching...').css('color', '#0284c7').show();

        $.ajax({
            url: "{{ route('purchase-returns.serial-lookup') }}",
            type: 'GET',
            data: {
                serial_no: serial,
                supplier_id: supplierId
            },
            success: function(res) {
                if (res.success && res.item) {
                    var item = res.item;
                    if (!supplierId && item.supplier_id) {
                        $('#supplier_id').val(item.supplier_id).trigger('change');
                    }

                    addedSerialsSet.add(serial);
                    addRow(
                        item.product_id,
                        item.product_name,
                        item.purchase_price,
                        item.purchase_master_id,
                        item.purchase_invoice_no,
                        [serial],
                        1
                    );

                    $('#serial_lookup_input').val('').focus();
                    $('#serial_feedback').html('<i class="fas fa-check-circle mr-1 text-success"></i>Added <b>' + serial + '</b> (' + item.product_name + ' from Bill #' + item.purchase_invoice_no + ')').css('color', '#16a34a').show();
                }
            },
            error: function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Serial not found or not eligible for return.';
                $('#serial_feedback').text(msg).css('color', '#dc2626').show();
            }
        });
    }

    $('#btnLookupSerial').on('click', handleSerialLookup);
    $('#serial_lookup_input').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            handleSerialLookup();
        }
    });

    // 5. Add Row Function
    function addRow(productId, productName, purchasePrice, purchaseMasterId, purchaseInvoiceNo, serials, defaultQty) {
        var idx = rowIndex++;
        var tr = $('<tr>').attr('data-row', idx);

        // Col 0: Sl
        var tdSl = $('<td>').addClass('row-sl text-muted').text($('#itemsBody tr').length + 1);

        // Col 1: Product
        var tdProduct = $('<td>');
        if (productName) {
            tdProduct.append($('<input type="hidden">').attr('name', 'items[' + idx + '][product_id]').val(productId));
            tdProduct.append($('<strong>').text(productName));
        } else {
            var select = $('<select>').attr('name', 'items[' + idx + '][product_id]').addClass('form-control select product-picker').attr('required', true);
            select.append($('<option>').val('').text('Select Product'));
            allProducts.forEach(function(p) {
                select.append($('<option>').val(p.id).text(p.product_name).attr('data-price', p.mrp_price || 0));
            });
            tdProduct.append(select);
        }

        // Col 2: Origin Bill
        var tdBill = $('<td>');
        if (purchaseMasterId) {
            tdBill.append($('<input type="hidden">').attr('name', 'items[' + idx + '][purchase_master_id]').val(purchaseMasterId));
            tdBill.append($('<span>').addClass('origin-bill-badge').html('<i class="fas fa-file-invoice mr-1"></i>#' + (purchaseInvoiceNo || purchaseMasterId)));
        } else {
            tdBill.append($('<input type="hidden">').attr('name', 'items[' + idx + '][purchase_master_id]').val(''));
            tdBill.append($('<span>').addClass('origin-bill-badge direct').text('Direct / Stock'));
        }

        // Col 3: Serials
        var tdSerials = $('<td>');
        if (serials && serials.length > 0) {
            var wrap = $('<div>').css('max-height', '90px').css('overflow-y', 'auto');
            serials.forEach(function(s) {
                var badge = $('<span>').addClass('serial-pill-checkbox checked').text(s);
                badge.append($('<input type="hidden">').attr('name', 'items[' + idx + '][serial_no][]').val(s));
                wrap.append(badge);
            });
            tdSerials.append(wrap);
        } else {
            tdSerials.html('<input type="text" name="items[' + idx + '][serial_no]" class="form-control form-control-sm" placeholder="Optional comma-separated serials">');
        }

        // Col 4: Qty
        var tdQty = $('<td>').css('text-align', 'center');
        var qtyInput = $('<input type="number" step="0.01" min="0.01">')
            .addClass('form-control form-control-sm item-qty text-center')
            .attr('name', 'items[' + idx + '][qty]')
            .val(defaultQty || 1)
            .attr('required', true);
        if (serials && serials.length > 0) {
            qtyInput.prop('readonly', true);
        }
        tdQty.append(qtyInput);

        // Col 5: Price
        var tdPrice = $('<td>').css('text-align', 'right');
        var priceInput = $('<input type="number" step="0.01" min="0">')
            .addClass('form-control form-control-sm item-price text-right')
            .attr('name', 'items[' + idx + '][purchase_price]')
            .val(purchasePrice || 0)
            .attr('required', true);
        tdPrice.append(priceInput);

        // Col 6: Total
        var tdTotal = $('<td>').addClass('item-total text-right font-weight-bold').css('color', 'var(--danger-600)').text('৳0.00');

        // Col 7: Delete button
        var tdDel = $('<td>').css('text-align', 'center');
        var btnDel = $('<button type="button">').addClass('btn btn-outline-danger btn-xs btn-delete-row').html('<i class="fas fa-trash-alt"></i>');
        tdDel.append(btnDel);

        tr.append(tdSl, tdProduct, tdBill, tdSerials, tdQty, tdPrice, tdTotal, tdDel);
        $('#itemsBody').append(tr);

        if (tr.find('.select').length > 0) {
            tr.find('.select').select2({ width: '100%' });
        }

        calculateRow(tr);
        renumberRows();
    }

    function renumberRows() {
        $('#itemsBody tr').each(function(i) {
            $(this).find('.row-sl').text(i + 1);
        });
    }

    function calculateRow(row) {
        var qty = parseFloat(row.find('.item-qty').val()) || 0;
        var price = parseFloat(row.find('.item-price').val()) || 0;
        var total = qty * price;
        row.find('.item-total').text('৳' + total.toFixed(2)).data('total', total);
        calculateGrandTotal();
    }

    function calculateGrandTotal() {
        var grand = 0;
        $('.item-total').each(function() {
            grand += parseFloat($(this).data('total')) || 0;
        });
        $('#grandTotalDisplay').text('৳' + grand.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
    }

    // Manual row button
    $('#btnAddCustomRow').on('click', function() {
        addRow(null, null, 0, null, null, [], 1);
    });

    // Row change events
    $(document).on('change keyup', '.item-qty, .item-price', function() {
        calculateRow($(this).closest('tr'));
    });

    $(document).on('change', '.product-picker', function() {
        var price = $(this).find(':selected').data('price') || 0;
        var row = $(this).closest('tr');
        row.find('.item-price').val(price);
        calculateRow(row);
    });

    $(document).on('click', '.btn-delete-row', function() {
        var row = $(this).closest('tr');
        row.find('input[name*="[serial_no]"]').each(function() {
            addedSerialsSet.delete($(this).val());
        });
        row.remove();
        renumberRows();
        calculateGrandTotal();
    });

    // Restore rows from old inputs (if redirected back due to validation error)
    var oldItems = @json($oldItems ?? []);
    if (oldItems && oldItems.length > 0) {
        oldItems.forEach(function(item) {
            if (item.serial_no && item.serial_no.length > 0) {
                item.serial_no.forEach(function(s) {
                    addedSerialsSet.add(s);
                });
            }
            addRow(
                item.product_id,
                item.product_name,
                item.purchase_price,
                item.purchase_master_id,
                item.purchase_invoice_no,
                item.serial_no,
                item.qty
            );
        });
    }

    // If pre-selected purchase ID passed in query
    @if(!empty($selectedPurchase))
        loadBillPreview({{ $selectedPurchase->id }});
    @endif
});
</script>
@endpush
