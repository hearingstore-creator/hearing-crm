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
                        <h5>Edit Medicine </h5>
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
                     <form action="{{route('medicine.update',$medicine->id)}}" enctype="multipart/form-data" method="post">
                        @csrf
                        @method('put')
                     
                     <div class="form-group row">
                       <label for="strength" class="col-md-2 col-form-label">Strength <i class="text-danger"> </i>:</label>
                        <div class="col-md-4">
                           <div class="">
                              <input type="text" name="strength" value="{{$medicine->strength}}" class="form-control text-left" id="strength" placeholder="Strength">
                           </div>
                        </div>
                        <label for="medicine_name" class="col-md-2 col-form-label">Medicine Name <i class="text-danger"> * </i>:</label>
                        <div class="col-md-4">
                           <div class="">
                              <input type="text" name="medicine_name" value="{{$medicine->medicine_name}}" class="form-control" id="medicine_name" placeholder="Medicine Name" required>
                           </div>
                        </div>
                     </div>
                     <div class="form-group row">
                        <label for="leaf_setting_id" class="col-md-2 col-form-label">Box Size <i class="text-danger"> * </i>:</label>
                        <div class="col-md-4">
                           <div class="">
                              <select name="leaf_setting_id" class="form-control select required" id="leaf_setting_id">
                                 <option selected="selected" value="">Select Leaf Pattern</option>
                                 @foreach ($leafSettings as $val)
                                    <option value="{{$val->id}}" {{$val->id == $medicine->leaf_setting_id ? 'selected' : ''}}>{{$val->leaf_type}}</option>
                                 @endforeach
                              </select>
                           </div>
                        </div>
                        <label for="generic_name" class="col-md-2 col-form-label">Generic Name:</label>
                        <div class="col-md-4">
                           <div class="">
                              <input type="text" class="form-control" name="generic_name" value="{{$medicine->generic_name}}" id="generic_name" placeholder="Generic Name">
                           </div>
                        </div>
                     </div>
                     <div class="form-group row">
                        <label for="shelf" class="col-md-2 col-form-label">Shelf:</label>
                        <div class="col-md-4">
                           <div class="">
                              <input class="form-control" id="shelf" type="text" name="shelf" value="{{$medicine->shelf}}" placeholder="Shelf">
                           </div>
                        </div>
                        <label for="unit_id" class="col-md-2 col-form-label">Unit <i class="text-danger"> * </i>:</label>
                        <div class="col-md-4">
                           <div class="">
                              <select name="unit_id" class="form-control select required" id="unit_id" required>
                                 <option selected="selected" value="">Select Unit</option>
                                 @foreach ($units as $val)
                                    <option value="{{$val->id}}" {{$val->id == $medicine->unit_id ? 'selected' : ''}}>{{$val->unit_name}}</option>
                                 @endforeach
                              </select>
                           </div>
                        </div>
                     </div>
                     <div class="form-group row">
                        <label for="type_id" class="col-md-2 col-form-label">Medicine Type:</label>
                        <div class="col-md-4">
                           <div class="">
                              <select name="type_id" class="form-control select required" id="type_id">
                                 <option selected="selected" value="">Select Medicine Type</option>
                                 @foreach ($types as $val)
                                    <option value="{{$val->id}}" {{$val->id == $medicine->type_id ? 'selected' : ''}}>{{$val->type_name}}</option>
                                 @endforeach
                              </select>
                           </div>
                        </div>
                        <label for="medicine_details" class="col-md-2 col-form-label">Medicine Details:</label>
                        <div class="col-md-4">
                           <div class="">
                              <input type="text" name="medicine_details" value="{{$medicine->medicine_details}}" class="form-control" id="medicine_details" placeholder="Medicine Details">
                           </div>
                        </div>
                     </div>
                     <div class="form-group row">
                        <label for="category_id" class="col-md-2 col-form-label">Category:</label>
                        <div class="col-md-4">
                           <div class="">
                              <select name="category_id" class="form-control select" id="category_id">
                                 <option selected="selected" value="">Select Category</option>
                                 @foreach ($categories as $val)
                                    <option value="{{$val->id}}" {{$val->id == $medicine->category_id ? 'selected' : ''}}>{{$val->category_name}}</option>
                                 @endforeach
                              </select>
                           </div>
                        </div>
                        <label for="manufacturer_id" class="col-md-2 col-form-label">Manufacturer <i class="text-danger"> * </i>:</label>
                        <div class="col-md-4">
                           <div class="">
                              <select name="manufacturer_id" class="form-control select required" id="manufacturer_id" required>
                                 <option selected="selected" value="">Select Manufacturer</option>
                                 @foreach ($manufacturers as $val)
                                    <option value="{{$val->id}}" {{$val->id == $medicine->manufacturer_id ? 'selected' : ''}}>{{$val->manufacturer_name}}</option>
                                 @endforeach
                              </select>
                           </div>
                        </div>
                     </div>
                     <div class="form-group row">
                        <label for="status" class="col-md-2 col-form-label">Status <i class="text-danger"> * </i>:</label>
                        <div class="col-md-4">
                           <div class="">
                              <label class="radio-inline my-2">
                              <input type="radio" name="status" value="1" checked="checked" {{ $medicine->status == 1 ? 'checked' : ''}}>
                              Active
                              </label>
                              <label class="radio-inline my-2">
                              <input type="radio" name="status" value="0" {{ $medicine->status == 0 ? 'checked' : ''}}>
                              Inactive
                              </label>
                           </div>
                        </div>
                        <label for="price" class="col-md-2 col-form-label">MRP Price<i class="text-danger"> * </i>:</label>
                        <div class="col-md-4">
                           <div class="">
                              <input class="form-control valid_number" id="price" type="text" name="price" value="{{$medicine->price}}" placeholder="Price" required>
                           </div>
                        </div>
                     </div>
                     <div class="form-group row">
                        <label for="image" class="col-md-2 col-form-label">Image:</label>
                        <div class="col-md-4">
                           <div class="">
                              <input name="image" type="file" class="form-control" id="image" placeholder="Image">
                              <div class="image pt-2">
                                  @if(!empty($medicine->image))
                                  <img src="{{asset($medicine->image)}}" class="img-rounded" width="150">
                                  @endif
                              </div>
                           </div>
                        </div>
                        <label for="cost" class="col-md-2 col-form-label">Purchase Price <i class="text-danger"> * </i>:</label>
                        <div class="col-md-4">
                           <div class="">
                              <input type="text" name="cost" class="form-control valid_number" value="{{$medicine->cost}}" id="cost" placeholder="Purchase Price" required>
                           </div>
                        </div>
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
<script src="{{asset("assets/plugins/select2/js/select2.full.min.js") }}"></script>
@endpush
@endsection
