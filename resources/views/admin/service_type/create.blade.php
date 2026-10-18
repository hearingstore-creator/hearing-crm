@extends('admin.layouts.master')
@section('page_title', 'Add Service Type')
@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush
@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-concierge-bell" style="color: var(--accent-500); margin-right: 8px;"></i>Add Service</h1>
            <p>Create a service and set its booking requirement (Walk-in / Appointment required / Either)</p>
        </div>
        <div class="header-actions">
            @can('permission', 'service_type.index')
            <a href="{{ url('admin/service-type') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>Service List</a>
            @endcan
        </div>
    </div>
    @include('admin.alert_message')
    <form action="{{ url('admin/service-type') }}" method="post">
        @csrf
        <div class="crm-form-section crm-animate-in delay-1">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--accent-500);"><i class="fas fa-concierge-bell"></i></div>
                <h6>Service Information</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="service_type_name">Service Name <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" class="form-control" id="service_type_name" placeholder="e.g. Audiometry, Ear Cleaning, Consultation" name="service_type_name" value="{{ old('service_type_name') }}" autocomplete="off" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="booking_type">Booking Type <span style="color: var(--danger-500);">*</span></label>
                            <select name="booking_type" id="booking_type" class="form-control" required>
                                <option value="either" {{ old('booking_type', 'either') == 'either' ? 'selected' : '' }}>Either (Walk-in or Appointment)</option>
                                <option value="walk_in" {{ old('booking_type') == 'walk_in' ? 'selected' : '' }}>Walk-in</option>
                                <option value="appointment_required" {{ old('booking_type') == 'appointment_required' ? 'selected' : '' }}>Appointment required</option>
                            </select>
                            <small class="form-text text-muted">Controls whether staff can mark done immediately or must schedule an appointment.</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="price">Standard Price (৳)</label>
                            <input type="number" step="0.01" class="form-control" id="price" placeholder="0.00" name="price" value="{{ old('price') }}" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="appointment_service_id">Linked Appointment Service <small class="text-muted">(Optional)</small></label>
                            <select name="appointment_service_id" id="appointment_service_id" class="form-control select2">
                                <option value="">-- Match Automatically by Name / Select Later --</option>
                                @foreach($appointmentServices as $as)
                                    <option value="{{ $as->id }}" {{ old('appointment_service_id') == $as->id ? 'selected' : '' }}>{{ $as->name }} ({{ ucfirst($as->category?->value ?? 'General') }})</option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">Auto-selects this service when booking an appointment after sale.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Status <span style="color: var(--danger-500);">*</span></label>
                            <div class="mt-2">
                                <label class="radio-inline"><input type="radio" name="status" value="1" {{ old('status', '1') == '1' ? 'checked' : '' }}> Active</label>
                                <label class="radio-inline ml-3"><input type="radio" name="status" value="0" {{ old('status') == '0' ? 'checked' : '' }}> Inactive</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="crm-form-submit-bar">
            <a href="{{ url('admin/service-type') }}" class="btn btn-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Save Service</button>
        </div>
    </form>
</div>
@endsection