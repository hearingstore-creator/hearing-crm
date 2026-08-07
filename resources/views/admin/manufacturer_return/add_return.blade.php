@extends('admin.layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content pt-3">
        <div class="container-fluid">
            @include('admin.alert_message')
            <div class="row">
                <div class="col-12 col-md-6 mb-3 mb-md-0">
                    <div class="card card-outline card-primary">
                        <div class="card-header py-2">
                            <div class="d-flex justify-content-center align-items-center">
                                <h6 class="fs-17 font-weight-600 mb-0">Return From Customer</h6>
                            </div>
                        </div>
                        <div class="card-body">
                            <form action="{{ url('admin/customer/add-return') }}" id="return_invoice_customer" method="post" accept-charset="utf-8">
                                @csrf
                                <div class="form-group">
                                    <label for="invoice_no" class="col-form-label">
                                        <b>Sale Invoice No <i class="text-danger">*</i></b>
                                    </label>
                                    <div class="row">
                                        <div class="col-8 col-sm-9">
                                            <input name="sale_invoice_no" class="form-control" type="text" placeholder="Invoice No" id="invoice_no" value="">
                                        </div>
                                        <div class="col-4 col-sm-3 pt-1">
                                            <button type="submit" class="btn btn-success btn-sm w-100">Search</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="card card-outline card-primary">
                        <div class="card-header py-2">
                            <div class="d-flex justify-content-center align-items-center">
                                <h6 class="fs-17 font-weight-600 mb-0">Return To Manufacturer</h6>
                            </div>
                        </div>
                        <div class="card-body">
                            <form action="{{ url('admin/manufacturer/add-return') }}" id="return_invoice_manufacturer" method="post" accept-charset="utf-8">
                                @csrf
                                <div class="form-group">
                                    <label for="purchase_invoice_no" class="col-form-label">
                                        <b>Purchase Invoice No <i class="text-danger">*</i></b>
                                    </label>
                                    <div class="row">
                                        <div class="col-8 col-sm-9">
                                            <input name="purchase_invoice_no" class="form-control" type="text" placeholder="Purchase Invoice No" id="purchase_invoice_no" value="" autocomplete="off">
                                        </div>
                                        <div class="col-4 col-sm-3 pt-1">
                                            <button type="submit" class="btn btn-success btn-sm w-100">Search</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /.container-fluid -->
    </section>
    <!-- /.content -->
@endsection
