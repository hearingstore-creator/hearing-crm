@extends('admin.layouts.master')
@section('page_title', 'New Automation')
@section('breadcrumb_parent', 'SMS Centre')
@section('breadcrumb_parent_url', url('admin/sms'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-robot" style="color: var(--accent-500); margin-right: 8px;"></i>New Automation Rule</h1>
            <p>Create an automated SMS trigger</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/sms#automation-tab') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>Back to SMS</a>
        </div>
    </div>

    @include('admin.alert_message')

    @include('admin.sms-automation._form', [
        'action'      => url('admin/sms-automations'),
        'method'      => 'POST',
        'submitLabel' => 'Save Automation',
    ])
</div>
@endsection
