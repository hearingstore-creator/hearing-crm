@extends('admin.layouts.master')

@section('content')
<section class="content pt-3">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card card-outline card-primary">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Time Slots</h5>
                            </div>
                            @can('permission', 'time_slot.create')
                            <div class="col-md-6 text-right">
                                <a href="{{ url('admin/time-slots/create') }}" class="btn-primary btn-cs-primary btn-sm">
                                    <i class="fas fa-plus mr-1"></i> Add Time Slot
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
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($timeSlots as $key => $slot)
                                <tr>
                                    <td>{{ ++$key }}</td>
                                    <td>{{ $slot->start_time->format('g:i A') }}</td>
                                    <td>{{ $slot->end_time->format('g:i A') }}</td>
                                    <td>
                                        @if($slot->status)
                                            <span class="badge badge-success">Active</span>
                                        @else
                                            <span class="badge badge-danger">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap justify-content-center">
                                            @can('permission', 'time_slot.edit')
                                            <a href="{{ route('time-slots.edit', $slot->id) }}" class="edit btn btn-success btn-sm mr-1">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endcan
                                            @can('permission', 'time_slot.delete')
                                            <form action="{{ route('time-slots.destroy', $slot->id) }}" method="POST">
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
