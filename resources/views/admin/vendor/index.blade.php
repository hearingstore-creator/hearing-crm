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
                                <h5>Vendor List</h5>
                            </div>
                            @can('permission', 'add_vendor.create')
                            <div class="col-md-6 text-right">
                                <a href="{{ url('admin/vendors/create') }}" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus mr-1"></i> Add Vendor
                                </a>
                            </div>
                            @endcan
                        </div>

                        {{-- Search Filters --}}
                        <div class="row pb-3 mt-3">
                            <div class="col-md-12">
                                <form method="GET">
                                    <div class="row">
                                        {{-- Name Filter --}}
                                        <div class="col-md-3">
                                            <input type="text" name="name"
                                                class="form-control"
                                                placeholder="Filter by Name"
                                                value="{{ request('name') }}">
                                        </div>

                                        {{-- Email Filter --}}
                                        <div class="col-md-3">
                                            <input type="text" name="email"
                                                class="form-control"
                                                placeholder="Filter by Email"
                                                value="{{ request('email') }}">
                                        </div>

                                        {{-- Mobile No Filter --}}
                                        <div class="col-md-3">
                                            <input type="text" name="mobile_no"
                                                class="form-control"
                                                placeholder="Filter by Mobile No"
                                                value="{{ request('mobile_no') }}">
                                        </div>

                                        <div class="col-md-3">
                                            <button class="btn btn-info" type="submit">
                                                <i class="fas fa-search"></i>
                                            </button>
                                            <a href="{{ url('admin/vendors') }}" class="btn btn-primary">
                                                <i class="fas fa-sync-alt"></i>
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    @include('admin.alert_message')

                    <div class="card-body">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>SL</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Mobile No</th>
                                    <th>Address</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($vendors as $key => $item)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ $item->name }}</td>
                                    <td>{{ $item->email }}</td>
                                    <td>{{ $item->mobile_no }}</td>
                                    <td>{{ $item->address }}</td>

                                    <td>
                                        @if($item->status == 1)
                                            <span class="badge badge-success">Active</span>
                                        @else
                                            <span class="badge badge-danger">Inactive</span>
                                        @endif
                                    </td>

                                    <td class="text-center">
                                        @can('permission', 'vendor_list.edit')
                                            <a href="{{ route('vendors.edit', $item->id) }}"
                                            class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i>
                                            </a>
                                        @endcan

                                        @can('permission', 'vendor_list.delete')
                                            <form action="{{ route('vendors.destroy', $item->id) }}"
                                                method="POST"
                                                class="d-inline"
                                                onsubmit="return confirm('Are you sure?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        @endcan
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
