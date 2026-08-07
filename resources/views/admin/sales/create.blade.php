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
                        <h5>Add Sale </h5>
                     </div>
                     @can('permission', 'sale_list.index')
                     <div class="col-md-6 text-right">
                        <a href="{{url('admin/sale')}}" class="btn-primary btn-cs-primary  btn-sm"><i class="fas fa-align-justify mr-1"></i>Sale List</a>
                     </div>
                     @endcan
                  </div>
               </div>
               @include('admin.alert_message')
               <div class="card-body">
                  <form action="{{ url('admin/sale') }}" enctype="multipart/form-data" method="post"
                     data-product-search="{{ url('/admin/product-search') }}"
                     data-customer-search="{{ url('/admin/customer-search') }}"
                     data-product-info="{{ url('admin/product-info') }}"
                     data-sale-add-row="{{ route('sale.add-row') }}"
                     data-sale-add-service-row="{{ route('sale.add-service-row') }}"
                     data-product-numbers-numbers="{{ url('admin/product-serial-numbers') }}"
                     data-customer-redeem-points="{{ url('/admin/customer-redeem-points') }}">
                     @csrf
                     <div class="form-group row">
                        <label for="customer_id" class="col-md-2 col-form-label">Customer <i class="text-danger"> * </i>:</label>
                        <div class="col-md-3">
                           <div class="">
                              <select class="form-control customer" name="customer_id" required>
                                 <option value="">Select</option>
                              </select>
                           </div>
                        </div>
                        <div class="col-md-1">
                           <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#customerModal" style="margin-top: 4px;">
                              <i class="fas fa-plus"></i> Add
                           </button>
                        </div>
                        <label for="date" class="col-md-2 col-form-label">Date <i class="text-danger"> * </i>:</label>
                        <div class="col-md-4">
                           <div class="">
                              <input type="date" name="date" value="{{date('Y-m-d')}}" class="form-control date" id="date" required>
                           </div>
                        </div>
                     </div>
                     <div class="form-group row">
                        <label class="col-md-2 col-form-label">Available Redeem Points:</label>
                        <div class="col-md-3">
                           <input type="text" class="form-control" id="available-redeem-points" value="0.00" readonly>
                           <small class="text-muted">Points available for redemption</small>
                        </div>
                        <label class="col-md-2 col-form-label">Redeem Points to Use:</label>
                        <div class="col-md-3">
                           <input type="number" class="form-control" name="redeem_points" id="redeem-points-input" value="0" min="0" step="0.01">
                           <small class="text-muted">Points to use in this sale</small>
                        </div>
                     </div>
                     <div class="form-group row">
                        <label for="reference_by" class="col-md-2 col-form-label">Referred By:</label>
                        <div class="col-md-4">
                           <div class="">
                              <select class="form-control doctor-select" name="reference_by" id="reference_by">
                                 <option value="">Select Doctor (Optional)</option>
                                 @foreach ($doctors ?? [] as $doctor)
                                    <option value="{{ $doctor->id }}"
                                       data-product-commission-percent="{{ $doctor->product_commission_percent }}"
                                       data-product-commission-amount="{{ $doctor->product_commission_amount }}"
                                       data-service-commission-percent="{{ $doctor->service_commission_percent }}"
                                       data-service-commission-amount="{{ $doctor->service_commission_amount }}">
                                       {{ $doctor->name }} ({{ $doctor->qualification ?? 'N/A' }})
                                    </option>
                                 @endforeach
                              </select>
                           </div>
                        </div>
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
                     </div>
                     <div class="form-group row">
                        <label for="delivery_status" class="col-md-2 col-form-label">Delivery Status:</label>
                        <div class="col-md-4">
                           <div class="">
                              <select name="delivery_status" class="form-control select2" id="delivery_status">
                                 @foreach (\App\Enums\DeliveryStatus::options() as $option)
                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                 @endforeach
                              </select>
                           </div>
                        </div>
                     </div>
                     <div class="form-group row">
                        <label for="details" class="col-md-2 col-form-label">Details:</label>
                        <div class="col-md-10">
                           <div class="">
                              <input class="form-control" id="details" type="text" name="details" placeholder="Details">
                           </div>
                        </div>
                     </div>

                     <div class="form-group row">
                        <label class="col-md-2 col-form-label">Sale Type <i class="text-danger"> * </i>:</label>
                        <div class="col-md-10">
                           <div class="form-check form-check-inline">
                              <input class="form-check-input" type="radio" name="sale_type" id="sale_type_product" value="product" checked>
                              <label class="form-check-label" for="sale_type_product">Product</label>
                           </div>
                           <div class="form-check form-check-inline">
                              <input class="form-check-input" type="radio" name="sale_type" id="sale_type_service" value="service">
                              <label class="form-check-label" for="sale_type_service">Service</label>
                           </div>
                        </div>
                     </div>

                     <div class="form-group row" id="product_commission_div">
                        <label class="col-md-2 col-form-label">Product Commission:</label>
                        <div class="col-md-10">
                           <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="is_percent_product_commission" id="is_percent_product_commission" value="1" checked>
                              <label class="form-check-label" for="is_percent_product_commission">Is Percent Product Commission</label>
                           </div>
                        </div>
                     </div>

                     <div class="form-group row" id="service_commission_div" style="display: none;">
                        <label class="col-md-2 col-form-label">Service Commission:</label>
                        <div class="col-md-10">
                           <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="is_percent_service_commission" id="is_percent_service_commission" value="1" checked>
                              <label class="form-check-label" for="is_percent_service_commission">Is Percent Service Commission</label>
                           </div>
                        </div>
                     </div>


                     <div class="panel-body">
                        <table class="table table-striped table-responsive">
                           <thead>
                              <tr>
                                 <th>Product </th>
                                 <th>Batch </th>
                                 <th>Stock Qty </th>
                                 <th>Serial No</th>
                                 <th>Qty </th>
                                 <th>Unit Price</th>
                                 <th>Discount %</th>
                                 <th>Dis. Taka</th>
                                 <th>Total</th>
                              </tr>
                           </thead>
                           <tbody class="neworderbody">
                              <tr>
                                 <td>
                                    <select class="form-control product" name="product_id[]">
                                       <option value="">Select Product</option>
                                    </select>
                                 </td>
                                 <td>
                                    <select class="form-control batch" name="batch_id[]">
                                       <option value="">Select Batch</option>
                                    </select>
                                 </td>
                                 <td>
                                    <input type="text" autocomplete="off" name="stock_qty[]" class="form-control stock-qty" readonly>
                                 </td>
                                 <td>
                                    <select class="form-control serial-no" multiple name="serial_no[0][]">
                                       <option value="">Select Serial No</option>
                                    </select>
                                 </td>
                                 <td>
                                    <input type="text" name="qty[]" class="form-control qty">
                                 </td>
                                 
                                 <td>
                                    <input type="hidden" name="purchase_price[]" class="form-control purchase-price">
                                    <input type="text" name="unit_price[]" class="form-control unit-price">
                                 </td>
                                 <td>
                                    <input type="text" name="discount[]" class="form-control discount" value="0">
                                 </td>
                                 <td>
                                    <input type="text" name="discount_taka[]" class="form-control discount-taka" value="0">
                                 </td>
                                 <td>
                                    <input type="text" name="total_price[]" class="form-control total-price" readonly="readonly">
                                 </td>
                                 <td>
                                 </td>
                              </tr>
                           </tbody>
                           <thead class="service-head" style="display: none;">
                              <tr>
                                 <th colspan="10" class="text-center bg-info text-white">Service Items</th>
                              </tr>
                              <tr>
                                 <th>Service Type <i class="text-danger">*</i></th>
                                 <th>Qty <i class="text-danger">*</i></th>
                                 <th>Unit Price<i class="text-danger">*</i></th>
                                 <th>Discount %</th>
                                 <th>Dis. Taka</th>
                                 <th>Total</th>
                                 <th>Action</th>
                              </tr>
                           </thead>

                           <!-- Service Section -->
                           <tbody class="service-body">
                              <!-- Initial service row will be added here dynamically -->
                           </tbody>

                           <tfoot>
                              <tr>
                                 <td class="text-right" colspan="8"><b>Sub Total:</b></td>
                                 <td class="text-right">
                                    <input type="text" class="text-right form-control sub-total" name="sub_total" placeholder="0.00" readonly="">
                                 </td>
                                 <td>
                                    <button type="button" class="btn btn-info add btn-sm" id="add_product_btn" title="Add Product"><i class="fas fa-plus"></i></button>
                                    <button type="button" class="btn btn-warning add-service btn-sm mt-1" id="add_service_btn" style="display: none;" title="Add Service"><i class="fas fa-wrench"></i></button>
                                 </td>

                              </tr>
                              <tr>
                                 <td class="text-right" colspan="8"><b>Vat:</b></td>
                                 <td class="text-right">
                                    <input type="text" class="text-right form-control vat" name="vat" value="0.00" placeholder="0.00">
                                 </td>
                                 <td></td>
                              </tr>
                              <tr>
                                 <td class="text-right" colspan="8"><b>Invoice Discount:</b></td>
                                 <td class="text-right">
                                    <input type="text" class="text-right form-control invoice-discount" name="invoice_discount" value="0.00" placeholder="0.00">
                                 </td>
                                 <td></td>
                              </tr>
                              <tr>
                                 <td class="text-right" colspan="8"><b>Total Discount:</b></td>
                                 <td class="text-right">
                                    <input type="text" class="text-right form-control total-discount" name="total_discount" value="0.00" readonly="readonly">
                                 </td>
                                 <td></td>
                              </tr>
                              <tr>
                                 <td class="text-right" colspan="8"><b>Grand Total:</b></td>
                                 <td class="text-right">
                                    <input type="text" class="text-right form-control grand-total" name="total_amount" value="0.00" readonly="readonly">
                                 </td>
                                 <td></td>
                              </tr>
                              <tr>
                                 <td class="text-right" colspan="8"><b>Paid Amount:</b></td>
                                 <td class="text-right">
                                    <input type="text" class="text-right form-control paid-amount" name="paid" value="0.00" placeholder="0.00">
                                 </td>
                                 <td></td>
                              </tr>
                              <tr>
                                 <td class="text-right" colspan="8"><b>Reference Amount (Doctor Commission):</b></td>
                                 <td class="text-right">
                                    <input type="text" class="text-right form-control reference-amount" name="reference_amount" value="0.00" placeholder="0.00">
                                 </td>
                                 <td></td>
                              </tr>
                              <tr>
                                 <td class="text-right" colspan="8"><b>Due Amount:</b></td>
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

<!-- Customer Creation Modal -->
<div class="modal fade" id="customerModal" tabindex="-1" role="dialog">
   <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
         <div class="modal-header">
            <h5 class="modal-title">Add New Customer</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
               <span aria-hidden="true">&times;</span>
            </button>
         </div>
         <form id="customerForm" action="{{ url('admin/customers') }}" method="post">
            @csrf
            <div class="modal-body">
               <div class="row">
                  <div class="col-md-12">
                     <div class="form-group row">
                        <label for="full_name" class="col-sm-4 col-form-label">Full Name <i class="text-danger">*</i>:</label>
                        <div class="col-sm-8">
                           <input type="text" class="form-control" id="full_name" placeholder="Full Name" name="full_name" autocomplete="off" required>
                        </div>
                     </div>
                  </div>

                  <div class="col-md-12">
                     <div class="form-group row">
                        <label for="phone_number" class="col-sm-4 col-form-label">Phone Number <i class="text-danger">*</i>:</label>
                        <div class="col-sm-8">
                           <input type="text" class="form-control" id="phone_number" placeholder="Phone Number" name="phone_number" autocomplete="off" required>
                        </div>
                     </div>
                  </div>
                  <div class="col-md-12">
                     <div class="form-group row">
                        <label for="age" class="col-sm-4 col-form-label">Age <i class="text-danger">*</i>:</label>
                        <div class="col-sm-8">
                           <input type="number" class="form-control" id="age" placeholder="Age" name="age" min="0" required>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
            <div class="modal-footer">
               <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
               <button type="button" class="btn btn-success" id="saveCustomerBtn">
                  <i class="fas fa-spinner fa-spin d-none"></i> Save Customer
               </button>
            </div>
         </form>
      </div>
   </div>
</div>

@push('scripts')
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('assets/sales.js') }}"></script>
@endpush
@endsection
