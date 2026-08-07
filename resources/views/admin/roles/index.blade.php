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
                        <h5> Role List</h5>
                     </div>
                     <div class="col-md-6 text-right">
                        <a href="{{url('admin/roles/create')}}" class="btn-primary btn-cs-primary btn-sm"><i class="fas fa-plus mr-1"></i>Add Role</a>
                     </div>
                  </div>
               </div>

               @include('admin.alert_message')
              
               <div class="card-body">
                <table  class="table table-bordered table-striped yajra-datatable"
                 data-datatable="role-list">
                    <thead>
                        <tr>
                            <th>SL</th>
                            <th>Name</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($roles as $key=> $val)
                         <tr>
                            <td>{{++$key}}</td> 
                            <td>{{$val->name}}</td>
                            @if($val->name != 'Super Admin')
                            <td>
                                <div class="row">
                                    <div class="d-flex flex-wrap align-items-center">
                                       <a href="{{route('roles.edit', $val->id)}}" class="edit btn btn-success btn-sm mr-1"><i class="fas fa-edit"> </i></a>
                                        <form action="{{route('roles.destroy', $val->id)}}" method="POST">
                                        <input type="hidden" name="_token" value=" {{csrf_token()}} ">
                                        <input type="hidden" name="_method" value="delete" />
                                        
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="confirmDelete(event)"><i class="far fa-trash-alt"> </i></button>               
                                        </form> 
                                    </div>
                                </div>
                            </td>
                            @endif
                         </tr>
                        @endforeach
                    </tbody>
                </table>
              </div>
            </div>
         </div>
      </div>
   </div>
   <!-- /.container-fluid -->
</section>
<!-- /.content -->
@endsection
