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
                            <div class="col-md-6"><h5>Appointments</h5></div>
                            <div class="col-md-6 text-right">
                               @can('permission', 'add_appointment.create')
                                <a href="{{ route('appointments.create') }}" class="btn-primary btn-cs-primary btn-sm mr-2">
                                    <i class="fas fa-plus mr-1"></i> Book Appointment
                                </a>
                                @endcan
                                @can('permission', 'calendar_view.index')
                                <a href="{{ url('admin/appointments/calendar') }}" class="btn-success btn-cs-primary btn-sm">
                                    <i class="fas fa-calendar mr-1"></i> Calendar View
                                </a>
                                @endcan
                            </div>
                            
                        </div>
                    </div>

                    @include('admin.alert_message')

                    <!-- Filters -->
                    <div class="card-body">
                        <form method="GET">
                            <div class="row pb-3">
                                <div class="col-md-2">
                                    <select class="form-control select" name="user_id">
                                        <option value="">Professional</option>
                                        @foreach ($professionals as $prof)
                                            <option value="{{ $prof->id }}" {{ request('user_id') == $prof->id ? 'selected' : '' }}>{{ $prof->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select class="form-control select" name="service_id">
                                        <option value="">Service</option>
                                        @foreach ($appointmentServices as $service)
                                            <option value="{{ $service->id }}" {{ request('service_id') == $service->id ? 'selected' : '' }}>{{ $service->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select class="form-control" name="status">
                                        <option value="">Status</option>
                                        @foreach ($appointmentStatuses as $status)
                                            <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>{{ $status->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="date" class="form-control" name="start_date" value="{{ request('start_date') }}">
                                </div>
                                <div class="col-md-2">
                                    <input type="date" class="form-control" name="end_date" value="{{ request('end_date') }}">
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-info" type="submit"><i class="fas fa-search"></i></button>
                                    <a href="{{ url('admin/appointments') }}" class="btn btn-primary"><i class="fas fa-sync-alt"></i></a>
                                </div>
                            </div>
                        </form>

                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>SL</th>
                                    <th>Patient</th>
                                    <th>Service</th>
                                    <th>Professional</th>
                                    <th>Date</th>
                                    <th>Time Slot</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($appointments as $key => $appt)
                                <tr>
                                    <td>{{ ++$key }}</td>
                                    <td>
                                        @if($appt->customer)
                                            <a href="{{ url('admin/customers/'.$appt->customer->id.'/edit') }}">{{ $appt->customer->lead?->full_name ?? 'Customer #'.$appt->customer_id }}</a>
                                        @elseif($appt->lead)
                                            <a href="{{ url('admin/leads/'.$appt->lead->id.'/edit') }}">{{ $appt->lead->full_name }}</a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($appt->service)
                                            <span class="badge badge-primary">{{ $appt->service->name }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($appt->user)
                                            {{ $appt->user->name }}
                                            @if($appt->user->designation)
                                                <br><small class="text-muted">{{ $appt->user->designation->label() }}</small>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $appt->appointment_date->format('d M Y') }}</td>
                                    <td>
                                        @if($appt->professionalTimeSlot && $appt->professionalTimeSlot->timeSlot)
                                            {{ $appt->professionalTimeSlot->timeSlot->start_time->format('g:i A') }} - {{ $appt->professionalTimeSlot->timeSlot->end_time->format('g:i A') }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($appt->status)
                                            <span class="badge badge-{{ $appt->status->color() }}">{{ $appt->status->label() }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap justify-content-center">
                                            @can('permission', 'appointment_list.edit')
                                            <a href="{{ route('appointments.edit', $appt->id) }}" class="edit btn btn-success btn-sm mr-1">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @endcan
                                            @can('permission', 'appointment_list.delete')
                                            <form action="{{ route('appointments.destroy', $appt->id) }}" method="POST" class="d-inline">
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

                        {{ $appointments->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            $('.select').select2({ width: '100%', allowClear: true });
        });
    </script>
@endpush
