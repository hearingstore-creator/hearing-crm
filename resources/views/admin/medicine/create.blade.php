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
                        <h5>Add Medicine </h5>
                     </div>
                     @can('permission', 'medicine.index')
                     <div class="col-md-6 text-right">
                        <a href="{{url('admin/medicine')}}" class="btn-primary btn-cs-primary  btn-sm"><i class="fas fa-align-justify mr-1"></i>Medicine List</a>
                     </div>
                     @endcan
                  </div>
               </div>

               @include('admin.alert_message')

               <div class="card-body">
                  <form action="{{url('admin/medicine')}}" enctype="multipart/form-data" method="post">
                     @csrf
                  
                     <div class="form-group row">
                        <label for="medicine_name" class="col-md-2 col-form-label">Medicine Name <i class="text-danger"> * </i>:</label>
                        <div class="col-md-4">
                            <div>
                                <input type="text" name="medicine_name" class="form-control" id="medicine_name" placeholder="Medicine Name" required>
                            </div>
                        </div>
                        <label for="strength" class="col-md-2 col-form-label">Strength <i class="text-danger"> </i>:</label>
                        <div class="col-md-4">
                           <div class="">
                              <input type="text" name="strength" class="form-control text-left" id="strength" placeholder="Strength">
                           </div>
                        </div>
                     </div>
                     <div class="form-group row">
                        
                        <label for="generic_name" class="col-md-2 col-form-label">Generic Name:</label>
                        <div class="col-md-4">
                           <div class="">
                              <input type="text" class="form-control" name="generic_name" id="generic_name" placeholder="Generic Name">
                           </div>
                        </div>
                        <label for="leaf_setting_id" class="col-md-2 col-form-label">Box Size <i class="text-danger"> * </i>:</label>
                        <div class="col-md-4">
                            <div>
                                <select name="leaf_setting_id" class="form-control select required" id="leaf_setting_id" required>
                                    <option selected="selected" value="">Select Leaf Pattern</option>
                                    @foreach ($leafSettings as $val)
                                        <option value="{{ $val->id }}">{{ $val->leaf_type }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                     </div>
                     <div class="form-group row">
                        
                        <label for="unit_id" class="col-md-2 col-form-label">Unit <i class="text-danger"> * </i>:</label>
                        <div class="col-md-4">
                            <div>
                                <select name="unit_id" class="form-control select required" id="unit_id" required>
                                    <option selected="selected" value="">Select Unit</option>
                                    @foreach ($units as $val)
                                        <option value="{{ $val->id }}">{{ $val->unit_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <label for="shelf" class="col-md-2 col-form-label">Shelf:</label>
                        <div class="col-md-4">
                           <div class="">
                              <input class="form-control" id="shelf" type="text" name="shelf" placeholder="Shelf">
                           </div>
                        </div>
                     </div>
                     <div class="form-group row">
                        <label for="medicine_details" class="col-md-2 col-form-label">Medicine Details:</label>
                        <div class="col-md-4">
                           <div class="">
                              <input type="text" name="medicine_details" class="form-control" id="medicine_details" placeholder="Medicine Details">
                           </div>
                        </div>
                        <label for="category_id" class="col-md-2 col-form-label">Category:</label>
                        <div class="col-md-4">
                           <div class="">
                              <select name="category_id" class="form-control select" id="category_id">
                                 <option selected="selected" value="">Select Category</option>
                                 @foreach ($categories as $val)
                                    <option value="{{$val->id}}">{{$val->category_name}}</option>
                                 @endforeach
                              </select>
                           </div>
                        </div>
                     </div>
                     <div class="form-group row">
                        
                        <label for="price" class="col-md-2 col-form-label">MRP Price<i class="text-danger"> * </i>:</label>
                        <div class="col-md-4">
                            <div>
                                <input class="form-control valid_number" id="price" type="text" name="price" placeholder="Price" required>
                            </div>
                        </div>

                        <label for="type_id" class="col-md-2 col-form-label">Medicine Type:</label>
                        <div class="col-md-4">
                            <div>
                                <select name="type_id" class="form-control select required" id="type_id">
                                    <option selected="selected" value="">Select Medicine Type</option>
                                    @foreach ($types as $val)
                                        <option value="{{ $val->id }}">{{ $val->type_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                     </div>
                     <div class="form-group row">
                        <label for="manufacturer_id" class="col-md-2 col-form-label">Manufacturer <i class="text-danger"> * </i>:</label>
                        <div class="col-md-4">
                            <div>
                                <select name="manufacturer_id" class="form-control select required" id="manufacturer_id" required>
                                    <option selected="selected" value="">Select Manufacturer</option>
                                    @foreach ($manufacturers as $val)
                                        <option value="{{ $val->id }}">{{ $val->manufacturer_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <label for="image" class="col-md-2 col-form-label">Image:</label>
                        <div class="col-md-4">
                           <div class="">
                              <input name="image" type="file" class="form-control" id="image">
                           </div>
                        </div>
                     </div>
                     <div class="form-group row">
                        
                        <label for="cost" class="col-md-2 col-form-label">Purchase Price <i class="text-danger"> * </i>:</label>
                        <div class="col-md-4">
                            <div>
                                <input type="text" name="cost" class="form-control valid_number" id="cost" placeholder="Purchase Price" required>
                            </div>
                        </div>
                        <label class="col-md-2 col-form-label">Status <i class="text-danger"> * </i>:</label>
                        <div class="col-md-4">
                            <div>
                                <div class="form-check form-check-inline my-2">
                                    <input class="form-check-input" type="radio" name="status" id="status_active" value="1" checked required>
                                    <label class="form-check-label" for="status_active">Active</label>
                                </div>
                                <div class="form-check form-check-inline my-2">
                                    <input class="form-check-input" type="radio" name="status" id="status_inactive" value="0" required>
                                    <label class="form-check-label" for="status_inactive">Inactive</label>
                                </div>
                            </div>
                        </div>
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
@endsection

@push('scripts')
<!-- Select2 -->
<script src="{{asset("assets/plugins/select2/js/select2.full.min.js") }}"></script>
@endpush
