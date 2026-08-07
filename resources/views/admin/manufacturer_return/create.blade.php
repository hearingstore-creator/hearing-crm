@extends('admin.layouts.master')

@section('content')
    <!-- Main content -->
    <section class="content pt-3">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5>Return To Manufacturer</h5>
                                </div>
                                <div class="col-md-6 text-right">
                                    <a href="{{ url('admin/manufacturer-return') }}"
                                        class="btn-primary btn-cs-primary btn-sm mr-1">
                                        <i class="fas fa-align-justify mr-1"></i>Manufacturer Return List
                                    </a>
                                    <a href="{{ url('admin/wastage-return') }}" class="btn-primary btn-cs-primary btn-sm">
                                        <i class="fas fa-align-justify mr-1"></i>Wastage Return List
                                    </a>
                                </div>
                            </div>
                        </div>
                        @include('admin.alert_message')
                        <div class="card-body">
                            <form action="{{ url('admin/manufacturer-return') }}" enctype="multipart/form-data"
                                method="post">
                                @csrf

                                <div class="form-group row">
                                    <label for="customer_id" class="col-md-2 col-form-label">Manufacturer <i
                                            class="text-danger"> * </i>:</label>
                                    <div class="col-md-4">
                                        <div class="">
                                            <input type="text" name=""
                                                value="{{ $purchaseMaster->manufacturer->manufacturer_name }}"
                                                class="form-control" id="manufacturer_name" readonly>
                                            <input class="form-control" type="hidden"
                                                value="{{ $purchaseMaster->manufacturer_id }}" name="manufacturer_id">
                                        </div>
                                    </div>
                                    <label for="date" class="col-md-2 col-form-label">Date <i class="text-danger"> *
                                        </i>:</label>
                                    <div class="col-md-4">
                                        <div class="">
                                            <input type="date" name="date" value="{{ date('Y-m-d') }}"
                                                class="form-control date" id="date">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="purchase_invoice_no" class="col-md-2 col-form-label">Purchase Invoice
                                        No:</label>
                                    <div class="col-md-4">
                                        <div class="">
                                            <input class="form-control" id="purchase_invoice_no" type="text"
                                                value="{{ $purchaseMaster->purchase_invoice_no }}"
                                                name="purchase_invoice_no" readonly>
                                        </div>
                                    </div>
                                    <label for="details" class="col-md-2 col-form-label">Details:</label>
                                    <div class="col-md-4">
                                        <div class="">
                                            <input class="form-control" id="details" type="text"
                                                value="{{ $purchaseMaster->details }}" name="details" placeholder="Details">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="details" class="col-md-2 col-form-label">Return Type:</label>

                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="type" id="inlineRadio1"
                                            value="{{ $manufacturer }}" checked>
                                        <label class="form-check-label" for="inlineRadio1">Manufacturer</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="type" id="inlineRadio2"
                                            value="{{ $wastage }}">
                                        <label class="form-check-label" for="inlineRadio2">Wastage</label>
                                    </div>
                                </div>
                                <div class="panel-body table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Medicine <i class="text-danger">*</i></th>
                                                <th>Stock Qty </th>
                                                <th>Purchase Qty <i class="text-danger">*</i></th>
                                                <th>Return Qty<i class="text-danger">*</i></th>
                                                <th>Purchase Price(unit)<i class="text-danger">*</i></th>
                                                <th>Deduction %</th>
                                                <th>Total</th>
                                                <th>Check Return</th>
                                            </tr>
                                        </thead>
                                        <tbody class="neworderbody">
                                            @foreach ($purchaseDetail as $key => $item)
                                                <tr>
                                                    <td>
                                                        <input type="hidden" autocomplete="off" name="id[]"
                                                            class="form-control" value="{{ $item->id }}">
                                                        <input type="text" name=""
                                                            value="{{ $item->medicine->medicine_name }}"
                                                            class="form-control" id="medicine_name" readonly>
                                                        <input type="hidden" autocomplete="off"
                                                            name="medicine[{{ $key }}][medicine_id]"
                                                            class="form-control" value="{{ $item->medicine_id }}">
                                                        <input type="hidden" autocomplete="off"
                                                            name="medicine[{{ $key }}][batch_id]"
                                                            class="form-control batch_id" value="{{ $item->batch_id }}">
                                                    </td>

                                                    <td>
                                                        <input type="text" autocomplete="off" name="stock_qty[]"
                                                            class="form-control stock-qty" value="{{ $item->stock }}"
                                                            readonly>
                                                    </td>
                                                    <td>
                                                        <input type="text" name="qty[]" class="form-control qty"
                                                            value="{{ $item->qty }}" readonly>
                                                    </td>
                                                    <td>
                                                        <input type="text"
                                                            name="medicine[{{ $key }}][return_qty]"
                                                            class="form-control return-qty" value="1">
                                                    </td>

                                                    <td>
                                                        <input type="text"
                                                            name="medicine[{{ $key }}][unit_price]"
                                                            class="form-control unit-price"
                                                            value="{{ number_format($item->purchase_price / $item->medicine->leaf->total_number_per_box, 2) }}"
                                                            readonly>
                                                    </td>

                                                    <td>
                                                        <input type="text"
                                                            name="medicine[{{ $key }}][deduction]"
                                                            class="form-control deduction" value="0">
                                                        <input type="hidden"
                                                            name="medicine[{{ $key }}][deduction_taka]"
                                                            class="form-control deduction-taka" value="0">
                                                    </td>
                                                    <td>
                                                        <input type="text"
                                                            name="medicine[{{ $key }}][total_price]"
                                                            class="form-control total-price"
                                                            value="{{ number_format($item->purchase_price / $item->medicine->leaf->total_number_per_box, 2) }}"
                                                            readonly="readonly">
                                                    </td>
                                                    <td>
                                                        <input type="checkbox"
                                                            name="medicine[{{ $key }}][check]"
                                                            class="form-control check" checked>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>

                                        <tfoot>
                                            <tr>
                                                <td class="text-right" colspan="7"><b>Total Deduction:</b></td>
                                                <td class="text-right">
                                                    <input type="text" class="text-right form-control total-deduction"
                                                        name="total_deduction" value="" placeholder="0.00"
                                                        readonly>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-right" colspan="7"><b>Net Return:</b></td>
                                                <td class="text-right">
                                                    <input type="text" class="text-right form-control grand-total"
                                                        name="total_amount" value="" placeholder="0.00" readonly>
                                                </td>
                                            </tr>
                                        </tfoot>

                                    </table>

                                    <hr>
                                </div>
                                <div class="form-group row">
                                    <div class="col-md-6 text-right"></div>
                                    <div class="col-md-6 text-right">
                                        <div>
                                            <button type="submit" class="btn btn-success">
                                                Update
                                            </button>
                                        </div>
                                    </div>
                                </div>
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

    @push('scripts')
        <script src="{{ asset('assets/manufacturer-return-scripts.js') }}"></script>
    @endpush
@endsection
