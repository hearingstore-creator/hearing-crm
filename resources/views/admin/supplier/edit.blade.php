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
                        <h5>Edit Supplier</h5>
                     </div>
                     @can('permission', 'supplier_list.index')
                     <div class="col-md-6 text-right">
                        <a href="{{ url('admin/suppliers') }}" class="btn-primary btn-cs-primary btn-sm">
                           <i class="fas fa-align-justify mr-1"></i>Supplier List
                        </a>
                     </div>
                     @endcan
                  </div>
               </div>

               @include('admin.alert_message')

               <div class="card-body">
                  <form action="{{ route('suppliers.update', $supplier->id) }}" enctype="multipart/form-data" method="post">
                     @csrf
                     @method('put')
                     <div class="row">
                        <div class="col-md-6">
                           <div class="form-group row">
                              <label for="supplier_name" class="col-sm-4 col-form-label">
                                 Supplier Name <i class="text-danger">*</i>:
                              </label>
                              <div class="col-sm-8">
                                 <input type="text" class="form-control" id="supplier_name" name="supplier_name" value="{{ $supplier->supplier_name }}" placeholder="Supplier Name" autocomplete="off" required>
                              </div>
                           </div>

                           <div class="form-group row">
                              <label for="phone" class="col-sm-4 col-form-label">Phone :</label>
                              <div class="col-sm-8">
                                 <input type="text" class="form-control" id="phone" name="phone" value="{{ $supplier->phone }}" placeholder="Phone">
                              </div>
                           </div>

                           <div class="form-group row">
                              <label for="address1" class="col-sm-4 col-form-label">Address 1:</label>
                              <div class="col-sm-8">
                                 <textarea class="form-control" id="address1" name="address1">{{ $supplier->address1 }}</textarea>
                              </div>
                           </div>

                           <div class="form-group row">
                              <label for="email1" class="col-sm-4 col-form-label">Email Address 1:</label>
                              <div class="col-sm-8">
                                 <input type="email" class="form-control" id="email1" name="email1" value="{{ $supplier->email1 }}" placeholder="Email Address 1">
                              </div>
                           </div>

                           <div class="form-group row">
                              <label for="state" class="col-sm-4 col-form-label">State:</label>
                              <div class="col-sm-8">
                                 <input type="text" class="form-control" id="state" name="state" value="{{ $supplier->state }}" placeholder="State">
                              </div>
                           </div>

                           <div class="form-group row">
                              <label for="country" class="col-sm-4 col-form-label">Country:</label>
                              <div class="col-sm-8">
                                 <input type="text" class="form-control" id="country" name="country" value="{{ $supplier->country }}" placeholder="Country">
                              </div>
                           </div>

                           <div class="form-group row">
                              <label for="status" class="col-sm-4 col-form-label">
                                 Status <i class="text-danger">*</i>:
                              </label>
                              <div class="col-sm-8">
                                 <label class="radio-inline my-2">
                                    <input type="radio" name="status" value="1" {{ $supplier->status == 1 ? 'checked' : '' }}> Active
                                 </label>
                                 <label class="radio-inline my-2 ml-3">
                                    <input type="radio" name="status" value="0" {{ $supplier->status == 0 ? 'checked' : '' }}> Inactive
                                 </label>
                              </div>
                           </div>
                        </div>

                        <div class="col-md-6">
                           <div class="form-group row">
                              <label for="mobile_no" class="col-sm-4 col-form-label">
                                 Mobile No <i class="text-danger">*</i>:
                              </label>
                              <div class="col-sm-8">
                                 <input type="text" class="form-control" id="mobile_no" name="mobile_no" value="{{ $supplier->mobile_no }}" placeholder="Mobile No" autocomplete="off" required>
                              </div>
                           </div>

                           <div class="form-group row">
                              <label for="fax" class="col-sm-4 col-form-label">Fax:</label>
                              <div class="col-sm-8">
                                 <input type="text" class="form-control" id="fax" name="fax" value="{{ $supplier->fax }}" placeholder="Fax">
                              </div>
                           </div>

                           <div class="form-group row">
                              <label for="address2" class="col-sm-4 col-form-label">Address 2:</label>
                              <div class="col-sm-8">
                                 <textarea class="form-control" id="address2" name="address2">{{ $supplier->address2 }}</textarea>
                              </div>
                           </div>

                           <div class="form-group row">
                              <label for="email2" class="col-sm-4 col-form-label">Email Address 2:</label>
                              <div class="col-sm-8">
                                 <input type="email" class="form-control" id="email2" name="email2" value="{{ $supplier->email2 }}" placeholder="Email Address 2">
                              </div>
                           </div>

                           <div class="form-group row">
                              <label for="city" class="col-sm-4 col-form-label">City:</label>
                              <div class="col-sm-8">
                                 <input type="text" class="form-control" id="city" name="city" value="{{ $supplier->city }}" placeholder="City">
                              </div>
                           </div>

                           <div class="form-group row">
                              <label for="zip" class="col-sm-4 col-form-label">Zip:</label>
                              <div class="col-sm-8">
                                 <input type="text" class="form-control" id="zip" name="zip" value="{{ $supplier->zip }}" placeholder="Zip">
                              </div>
                           </div>

                           <div class="form-group row">
                              <label for="previous_balance" class="col-sm-4 col-form-label">Previous Balance:</label>
                              <div class="col-sm-8">
                                 <input type="text" class="form-control" id="previous_balance" name="previous_balance" placeholder="Previous Balance">
                              </div>
                           </div>
                        </div>
                     </div>

                     <div class="form-group row">
                        <div class="col-md-6"></div>
                        <div class="col-md-6 text-right">
                           <button type="submit" class="btn btn-success">Update</button>
                        </div>
                     </div>
                  </form>
               </div>
            </div>
         </div>
      </div>
   </div>
</section>
@endsection
