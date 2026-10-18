@extends('admin.layouts.master')
@section('page_title', 'Edit Automation')
@section('breadcrumb_parent', 'SMS Centre')
@section('breadcrumb_parent_url', url('admin/sms'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-robot" style="color: var(--accent-500); margin-right: 8px;"></i>Edit Automation Rule</h1>
            <p>Update trigger and message settings</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/sms#automation-tab') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>Back to SMS</a>
        </div>
    </div>

    @include('admin.alert_message')

    <form action="{{ url('admin/sms-automations/' . $automation->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="crm-form-section crm-animate-in delay-1">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--accent-500);"><i class="fas fa-bolt"></i></div>
                <h6>Trigger Settings</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name">Rule Name <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" class="form-control" name="name" id="name" value="{{ old('name', $automation->name) }}" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="trigger_type">Trigger Event <span style="color: var(--danger-500);">*</span></label>
                            <select name="trigger_type" id="trigger_type" class="form-control" required>
                                @foreach($triggers as $trigger)
                                    <option value="{{ $trigger->value }}" {{ $automation->trigger_type == $trigger->value ? 'selected' : '' }}>
                                        {{ $trigger->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="description">Description</label>
                            <input type="text" class="form-control" name="description" id="description" value="{{ old('description', $automation->description) }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="crm-form-section crm-animate-in delay-2">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--success-500);"><i class="fas fa-comment-dots"></i></div>
                <h6>Message Content</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="message_body" class="mb-0">Message Body <span style="color: var(--danger-500);">*</span></label>
                                <small class="text-muted">Use variables like @{{name}}, @{{device}}, @{{warranty_date}}</small>
                            </div>
                            <textarea name="message_body" id="message_body" class="form-control" rows="5" required>{{ old('message_body', $automation->message_body) }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-0">
                            <label>Status</label>
                            <div class="mt-1">
                                <label class="radio-inline my-2"><input type="radio" name="is_active" value="1" {{ $automation->is_active ? 'checked' : '' }}> Active</label>
                                <label class="radio-inline my-2 ml-3"><input type="radio" name="is_active" value="0" {{ !$automation->is_active ? 'checked' : '' }}> Inactive</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="crm-form-submit-bar">
            <a href="{{ url('admin/sms#automation-tab') }}" class="btn btn-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Update Automation</button>
        </div>
    </form>
</div>
@endsection
