@extends('admin.layouts.master')

@section('content')
<section class="content pt-3">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card card-outline card-primary">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-6"><h5>Appointment Services</h5></div>
                            @can('permission', 'add_service.create')
                            <div class="col-md-6 text-right">
                                <a href="{{ url('admin/appointment-services/create') }}" class="btn-primary btn-cs-primary btn-sm">
                                    <i class="fas fa-plus mr-1"></i> Add Service
                                </a>
                            </div>
                            @endcan
                        </div>
                    </div>

                    @include('admin.alert_message')

                    <div class="card-body">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>SL</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Max/Day</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($services as $key => $service)
                                <tr>
                                    <td>{{ ++$key }}</td>
                                    <td>{{ $service->name }}</td>
                                    <td>
                                        @if($service->category)
                                            <span class="badge badge-{{ $service->category->color() }}">{{ $service->category->label() }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $service->max_per_day }}</td>
                                    <td>
                                        @if($service->status)
                                            <span class="badge badge-success">Active</span>
                                        @else
                                            <span class="badge badge-danger">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap justify-content-center">
                                            @can('permission', 'service_list.edit')
                                            <a href="{{ route('appointment-services.edit', $service->id) }}" class="edit btn btn-success btn-sm mr-1">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endcan
                                            @can('permission', 'service_list.delete')
                                            <form action="{{ route('appointment-services.destroy', $service->id) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
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
</section>
@endsection
