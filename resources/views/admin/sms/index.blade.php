@extends('admin.layouts.master')
@section('page_title', 'SMS Centre')
@section('breadcrumb_parent', 'Communication')
@section('breadcrumb_parent_url', url('admin/sms'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/sms-audience-builder.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/sms-optout.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-comment-dots" style="color: var(--accent-500); margin-right: 8px;"></i>SMS Centre</h1>
            <p>Send SMS messages, manage templates and automations</p>
        </div>
    </div>

    @include('admin.alert_message')

    <div class="crm-table-card crm-animate-in delay-1" style="padding: 0;">
        <ul class="nav nav-tabs" id="smsTabs" role="tablist" style="padding: 16px 20px 0; border-bottom: 1px solid var(--gray-200);">
            <li class="nav-item">
                <a class="nav-link active" id="compose-link" data-toggle="tab" href="#compose-tab" role="tab" style="border-radius: 8px 8px 0 0;">
                    <i class="fas fa-paper-plane mr-1"></i> Compose & Send
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="automation-link" data-toggle="tab" href="#automation-tab" role="tab" style="border-radius: 8px 8px 0 0;">
                    <i class="fas fa-robot mr-1"></i> Automation
                    @if($automations->count() > 0)
                    <span class="badge badge-pill badge-primary ml-1" style="font-size: 10px;">{{ $automations->count() }}</span>
                    @endif
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="templates-link" data-toggle="tab" href="#templates-tab" role="tab" style="border-radius: 8px 8px 0 0;">
                    <i class="fas fa-file-alt mr-1"></i> Templates
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="optout-link" data-toggle="tab" href="#optout-tab" role="tab" style="border-radius: 8px 8px 0 0;">
                    <i class="fas fa-user-slash mr-1"></i> Opt-out
                    @if($optOuts->count() > 0)
                    <span class="badge badge-pill badge-secondary ml-1" style="font-size: 10px;">{{ $optOuts->count() }}</span>
                    @endif
                </a>
            </li>
        </ul>

        <div class="tab-content" style="padding: 20px;">
            @include('admin.sms.partials.compose')
            @include('admin.sms.partials.automation')
            @include('admin.sms.partials.templates')
            @include('admin.sms.partials.optout')
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
<script>
    window.SMS_BASE_URL = '{{ url("admin") }}';
    window.SMS_CSRF = '{{ csrf_token() }}';
</script>
<script src="{{ asset('assets/js/sms-audience-builder.js') }}"></script>
<script>
    // Tab hash activation
    $(function() {
        var hash = window.location.hash;
        if (hash) {
            var tabLink = $('a[href="' + hash + '"]');
            if (tabLink.length) tabLink.tab('show');
        }
        $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
            history.replaceState(null, null, e.target.getAttribute('href'));
        });
    });
</script>
@endpush
