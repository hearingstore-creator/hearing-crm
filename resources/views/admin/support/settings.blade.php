@extends('admin.layouts.master')
@section('page_title', 'Support Settings')
@section('breadcrumb_parent', 'Support')
@section('breadcrumb_parent_url', url('admin/ticket'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <style>
        .ss-card { background:var(--card-bg); border:1px solid var(--card-border); border-radius:14px; padding:18px; margin-bottom:16px; }
        .ss-card h3 { font-size:14px; font-weight:700; margin:0 0 4px; }
        .ss-card p { font-size:12.5px; color:var(--page-text-muted); margin:0 0 12px; }
        .ss-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:14px; }
        .ss-grid label { font-size:12px; font-weight:700; color:var(--page-text-muted); text-transform:uppercase; letter-spacing:.04em; }
        .ss-flow { display:grid; gap:8px; font-size:13px; }
        .ss-flow div { display:flex; gap:10px; align-items:flex-start; }
        .ss-flow i { color:var(--accent-500); margin-top:3px; width:16px; text-align:center; }
    </style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px; max-width: 980px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-sliders-h" style="color: var(--accent-500); margin-right: 8px;"></i>Support Settings</h1>
            <p>The rules behind ticket deadlines and the automatic alerts. Applies to every branch.</p>
        </div>
    </div>

    @include('admin.alert_message')
    @if ($errors->any())
        <div class="alert alert-danger m-2">@foreach ($errors->all() as $msg)<div>{{ $msg }}</div>@endforeach</div>
    @endif

    <form method="POST" action="{{ route('support.settings.update') }}" id="support-settings-form">
        @csrf
        <div class="ss-card">
            <h3>Time allowed to answer a ticket</h3>
            <p>Counted from the time the ticket was opened. The clock pauses while the ticket is Pending or On Hold, and stops when it is Solved or Closed.</p>
            <div class="ss-grid">
                @foreach (['sla_critical_hours' => 'Critical', 'sla_high_hours' => 'High', 'sla_medium_hours' => 'Medium', 'sla_low_hours' => 'Low'] as $field => $label)
                    <div>
                        <label for="{{ $field }}">{{ $label }} (hours)</label>
                        <input type="number" min="1" max="2000" class="form-control" name="{{ $field }}" id="{{ $field }}" value="{{ old($field, $setting->{$field}) }}" required>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="ss-card">
            <h3>Reminders and closing</h3>
            <p>Both run by themselves in the background.</p>
            <div class="ss-grid">
                <div>
                    <label for="stale_days">Remind the owner after (days with no update)</label>
                    <input type="number" min="1" max="60" class="form-control" name="stale_days" id="stale_days" value="{{ old('stale_days', $setting->stale_days) }}" required>
                </div>
                <div>
                    <label for="auto_close_days">Close a Solved ticket after (days)</label>
                    <input type="number" min="1" max="60" class="form-control" name="auto_close_days" id="auto_close_days" value="{{ old('auto_close_days', $setting->auto_close_days) }}" required>
                </div>
            </div>
        </div>

        <div class="ss-card">
            <h3>What runs automatically</h3>
            <div class="ss-flow">
                <div><i class="fas fa-bell"></i><span>A new ticket tells its owner, or the support managers when nobody owns it.</span></div>
                <div><i class="fas fa-user-check"></i><span>Giving a ticket to someone tells them. Reopening a ticket tells its owner.</span></div>
                <div><i class="fas fa-stopwatch"></i><span>A ticket past its deadline alerts the owner and the managers, once. Checked every 15 minutes.</span></div>
                <div><i class="fas fa-hourglass-half"></i><span>A ticket with no update for the days above reminds its owner, once per quiet spell.</span></div>
                <div><i class="fas fa-tasks"></i><span>A task due tomorrow or overdue alerts its owner. Every person also gets one morning summary of their tickets and tasks.</span></div>
                <div><i class="fas fa-sms"></i><span>Marking a ticket Solved can text the patient. Switch it on in SMS Centre, Automation, "Service Ticket Solved".</span></div>
            </div>
            <p style="margin-top:12px;">Each person can mute any of these alerts in their notification settings.</p>
        </div>

        @can('permission', 'support_settings.edit')
            <button type="submit" class="btn btn-primary" id="btn-save-support-settings"><i class="fas fa-check mr-1"></i>Save settings</button>
        @endcan
    </form>
</div>
@endsection
