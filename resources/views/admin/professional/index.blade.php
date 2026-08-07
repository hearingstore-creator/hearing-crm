@extends('admin.layouts.master')

@section('content')
<section class="content pt-3">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card card-outline card-primary">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-6"><h5>Professionals</h5></div>
                            @can('permission', 'add_professional.create')
                            <div class="col-md-6 text-right">
                                <a href="{{ url('admin/professionals/create') }}" class="btn-primary btn-cs-primary btn-sm">
                                    <i class="fas fa-plus mr-1"></i> Add Professional
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
                                    <th>Designation</th>
                                    <th>Phone</th>
                                    <th>Services</th>
                                    <th>Working Days</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($professionals as $key => $prof)
                                <tr>
                                    <td>{{ ++$key }}</td>
                                    <td>{{ $prof->name }}</td>
                                    <td>
                                        @if($prof->designation)
                                            <span class="badge badge-{{ $prof->designation->color() }}">{{ $prof->designation->label() }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $prof->mobile_no ?? '—' }}</td>
                                    <td>
                                        @foreach ($prof->appointmentServices as $svc)
                                            <span class="badge badge-primary mr-1 mb-1">{{ $svc->name }}</span>
                                        @endforeach
                                    </td>
                                    <td>
                                        @foreach ($prof->workingDays->where('is_active', true) as $wd)
                                            <span class="badge badge-secondary mr-1 mb-1">{{ $wd->day?->shortLabel() }}</span>
                                        @endforeach
                                    </td>
                                    <td>
                                        @if($prof->status)
                                            <span class="badge badge-success">Active</span>
                                        @else
                                            <span class="badge badge-danger">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap justify-content-center">
                                            @can('permission', 'professional_list.edit')
                                            <a href="{{ route('professionals.edit', $prof->id) }}" class="edit btn btn-success btn-sm mr-1">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endcan
                                            @can('permission', 'professional_list.delete')
                                            <form action="{{ route('professionals.destroy', $prof->id) }}" method="POST">
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
