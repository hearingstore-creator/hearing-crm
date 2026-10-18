@extends('admin.layouts.master')
@section('page_title', 'Edit Purchase')
@section('breadcrumb_parent', 'Purchases')
@section('breadcrumb_parent_url', url('admin/purchase'))

@push('css')
    <!-- Select2 & CRM Module Theme Styles -->
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/sales-invoice.css') }}">
    <style>
        .items-table .purchase-price,
        .items-table .mrp-price,
        .items-table .total-purchase-price {
            text-align: right;
            font-weight: 600;
        }
        .items-table .total-purchase-price {
            background: var(--gray-50) !important;
            border-style: dashed !important;
            color: var(--accent-700) !important;
            font-weight: 700;
        }
        .items-table .batch-no,
        .items-table .serial-no {
            font-size: 13px;
        }
        .summary-hero.purchase-hero {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
        }
    </style>
@endpush

@section('content')
<div class="container-fluid sale-page">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-edit" style="color: var(--accent-500); margin-right: 8px;"></i>Edit Purchase #{{ $purchaseMaster->purchase_invoice_no }}</h1>
            <p>Modify stock details, supplier bill &amp; payment records</p>
        </div>
        <div class="header-actions">
            @can('permission', 'purchase_list.index')
                <a href="{{ url('admin/purchase') }}" class="btn btn-secondary btn-sm" id="btn-purchase-list">
                    <i class="fas fa-list mr-1"></i>Purchase List
                </a>
            @endcan
            <button type="submit" form="purchaseEditForm" class="btn btn-primary btn-sm" id="btn-save-purchase-top">
                <i class="fas fa-check mr-1"></i>Update Purchase
            </button>
        </div>
    </div>

    @include('admin.alert_message')

    <form id="purchaseEditForm" action="{{ route('purchase.update', $purchaseMaster->id) }}" enctype="multipart/form-data" method="post"
          data-product-search="{{ url('/admin/product-search') }}"
          data-purchase-add-row="{{ route('purchase.add-row') }}"
          data-product-info="{{ url('admin/product-info') }}">
        @csrf
        @method('PUT')

        <div class="sale-layout">
            {{-- ================= MAIN COLUMN ================= --}}
            <div class="sale-main">

                {{-- STEP 1: Supplier & Bill Details --}}
                <div class="sale-card crm-animate-in delay-1">
                    <div class="sale-card-header">
                        <div class="sale-card-title">
                            <span class="sale-step">1</span>
                            <div>
                                <h3>Supplier &amp; Bill Details</h3>
                                <small>Who you are buying from and payment terms</small>
                            </div>
                        </div>
                    </div>
                    <div class="sale-card-body">
                        <div class="sale-grid">
                            <div class="sale-field sale-col-8">
                                <label for="supplier_id">Supplier <i class="req">*</i></label>
                                <div class="sale-input-group">
                                    <div class="sale-input-grow">
                                        <select name="supplier_id" class="form-control select required" id="supplier_id" required>
                                            <option value="">Search or select supplier…</option>
                                            @foreach ($suppliers as $val)
                                                <option value="{{ $val->id }}" {{ $purchaseMaster->supplier_id == $val->id ? 'selected' : '' }}>
                                                    {{ $val->supplier_name }} {{ $val->mobile_no ? '('.$val->mobile_no.')' : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <a href="{{ url('admin/supplier/create') }}" target="_blank" class="btn-soft-accent" title="Add New Supplier">
                                        <i class="fas fa-truck-loading"></i> New
                                    </a>
                                </div>
                            </div>

                            <div class="sale-field sale-col-4">
                                <label for="date">Purchase Date <i class="req">*</i></label>
                                <input type="date" name="date" value="{{ $purchaseMaster->date ? $purchaseMaster->date->format('Y-m-d') : date('Y-m-d') }}" class="form-control date" id="date" required>
                            </div>

                            <div class="sale-field sale-col-6">
                                <label for="supplier_memo_no">Supplier Cash Memo / Bill No</label>
                                <input class="form-control" id="supplier_memo_no" type="text" name="supplier_memo_no" value="{{ old('supplier_memo_no', $purchaseMaster->supplier_memo_no) }}" placeholder="e.g. Memo # / Challan # / Bill #">
                            </div>

                            <div class="sale-field sale-col-6">
                                <label for="payment_type_id">Payment Mode <i class="req">*</i></label>
                                <select name="payment_type_id" class="form-control select required" id="payment_type_id" required>
                                    @foreach ($paymentTypes as $key => $val)
                                        <option value="{{ $key }}" {{ old('payment_type_id', $purchaseMaster->payment_id) == $key ? 'selected' : '' }}>{{ $val }}</option>
                                    @endforeach
                                </select>
                                <div id="payment-type-note" class="mt-2" style="font-size: 11.5px; line-height: 1.4;"></div>
                            </div>

                            {{-- Conditional Bank / Transfer Details --}}
                            <div class="sale-col-12" id="purchase-bank-fields" style="display: none; background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 10px; padding: 14px 16px;">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label for="bank_name" id="lbl-purchase-bank" style="font-size: 12px; font-weight: 600; color: #475569;">Bank Name &amp; Branch</label>
                                        <input class="form-control" id="bank_name" type="text" name="bank_name" value="{{ old('bank_name') }}" placeholder="e.g. City Bank, Gulshan Branch">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="transaction_no" id="lbl-purchase-ref" style="font-size: 12px; font-weight: 600; color: #475569;">Cheque / Transaction No</label>
                                        <input class="form-control" id="transaction_no" type="text" name="transaction_no" value="{{ old('transaction_no') }}" placeholder="e.g. Cheque # 124589 or Txn ID">
                                    </div>
                                </div>
                            </div>

                            <div class="sale-field sale-col-12">
                                <label for="details">Purchase Remarks / Notes</label>
                                <input class="form-control" id="details" type="text" name="details" value="{{ old('details', $purchaseMaster->details) }}" placeholder="Add any shipment notes or internal reference (optional)">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- STEP 2: Purchase Line Items --}}
                <div class="sale-card crm-animate-in delay-2">
                    <div class="sale-card-header">
                        <div class="sale-card-title">
                            <span class="sale-step">2</span>
                            <div>
                                <h3>Purchase Items</h3>
                                <small>Stock quantity, purchase cost &amp; retail selling price</small>
                            </div>
                        </div>
                    </div>

                    <div class="items-scroll">
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th style="min-width: 250px;">Product <i class="req">*</i></th>
                                    <th style="width: 130px;">Batch No</th>
                                    <th style="min-width: 170px;">Serial No</th>
                                    <th style="width: 80px;" class="text-center">Stock</th>
                                    <th style="width: 80px;" class="text-center">Qty <i class="req">*</i></th>
                                    <th style="width: 120px;" class="text-right">Purchase Price <i class="req">*</i></th>
                                    <th style="width: 120px;" class="text-right">MRP Price <i class="req">*</i></th>
                                    <th style="width: 130px;" class="text-right">Total (৳)</th>
                                    <th style="width: 50px;" class="text-center"></th>
                                </tr>
                            </thead>
                            <tbody class="neworderbody">
                                @foreach ($purchaseDetail as $key => $item)
                                    <tr>
                                        <td>
                                            <input type="hidden" name="old[{{$key}}][id]" value="{{$item->id}}">
                                            <input type="hidden" name="old[{{$key}}][batch_id]" value="{{$item->batch?->id}}">
                                            <select class="form-control product" name="old[{{$key}}][product_id]" required>
                                                <option value="{{$item->product_id}}" selected="selected">
                                                    {{ ($item->product?->category?->category_name) . ' - ' . $item->product?->model }}
                                                </option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="old[{{$key}}][batch_no]" class="form-control batch-no" value="{{$item->batch?->batch_no}}" placeholder="Batch No">
                                        </td>
                                        <td>
                                            <input type="text" name="old[{{$key}}][serial_no]" class="form-control serial-no" value="{{ is_array($item->serial_no) ? implode(',', $item->serial_no) : $item->serial_no }}" placeholder="Serial No (comma separated)">
                                        </td>
                                        <td>
                                            <input type="text" name="old[{{$key}}][stock_qty]" class="form-control stock-qty" value="{{$item->batch?->stock ?? 0}}" readonly tabindex="-1">
                                        </td>
                                        <td>
                                            <input type="text" name="old[{{$key}}][qty]" class="form-control qty" value="{{$item->qty}}" required>
                                        </td>
                                        <td>
                                            <input type="text" name="old[{{$key}}][purchase_price]" class="form-control purchase-price" value="{{$item->purchase_price}}" required>
                                        </td>
                                        <td>
                                            <input type="text" name="old[{{$key}}][mrp_price]" class="form-control mrp-price" value="{{$item->mrp_price}}" required>
                                        </td>
                                        <td>
                                            <input type="text" name="old[{{$key}}][total_purchase_price]" class="form-control total-purchase-price" value="{{$item->purchase_price * $item->qty}}" readonly tabindex="-1">
                                        </td>
                                        <td class="text-center">
                                            @if((int)($item->batch?->stock ?? 0) == 0 && !$loop->first)
                                                <button type="button" class="btn btn-danger btn-sm delete" title="Remove line"><i class="fas fa-trash-alt"></i></button>
                                            @else
                                                <span class="row-lock" title="Existing Item"><i class="fas fa-lock"></i></span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="items-footer">
                        <div>
                            <button type="button" class="btn-add-line add" id="add_row_btn">
                                <i class="fas fa-plus-circle"></i> Add product line
                            </button>
                        </div>
                        <div class="items-count"><b id="items-count">{{ count($purchaseDetail) }}</b> line item(s)</div>
                    </div>
                </div>

            </div>

            {{-- ================= SUMMARY COLUMN (ASIDE) ================= --}}
            <aside class="sale-aside">
                <div class="sale-card summary-card crm-animate-in delay-3">
                    <div class="summary-hero purchase-hero">
                        <div class="lbl">Grand Total (৳)</div>
                        <input type="text" class="form-control grand-total" name="total_amount" value="{{ number_format($purchaseMaster->total_amount, 2, '.', '') }}" readonly="readonly" tabindex="-1">
                        <div class="sub">Step 3 — review &amp; payment terms</div>
                    </div>

                    <div class="summary-body">
                        <div class="summary-row">
                            <span class="k"><i class="fas fa-receipt"></i>Sub Total</span>
                            <input type="text" class="form-control v-plain sub-total" name="sub_total" placeholder="0.00" readonly tabindex="-1">
                        </div>
                        <div class="summary-row">
                            <label class="k mb-0" for="vat"><i class="fas fa-percent"></i>VAT (৳)</label>
                            <input type="text" class="form-control v-input vat" id="vat" name="vat" value="{{ number_format($purchaseMaster->vat ?? 0, 2, '.', '') }}" placeholder="0.00">
                        </div>
                        <div class="summary-row">
                            <label class="k mb-0" for="discount"><i class="fas fa-tag"></i>Discount (৳)</label>
                            <input type="text" class="form-control v-input discount" id="discount" name="discount" value="{{ number_format($purchaseMaster->discount ?? 0, 2, '.', '') }}" placeholder="0.00">
                        </div>

                        <div class="summary-pay">
                            <div class="summary-pay-head">
                                <label for="paid_amount">Paid Amount (৳)</label>
                                <button type="button" class="btn-pay-full" id="btn-pay-full">
                                    <i class="fas fa-check mr-1"></i>Full payment
                                </button>
                            </div>
                            <input type="text" class="form-control paid-amount" id="paid_amount" name="paid" value="{{ number_format($purchaseMaster->paid ?? 0, 2, '.', '') }}" placeholder="0.00">

                            <div class="summary-due" id="summary-due">
                                <span class="k">Due to Supplier</span>
                                <input type="text" class="form-control due-amount" name="due_amount" value="{{ number_format(($purchaseMaster->total_amount ?? 0) - ($purchaseMaster->paid ?? 0), 2, '.', '') }}" placeholder="0.00" readonly="readonly" tabindex="-1">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block mt-3" id="btn-save-purchase-bottom" style="height: 48px; border-radius: 10px; font-weight: 700; font-size: 15px; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.35);">
                            <i class="fas fa-check-circle mr-2"></i>Update Purchase Bill
                        </button>
                    </div>
                </div>
            </aside>
        </div>
    </form>

</div>
@endsection

@push('scripts')
    <!-- Select2 & Purchase Module Script -->
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('assets/purchase.js') }}"></script>
    <script>
        $(document).ready(function() {
            // Trigger initial payment mode setup
            $('#payment_type_id').trigger('change');

            // Update line items count display
            function updateLineItemsCount() {
                var count = $('.neworderbody tr').length;
                $('#items-count').text(count);
            }

            // Hook onto add & delete buttons for line count
            $(document).on('click', '.add, .delete', function() {
                setTimeout(updateLineItemsCount, 150);
            });

            // Quick "Full Payment" handler
            $('#btn-pay-full').on('click', function() {
                var paymentType = $('#payment_type_id').val();
                if (paymentType === '4') {
                    alert('Pure Credit purchases cannot record an immediate cash/bank disbursement. The bill is recorded as full Accounts Payable.');
                    return;
                }
                var grandTotal = parseFloat($('.grand-total').val()) || 0;
                $('.paid-amount').val(grandTotal.toFixed(2)).trigger('keyup');
            });
        });
    </script>
@endpush
