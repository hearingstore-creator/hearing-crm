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
                        <h5> Users List</h5>
                     </div>
                     <div class="col-md-6 text-right">
                        <a href="{{ url('admin/user/create') }}" class="btn-primary btn-cs-primary btn-sm">
                            <i class="fas fa-plus mr-1"></i> Add User
                        </a>
                     </div>
                  </div>
               </div>

               @include('admin.alert_message')

               <div class="card-body">
                  <table class="table table-bordered table-striped yajra-datatable"
                         data-datatable="user-list">
                      <thead>
                          <tr>
                              <th>SL</th>
                              <th>Name</th>
                              <th>Email</th>
                              <th>Roles</th>
                              <th>Details</th>
                              <th>Status</th>
                              <th>Action</th>
                          </tr>
                      </thead>
                      <tbody>
                          @foreach ($users as $key => $user)
                              <tr>
                                  <td>{{ ++$key }}</td>
                                  <td>{{ $user->name }}</td>
                                  <td>{{ $user->email }}</td>
                                  <td>
                                      @if($user->roles->isNotEmpty())
                                          @foreach($user->roles as $role)
                                              <span class="badge badge-info">{{ $role->name }}</span>
                                          @endforeach
                                      @else
                                          <span class="badge badge-secondary">No Role</span>
                                      @endif
                                  </td>
                                  <td>{{ $user->details ?? '-' }}</td>
                                  <td>
                                      @if($user->status == 1)
                                          <span class="badge badge-success">Active</span>
                                      @else
                                          <span class="badge badge-danger">Inactive</span>
                                      @endif
                                  </td>
                                  <td>
                                      <div class="d-flex">
                                          <a href="{{ route('user.edit', $user->id) }}" class="btn btn-success btn-sm mr-1">
                                              <i class="fas fa-edit"></i>
                                          </a>
                                          <form action="{{ route('user.destroy', $user->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                              @csrf
                                              @method('DELETE')
                                              <button type="submit" class="btn btn-danger btn-sm">
                                                  <i class="far fa-trash-alt"></i>
                                              </button>
                                          </form>
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
</section>
@endsection
