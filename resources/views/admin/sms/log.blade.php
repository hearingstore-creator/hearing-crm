@extends('admin.layouts.master')
@section('page_title', 'SMS Send Log')
@section('breadcrumb_parent', 'SMS Centre')
@section('breadcrumb_parent_url', url('admin/sms'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<style>
/* Log filter bar */
.log-filter-bar {
    display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end;
    padding: 16px 20px; background: var(--gray-50); border-bottom: 1px solid var(--gray-100);
    border-radius: 12px 12px 0 0;
}
.log-filter-group { display: flex; flex-direction: column; gap: 4px; }
.log-filter-group label {
    font-size: 10px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.04em; color: var(--page-text-muted);
}
.log-filter-group input,
.log-filter-group select {
    font-size: 12px; border-radius: 6px; border: 1px solid var(--gray-200);
    padding: 6px 10px; background: var(--card-bg); color: var(--page-text);
    min-width: 130px;
}
.log-filter-actions { display: flex; gap: 6px; }

/* Log table */
.log-table { width: 100%; border-collapse: collapse; }
.log-table thead th {
    padding: 10px 14px; font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.04em;
    color: var(--page-text-muted); background: var(--table-header-bg);
    border-bottom: 2px solid var(--gray-200);
}
.log-table tbody td {
    padding: 12px 14px; font-size: 13px; border-bottom: 1px solid var(--gray-100);
    vertical-align: middle; color: var(--page-text);
}
.log-table tbody tr:hover { background: var(--gray-50); }
.log-table tbody tr:last-child td { border-bottom: none; }

/* Log phone cell */
.log-phone {
    font-family: 'SFMono-Regular', Consolas, monospace; font-size: 12px;
    padding: 3px 8px; background: var(--gray-100); border-radius: 4px;
    display: inline-block; font-weight: 600;
}
/* Log message cell */
.log-msg {
    max-width: 280px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    font-size: 12px; color: var(--page-text-secondary);
}
/* Status and type badges */
.log-badge {
    font-size: 10px; font-weight: 700; padding: 3px 10px; border-radius: 99px;
    text-transform: uppercase; letter-spacing: 0.03em; display: inline-block;
}
.log-badge-sent { background: var(--success-100); color: var(--success-700); }
.log-badge-failed { background: var(--danger-100, #FEE2E2); color: var(--danger-700); }
.log-badge-pending { background: var(--warning-100); color: var(--warning-700); }
.log-badge-bulk { background: rgba(59,130,246,0.1); color: #3B82F6; }
.log-badge-single { background: rgba(139,92,246,0.1); color: #8B5CF6; }
.log-badge-automation { background: rgba(245,158,11,0.1); color: #F59E0B; }
.log-badge-transactional { background: rgba(20,184,166,0.1); color: #14B8A6; }

.log-source {
    font-size: 12px; font-weight: 600; color: var(--accent-600);
}
.log-date {
    font-size: 11px; color: var(--page-text-muted); white-space: nowrap;
}
.log-entity {
    font-size: 11px; font-weight: 600;
}
.log-followup-badge {
    font-size: 9px; font-weight: 700; padding: 1px 6px; border-radius: 4px;
    background: var(--success-100); color: var(--success-700);
}

/* Pagination */
.log-pagination {
    padding: 12px 20px; display: flex; justify-content: center;
    border-top: 1px solid var(--gray-100);
}
.log-pagination .pagination { margin: 0; }
.log-pagination .page-link {
    border-radius: 6px; margin: 0 2px; font-size: 12px; font-weight: 600;
    border-color: var(--gray-200);
}
.log-pagination .page-item.active .page-link {
    background: var(--accent-500); border-color: var(--accent-500);
}

/* Empty state */
.log-empty {
    text-align: center; padding: 50px 20px; color: var(--page-text-muted);
}
.log-empty i { font-size: 40px; display: block; margin-bottom: 12px; color: var(--gray-300); }
.log-empty p { font-size: 14px; margin: 0; }
</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-history" style="color: var(--accent-500); margin-right: 8px;"></i>SMS Send Log</h1>
            <p>Track every SMS message sent from the system</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/sms-analytics') }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-chart-line mr-1"></i>Analytics</a>
            <a href="{{ url('admin/sms') }}" class="btn btn-primary btn-sm"><i class="fas fa-comment-dots mr-1"></i>SMS Centre</a>
        </div>
    </div>

    @include('admin.alert_message')

    <div class="crm-table-card crm-animate-in delay-1" style="border-radius:12px;overflow:hidden;">
        {{-- Filter Bar --}}
        <form method="GET">
            <div class="log-filter-bar">
                <div class="log-filter-group">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Phone or message..." value="{{ request('search', '') }}">
                </div>
                <div class="log-filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Status</option>
                        @foreach(\App\Enums\SmsStatus::cases() as $status)
                        <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>
                            {{ $status->label() }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="log-filter-group">
                    <label>Type</label>
                    <select name="type">
                        <option value="">All Types</option>
                        @foreach(\App\Enums\SmsType::cases() as $type)
                        <option value="{{ $type->value }}" {{ request('type') == $type->value ? 'selected' : '' }}>
                            {{ $type->label() }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="log-filter-group">
                    <label>From</label>
                    <input type="date" name="date_from" value="{{ request('date_from', '') }}">
                </div>
                <div class="log-filter-group">
                    <label>To</label>
                    <input type="date" name="date_to" value="{{ request('date_to', '') }}">
                </div>
                <div class="log-filter-actions">
                    <button class="btn btn-primary btn-sm" type="submit" style="border-radius:6px;"><i class="fas fa-search mr-1"></i>Filter</button>
                    <a href="{{ url('admin/sms-log') }}" class="btn btn-outline-secondary btn-sm" style="border-radius:6px;"><i class="fas fa-sync-alt"></i></a>
                </div>
            </div>
        </form>

        {{-- Table --}}
        <div style="overflow-x:auto;">
            <table class="log-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Phone</th>
                        <th>Message</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Recipient</th>
                        <th>Source</th>
                        <th>Sent At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $key => $log)
                    @php
                        $statusVal = $log->status instanceof \App\Enums\SmsStatus ? $log->status->value : $log->status;
                        $typeVal = $log->type instanceof \App\Enums\SmsType ? $log->type->value : $log->type;
                    @endphp
                    <tr>
                        <td style="font-weight:600;color:var(--page-text-muted);font-size:12px;">{{ ($logs->currentPage() - 1) * $logs->perPage() + ++$key }}</td>
                        <td><span class="log-phone">{{ $log->phone_number }}</span></td>
                        <td>
                            <div class="log-msg" title="{{ $log->message }}">{{ $log->message }}</div>
                        </td>
                        <td><span class="log-badge log-badge-{{ $typeVal }}">{{ $log->type_label }}</span></td>
                        <td>
                            <span class="log-badge log-badge-{{ $statusVal }}">
                                @if($statusVal === 'sent') ✓ @elseif($statusVal === 'failed') ✗ @endif
                                {{ $log->status_label }}
                            </span>
                        </td>
                        <td>
                            <span class="log-entity">
                                <i class="fas {{ $log->recipient_type === 'lead' ? 'fa-user-tag' : 'fa-user' }} mr-1" style="color:var(--accent-400);"></i>
                                {{ ucfirst($log->recipient_type) }}
                                @if($log->recipient_id)
                                    <a href="{{ url('admin/' . ($log->recipient_type === 'lead' ? 'leads' : 'customers') . '/' . $log->recipient_id) }}"
                                       style="font-size:10px;color:var(--accent-600);" title="View profile">
                                        #{{ $log->recipient_id }}
                                    </a>
                                @endif
                            </span>
                            @if($log->follow_up_created)
                                <span class="log-followup-badge ml-1">+FU</span>
                            @endif
                        </td>
                        <td>
                            @if($log->campaign_name)
                                <span class="log-source">{{ $log->campaign_name }}</span>
                            @elseif($log->campaign)
                                <span class="log-source">{{ $log->campaign->name }}</span>
                            @else
                                <span style="color:var(--page-text-muted);font-size:12px;">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="log-date">
                                {{ $log->sent_at ? $log->sent_at->format('d M Y') : '-' }}
                                @if($log->sent_at)
                                    <br><span style="font-size:10px;">{{ $log->sent_at->format('h:i A') }}</span>
                                @endif
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8">
                            <div class="log-empty">
                                <i class="fas fa-inbox"></i>
                                <p>No SMS logs found matching your criteria.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
        <div class="log-pagination">
            {{ $logs->withQueryString()->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
