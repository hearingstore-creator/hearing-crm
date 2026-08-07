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
                                <h5>Supplier List</h5>
                            </div>
                            @can('permission', 'add_supplier.create')
                            <div class="col-md-6 text-right">
                                <a href="{{ url('admin/suppliers/create') }}" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus mr-1"></i> Add Supplier
                                </a>
                            </div>
                            @endcan
                        </div>
                    </div>

                    @include('admin.alert_message')

                    <div class="card-body">
                        <table class="table table-bordered table-striped"
                               data-datatable="supplier-list">
                            <thead>
                                <tr>
                                    <th>SL</th>
                                    <th>Supplier Name</th>
                                    <th>Email Address 1</th>
                                    <th>Mobile No</th>
                                    <th>Phone</th>
                                    <th>Address 1</th>
                                    <th>Fax</th>
                                    <th>State</th>
                                    <th>Country</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($suppliers as $key => $item)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ $item->supplier_name }}</td>
                                    <td>{{ $item->email1 }}</td>
                                    <td>{{ $item->mobile_no }}</td>
                                    <td>{{ $item->phone }}</td>
                                    <td>{{ $item->address1 }}</td>
                                    <td>{{ $item->fax }}</td>
                                    <td>{{ $item->state }}</td>
                                    <td>{{ $item->country }}</td>

                                    <td>
                                        @if($item->status == 1)
                                            <span class="badge badge-success">Active</span>
                                        @else
                                            <span class="badge badge-danger">Inactive</span>
                                        @endif
                                    </td>

                                    <td class="text-center">
                                        @can('permission', 'supplier_list.edit')
                                            <a href="{{ route('suppliers.edit', $item->id) }}" 
                                            class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i>
                                            </a>
                                        @endcan    

                                        @can('permission', 'supplier_list.delete')
                                            <form action="{{ route('suppliers.destroy', $item->id) }}" 
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
