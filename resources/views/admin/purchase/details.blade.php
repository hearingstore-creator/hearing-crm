@extends('admin.layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content purchase-details pt-3">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-12 col-lg-10 col-xl-8">
                    <div class="header mb-3 shadow-none">
                        <div class="header-body">
                            <div class="row align-items-center">
                                <div class="col-12 col-md-6 mb-2 mb-md-0">
                                    <h4 class="mb-0">Purchase Details</h4>
                                </div>
                                <div class="col-12 col-md-6 text-md-right">
                                    @can('permission', 'purchase_list.index')
                                    <a href="{{ url('admin/purchase') }}" class="btn btn-primary btn-sm">
                                        <i class="fas fa-align-justify mr-1"></i>Purchase List
                                    </a>
                                    @endcan
                                    <a href="#" class="btn btn-success btn-sm print-btn">
                                        <i class="fas fa-print mr-1"></i>Print Invoice
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card card-body p-4 p-md-5">
                        <div class="print-area" id="print-area">
                            <!-- Invoice Header -->
                            <div class="row mb-4">
                                <div class="col-12 text-center">
                                    @if (!empty($setting->logo))
                                        <img src="{{ asset($setting->logo) }}" alt="Company Logo" class="img-fluid mb-3">
                                    @endif
                                    <h4 class="mb-1 font-weight-bold">{{ $setting->company_name }}</h4>
                                    <p class="text-muted">Invoice: {{ $purchaseMaster->purchase_invoice_no }}</p>
                                </div>
                            </div>

                            <!-- Billing Information -->
                            <div class="row mb-4">
                                <div class="col-12 col-md-6 mb-4 mb-md-0">
                                    <div class="billing-details">
                                        <h5 class="text-uppercase text-primary font-weight-600 mb-3">Billing To</h5>
                                        <p class="text-muted mb-2">
                                            <strong class="d-block text-body fs-16">{{ $purchaseMaster->manufacturer->manufacturer_name }}</strong>
                                            {{ $purchaseMaster->manufacturer->address1 }}<br>
                                            {{ $purchaseMaster->manufacturer->email1 }}<br>
                                            Mobile: {{ $purchaseMaster->manufacturer->mobile_no }}
                                        </p>
                                        <div class="mt-3">
                                            <h6 class="text-uppercase text-muted fs-12 font-weight-600 mb-1">Date</h6>
                                            <p class="mb-2">{{ $purchaseMaster->date }}</p>
                                            @if (!empty($purchaseMaster->company_invoice_no))
                                                <span class="text-muted">Company Invoice No: {{ $purchaseMaster->company_invoice_no }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="billing-details text-md-right">
                                        <h5 class="text-uppercase text-primary font-weight-600 mb-3">Billing From</h5>
                                        <p class="text-muted mb-2">
                                            <strong class="d-block text-body fs-16">{{ $setting->company_name }}</strong>
                                            {{ $setting->address }}<br>
                                            {{ $setting->email }}<br>
                                            @if (!empty($setting->mobile))
                                                Mobile: {{ $setting->mobile }}
                                            @endif
                                        </p>
                                        <div class="mt-3">
                                            <h6 class="text-uppercase text-muted fs-12 font-weight-600 mb-1">Invoice No</h6>
                                            <p class="mb-0">{{ $purchaseMaster->purchase_invoice_no }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Invoice Table -->
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th class="text-center">Sl</th>
                                            <th>Medicine Name</th>
                                            <th class="text-center">Box</th>
                                            <th class="text-center">PCS</th>
                                            <th class="text-right">Price</th>
                                            <th class="text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $subtotal = 0;
                                        @endphp
                                        @foreach ($purchaseDetail as $key => $item)
                                            @php
                                                $totalPrice = $item->purchase_price * $item->box_qty;
                                                $subtotal += $totalPrice;
                                            @endphp
                                            <tr>
                                                <td class="text-center">{{ ++$key }}</td>
                                                <td>{{ $item->medicine->medicine_name }}</td>
                                                <td class="text-center">{{ $item->box_qty }}</td>
                                                <td class="text-center">{{ $item->qty }}</td>
                                                <td class="text-right">{{ $item->purchase_price }}</td>
                                                <td class="text-right">{{ number_format($totalPrice, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="4"></td>
                                            <td class="text-right"><strong>Sub Total</strong></td>
                                            <td class="text-right">{{ number_format($subtotal, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td colspan="4"></td>
                                            <td class="text-right"><strong>Vat</strong></td>
                                            <td class="text-right">{{ number_format($purchaseMaster->vat, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td colspan="4"></td>
                                            <td class="text-right"><strong>Discount</strong></td>
                                            <td class="text-right">{{ number_format($purchaseMaster->discount, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td colspan="4"></td>
                                            <td class="text-right"><strong>Grand Total</strong></td>
                                            <td class="text-right">{{ number_format($purchaseMaster->total_amount) }}</td>
                                        </tr>
                                        <tr>
                                            <td colspan="4"></td>
                                            <td class="text-right"><strong>Paid Amount</strong></td>
                                            <td class="text-right">{{ number_format($purchaseMaster->paid, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td colspan="4"></td>
                                            <td class="text-right"><strong>Due</strong></td>
                                            <td class="text-right">{{ number_format($purchaseMaster->total_amount - $purchaseMaster->paid, 2) }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <!-- Thank You Message -->
                            <div class="text-center mt-4">
                                <p class="text-muted mb-0">THANK YOU</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
