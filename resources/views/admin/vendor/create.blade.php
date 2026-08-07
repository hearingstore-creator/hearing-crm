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
                        <h5>Add Vendor</h5>
                     </div>
                     <div class="col-md-6 text-right">
                        @can('permission', 'vendor_list.index')
                        <a href="{{ url('admin/vendors') }}" class="btn-primary btn-cs-primary btn-sm">
                           <i class="fas fa-align-justify mr-1"></i> Vendor List
                        </a>
                        @endcan
                     </div>
                  </div>
               </div>

               @include('admin.alert_message')

               <div class="card-body">
                  <form action="{{ url('admin/vendors') }}" enctype="multipart/form-data" method="post">
                     @csrf
                     <div class="row">
                        <div class="col-md-6">
                           <div class="form-group row">
                              <label for="name" class="col-sm-4 col-form-label">Name <i class="text-danger">*</i>:</label>
                              <div class="col-sm-8">
                                 <input type="text" class="form-control" id="name" name="name" placeholder="Vendor Name" autocomplete="off" required>
                              </div>
                           </div>

                           <div class="form-group row">
                              <label for="address" class="col-sm-4 col-form-label">Address:</label>
                              <div class="col-sm-8">
                                 <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                              </div>
                           </div>
                        </div>

                        <div class="col-md-6">
                           <div class="form-group row">
                              <label for="mobile_no" class="col-sm-4 col-form-label">Mobile No <i class="text-danger">*</i>:</label>
                              <div class="col-sm-8">
                                 <input type="text" class="form-control" id="mobile_no" name="mobile_no" placeholder="Mobile No" autocomplete="off" required>
                              </div>
                           </div>

                           <div class="form-group row">
                              <label for="email" class="col-sm-4 col-form-label">Email <i class="text-danger">*</i>:</label>
                              <div class="col-sm-8">
                                 <input type="email" class="form-control" id="email" name="email" placeholder="Email Address" required>
                              </div>
                           </div>

                           <div class="form-group row">
                              <label for="password" class="col-sm-4 col-form-label">Password <i class="text-danger">*</i>:</label>
                              <div class="col-sm-8">
                                 <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                              </div>
                           </div>
                        </div>
                     </div>

                     <div class="form-group row">
                        <div class="col-md-6 text-right"></div>
                        <div class="col-md-6 text-right">
                           <button type="submit" class="btn btn-success">Save</button>
                        </div>
                     </div>
                  </form>
               </div>
            </div>
         </div>
      </div>
   </div>
</section>
<!-- /.content -->
@endsection
