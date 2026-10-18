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
                        <h5>Edit Sale </h5>
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
                <form action="{{ route('sale.update', $saleMaster->id) }}" enctype="multipart/form-data" method="post"
                     data-product-search="{{ url('/admin/product-search') }}"
                     data-customer-search="{{ url('/admin/customer-search') }}"
                     data-product-info="{{ url('admin/product-info') }}"
                     data-sale-add-row="{{ route('sale.add-row') }}"
                     data-sale-add-service-row="{{ route('sale.add-service-row') }}"
                     data-product-numbers-numbers="{{ url('admin/product-serial-numbers') }}"
                     data-customer-redeem-points="{{ url('/admin/customer-redeem-points') }}">
                     @csrf
                     @method('put')
                     <div class="form-group row">
                        <label for="customer_id" class="col-md-2 col-form-label">Customer <i class="text-danger"> * </i>:</label>
                        <div class="col-md-4">
                           <div class="">
                              <select class="form-control customer" name="customer_id" required>
                                 <option value="">Select</option>
                                 <option value="{{ $saleMaster->customer_id }}" selected>
                                       {{ $saleMaster->customer->lead->full_name }}
                                 </option>
                              </select>
                           </div>
                        </div>
                        <label for="date" class="col-md-2 col-form-label">Date <i class="text-danger"> * </i>:</label>
                        <div class="col-md-4">
                           <div class="">
                              <input type="date" name="date" value="{{ $saleMaster->date->format('Y-m-d') }}" class="form-control date" id="date" required>
                           </div>
                        </div>
                     </div>
                     <div class="form-group row">
                        <label class="col-md-2 col-form-label">Available Redeem Points:</label>
                        <div class="col-md-4">
                           <input type="text" class="form-control" id="available-redeem-points" value="{{ $saleMaster->customer->getAvailableRedeemPoints() }}" readonly>
                           <small class="text-muted">Points available for redemption</small>
                        </div>
                        <label class="col-md-2 col-form-label">Redeem Points to Use:</label>
                        <div class="col-md-4">
                           <input type="number" class="form-control" name="redeem_points" id="redeem-points-input"
                                  value="{{ $existingRedeemPoints }}"
                                  data-existing-redeemed="{{ $existingRedeemPoints }}"
                                  min="0" step="0.01">
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
                                            {{ $saleMaster->reference_by == $doctor->id ? 'selected' : '' }}
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
                                 <option value="">Select Payment Type</option>
                                 @foreach ($paymentTypes as $key => $val)
                                 <option value="{{ $key }}" {{ $saleMaster->payment_id == $key ? 'selected' : '' }}>{{ $val }}</option>
                                 @endforeach
                              </select>
                           </div>
                        </div>
                     </div>
                     <div class="form-group row" id="doctor-chamber-wrap" style="{{ $saleMaster->reference_by ? '' : 'display: none;' }}">
                        <label for="doctor_chamber_id" class="col-md-2 col-form-label"><i class="fas fa-hospital-alt text-primary mr-1"></i>Doctor Chamber:</label>
                        <div class="col-md-4">
                           <select class="form-control select" name="doctor_chamber_id" id="doctor_chamber_id" data-initial-chamber="{{ $saleMaster->doctor_chamber_id }}">
                              <option value="">Select Chamber (Optional)</option>
                              @if($saleMaster->reference_by)
                                 @php
                                    $selectedDoc = ($doctors ?? collect())->firstWhere('id', $saleMaster->reference_by);
                                 @endphp
                                 @if($selectedDoc)
                                    @foreach($selectedDoc->chambers as $chamber)
                                       <option value="{{ $chamber->id }}" {{ $saleMaster->doctor_chamber_id == $chamber->id ? 'selected' : '' }}>
                                          {{ $chamber->chamber_name }} ({{ $chamber->address }})
                                       </option>
                                    @endforeach
                                 @endif
                              @endif
                           </select>
                        </div>
                     </div>
                     <div class="form-group row">
                        <label for="delivery_status" class="col-md-2 col-form-label">Delivery Status:</label>
                        <div class="col-md-4">
                           <div class="">
                              <select name="delivery_status" class="form-control select2" id="delivery_status">
                                 @foreach (\App\Enums\DeliveryStatus::options() as $option)
                                    <option value="{{ $option['value'] }}" {{ $saleMaster->delivery_status === $option['value'] ? 'selected' : '' }}>{{ $option['label'] }}</option>
                                 @endforeach
                              </select>
                           </div>
                        </div>
                     </div>
                     <div class="form-group row">
                        <label for="details" class="col-md-2 col-form-label">Details:</label>
                        <div class="col-md-10">
                           <div class="">
                              <input class="form-control" id="details" type="text" name="details" value="{{ $saleMaster->details }}" placeholder="Details">
                           </div>
                        </div>
                     </div>

                     {{-- <div class="form-group row">
                        <label class="col-md-2 col-form-label">Sale Type <i class="text-danger"> * </i>:</label>
                        <div class="col-md-10">
                           <div class="form-check form-check-inline">
                              <input class="form-check-input" type="radio" name="sale_type" id="sale_type_product" value="product" @if(!$saleMaster->is_percent_service_commission) checked @endif>
                              <label class="form-check-label" for="sale_type_product">Product</label>
                           </div>
                           <div class="form-check form-check-inline">
                              <input class="form-check-input" type="radio" name="sale_type" id="sale_type_service" value="service" @if($saleMaster->is_percent_service_commission) checked @endif>
                              <label class="form-check-label" for="sale_type_service">Service</label>
                           </div>
                        </div>
                     </div> --}}

                     <div class="form-group row" id="product_commission_div" @if($saleMaster->is_percent_service_commission) style="display: none;" @endif>
                        <label class="col-md-2 col-form-label">Product Commission:</label>
                        <div class="col-md-10">
                           <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="is_percent_product_commission" id="is_percent_product_commission" value="1" @if($saleMaster->is_percent_product_commission) checked @endif>
                              <label class="form-check-label" for="is_percent_product_commission">Is Percent Product Commission</label>
                           </div>
                        </div>
                     </div>

                     <div class="form-group row" id="service_commission_div" @if(!$saleMaster->is_percent_service_commission) style="display: none;" @endif>
                        <label class="col-md-2 col-form-label">Service Commission:</label>
                        <div class="col-md-10">
                           <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="is_percent_service_commission" id="is_percent_service_commission" value="1" @if($saleMaster->is_percent_service_commission) checked @endif>
                              <label class="form-check-label" for="is_percent_service_commission">Is Percent Service Commission</label>
                           </div>
                        </div>
                     </div>
                     
                     <div class="panel-body">
                        <table class="table table-striped table-responsive">
                           @if(count($saleDetail) > 0)
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
                                 <th></th>
                              </tr>
                           </thead>
                           @endif
                           <tbody class="neworderbody">
                               @php
                                   $subTotal = 0;
                                   $subTotalDiscount = 0;
                               @endphp
                               @foreach ($saleDetail as $key => $item)
                              <tr data-category-id="{{ $item->product?->category_id }}">
                                 <td>
                                    <input type="hidden" autocomplete="off" name="old[{{ $key }}][id]" class="form-control" value="{{ $item->id }}">
                                    <select class="form-control product" name="old[{{ $key }}][product_id]" required>
                                       <option value="">Select Product</option>
                                       <option value="{{ $item->product_id }}" selected>{{ ($item->product?->category?->category_name) . ' - ' . $item->product?->model }}</option>
                                    </select>
                                 </td>
                                 <td>
                                    <select class="form-control batch" name="old[{{ $key }}][batch_id]" @if(empty($item->batch_id)) disabled @endif>
                                       <option value="">Select Batch</option>
                                       @if($item->batch_id)
                                           <option value="{{ $item->batch_id }}" selected>{{ $item->batch->batch_no ?? 'Batch' }}</option>
                                       @endif
                                    </select>
                                 </td>
                                 <td>
                                    <input type="text" autocomplete="off" name="old[{{ $key }}][stock_qty]" class="form-control stock-qty" value="{{ $item->stock ?? 0 }}" readonly>
                                 </td>
                                 <td>
                                    <select class="form-control serial-no" multiple name="old[{{ $key }}][serial_no][]" @if(empty($productSerials[$item->product_id])) disabled @endif>
                                       @if(isset($productSerials[$item->product_id]))
                                           @foreach ($productSerials[$item->product_id] as $serial)
                                               <option value="{{ $serial['serial_number'] }}" {{ $serial['selected'] ? 'selected' : '' }} data-mrp-price="{{ $serial['mrp_price'] }}">
                                                   {{ $serial['serial_number'] }}
                                               </option>
                                           @endforeach
                                       @else
                                           <option value="">No serial numbers available</option>
                                       @endif
                                    </select>
                                 </td>
                                 <td>
                                    <input type="text" name="old[{{ $key }}][qty]" class="form-control qty" value="{{ $item->qty }}" required>
                                 </td>

                                 <td>
                                    <input type="hidden" name="old[{{ $key }}][purchase_price]" class="form-control purchase-price" value="{{ $item->purchase_price }}">
                                    <input type="text" name="old[{{ $key }}][unit_price]" class="form-control unit-price" value="{{ $item->mrp_price }}" required>
                                 </td>
                                 @php
                                     $discountTaka = ($item->qty * $item->mrp_price / 100) * $item->discount;
                                     $totalPrice = $item->qty * $item->mrp_price - $discountTaka;
                                 @endphp
                                 <td>
                                    <input type="text" name="old[{{ $key }}][discount]" class="form-control discount" value="{{ $item->discount }}">
                                 </td>
                                 <td>
                                    <input type="text" name="old[{{ $key }}][discount_taka]" class="form-control discount-taka" value="{{ number_format($discountTaka, 2) }}">
                                 </td>
                                 <td>
                                    <input type="text" name="old[{{ $key }}][total_price]" class="form-control total-price" value="{{ number_format($totalPrice, 2) }}" readonly="readonly">
                                 </td>
                                 <td>
                                 </td>
                              </tr>
                              @php
                                  $subTotal += $totalPrice;
                                  $subTotalDiscount += $discountTaka;
                              @endphp
                              @endforeach
                           </tbody>

                           @php
                              $serviceSubTotal = 0;
                              $serviceSubTotalDiscount = 0;
                           @endphp
                           @if(count($serviceDetails) > 0)
                           <thead class="service-head">
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
                               
                               @if(isset($serviceDetails))
                                   @foreach ($serviceDetails as $key => $service)
                                   <tr>
                                       <td>
                                           <input type="hidden" autocomplete="off" name="old_service[{{ $key }}][id]" class="form-control" value="{{ $service->id }}">
                                           <select class="form-control service-type" name="old_service[{{ $key }}][service_type_id]" required>
                                               <option value="">Select Service Type</option>
                                               @if(isset($serviceTypes))
                                                   @foreach ($serviceTypes as $type)
                                                   <option value="{{ $type->id }}" {{ $service->service_type_id == $type->id ? 'selected' : '' }}>{{ $type->service_type_name }}</option>
                                                   @endforeach
                                               @endif
                                           </select>
                                       </td>
                                       <td>
                                           <input type="text" name="old_service[{{ $key }}][service_qty]" class="form-control service-qty" value="{{ $service->qty }}" required>
                                       </td>
                                       <td>
                                           <input type="text" name="old_service[{{ $key }}][service_price]" class="form-control service-price" value="{{ $service->service_price }}" required>
                                       </td>
                                       @php
                                           $serviceDiscountTaka = ($service->qty * $service->service_price / 100) * ($service->discount ?? 0);
                                           $serviceTotalPrice = $service->qty * $service->service_price - $serviceDiscountTaka;
                                       @endphp
                                       <td>
                                           <input type="text" name="old_service[{{ $key }}][discount]" class="form-control service-discount" value="{{ $service->discount ?? 0 }}">
                                       </td>
                                       <td>
                                           <input type="text" name="old_service[{{ $key }}][discount_taka]" class="form-control service-discount-taka" value="{{ number_format($serviceDiscountTaka, 2) }}">
                                       </td>
                                       <td>
                                           <input type="text" name="old_service[{{ $key }}][total_price]" class="form-control service-total-price" value="{{ number_format($serviceTotalPrice, 2) }}" readonly="readonly">
                                       </td>
                                       <td>
                                           <input type="button" class="btn btn-danger btn-sm delete" value="x">
                                       </td>
                                   </tr>
                                   @php
                                       $serviceSubTotal += $serviceTotalPrice;
                                       $serviceSubTotalDiscount += $serviceDiscountTaka;
                                   @endphp
                                   @endforeach
                               @endif
                           </tbody>
                           @endif

                           <tfoot>
                              <tr>
                                 <td class="text-right" colspan="8"><b>Sub Total: </b></td>
                                 <td class="text-right">
                                    <input type="text" class="text-right form-control sub-total" name="sub_total" value="{{ number_format($subTotal + $serviceSubTotal, 2) }}" placeholder="0.00" readonly="">
                                 </td>
                                 <td>
                                    @if($saleMaster->is_percent_product_commission)
                                    <button type="button" class="btn btn-info add btn-sm" id="add_product_btn" title="Add Product"><i class="fas fa-plus"></i></button>
                                    @endif
                                    @if($saleMaster->is_percent_service_commission)
                                    <button type="button" class="btn btn-warning add-service btn-sm mt-1" id="add_service_btn" title="Add Service"><i class="fas fa-wrench"></i></button>
                                    @endif
                                 </td>
                              </tr>
                              <tr>
                                 <td class="text-right" colspan="8"><b>Vat:</b></td>
                                 <td class="text-right">
                                    <input type="text" class="text-right form-control vat" name="vat" value="{{ $saleMaster->vat }}" placeholder="0.00">
                                 </td>
                                 <td></td>
                              </tr>
                              <tr>
                                 <td class="text-right" colspan="8"><b>Invoice Discount:</b></td>
                                 <td class="text-right">
                                    <input type="text" class="text-right form-control invoice-discount" name="invoice_discount" value="{{ $saleMaster->total_discount - ($subTotalDiscount + $serviceSubTotalDiscount) }}" placeholder="0.00">
                                 </td>
                                 <td></td>
                              </tr>
                              <tr>
                                 <td class="text-right" colspan="8"><b>Total Discount:</b></td>
                                 <td class="text-right">
                                    <input type="text" class="text-right form-control total-discount" name="total_discount" value="{{ $saleMaster->total_discount }}" readonly="readonly">
                                 </td>
                                 <td></td>
                              </tr>
                              <tr>
                                 <td class="text-right" colspan="8"><b>Grand Total:</b></td>
                                 <td class="text-right">
                                    <input type="text" class="text-right form-control grand-total" name="total_amount" value="{{ $saleMaster->total_amount }}" readonly="readonly">
                                 </td>
                                 <td></td>
                              </tr>
                              <tr>
                                 <td class="text-right" colspan="8"><b>Paid Amount:</b></td>
                                 <td class="text-right">
                                    <input type="text" class="text-right form-control paid-amount" name="paid" value="{{ $saleMaster->paid }}" placeholder="0.00">
                                 </td>
                                 <td></td>
                              </tr>
                              <tr>
                                 <td class="text-right" colspan="8"><b>Reference Amount (Doctor Commission):</b></td>
                                 <td class="text-right">
                                    <input type="text" class="text-right form-control reference-amount" name="reference_amount" value="{{ number_format($saleMaster->reference_amount, 2) }}" placeholder="0.00">
                                    <div id="doc-comm-breakdown" style="display:none; font-size:11px; color:#4338ca; background:#eef2ff; border-radius:4px; padding:3px 6px; margin-top:4px;"></div>
                                 </td>
                                 <td></td>
                              </tr>
                              <tr>
                                 <td class="text-right" colspan="8"><b>Due Amount:</b></td>
                                 <td class="text-right">
                                    <input type="text" class="text-right form-control due-amount" name="due_amount" value="{{ $saleMaster->total_amount - $saleMaster->paid }}" placeholder="0.00" readonly="readonly">
                                 </td>
                                 <td></td>
                              </tr>
                              <tr id="wrap-due-date" style="{{ ($saleMaster->total_amount - $saleMaster->paid) > 0.009 ? '' : 'display:none;' }}">
                                 <td class="text-right" colspan="8"><b style="color:#b91c1c;"><i class="far fa-calendar-alt mr-1"></i>Due Promise Date:</b></td>
                                 <td class="text-right">
                                    <input type="date" class="form-control" name="due_date" value="{{ old('due_date', $saleMaster->due_date?->format('Y-m-d')) }}" min="{{ date('Y-m-d') }}" style="border-color:#fda4af;">
                                 </td>
                                 <td></td>
                              </tr>
                              <tr>
                                 <td class="text-right" colspan="8"><b style="color:var(--accent-600);"><i class="fas fa-clipboard-check mr-1"></i>Fulfillment Status:</b></td>
                                 <td class="text-right">
                                    <select class="form-control" name="delivery_status" style="font-weight:600;">
                                       @foreach (\App\Enums\DeliveryStatus::casesFor($saleMaster->isServiceSale()) as $st)
                                          <option value="{{ $st->value }}" @selected($saleMaster->delivery_status === $st->value)>
                                             {{ $st->label($saleMaster->isServiceSale()) }}
                                          </option>
                                       @endforeach
                                    </select>
                                 </td>
                                 <td></td>
                              </tr>
                              <tr>
                                 <td class="text-right" colspan="8"><b><i class="far fa-clock mr-1"></i>Promised Delivery / Report Time:</b></td>
                                 <td class="text-right">
                                    <input type="datetime-local" class="form-control" name="delivery_date_time" value="{{ old('delivery_date_time', $saleMaster->delivery_date_time?->format('Y-m-d\TH:i')) }}">
                                 </td>
                                 <td></td>
                              </tr>
                              @if(!$saleMaster->isServiceSale())
                              <tr>
                                 <td class="text-right" colspan="8"><b style="color:#7c3aed;"><i class="fas fa-flask mr-1"></i>Lab / Fabricator:</b></td>
                                 <td class="text-right">
                                    <input type="text" class="form-control" name="lab_name" value="{{ old('lab_name', $saleMaster->lab_name) }}" placeholder="e.g. In-house Lab, Signia Lab">
                                 </td>
                                 <td></td>
                              </tr>
                              @endif
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
    <script>
        window.crmDoctorRules = @json(($doctors ?? collect())->mapWithKeys(fn($d) => [$d->id => $d->getCommissionRulesArray()]));
    </script>
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('assets/sales.js') }}?v={{ @filemtime(public_path('assets/sales.js')) ?: time() }}"></script>
    <script>
        $(function () {
            function syncDueRow() {
                var due = parseFloat($('.due-amount').val()) || 0;
                if (due > 0.009) {
                    $('#wrap-due-date').show();
                } else {
                    $('#wrap-due-date').hide();
                }
            }
            $(document).on('keyup change input', '#editSaleForm, .paid-amount, .grand-total', function () {
                setTimeout(syncDueRow, 50);
            });
            syncDueRow();
        });
    </script>
@endpush
@endsection