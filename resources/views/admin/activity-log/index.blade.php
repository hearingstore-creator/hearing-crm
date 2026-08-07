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
                                <h5>Activity Log</h5>
                            </div>
                        </div>

                        {{-- Search Filters --}}
                        <div class="row pb-3 mt-3">
                            <div class="col-md-12">
                                <form method="GET" action="{{ url('admin/activity-log') }}">
                                    <div class="row">
                                        {{-- User Filter --}}
                                        <div class="col-md-3">
                                            <select class="form-control select2" name="user_id">
                                                <option value="">Select User</option>
                                                @foreach ($users ?? [] as $user)
                                                    <option value="{{ $user->id }}"
                                                        {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                                        {{ $user->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Subject Type Filter --}}
                                        <div class="col-md-3">
                                            <select class="form-control select2" name="subject_type">
                                                <option value="">Select Subject Type</option>
                                                @foreach ($subjectTypes ?? [] as $subjectType)
                                                    <option value="{{ $subjectType }}"
                                                        {{ request('subject_type') == $subjectType ? 'selected' : '' }}>
                                                        {{ class_basename($subjectType) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Action Filter --}}
                                        <div class="col-md-2">
                                            <select class="form-control select2" name="action">
                                                <option value="">Select Action</option>
                                                <option value="created" {{ request('action') == 'created' ? 'selected' : '' }}>Created</option>
                                                <option value="updated" {{ request('action') == 'updated' ? 'selected' : '' }}>Updated</option>
                                                <option value="deleted" {{ request('action') == 'deleted' ? 'selected' : '' }}>Deleted</option>
                                            </select>
                                        </div>

                                        {{-- Start Date --}}
                                        <div class="col-md-2">
                                            <input type="date" name="start_date"
                                                class="form-control"
                                                value="{{ request('start_date') }}">
                                        </div>

                                        {{-- End Date --}}
                                        <div class="col-md-2">
                                            <input type="date" name="end_date"
                                                class="form-control"
                                                value="{{ request('end_date') }}">
                                        </div>

                                        <div class="col-md-12 mt-3">
                                            <button class="btn btn-info" type="submit">
                                                <i class="fas fa-search"></i> Search
                                            </button>
                                            <a href="{{ url('admin/activity-log') }}" class="btn btn-primary">
                                                <i class="fas fa-sync-alt"></i> Reset
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
                                    <th width="5%">SL</th>
                                    <th width="15%">User</th>
                                    <th width="15%">Subject</th>
                                    <th width="15%">Action</th>
                                    <th width="20%">Changes</th>
                                    {{-- <th width="10%">IP Address</th> --}}
                                    <th width="10%">Date & Time</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($activityLogs ?? [] as $key => $log)
                                <tr>
                                    <td>{{ ++$key + ($activityLogs->firstItem() - 1) }}</td>
                                    <td>
                                        {{ $log->user->name ?? 'N/A' }}
                                        <br>
                                        <small class="text-muted">{{ $log->user->email ?? '' }}</small>
                                    </td>
                                    <td>
                                        @if($log->subject_type)
                                            <strong>{{ class_basename($log->subject_type) }}</strong>
                                            <br>
                                            <small class="text-muted">ID: {{ $log->subject_id ?? 'N/A' }}</small>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-info">
                                            {{ $log->action ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($log->old_values || $log->new_values)
                                            @if($log->old_values)
                                                <strong>Old:</strong>
                                                <pre class="mb-1" style="font-size: 11px; max-height: 100px; overflow-y: auto;">{{ json_encode($log->old_values, JSON_PRETTY_PRINT) }}</pre>
                                            @endif

                                            @if($log->new_values)
                                                <strong>New:</strong>
                                                <pre class="mb-0" style="font-size: 11px; max-height: 100px; overflow-y: auto;">{{ json_encode($log->new_values, JSON_PRETTY_PRINT) }}</pre>
                                            @endif
                                        @else
                                            <span class="text-muted">No changes</span>
                                        @endif
                                    </td>
                                    {{-- <td>
                                        <small>{{ $log->ip_address ?? 'N/A' }}</small> --}}
                                        {{-- @if($log->user_agent)
                                            <br>
                                            <small class="text-muted" style="font-size: 10px;">
                                                {{ \Illuminate\Support\Str::limit($log->user_agent, 30) }}
                                            </small>
                                        @endif --}}
                                    {{-- </td> --}}
                                    <td>
                                        {{ $log->created_at ? $log->created_at->format('d M Y, h:i A') : 'N/A' }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center">No activity logs found</td>
                                </tr>
                                @endforelse
                            </tbody>

                        </table>

                        @if($activityLogs ?? null)
                            <div class="d-flex justify-content-center">
                                {{ $activityLogs->appends(request()->query())->links() }}
                            </div>
                        @endif
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
            $('.select2').select2({
                width: '100%',
                placeholder: 'Select option'
            });
        });
    </script>
@endpush
@endsection
