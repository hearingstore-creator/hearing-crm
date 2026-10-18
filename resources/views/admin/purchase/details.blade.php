@extends('admin.layouts.master')
@section('page_title', 'Purchase Invoice #' . $purchaseMaster->purchase_invoice_no)
@section('breadcrumb_parent', 'Purchases')
@section('breadcrumb_parent_url', url('admin/purchase'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <style>
        .invoice-container {
            padding: 0 24px 40px;
        }
        .invoice-card {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            padding: 40px 48px;
            margin-bottom: 30px;
        }
        @media (max-width: 768px) {
            .invoice-container {
                padding: 0 12px 24px;
            }
            .invoice-card {
                padding: 20px 16px;
            }
        }
        .billing-block {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px 22px;
            height: 100%;
        }
        .billing-block h6 {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #475569;
            margin-bottom: 12px;
        }
        .doc-table th {
            background: #f8fafc;
            color: #334155;
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 14px;
            border-bottom: 2px solid #cbd5e1;
        }
        .doc-table td {
            padding: 12px 14px;
            vertical-align: middle;
            font-size: 13px;
            border-bottom: 1px solid #f1f5f9;
        }
        .doc-table tfoot td {
            padding: 10px 14px;
            font-size: 13.5px;
        }
        @media print {
            body {
                background: #ffffff !important;
            }
            .main-header, .main-sidebar, .crm-page-header, .crm-footer, .header-actions, .d-print-none {
                display: none !important;
            }
            .content-wrapper {
                margin-left: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
            }
            .invoice-container {
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            .invoice-card {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .billing-block {
                background: transparent !important;
                border: 1px solid #cbd5e1 !important;
            }
            .doc-table th {
                background: #f1f5f9 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
@endpush

@section('content')
<div class="container-fluid invoice-container">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in d-print-none">
        <div class="page-title-group">
            <h1>
                <i class="fas fa-file-invoice text-primary mr-2"></i>Purchase Invoice
                <span style="font-weight: 400; color: #64748b; font-size: 18px;">#{{ $purchaseMaster->purchase_invoice_no }}</span>
            </h1>
            <p>
                Recorded on {{ $purchaseMaster->date ? $purchaseMaster->date->format('d M Y') : '—' }} &bull; 
                Supplier: <strong>{{ $purchaseMaster->supplier->supplier_name ?? '—' }}</strong>
                @if(!empty($purchaseMaster->supplier_memo_no))
                    &bull; Memo No: <strong class="text-dark">{{ $purchaseMaster->supplier_memo_no }}</strong>
                @endif
            </p>
        </div>
        <div class="header-actions">
            @php
                $dueBal = max(0, (float)$purchaseMaster->total_amount - (float)$purchaseMaster->paid);
            @endphp

            @if ($dueBal > 0)
                @can('permission', 'supplier_payment.create')
                    <a href="{{ route('suppliers.payment.create', ['supplier_id' => $purchaseMaster->supplier_id]) }}" class="btn btn-warning btn-sm">
                        <i class="fas fa-money-bill-wave mr-1"></i>Pay Supplier
                    </a>
                @endcan
            @endif

            @can('permission', 'purchase_return.create')
                <a href="{{ url('admin/purchase-returns/create?purchase_id='.$purchaseMaster->id) }}" class="btn btn-outline-danger btn-sm" title="Return items to supplier">
                    <i class="fas fa-undo mr-1"></i>Purchase Return
                </a>
            @endcan

            @can('permission', 'purchase_list.edit')
                <a href="{{ route('purchase.edit', $purchaseMaster->id) }}" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-pen mr-1"></i>Edit
                </a>
            @endcan

            @can('permission', 'purchase_list.index')
                <a href="{{ url('admin/purchase') }}" class="btn btn-secondary btn-sm">
                    <i class="fas fa-list mr-1"></i>Purchase List
                </a>
            @endcan

            <button type="button" class="btn btn-success btn-sm print-btn" id="btn-print-invoice">
                <i class="fas fa-print mr-1"></i>Print Invoice
            </button>
        </div>
    </div>

    @include('admin.alert_message')

    {{-- Main Printable Invoice Card --}}
    <div class="row justify-content-center">
        <div class="col-12 col-xl-11">
            <div class="invoice-card crm-animate-in delay-1" id="print-area">

                {{-- Dual-Branding Document Header (Hearing Store HQ + Branch) --}}
                @include('admin.partials.document_header', [
                    'vendor'       => $purchaseMaster->vendor,
                    'setting'      => $setting,
                    'documentType' => 'PURCHASE ORDER',
                    'documentNo'   => '#' . $purchaseMaster->purchase_invoice_no,
                    'date'         => $purchaseMaster->date,
                    'status'       => 'Received',
                    'statusClass'  => 'completed',
                ])

                {{-- Billing & Shipment Information --}}
                <div class="row mb-4" style="margin-top: 24px;">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <div class="billing-block">
                            <h6><i class="fas fa-truck text-primary mr-1"></i>Supplier (Billed From)</h6>
                            <div style="font-size: 15px; font-weight: 700; color: #0f172a; margin-bottom: 4px;">
                                {{ $purchaseMaster->supplier->supplier_name ?? '—' }}
                            </div>
                            <div style="font-size: 12.5px; color: #475569; line-height: 1.5;">
                                @if(!empty($purchaseMaster->supplier->address1))
                                    <div>{{ $purchaseMaster->supplier->address1 }}</div>
                                @endif
                                @if(!empty($purchaseMaster->supplier->email1))
                                    <div><i class="far fa-envelope mr-1 text-muted"></i>{{ $purchaseMaster->supplier->email1 }}</div>
                                @endif
                                @if(!empty($purchaseMaster->supplier->mobile_no))
                                    <div><i class="fas fa-phone-alt mr-1 text-muted"></i>{{ $purchaseMaster->supplier->mobile_no }}</div>
                                @endif
                            </div>

                            @if (!empty($purchaseMaster->supplier_memo_no))
                                <div class="mt-3 pt-2 border-top" style="font-size: 12px;">
                                    <span class="text-muted">Supplier Memo / Bill No:</span>
                                    <strong class="text-dark ml-1">{{ $purchaseMaster->supplier_memo_no }}</strong>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="billing-block">
                            <h6><i class="fas fa-clinic-medical text-primary mr-1"></i>Receiving Branch / Warehouse</h6>
                            <div style="font-size: 15px; font-weight: 700; color: #0f172a; margin-bottom: 4px;">
                                {{ $purchaseMaster->vendor?->name ?? ($setting->company_name ?? 'Hearing Store Head Office') }}
                            </div>
                            <div style="font-size: 12.5px; color: #475569; line-height: 1.5;">
                                @if(!empty($purchaseMaster->vendor?->address ?? $setting->address))
                                    <div>{{ $purchaseMaster->vendor?->address ?? $setting->address }}</div>
                                @endif
                                @if(!empty($purchaseMaster->vendor?->email ?? $setting->email))
                                    <div><i class="far fa-envelope mr-1 text-muted"></i>{{ $purchaseMaster->vendor?->email ?? $setting->email }}</div>
                                @endif
                                @if(!empty($purchaseMaster->vendor?->mobile_no ?? $setting->mobile))
                                    <div><i class="fas fa-phone-alt mr-1 text-muted"></i>{{ $purchaseMaster->vendor?->mobile_no ?? $setting->mobile }}</div>
                                @endif
                            </div>

                            <div class="mt-3 pt-2 border-top d-flex align-items-center justify-content-between" style="font-size: 12px;">
                                <span class="text-muted">Payment Terms:</span>
                                @php
                                    $pId = (int)$purchaseMaster->payment_id;
                                    $paymentTypesList = \App\Classes\Helper::paymentType();
                                    $pName = $paymentTypesList[$pId] ?? ($pId === 4 ? 'Credit / Pay Later' : 'Credit');
                                    $pBadge = $pId === 4 ? 'badge-warning text-dark' : ($pId === 1 ? 'badge-success' : 'badge-primary');
                                @endphp
                                <span class="badge {{ $pBadge }}" style="font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px;">
                                    {{ $pName }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Product Items Table --}}
                <div class="table-responsive mb-4" style="border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;">
                    <table class="table mb-0 doc-table">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 50px;">#</th>
                                <th>Product Item Details</th>
                                <th style="width: 130px;">Batch No</th>
                                <th class="text-center" style="width: 90px;">Qty</th>
                                <th class="text-right" style="width: 140px;">Unit Price (৳)</th>
                                <th class="text-right" style="width: 150px;">Total Cost (৳)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $subtotal = 0;
                            @endphp
                            @forelse (($purchaseDetail ?? []) as $key => $item)
                                @php
                                    $qty = (float) $item->qty;
                                    $totalPrice = (float) $item->purchase_price * $qty;
                                    $subtotal += $totalPrice;
                                    $brandName = $item->product?->brand?->brand_name ?? '';
                                    $modelName = $item->product?->model ?? '';
                                    $catName = $item->product?->category?->category_name ?? '';
                                    $displayTitle = trim("{$brandName} {$modelName}") ?: ('Product #' . $item->product_id);
                                    $serials = is_array($item->serial_no) ? $item->serial_no : (json_decode($item->serial_no, true) ?: (empty($item->serial_no) ? [] : explode(',', $item->serial_no)));
                                @endphp
                                <tr>
                                    <td class="text-center text-muted" style="font-size: 12px;">{{ ++$key }}</td>
                                    <td>
                                        <div style="font-weight: 700; color: #0f172a; font-size: 13.5px;">{{ $displayTitle }}</div>
                                        @if(!empty($catName))
                                            <span class="badge badge-light border text-muted" style="font-size: 10.5px; font-weight: 500;">
                                                {{ $catName }}
                                            </span>
                                        @endif
                                        @if(!empty($serials))
                                            <div class="mt-1" style="display: flex; flex-wrap: wrap; gap: 4px;">
                                                @foreach($serials as $sn)
                                                    @if(trim($sn))
                                                        <span class="badge badge-light border" style="font-size: 11px; font-weight: 500; color: #334155; padding: 2px 6px;">
                                                            <i class="fas fa-barcode text-primary mr-1"></i>{{ trim($sn) }}
                                                        </span>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        @if(!empty($item->batch?->batch_no))
                                            <span class="badge badge-secondary" style="font-size: 11px; padding: 3px 8px;">
                                                {{ $item->batch->batch_no }}
                                            </span>
                                        @else
                                            <span class="text-muted" style="font-size: 12px;">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center font-weight-bold" style="font-size: 14px; color: #0f172a;">
                                        {{ $qty }}
                                    </td>
                                    <td class="text-right" style="font-weight: 600; color: #334155;">
                                        ৳{{ number_format($item->purchase_price, 2) }}
                                    </td>
                                    <td class="text-right font-weight-bold" style="font-size: 13.5px; color: #0f172a;">
                                        ৳{{ number_format($totalPrice, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No items recorded on this purchase.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot style="background: #f8fafc; border-top: 2px solid #e2e8f0;">
                            <tr>
                                <td colspan="4" class="border-0"></td>
                                <td class="text-right font-weight-bold text-muted border-0">Sub Total:</td>
                                <td class="text-right font-weight-bold border-0">৳{{ number_format($subtotal, 2) }}</td>
                            </tr>
                            @if((float) ($purchaseMaster->vat ?? 0) > 0)
                                <tr>
                                    <td colspan="4" class="border-0"></td>
                                    <td class="text-right font-weight-bold text-muted border-0">VAT:</td>
                                    <td class="text-right font-weight-bold border-0">৳{{ number_format($purchaseMaster->vat, 2) }}</td>
                                </tr>
                            @endif
                            @if((float) ($purchaseMaster->discount ?? 0) > 0)
                                <tr>
                                    <td colspan="4" class="border-0"></td>
                                    <td class="text-right font-weight-bold text-muted border-0">Discount:</td>
                                    <td class="text-right font-weight-bold text-success border-0">-৳{{ number_format($purchaseMaster->discount, 2) }}</td>
                                </tr>
                            @endif
                            <tr style="border-top: 1.5px solid #cbd5e1; border-bottom: 1.5px solid #cbd5e1; background: #f1f5f9;">
                                <td colspan="4" class="border-0"></td>
                                <td class="text-right font-weight-bold text-dark border-0" style="font-size: 15px;">Grand Total:</td>
                                <td class="text-right font-weight-bold text-primary border-0" style="font-size: 16px;">
                                    ৳{{ number_format($purchaseMaster->total_amount, 2) }}
                                </td>
                            </tr>
                            <tr>
                                <td colspan="4" class="border-0"></td>
                                <td class="text-right font-weight-bold text-muted border-0">Paid Amount:</td>
                                <td class="text-right font-weight-bold text-success border-0">
                                    ৳{{ number_format($purchaseMaster->paid, 2) }}
                                </td>
                            </tr>
                            <tr>
                                <td colspan="4" class="border-0"></td>
                                <td class="text-right font-weight-bold text-muted border-0">Due (Payable):</td>
                                <td class="text-right font-weight-bold text-danger border-0" style="font-size: 15px;">
                                    ৳{{ number_format(max(0, (float) $purchaseMaster->total_amount - (float) $purchaseMaster->paid), 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                @if(!empty($purchaseMaster->details))
                    <div class="mb-4 p-3" style="background: #f8fafc; border-left: 3px solid #6366f1; border-radius: 6px; font-size: 12.5px; color: #475569;">
                        <strong class="text-dark">Remarks / Shipment Notes:</strong> {{ $purchaseMaster->details }}
                    </div>
                @endif

                {{-- Document Footer Note --}}
                <div class="text-center mt-5 pt-3 border-top" style="font-size: 11.5px; color: #94a3b8;">
                    <p class="mb-1 font-weight-bold">HEARING STORE &bull; OFFICIAL PURCHASE VOUCHER</p>
                    <p class="mb-0">This is a system-generated document. Verified and recorded in central warehouse inventory.</p>
                </div>

            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // Working Print trigger for all print buttons
        $(document).on('click', '.print-btn, #btn-print-invoice', function(e) {
            e.preventDefault();
            window.print();
        });
    });
</script>
@endpush
