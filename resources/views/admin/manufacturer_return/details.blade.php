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
                <div class="col">
                </div>
                <div class="col-12 col-sm-auto text-center text-sm-right mt-2 mt-sm-0">
                    <div class="btn-group flex-wrap" role="group">
                        <a href="{{url('admin/manufacturer-return')}}" class="btn btn-primary btn-sm mb-1 mb-sm-0 mr-1">
                            <i class="fas fa-align-justify mr-1"></i>
                            <span class="d-none d-md-inline">Manufacturer Return List</span>
                            <span class="d-md-none">List</span>
                        </a>
                        <a href="#" class="btn btn-success btn-sm print-btn">
                            <i class="fas fa-print mr-1"></i>
                            <span class="d-none d-md-inline">Print Invoice</span>
                            <span class="d-md-none">Print</span>
                        </a>
                    </div>
                </div>
             </div>
          </div>
       </div>
        <div class="card card-body p-3 p-md-5">
            <div id="print-area">
                <div class="row">
                    <div class="col-12 text-center">
                        <h4 class="mb-0 font-weight-bold">{{$setting->company_name}}</h4>
                        <p class="text-muted mb-4 mb-md-5">Invoice: {{$mRMaster->purchase_invoice_no}}</p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12 col-md-6 mb-4 mb-md-0">
                        <h3 class="text-uppercase text-primary font-weight-600 mb-3">Billing From</h3>
                        <p class="text-muted mb-4">
                            <strong class="text-body">{{$setting->company_name}}</strong> <br>
                            {{$setting->address}} <br>
                            {{$setting->email}} <br>
                            {{$setting->phone}} 
                        </p>
                        <h6 class="text-uppercase text-muted fs-12 font-weight-600">Purchase Invoice No</h6>
                        <p class="mb-4">{{$mRMaster->purchase_invoice_no}}</p>
                    </div>
                    <div class="col-12 col-md-6 text-left text-md-right">
                        <h3 class="text-uppercase text-primary font-weight-600 mb-3">Billing To</h3>
                        <p class="text-muted mb-4">
                            <strong class="text-body fs-16">{{$mRMaster->purchase->manufacturer->manufacturer_name}}</strong>
                            <br>
                            Phone: {{$mRMaster->purchase->manufacturer->phone}}
                        </p>
                        <h6 class="text-uppercase text-muted fs-12 font-weight-600">Date</h6>
                        <p class="mb-4"><time datetime="">{{$mRMaster->date}}</time></p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="table-responsive">
                            <table class="table my-4 table-bordered table-striped">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="bg-transparent border-top-0 text-center">
                                            <span class="h6 font-weight-bold">Sl</span>
                                        </th>
                                        <th class="bg-transparent border-top-0 text-center">
                                            <span class="h6 font-weight-bold">
                                                <span class="d-none d-sm-inline">Medicine Name</span>
                                                <span class="d-sm-none">Medicine</span>
                                            </span>
                                        </th>
                                        <th class="bg-transparent border-top-0 text-center">
                                            <span class="h6 font-weight-bold">
                                                <span class="d-none d-sm-inline">Quantity</span>
                                                <span class="d-sm-none">Qty</span>
                                            </span>
                                        </th>
                                        <th class="bg-transparent border-top-0 text-center">
                                            <span class="h6 font-weight-bold">
                                                <span class="d-none d-sm-inline">Deduction Taka</span>
                                                <span class="d-sm-none">Deduction</span>
                                            </span>
                                        </th>
                                        <th class="bg-transparent border-top-0 text-center">
                                            <span class="h6 font-weight-bold">
                                                <span class="d-none d-sm-inline">Unit Price</span>
                                                <span class="d-sm-none">Price</span>
                                            </span>
                                        </th>
                                        <th class="bg-transparent border-top-0 text-center">
                                            <span class="h6 font-weight-bold">
                                                <span class="d-none d-sm-inline">Total Amount</span>
                                                <span class="d-sm-none">Total</span>
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $subtotal = 0;
                                        $subTotalDeduction = 0;
                                    @endphp
                                    @foreach ($mRDetail as $key => $item)
                                        <tr>
                                            @php
                                                $totalPrice = $item->return_qty * $item->unit_price - $item->deduction_taka;
                                                $subtotal += $totalPrice;
                                                $subTotalDeduction += $item->deduction_taka;
                                            @endphp
                                            
                                            <td class="text-center align-middle">{{++$key}}</td>
                                            <td class="text-center align-middle">
                                                <div class="text-truncate d-sm-none" title="{{$item->medicine->medicine_name}}">
                                                    {{$item->medicine->medicine_name}}
                                                </div>
                                                <div class="d-none d-sm-block">
                                                    {{$item->medicine->medicine_name}}
                                                </div>
                                            </td>
                                            <td class="text-center align-middle">
                                                {{$item->return_qty}}
                                            </td>
                                            <td class="text-center align-middle">
                                                {{$item->deduction_taka}}
                                            </td>
                                            <td class="text-center align-middle">
                                                {{$item->unit_price}}
                                            </td>
                                            <td class="text-center align-middle">
                                                {{number_format($totalPrice, 2)}}
                                            </td>
                                        </tr>
                                    @endforeach
                                    <tr>
                                        <td class="border-top border-top-2 text-right font-weight-bold" colspan="5">
                                            Total Deduction
                                        </td>
                                        <td class="text-center border-top border-top-2 font-weight-bold">
                                            {{number_format($subTotalDeduction, 2)}}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="border-top border-top-2 text-right font-weight-bold" colspan="5">
                                            Grand Total
                                        </td>
                                        <td class="text-center border-top border-top-2 font-weight-bold">
                                            {{number_format($subtotal, 2)}}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <hr class="my-4 my-md-5">
                        <div class="text-center">
                            <p class="text-muted mb-0 h5">THANK YOU</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
 </div>
</div>
</section>
@endsection
