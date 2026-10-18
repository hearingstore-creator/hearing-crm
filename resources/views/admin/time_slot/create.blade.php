@extends('admin.layouts.master')
@section('page_title', 'Add Time Slot')
@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush
@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-clock" style="color: var(--accent-500); margin-right: 8px;"></i>Add Time Slot</h1>
            <p>Create a new appointment time slot</p>
        </div>
        <div class="header-actions">
            @can('permission', 'time_slot.index')
            <a href="{{ url('admin/time-slots') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>Time Slot List</a>
            @endcan
        </div>
    </div>
    @include('admin.alert_message')
    <form action="{{ url('admin/time-slots') }}" method="post">
        @csrf
        <div class="crm-form-section crm-animate-in delay-1">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--accent-500);"><i class="fas fa-clock"></i></div>
                <h6>Time Slot Information</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Start Time <span style="color: var(--danger-500);">*</span></label>
                            <input type="time" class="form-control" name="start_time" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>End Time <span style="color: var(--danger-500);">*</span></label>
                            <input type="time" class="form-control" name="end_time" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Status <span style="color: var(--danger-500);">*</span></label>
                            <div class="mt-1">
                                <label class="radio-inline my-2"><input type="radio" name="status" value="1" checked> Active</label>
                                <label class="radio-inline my-2 ml-3"><input type="radio" name="status" value="0"> Inactive</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="crm-form-submit-bar">
            <a href="{{ url('admin/time-slots') }}" class="btn btn-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Save</button>
        </div>
    </form>
</div>
@endsection
