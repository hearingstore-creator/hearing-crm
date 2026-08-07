@extends('admin.layouts.master')

@push('css')
    <!-- Select2 -->
  <link rel="stylesheet" href="{{ asset("assets/plugins/select2/css/select2.min.css") }}">
@endpush

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
                        <h5>Add Purchase </h5>
                     </div>
                     @can('permission', 'purchase_list.index')
                     <div class="col-md-6 text-right">
                        <a href="{{url('admin/purchase')}}" class="btn-primary btn-cs-primary  btn-sm"><i class="fas fa-align-justify mr-1"></i>Purchase List</a>
                     </div>
                     @endcan
                  </div>
               </div>
               @include('admin.alert_message')
               <div class="card-body">
                  <form action="{{url('admin/purchase')}}" enctype="multipart/form-data" method="post"
                        data-product-search="{{ url('/admin/product-search') }}"
                        data-purchase-add-row="{{ route('purchase.add-row') }}"
                        data-product-info="{{ url('admin/product-info') }}">
                     @csrf
                     <div class="form-group row">
                        <label for="supplier_id" class="col-md-2 col-form-label">Supplier <i class="text-danger"> * </i>:</label>
                        <div class="col-md-4">
                           <div class="">
                              <select name="supplier_id" class="form-control select required" id="supplier_id" required>
                                 <option selected="selected" value="">Select Supplier</option>
                                 @foreach ($suppliers as $val)
                                 <option value="{{$val->id}}">{{$val->supplier_name}}</option>
                                 @endforeach
                              </select>
                           </div>
                        </div>
                        <label for="date" class="col-md-2 col-form-label">Date <i class="text-danger"> * </i>:</label>
                        <div class="col-md-4">
                           <div class="">
                              <input type="date" name="date" value="{{date('Y-m-d')}}" class="form-control date" id="date" required> 
                           </div>
                        </div>
                     </div>
                     <div class="form-group row">
                        <label for="payment_type_id" class="col-md-2 col-form-label">Payment Type<i class="text-danger"> * </i>:</label>
                        <div class="col-md-4">
                           <div class="">
                              <select name="payment_type_id" class="form-control select required" id="payment_type_id" required>
                                 <option selected="selected" value="">Select Payment Type</option>
                                 @foreach ($paymentTypes as $key => $val)
                                 <option value="{{$key}}">{{$val}}</option>
                                 @endforeach
                              </select>
                           </div>
                        </div>
                        <label for="details" class="col-md-2 col-form-label">Details:</label>
                        <div class="col-md-4">
                           <div class="">
                              <input class="form-control" id="details" type="text" name="details" placeholder="Details">
                           </div>
                        </div>
                     </div>
                     <div class="panel-body">
                        <table class="table table-striped table-responsive purchase-form">
                           <thead>
                              <tr>
                                 <th>Product <i class="text-danger">*</i></th>
                                 <th>Batch No</th>
                                 <th>Serial No</th>
                                 <th>Stock Qty</th>
                                 <th>Qty <i class="text-danger">*</i></th>
                                 <th>Purchase Price<i class="text-danger">*</i></th>
                                 <th>MRP Price<i class="text-danger">*</i></th>
                                 <th>Total Purchase Price</th>
                              </tr>
                           </thead>
                           <tbody class="neworderbody">
                              <tr>
                                 <td>
                                    <select class="form-control product" name="product_id[]" required>
                                       <option value="">Select</option>
                                    </select>
                                 </td>
                                 <td>
                                    <input type="text" name="batch_no[]" class="form-control batch-no" placeholder="Batch No">
                                 </td>
                                 <td>
                                    <input type="text" name="serial_no[]" class="form-control serial-no" placeholder="Serial No (comma separated)">
                                 </td>
                                 <td>
                                    <input type="text" name="stock_qty[]" class="form-control stock-qty" readonly>
                                 </td>
                                 <td>
                                    <input type="text" name="qty[]" class="form-control qty" required>
                                 </td>
                                 <td>
                                    <input type="text" name="purchase_price[]" class="form-control purchase-price" required>
                                 </td>
                                 <td>
                                    <input type="text" name="mrp_price[]" class="form-control mrp-price" required>
                                 </td>
                                 <td>
                                    <input type="text" name="total_purchase_price[]" class="form-control total-purchase-price" readonly="readonly">
                                 </td>
                                 <td>
                                 </td>
                              </tr>
                           </tbody>

                           <tfoot>
                              <tr>
                                 <td class="text-right" colspan="6"><b>Sub Total:</b></td>
                                 <td class="text-right">
                                    <input type="text" class="text-right form-control sub-total" name="sub_total" placeholder="0.00" readonly="">
                                 </td>
                                 <td>
                                    <button type="button" class="btn btn-info add btn-sm"><i class="fas fa-plus"></i></button>
                                 </td>

                              </tr>
                              <tr>
                                 <td class="text-right" colspan="6"><b>Vat:</b></td>
                                 <td class="text-right">
                                    <input type="text" class="text-right form-control vat" name="vat" value="0.00" placeholder="0.00">
                                 </td>
                                 <td></td>
                              </tr>
                              <tr>
                                 <td class="text-right" colspan="6"><b>Discount:</b></td>
                                 <td class="text-right">
                                    <input type="text" class="text-right form-control discount" name="discount" value="0.00" placeholder="0.00">
                                 </td>
                                 <td></td>
                              </tr>
                              <tr>
                                 <td class="text-right" colspan="6"><b>Grand Total:</b></td>
                                 <td class="text-right">
                                    <input type="text" class="text-right form-control grand-total" name="total_amount" value="0.00" readonly="readonly">
                                 </td>
                                 <td></td>
                              </tr>
                              <tr>
                                 <td class="text-right" colspan="6"><b>Paid Amount:</b></td>
                                 <td class="text-right">
                                    <input type="text" class="text-right form-control paid-amount" name="paid" value="0.00" placeholder="0.00">
                                 </td>
                                 <td></td>
                              </tr>
                              <tr>
                                 <td class="text-right" colspan="6"><b>Due Amount:</b></td>
                                 <td class="text-right">
                                    <input type="text" class="text-right form-control due-amount" name="due_amount" value="0.00" placeholder="0.00" readonly="readonly">
                                 </td>
                                 <td></td>
                              </tr>
                           </tfoot>
                           
                        </table>
                        
                        <hr>
                     </div>
                     <div class="form-group row">
                        <div class="col-md-6 text-right"></div>
                        <div class="col-md-6 text-right">
                           <div class="">
                              <button type="submit" class="btn btn-success">
                              Save</button>
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
<!-- Select2 -->
<script src="{{asset('assets/plugins/select2/js/select2.full.min.js')}}"></script>
<script src="{{ asset('assets/purchase.js') }}"></script>
@endpush
@endsection
