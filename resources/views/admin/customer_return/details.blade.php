@extends('admin.layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content pt-3">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-12">
                    <div class="header mb-2 shadow-none">
                        <div class="header-body">
                            <div class="row align-items-center">
                                <div class="col-12 col-md-6 mb-2 mb-md-0">
                                </div>
                                <div class="col-12 col-md-6 text-md-right">
                                    <div class="btn-group">
                                        <a href="{{ url('admin/customer-return') }}"
                                            class="btn btn-primary btn-cs-primary btn-sm mb-2 mb-md-0 mr-1">
                                            <i class="fas fa-align-justify mr-1"></i>Customer Return List
                                        </a>
                                        <a href="#" class="btn btn-success btn-cs-primary btn-sm mb-2 mb-md-0 print-btn">
                                            <i class="fas fa-print mr-1"></i>Print Invoice
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card card-body p-3 p-md-5">
                        <div id="print-area">
                            <div class="row mb-4">
                                <div class="col-12 text-center">
                                    <h4 class="mb-2 font-weight-bold">{{ $setting->company_name }}</h4>
                                    <p class="text-muted">Invoice: {{ $cRMaster->sale_invoice_no }}</p>
                                </div>
                            </div>
                            <div class="row mb-4">
                                <div class="col-12 col-md-6 mb-4 mb-md-0">
                                    <div class="billing-details">
                                        <h3 class="text-uppercase text-primary font-weight-600 mb-3">Billing From</h3>
                                        <div class="text-muted mb-4">
                                            <strong class="text-body d-block mb-2">{{ $setting->company_name }}</strong>
                                            <span class="d-block">{{ $setting->address }}</span>
                                            <span class="d-block">{{ $setting->email }}</span>
                                            <span class="d-block">{{ $setting->phone }}</span>
                                        </div>
                                        <h6 class="text-uppercase text-muted fs-12 font-weight-600 mb-2">Sale Invoice No
                                        </h6>
                                        <p class="mb-0">{{ $cRMaster->sale_invoice_no }}</p>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="billing-details text-md-right">
                                        <h3 class="text-uppercase text-primary font-weight-600 mb-3">Billing To</h3>
                                        <div class="text-muted mb-4">
                                            <strong
                                                class="text-body fs-16 d-block mb-2">{{ $cRMaster->sale->customer->name }}</strong>
                                            <span class="d-block">Phone: {{ $cRMaster->sale->customer->phone }}</span>
                                        </div>
                                        <h6 class="text-uppercase text-muted fs-12 font-weight-600 mb-2">Date</h6>
                                        <p class="mb-0"><time datetime="">{{ $cRMaster->date }}</time></p>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped my-4">
                                            <thead>
                                                <tr>
                                                    <th class="bg-transparent">
                                                        <span class="h6 font-weight-bold">Sl</span>
                                                    </th>
                                                    <th class="bg-transparent text-center">
                                                        <span class="h6 font-weight-bold">Medicine</span>
                                                    </th>
                                                    <th class="bg-transparent text-center">
                                                        <span class="h6 font-weight-bold">Qty</span>
                                                    </th>
                                                    <th class="bg-transparent text-center">
                                                        <span class="h6 font-weight-bold">Deduction %</span>
                                                    </th>
                                                    <th class="bg-transparent text-center">
                                                        <span class="h6 font-weight-bold">Deduction (Tk)</span>
                                                    </th>
                                                    <th class="bg-transparent text-center">
                                                        <span class="h6 font-weight-bold">Unit Price</span>
                                                    </th>
                                                    <th class="bg-transparent text-center">
                                                        <span class="h6 font-weight-bold">Total</span>
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php
                                                    $subtotal = 0;
                                                    $subTotalDeduction = 0;
                                                @endphp
                                                @foreach ($cRDetail as $key => $item)
                                                    <tr>
                                                        @php
                                                            $totalPrice =
                                                                $item->return_qty * $item->unit_price -
                                                                $item->deduction_taka;
                                                            $subtotal += $totalPrice;
                                                            $subTotalDeduction += $item->deduction_taka;
                                                        @endphp

                                                        <td class="px-0 text-center">{{ ++$key }}</td>
                                                        <td class="px-0 text-center">
                                                            {{ $item->medicine->medicine_name }}
                                                        </td>
                                                        <td class="px-0 text-center">
                                                            {{ $item->return_qty }}
                                                        </td>
                                                        <td class="px-0 text-center">
                                                            {{ $item->deduction }}%
                                                        </td>
                                                        <td class="px-0 text-center">
                                                            {{ $item->deduction_taka }}
                                                        </td>
                                                        <td class="px-0 text-center">
                                                            {{ $item->unit_price }}
                                                        </td>

                                                        <td class="px-0 text-center">
                                                            {{ number_format($totalPrice, 2) }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                                <tr>
                                                    <td class="px-0 border-top border-top-2 text-right" colspan="6">
                                                        <strong>Total Deduction</strong>
                                                    </td>
                                                    <td class="px-0 border-top border-top-2 text-center">
                                                        <span class="fs-16 font-weight-600">
                                                            {{ number_format($subTotalDeduction, 2) }} </span>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="px-0 border-top border-top-2 text-right" colspan="6">
                                                        <strong>Grand Total</strong>
                                                    </td>
                                                    <td class="px-0 text-right border-top border-top-2 text-center">
                                                        <span class="fs-16 font-weight-600">
                                                            {{ number_format($subtotal, 2) }} </span>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <hr class="my-5">
                                    <p class="text-muted mb-0">
                                        THANK YOU
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
