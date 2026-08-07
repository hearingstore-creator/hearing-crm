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
                                <h5>Task List</h5>
                            </div>
                            <div class="col-md-6 text-right">
                                @can('permission', 'add_task.create')
                                <a href="{{ url('admin/task/create') }}"
                                   class="btn-primary btn-cs-primary btn-sm ml-2">
                                    <i class="fas fa-plus mr-1"></i>Add Task
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
                                            <select class="form-control select2" name="status">
                                                <option value="">Select Status</option>
                                                @foreach ($statuses as $status)
                                                    <option value="{{ $status->value }}"
                                                        {{ request('status') == $status->value ? 'selected' : '' }}>
                                                        {{ $status->label() }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Priority Filter --}}
                                        <div class="col-md-2">
                                            <select class="form-control select2" name="priority">
                                                <option value="">Select Priority</option>
                                                @foreach ($priorities as $priority)
                                                    <option value="{{ $priority->value }}"
                                                        {{ request('priority') == $priority->value ? 'selected' : '' }}>
                                                        {{ $priority->label() }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Customer Filter --}}
                                        <div class="col-md-2">
                                            <select class="form-control select2" name="customer_id">
                                                <option value="">Select Customer</option>
                                                @foreach ($customers as $customer)
                                                    <option value="{{ $customer->id }}"
                                                        {{ request('customer_id') == $customer->id ? 'selected' : '' }}>
                                                        {{ $customer->lead->full_name ?? '-' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Assigned To Filter --}}
                                        <div class="col-md-2">
                                            <select class="form-control select2" name="assigned_to">
                                                <option value="">Select User</option>
                                                @foreach ($users as $user)
                                                    <option value="{{ $user->id }}"
                                                        {{ request('assigned_to') == $user->id ? 'selected' : '' }}>
                                                        {{ $user->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Search --}}
                                        <div class="col-md-2">
                                            <input type="text" name="search"
                                                class="form-control"
                                                placeholder="Search..."
                                                value="{{ request('search') }}">
                                        </div>

                                        <div class="col-md-2">
                                            <button class="btn btn-info" type="submit">
                                                <i class="fas fa-search"></i>
                                            </button>
                                            <a href="{{ url('admin/task') }}" class="btn btn-primary">
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
                                    <th>Assigned To</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Due Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($tasks ?? [] as $key => $task)
                                <tr>
                                    <td>{{ ($tasks->currentPage() - 1) * $tasks->perPage() + $key + 1 }}</td>
                                    <td>{{ $task->subject }}</td>
                                    <td>{{ $task->customer->lead->full_name ?? '-' }}</td>
                                    <td>{{ $task->assignedTo->name ?? '-' }}</td>
                                    <td>
                                        <span class="badge bg-{{ $task->status->color() }}">
                                            {{ $task->status->label() }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $task->priority->color() }}">
                                            {{ $task->priority->label() }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($task->due_date)
                                            {{ $task->due_date->format('d M Y') }}
                                            @if($task->isOverdue())
                                                <i class="fas fa-exclamation-triangle text-danger" title="Overdue"></i>
                                            @endif
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @can('permission', 'task_list.edit')
                                        <a href="{{ url('admin/task/'.$task->id.'/edit') }}"
                                           class="btn btn-info btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        @endcan

                                        @can('permission', 'task_list.delete')
                                        <form action="{{ url('admin/task', $task->id) }}" method="POST"
                                              style="display: inline-block;"
                                              onsubmit="return confirm('Are you sure you want to delete this task?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm" title="Delete">
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

                    @if($tasks ?? null)
                    <div class="card-footer">
                        {{ $tasks->appends(request()->all())->links() }}
                    </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</section>
@push('scripts')
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            $('.select2').select2();
        });
    </script>
@endpush
@endsection
