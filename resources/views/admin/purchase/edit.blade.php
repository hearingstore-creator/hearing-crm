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
                        <h5>Edit Purchase </h5>
                     </div>
                  </div>
               </div>
               @include('admin.alert_message')
               <div class="card-body">
                  <form action="{{route('purchase.update', $purchaseMaster->id)}}" enctype="multipart/form-data" method="post"
                        data-product-search="{{ url('/admin/product-search') }}"
                        data-purchase-add-row="{{ route('purchase.add-row') }}"
                        data-product-info="{{ url('admin/product-info') }}">
                     @csrf
                     @method('put')
                     <div class="form-group row">
                        <label for="supplier_id" class="col-md-2 col-form-label">Supplier <i class="text-danger"> * </i>:</label>
                        <div class="col-md-4">
                           <div class="">
                              <select name="supplier_id" class="form-control select required" id="supplier_id" required>
                                 <option selected="selected" value="">Select Supplier</option>
                                 @foreach ($suppliers as $val)
                                 <option value="{{$val->id}}" {{$purchaseMaster->supplier_id == $val->id ? 'selected' : ''}}>{{$val->supplier_name}}</option>
                                 @endforeach
                              </select>
                           </div>
                        </div>
                        <label for="date" class="col-md-2 col-form-label">Date <i class="text-danger"> * </i>:</label>
                        <div class="col-md-4">
                           <div class="">
                              <input type="date" name="date" value="{{$purchaseMaster->date->format('Y-m-d')}}" class="form-control date" id="date" required> 
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
                                 <option value="{{$key}}" {{ $purchaseMaster->payment_id == $key ? 'selected' : '' }}> {{ $val }}</option>
                                 @endforeach
                              </select>
                           </div>
                        </div>
                        <label for="details" class="col-md-2 col-form-label">Details:</label>
                        <div class="col-md-4">
                           <div class="">
                              <input class="form-control" id="details" type="text" name="details" value="{{$purchaseMaster->details}}" placeholder="Details">
                           </div>
                        </div>
                     </div>
                     <div class="panel-body">
                        <table class="table table-striped table-responsive purchase-form">
                           <thead>
                              <tr>
                                 <th>Product <i class="text-danger">*</i></th>
                                 <th class="batch-header">Batch No</th>
                                 <th class="serial-header">Serial No</th>
                                 <th>Stock Qty</th>
                                 <th>Qty <i class="text-danger">*</i></th>
                                 <th>Purchase Price<i class="text-danger">*</i></th>
                                 <th>MRP Price<i class="text-danger">*</i></th>
                                 <th>Total Purchase Price</th>
                              </tr>
                           </thead>
                           <tbody class="neworderbody">
                              @foreach ($purchaseDetail as $key => $item)
                              @php
                                // $serial_no = implode(',', $item->serial_no);
                              @endphp
                              <tr>
                                 <td>
                                    <input type="hidden" autocomplete="off" name="old[{{$key}}][id]" class="form-control" value="{{$item->id}}">
                                    <input type="hidden" autocomplete="off" name="old[{{$key}}][batch_id]" class="form-control" value="{{$item->batch?->id}}">
                                    <select class="form-control product" name="old[{{$key}}][product_id]">
                                       <option value="">Select</option>
                                       <option value="{{$item->product_id}}" selected="selected">
                                         {{ ($item->product?->category?->category_name) . ' - ' . $item->product?->model }}
                                       </option>
                                    </select>
                                 </td>
                                 <td class="batch-field">
                                    <input type="text" name="old[{{$key}}][batch_no]" class="form-control batch-no" value="{{$item->batch?->batch_no}}" placeholder="Batch No">
                                 </td>
                                 <td class="serial-field">
                                    <input type="text" 
                                       name="old[{{$key}}][serial_no]" 
                                       class="form-control serial-no" 
                                       value="{{ is_array($item->serial_no) ? implode(',', $item->serial_no) : $item->serial_no }}" 
                                       placeholder="Serial No (comma separated)">
                                 </td>
                                 <td>
                                    <input type="text" name="old[{{$key}}][stock_qty]" class="form-control stock-qty" value="{{$item->batch?->stock ?? 0}}" readonly="readonly" >
                                 </td>
                                 <td>
                                    <input type="text" name="old[{{$key}}][qty]" class="form-control qty" value="{{$item->qty}}">
                                 </td>
                                 <td>
                                    <input type="text" name="old[{{$key}}][purchase_price]" class="form-control purchase-price" value="{{$item->purchase_price}}">
                                 </td>
                                 <td>
                                    <input type="text" name="old[{{$key}}][mrp_price]" class="form-control mrp-price" value="{{$item->mrp_price}}">
                                 </td>
                                 <td>
                                    <input type="text" name="old[{{$key}}][total_purchase_price]" class="form-control total-purchase-price" value="{{$item->purchase_price * $item->qty}}" readonly="readonly">
                                 </td>
                                 @if((int)$item->batch?->stock == (int)0)
                                    <td>
                                       <input type="button" class="btn btn-danger btn-sm delete" value="x">
                                    </td>
                                 @else
                                 <td></td>
                                 @endif
                              </tr>
                              @endforeach
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
                                    <input type="text" class="text-right form-control vat" name="vat" value="{{$purchaseMaster->vat}}" placeholder="0.00">
                                 </td>
                                 <td></td>
                              </tr>
                              <tr>
                                 <td class="text-right" colspan="6"><b>Discount:</b></td>
                                 <td class="text-right">
                                    <input type="text" class="text-right form-control discount" name="discount" value="{{$purchaseMaster->discount}}" placeholder="0.00">
                                 </td>
                                 <td></td>
                              </tr>
                              <tr>
                                 <td class="text-right" colspan="6"><b>Grand Total:</b></td>
                                 <td class="text-right">
                                    <input type="text" class="text-right form-control grand-total" name="total_amount" value="{{$purchaseMaster->total_amount}}" readonly="readonly">
                                 </td>
                                 <td></td>
                              </tr>
                              <tr>
                                 <td class="text-right" colspan="6"><b>Paid Amount:</b></td>
                                 <td class="text-right">
                                    <input type="text" class="text-right form-control paid-amount" name="paid" value="{{$purchaseMaster->paid}}" placeholder="0.00">
                                 </td>
                                 <td></td>
                              </tr>
                              <tr>
                                 <td class="text-right" colspan="6"><b>Due Amount:</b></td>
                                 <td class="text-right">
                                    <input type="text" class="text-right form-control due-amount" name="due_amount" value="{{$purchaseMaster->total_amount - $purchaseMaster->paid}}" placeholder="0.00" readonly="readonly">
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
                              Update</button>
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
<script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
<script src="{{ asset('assets/purchase.js') }}"></script>
@endpush
@endsection
