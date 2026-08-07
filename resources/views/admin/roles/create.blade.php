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
                        <h5> Add Role</h5>
                     </div>
                     <div class="col-md-6 text-right">
                        <a href="{{url('admin/roles')}}" class="btn-primary btn-cs-primary  btn-sm"><i class="fas fa-align-justify mr-1"></i>Type List</a>
                     </div>
                  </div>
               </div>

               @include('admin.alert_message')

               <div class="card-body">
                  <form action="{{url('admin/roles')}}" enctype="multipart/form-data" method="post">
                     @csrf
                     <div class="row">
                        <div class="col-md-12">
                           <div class="form-group row">
                              <label for="name" class="col-sm-3 col-form-label">Role Name <i class="text-danger"> * </i>:</label>
                              <div class="col-sm-6">
                                 <input type="text" class="form-control" id="name" placeholder="Type Name" name="name" autocomplete="off" required>
                              </div>
                           </div>
                        </div>
                     </div>
                     <div class="form-group row">
                        <div class="col-md-6 text-right">
                        </div>
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
