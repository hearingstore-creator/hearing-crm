@extends('admin.layouts.master')
@section('page_title', 'Sign-ins')
@section('breadcrumb_parent', 'Activity Log')
@section('breadcrumb_parent_url', url('admin/activity-log'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-history" style="color: var(--accent-500); margin-right: 8px;"></i>Activity Log</h1>
            <p>Who signed in, and who tried and failed</p>
        </div>
        <div class="header-actions">
            <a href="{{ route('activity-log.export', request()->query()) }}" class="btn btn-secondary btn-sm" id="btn-export"><i class="fas fa-file-csv mr-1"></i>Export CSV</a>
        </div>
    </div>

    @include('admin.activity-log.tabs', ['active' => 'signins'])
    @include('admin.alert_message')

    <div class="crm-table-card crm-animate-in delay-1">
        <form method="GET" action="{{ url('admin/activity-log') }}" style="display:flex; flex-wrap:wrap; gap:8px; padding:10px 14px; border-bottom:1px solid var(--card-border);" id="signin-filters">
            <input type="hidden" name="tab" value="signins">
            <select name="user_id" class="form-control form-control-sm" style="width:auto;" onchange="this.form.submit()">
                <option value="">Everyone</option>
                @foreach ($users as $u)<option value="{{ $u->id }}" @selected((string) request('user_id') === (string) $u->id)>{{ $u->name }}</option>@endforeach
            </select>
            <select name="event" class="form-control form-control-sm" style="width:auto;" onchange="this.form.submit()">
                <option value="">All events</option>
                @foreach (\App\Models\LoginEvent::LABELS as $key => $label)<option value="{{ $key }}" @selected(request('event') === $key)>{{ $label }}</option>@endforeach
            </select>
            <input type="date" name="start_date" class="form-control form-control-sm" style="width:auto;" value="{{ request('start_date') }}" title="From">
            <input type="date" name="end_date" class="form-control form-control-sm" style="width:auto;" value="{{ request('end_date') }}" title="To">
            <button class="btn btn-secondary btn-sm" type="submit">Filter</button>
            @if (request()->hasAny(['user_id', 'event', 'start_date', 'end_date']))<a href="{{ url('admin/activity-log?tab=signins') }}" class="btn btn-link btn-sm">Clear</a>@endif
        </form>

        <div style="overflow-x:auto;">
            <table class="crm-table" id="signins-table">
                <thead><tr><th>When</th><th>Person</th><th>What happened</th><th>IP address</th></tr></thead>
                <tbody>
                    @forelse ($events as $e)
                        <tr>
                            <td style="font-size:13px;" title="{{ $e->created_at->diffForHumans() }}">{{ $e->created_at->format('d M Y, h:i A') }}</td>
                            <td style="font-size:13px;">{{ $e->user->name ?? '—' }}<div style="font-size:12px; color:var(--page-text-muted);">{{ $e->email }}</div></td>
                            <td>
                                <span class="crm-status-badge {{ $e->event === 'login' ? 'badge-status-won' : 'badge-status-lost' }}"><span class="dot"></span>{{ \App\Models\LoginEvent::LABELS[$e->event] ?? $e->event }}</span>
                            </td>
                            <td style="font-size:13px;">{{ $e->ip }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="text-align:center; padding:40px !important; color:var(--page-text-muted);">No sign-ins match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="crm-table-footer">{{ $events->links() }}</div>
    </div>
</div>
@endsection
