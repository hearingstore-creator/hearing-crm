@extends('admin.layouts.master')

@push('css')
    <!-- DataTables -->
    @include('admin.partials.datatables-css')
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
                        <h5> Leaf Settings List</h5>
                     </div>
                     @can('permission', 'leaf_settings.create')
                     <div class="col-md-6 text-right">
                        <a href="{{url('admin/leaf-settings/create')}}" class="btn-primary btn-cs-primary btn-sm"><i class="fas fa-plus mr-1"></i>Add Leaf Settings</a>
                     </div>
                     @endcan
                  </div>
               </div>

               @include('admin.alert_message')
              
               <div class="card-body">
                <table  class="table table-bordered table-striped yajra-datatable"
                 data-datatable="leaf-settings-list">
                    <thead>
                        <tr>
                            <th>SL</th>
                            <th>Leaf Type</th>
                            <th>Total Number Per Box</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($leafSettings as $key=> $val)
                   <tr>
                     <td>{{++$key}}</td> 
                     <td>{{$val->leaf_type}}</td>
                     <td>{{$val->total_number_per_box}}</td>

                     <td>
                     @if($val->status==1)
                        <span class="badge badge-success">Active</span>
                      @else
                        <span class="badge badge-danger">Inactive</span>
                      @endif
                     </td>
                     <td>
                        <div class="d-flex flex-wrap gap-1 justify-content-center">
                           @can('permission', 'leaf_settings.edit')
                              <a href="{{ route('leaf-settings.edit', $val->id) }}" class="edit btn btn-success btn-sm mr-1">
                                 <i class="fas fa-edit"></i>
                              </a>
                           @endcan
                           @can('permission', 'leaf_settings.delete')
                              <form action="{{ route('leaf-settings.destroy', $val->id) }}" method="POST">
                                 <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                 <input type="hidden" name="_method" value="delete">
                                 <button type="submit" class="btn btn-danger btn-sm" onclick="confirmDelete(event)">
                                    <i class="far fa-trash-alt"></i>
                                 </button>
                              </form>
                           @endcan
                        </div>
                     </td>
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

@push('scripts')
@include('admin.partials.datatables-scripts')
@endpush
