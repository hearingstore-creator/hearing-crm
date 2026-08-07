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
                        <h5>Template List</h5>
                     </div>
                     @can('permission', 'template.create')
                     <div class="col-md-6 text-right">
                        <a href="{{url('admin/template/create')}}" class="btn-primary btn-cs-primary btn-sm"><i class="fas fa-plus mr-1"></i>Add Template</a>
                     </div>
                     @endcan
                  </div>
               </div>

               @include('admin.alert_message')

               <div class="card-body">
                <table  class="table table-bordered table-striped"
                 data-datatable="template-list">
                    <thead>
                        <tr>
                            <th>SL</th>
                            <th>Title</th>
                            <th>Content</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($templates as $key=> $val)
                         <tr>
                            <td>{{++$key}}</td>
                            <td>{{$val->title}}</td>
                            <td>{{ Str::limit(strip_tags($val->content), 100) }} </td>
                            <td>
                            @if($val->status==1)

                                <span class="badge badge-success">Active</span>
                             @else
                                <span class="badge badge-danger">Inactive</span>
                             @endif
                            </td>
                            <td>
                                <div class="d-flex flex-wrap justify-content-center">
                                    @can('permission', 'template.edit')
                                    <a href="{{ route('template.edit', $val->id) }}" class="edit btn btn-success btn-sm mr-1">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    @endcan
                                    @can('permission', 'template.delete')
                                    <form action="{{ route('template.destroy', $val->id) }}" method="POST">
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
