@extends('admin.layouts.master')
@section('page_title', 'Edit Appointment Service')
@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush
@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-briefcase-medical" style="color: var(--accent-500); margin-right: 8px;"></i>Edit Appointment Service</h1>
            <p>Update service information</p>
        </div>
        <div class="header-actions">
            @can('permission', 'service_list.index')
            <a href="{{ url('admin/appointment-services') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>Service List</a>
            @endcan
        </div>
    </div>
    @include('admin.alert_message')
    <form action="{{ route('appointment-services.update', $service->id) }}" method="post">
        @csrf
        @method('PUT')
        <div class="crm-form-section crm-animate-in delay-1">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--accent-500);"><i class="fas fa-briefcase-medical"></i></div>
                <h6>Service Information</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Service Name <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" class="form-control" name="name" value="{{ $service->name }}" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Category <span style="color: var(--danger-500);">*</span></label>
                            <select class="form-control" name="category" required>
                                <option value="">Select Category</option>
                                @foreach ($serviceCategories as $category)
                                    <option value="{{ $category->value }}" {{ $service->category?->value === $category->value ? 'selected' : '' }}>{{ $category->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Description</label>
                            <textarea class="form-control" name="description" rows="2">{{ $service->description }}</textarea>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Max Per Day</label>
                            <input type="number" class="form-control" name="max_per_day" value="{{ $service->max_per_day }}" min="1" max="100">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Status <span style="color: var(--danger-500);">*</span></label>
                            <div class="mt-1">
                                <label class="radio-inline my-2"><input type="radio" name="status" value="1" {{ $service->status ? 'checked' : '' }}> Active</label>
                                <label class="radio-inline my-2 ml-3"><input type="radio" name="status" value="0" {{ !$service->status ? 'checked' : '' }}> Inactive</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="crm-form-section crm-animate-in delay-2 mt-3">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: #2563eb;"><i class="fas fa-clock"></i></div>
                <h6>Clinical Scheduling & Timing Settings</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Service Duration (Minutes) <span style="color: var(--danger-500);">*</span></label>
                            <input type="number" class="form-control" id="duration_minutes" name="duration_minutes" value="{{ $service->duration_minutes ?? 45 }}" min="5" max="480" step="5" required>
                            <small class="form-text text-muted">
                                Time required for specialist to deliver this service.
                            </small>
                            <div class="mt-2" style="display: flex; gap: 6px; flex-wrap: wrap;">
                                <button type="button" class="btn btn-outline-secondary btn-xs" onclick="document.getElementById('duration_minutes').value=15">15m</button>
                                <button type="button" class="btn btn-outline-secondary btn-xs" onclick="document.getElementById('duration_minutes').value=30">30m</button>
                                <button type="button" class="btn btn-outline-secondary btn-xs" onclick="document.getElementById('duration_minutes').value=45">45m</button>
                                <button type="button" class="btn btn-outline-secondary btn-xs" onclick="document.getElementById('duration_minutes').value=60">60m</button>
                                <button type="button" class="btn btn-outline-secondary btn-xs" onclick="document.getElementById('duration_minutes').value=90">90m</button>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Buffer Time After Service (Minutes)</label>
                            <input type="number" class="form-control" id="buffer_after_minutes" name="buffer_after_minutes" value="{{ $service->buffer_after_minutes ?? 10 }}" min="0" max="120" step="5">
                            <small class="form-text text-muted">
                                Clean-down & equipment reset buffer before next slot.
                            </small>
                            <div class="mt-2" style="display: flex; gap: 6px;">
                                <button type="button" class="btn btn-outline-secondary btn-xs" onclick="document.getElementById('buffer_after_minutes').value=0">0m</button>
                                <button type="button" class="btn btn-outline-secondary btn-xs" onclick="document.getElementById('buffer_after_minutes').value=5">5m</button>
                                <button type="button" class="btn btn-outline-secondary btn-xs" onclick="document.getElementById('buffer_after_minutes').value=10">10m</button>
                                <button type="button" class="btn btn-outline-secondary btn-xs" onclick="document.getElementById('buffer_after_minutes').value=15">15m</button>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Calendar Badge Color</label>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <input type="color" class="form-control" name="color_hex" value="{{ $service->color_hex ?: '#0284c7' }}" style="height: 38px; width: 60px; padding: 2px; cursor: pointer; border-radius: 6px;">
                                <span class="text-muted" style="font-size: 13px;">Color tag on calendar views</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-md-6">
                        <div class="custom-control custom-checkbox p-3" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                            <input type="checkbox" class="custom-control-input" id="requires_resource" name="requires_resource" value="1" {{ $service->requires_resource ? 'checked' : '' }}>
                            <label class="custom-control-label font-weight-600" for="requires_resource" style="cursor: pointer; color: #1e293b;">
                                <i class="fas fa-door-closed mr-1 text-warning"></i>Requires Sound Booth (Audiometry Room)
                            </label>
                            <div class="text-muted small mt-1" style="margin-left: 24px;">
                                Enforces strict concurrency control so overlapping bookings cannot exceed available sound booths.
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="custom-control custom-checkbox p-3" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                            <input type="checkbox" class="custom-control-input" id="is_online_booking_enabled" name="is_online_booking_enabled" value="1" {{ $service->is_online_booking_enabled ? 'checked' : '' }}>
                            <label class="custom-control-label font-weight-600" for="is_online_booking_enabled" style="cursor: pointer; color: #1e293b;">
                                <i class="fas fa-globe mr-1 text-success"></i>Enable Online Booking on Website
                            </label>
                            <div class="text-muted small mt-1" style="margin-left: 24px;">
                                Allows public website visitors to discover and reserve this service via self-service portal.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="crm-form-submit-bar">
            <a href="{{ url('admin/appointment-services') }}" class="btn btn-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Update</button>
        </div>
    </form>
</div>
@endsection
