@extends('admin.layouts.master')
@push('css')
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
@endpush

@section('content')
<section class="content pt-3">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card card-outline card-primary">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Ticket List</h5>
                            </div>
                            <div class="col-md-6 text-right">
                                @can('permission','add_ticket.create')
                                <a href="{{ url('admin/ticket/create') }}"
                                   class="btn-primary btn-cs-primary btn-sm ml-2">
                                    <i class="fas fa-plus mr-1"></i>Add Ticket
                                </a>
                                @endcan
                            </div>
                        </div>

                        {{-- Search Filters --}}
                        <div class="row pb-3 mt-3">
                            <div class="col-md-12">
                                <form method="GET">
                                    <div class="row">
                                        {{-- Status Filter --}}
                                        <div class="col-md-2">
                                            <select class="form-control select" name="status">
                                                <option value="">Select Status</option>
                                                @foreach ($statuses ?? [] as $status)
                                                    <option value="{{ $status->value }}"
                                                        {{ request('status') == $status->value ? 'selected' : '' }}>
                                                        {{ $status->value }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Priority Filter --}}
                                        <div class="col-md-2">
                                            <select class="form-control select" name="priority">
                                                <option value="">Select Priority</option>
                                                @foreach ($priorities ?? [] as $priority)
                                                    <option value="{{ $priority->value }}"
                                                        {{ request('priority') == $priority->value ? 'selected' : '' }}>
                                                        {{ $priority->value }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Customer Filter --}}
                                        <div class="col-md-2">
                                            <select class="form-control select" name="customer_id">
                                                <option value="">Select Customer</option>
                                                @foreach ($customers ?? [] as $customer)
                                                    <option value="{{ $customer->id }}"
                                                        {{ request('customer_id') == $customer->id ? 'selected' : '' }}>
                                                        {{ $customer->lead->full_name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Assigned To Filter --}}
                                        <div class="col-md-2">
                                            <select class="form-control select" name="assigned_to">
                                                <option value="">Select Assigned User</option>
                                                @foreach ($users ?? [] as $user)
                                                    <option value="{{ $user->id }}"
                                                        {{ request('assigned_to') == $user->id ? 'selected' : '' }}>
                                                        {{ $user->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="col-md-2">
                                            <input type="date" name="start_date"
                                                class="form-control"
                                                placeholder="Start Date"
                                                value="{{ request('start_date') }}">
                                        </div>

                                        <div class="col-md-2">
                                            <input type="date" name="end_date"
                                                class="form-control"
                                                placeholder="End Date"
                                                value="{{ request('end_date') }}">
                                        </div>

                                        <div class="col-md-12 mt-2">
                                            <button class="btn btn-info" type="submit">
                                                <i class="fas fa-search"></i>
                                            </button>
                                            <a href="{{ url('admin/ticket') }}" class="btn btn-primary">
                                                <i class="fas fa-sync-alt"></i>
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                    </div>

                    @include('admin.alert_message')

                    <div class="card-body table-responsive">
                        <table class="table table-bordered w-100">
                            <thead>
                                <tr>
                                    <th>SL</th>
                                    <th>Subject</th>
                                    <th>Customer</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Assigned To</th>
                                    <th>Created At</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($tickets ?? [] as $key => $ticket)
                                <tr>
                                    <td>{{ ++$key }}</td>
                                    <td>{{ $ticket->subject }}</td>
                                    <td>{{ $ticket->customer->lead->full_name ?? '' }}</td>
                                    <td>
                                        <span class="badge" style="background-color: {{ $ticket->status->color() }}; color: #fff">
                                            {{ $ticket->status->value }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge" style="background-color: {{ $ticket->priority->color() }}; color: #fff">
                                            {{ $ticket->priority->value }}
                                        </span>
                                    </td>
                                    <td>{{ $ticket->assignedTo->name ?? 'Unassigned' }}</td>
                                    <td>{{ $ticket->created_at->format('d M Y, h:i A') }}</td>
                                    <td>
                                        <div class="d-flex flex-wrap justify-content-center">
                                            @can('permission','ticket_list.edit')
                                            <a href="{{ url('admin/ticket/'.$ticket->id.'/edit') }}"
                                               class="btn btn-info btn-sm mb-1 mr-1"
                                               title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endcan

                                            @can('permission','ticket_list.delete')
                                                <form action="{{ url('admin/ticket/'.$ticket->id) }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-sm"
                                                        onclick="confirmDelete(event)">
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

                    <div class="card-footer">
                        {{ $tickets->appends(request()->input())->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@push('scripts')
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            $('.select').select2();
        });
    </script>
@endpush
@endsection
