@extends('admin.layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content sales-details pt-3">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-12 col-lg-10 col-xl-10">
                    <div class="header mb-3 shadow-none">
                        <div class="header-body">
                            <div class="row align-items-center">
                                <div class="col-12 col-md-6 mb-2 mb-md-0">
                                    <h4 class="mb-0">Sale Details</h4>
                                </div>
                                <div class="col-12 col-md-6 text-md-right">
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
                                    <p class="text-muted">Invoice: {{ $saleMaster->sale_invoice_no }}</p>
                                </div>
                            </div>

                            <!-- Billing Information -->
                            <div class="row mb-4">
                                <div class="col-12 col-md-6 mb-4 mb-md-0">
                                    <div class="billing-details">
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
                                            <p class="mb-0">{{ $saleMaster->sale_invoice_no }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="billing-details text-md-right">
                                        <h5 class="text-uppercase text-primary font-weight-600 mb-3">Billing To</h5>
                                        <p class="text-muted mb-2">
                                            <strong class="d-block text-body fs-16">{{ $saleMaster->customer->lead->full_name }}</strong>
                                            Mobile: {{ $saleMaster->customer->lead->phone_number }}
                                        </p>
                                        <div class="mt-3">
                                            <h6 class="text-uppercase text-muted fs-12 font-weight-600 mb-1">Date</h6>
                                            <p class="mb-0">{{ $saleMaster->date->format('d M Y') }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @php
                                $subtotal = 0;
                                $subTotalDiscount = 0;
                                $serviceSubtotal = 0;
                                $serviceSubTotalDiscount = 0;
                            @endphp
                            @if(isset($saleDetail) && count($saleDetail) > 0)
                            <!-- Invoice Table -->
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th class="text-center">Sl</th>
                                            <th>Product</th>
                                            <th>Serial No</th>
                                            <th class="text-center">Quantity</th>
                                            <th class="text-right">Price</th>
                                            <th class="text-right">Discount %</th>
                                            <th class="text-right">Discount Taka</th>
                                            <th class="text-right">Total Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($saleDetail as $key => $item)
                                            @php
                                                $discountTaka = ($item->qty * $item->mrp_price /100) * $item->discount;
                                                $totalPrice = $item->qty * $item->mrp_price - $discountTaka;
                                                $subtotal += $totalPrice;
                                                $subTotalDiscount += $discountTaka;
                                                $invoiceDiscount = $saleMaster->total_discount - $subTotalDiscount;
                                                $serialNo = ! empty($item->serial_no) ? implode(', ', $item->serial_no) : null;
                                            @endphp
                                            <tr>
                                                <td class="text-center">{{ ++$key }}</td>
                                                <td>{{ ($item->product?->category?->category_name) . ' - ' . $item->product?->model }}</td>
                                                <td class="text-center">{{ $serialNo }}</td>
                                                <td class="text-center">{{ $item->qty }}</td>
                                                <td class="text-right">{{ $item->mrp_price }}</td>
                                                <td class="text-right">{{ $item->discount }}</td>
                                                <td class="text-right">{{ $discountTaka }}</td>
                                                <td class="text-right">{{ number_format($totalPrice, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @endif

                            <!-- Service Details Section -->
                            @if(isset($serviceDetails) && count($serviceDetails) > 0)
                            <div class="table-responsive mt-4">
                                <h5 class="text-primary font-weight-600 mb-3">Service Details</h5>
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th class="text-center">Sl</th>
                                            <th>Service Type</th>
                                            <th class="text-center">Quantity</th>
                                            <th class="text-right">Unit Price</th>
                                            <th class="text-right">Discount %</th>
                                            <th class="text-right">Discount Taka</th>
                                            <th class="text-right">Total Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($serviceDetails as $key => $item)
                                            @php
                                                $serviceDiscountTaka = ($item->qty * $item->service_price / 100) * ($item->discount ?? 0);
                                                $serviceTotalPrice = $item->qty * $item->service_price - $serviceDiscountTaka;
                                                $serviceSubtotal += $serviceTotalPrice;
                                                $serviceSubTotalDiscount += $serviceDiscountTaka;
                                            @endphp
                                            <tr>
                                                <td class="text-center">{{ ++$key }}</td>
                                                <td>{{ $item->serviceType->service_type_name ?? 'N/A' }}</td>
                                                <td class="text-center">{{ $item->qty }}</td>
                                                <td class="text-right">{{ $item->service_price }}</td>
                                                <td class="text-right">{{ $item->discount ?? 0 }}</td>
                                                <td class="text-right">{{ number_format($serviceDiscountTaka, 2) }}</td>
                                                <td class="text-right">{{ number_format($serviceTotalPrice, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @endif

                            <!-- Combined Summary Table -->
                            <div class="table-responsive mt-4">
                                <table class="table table-bordered">
                                    <tfoot>
                                        @php
                                            $totalSubtotal = $subtotal + ($serviceSubtotal ?? 0);
                                            $totalSubTotalDiscount = $subTotalDiscount + ($serviceSubTotalDiscount ?? 0);
                                            $invoiceDiscount = $saleMaster->total_discount - $totalSubTotalDiscount;
                                        @endphp
                                        <tr>
                                            <td colspan="6" class="text-right"><strong>Sub Total</strong></td>
                                            <td class="text-right">{{ number_format($totalSubtotal, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td colspan="6" class="text-right"><strong>Vat</strong></td>
                                            <td class="text-right">{{ number_format($saleMaster->vat, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td colspan="6" class="text-right"><strong>Invoice Discount</strong></td>
                                            <td class="text-right">{{ number_format($invoiceDiscount, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td colspan="6" class="text-right"><strong>Grand Total</strong></td>
                                            <td class="text-right">{{ number_format($totalSubtotal - $invoiceDiscount + $saleMaster->vat, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td colspan="6" class="text-right"><strong>Paid Amount</strong></td>
                                            <td class="text-right">{{ number_format($saleMaster->paid, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td colspan="6" class="text-right"><strong>Due</strong></td>
                                            <td class="text-right">{{ number_format($saleMaster->total_amount - $saleMaster->paid, 2) }}</td>
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
