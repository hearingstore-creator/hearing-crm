@extends('admin.layouts.master')

@section('content')
<section class="content pt-3">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card card-outline card-primary">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-6"><h5>Edit Time Slot</h5></div>
                            @can('permission', 'time_slot.index')
                            <div class="col-md-6 text-right">
                                <a href="{{ url('admin/time-slots') }}" class="btn-primary btn-cs-primary btn-sm">
                                    <i class="fas fa-list mr-1"></i> Time Slot List
                                </a>
                            </div>
                            @endcan
                        </div>
                    </div>

                    @include('admin.alert_message')

                    <div class="card-body">
                        <form action="{{ route('time-slots.update', $timeSlot->id) }}" method="post">
                            @csrf
                            @method('PUT')
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Start Time <i class="text-danger">*</i>:</label>
                                        <div class="col-sm-6">
                                            <input type="time" class="form-control" name="start_time" value="{{ $timeSlot->start_time->format('H:i') }}" required>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">End Time <i class="text-danger">*</i>:</label>
                                        <div class="col-sm-6">
                                            <input type="time" class="form-control" name="end_time" value="{{ $timeSlot->end_time->format('H:i') }}" required>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Status <i class="text-danger">*</i>:</label>
                                        <div class="col-sm-3">
                                            <label class="radio-inline my-2">
                                                <input type="radio" name="status" value="1" {{ $timeSlot->status ? 'checked' : '' }}> Active
                                            </label>
                                            <label class="radio-inline my-2">
                                                <input type="radio" name="status" value="0" {{ !$timeSlot->status ? 'checked' : '' }}> Inactive
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group row">
                                <div class="col-md-6 text-right">
                                    <button type="submit" class="btn btn-success">Update</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
