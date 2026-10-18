@extends('admin.layouts.master')
@section('page_title', 'SMS Analytics')
@section('breadcrumb_parent', 'SMS Centre')
@section('breadcrumb_parent_url', url('admin/sms'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<style>
/* Analytics Dashboard */
.analytics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.analytics-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 12px;
    padding: 20px;
    position: relative;
    overflow: hidden;
    transition: all .2s;
}
.analytics-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
}
.analytics-card .ac-icon {
    width: 44px; height: 44px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; margin-bottom: 12px;
}
.analytics-card .ac-value {
    font-size: 28px; font-weight: 800; letter-spacing: -0.02em;
    line-height: 1; margin-bottom: 4px;
}
.analytics-card .ac-label {
    font-size: 12px; font-weight: 600; color: var(--page-text-muted);
    text-transform: uppercase; letter-spacing: 0.04em;
}
.analytics-card .ac-accent {
    position: absolute; top: 0; right: 0; width: 100px; height: 100px;
    border-radius: 0 0 0 100%; opacity: 0.06;
}

.ac-blue .ac-icon   { background: rgba(59,130,246,0.1); color: #3B82F6; }
.ac-blue .ac-value   { color: #3B82F6; }
.ac-blue .ac-accent  { background: #3B82F6; }

.ac-green .ac-icon  { background: rgba(34,197,94,0.1); color: #22C55E; }
.ac-green .ac-value  { color: #22C55E; }
.ac-green .ac-accent { background: #22C55E; }

.ac-red .ac-icon    { background: rgba(239,68,68,0.1); color: #EF4444; }
.ac-red .ac-value    { color: #EF4444; }
.ac-red .ac-accent   { background: #EF4444; }

.ac-purple .ac-icon { background: rgba(139,92,246,0.1); color: #8B5CF6; }
.ac-purple .ac-value { color: #8B5CF6; }
.ac-purple .ac-accent{ background: #8B5CF6; }

.ac-amber .ac-icon  { background: rgba(245,158,11,0.1); color: #F59E0B; }
.ac-amber .ac-value  { color: #F59E0B; }
.ac-amber .ac-accent { background: #F59E0B; }

.ac-teal .ac-icon   { background: rgba(20,184,166,0.1); color: #14B8A6; }
.ac-teal .ac-value   { color: #14B8A6; }
.ac-teal .ac-accent  { background: #14B8A6; }

/* Messages by Type bars */
.type-bar-wrap { margin-bottom: 12px; }
.type-bar-header { display: flex; justify-content: space-between; margin-bottom: 4px; }
.type-bar-name { font-size: 13px; font-weight: 600; color: var(--page-text); }
.type-bar-count { font-size: 12px; font-weight: 600; color: var(--page-text-muted); }
.type-bar-track { height: 8px; border-radius: 99px; background: var(--gray-100); overflow: hidden; }
.type-bar-fill {
    height: 100%; border-radius: 99px; transition: width .5s ease;
    background: linear-gradient(90deg, var(--accent-400), var(--accent-600));
}

/* Campaign table */
.campaign-row {
    display: flex; align-items: center; padding: 12px 0;
    border-bottom: 1px solid var(--gray-100);
    gap: 12px;
}
.campaign-row:last-child { border-bottom: none; }
.campaign-name { flex: 1; font-weight: 600; font-size: 13px; color: var(--page-text); }
.campaign-stat {
    text-align: center; min-width: 60px;
}
.campaign-stat-val { font-size: 16px; font-weight: 700; }
.campaign-stat-label { font-size: 10px; text-transform: uppercase; color: var(--page-text-muted); font-weight: 600; }
.campaign-date { font-size: 11px; color: var(--page-text-muted); min-width: 90px; text-align: right; }
.campaign-badge {
    padding: 3px 10px; border-radius: 99px; font-size: 10px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.03em;
}

/* Period selector */
.period-selector {
    display: flex; gap: 4px; background: var(--gray-100); border-radius: 8px; padding: 3px;
}
.period-btn {
    padding: 6px 14px; border: none; border-radius: 6px; font-size: 12px;
    font-weight: 600; cursor: pointer; background: transparent;
    color: var(--page-text-muted); transition: all .15s;
}
.period-btn:hover { color: var(--page-text); }
.period-btn.active {
    background: var(--card-bg); color: var(--accent-600);
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

/* Follow-up progress ring */
.fu-progress {
    display: flex; align-items: center; gap: 16px; padding: 16px 0;
}
.fu-ring {
    position: relative; width: 80px; height: 80px;
}
.fu-ring svg { transform: rotate(-90deg); }
.fu-ring-bg { fill: none; stroke: var(--gray-100); stroke-width: 6; }
.fu-ring-fill { fill: none; stroke: var(--success-500); stroke-width: 6; stroke-linecap: round; transition: stroke-dashoffset .6s; }
.fu-ring-text {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
    font-size: 16px; font-weight: 800; color: var(--success-600);
}
.fu-stats { flex: 1; }
.fu-stat-row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 13px; }
.fu-stat-label { color: var(--page-text-muted); font-weight: 500; }
.fu-stat-value { font-weight: 700; }
</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-chart-line" style="color: var(--accent-500); margin-right: 8px;"></i>SMS Analytics</h1>
            <p>Campaign performance and delivery insights</p>
        </div>
        <div class="header-actions" style="display:flex;gap:8px;align-items:center;">
            {{-- Period Selector --}}
            <form method="GET" id="periodForm">
                <div class="period-selector">
                    @foreach(['7_days' => '7D', '30_days' => '30D', '90_days' => '90D', '12_months' => '12M'] as $val => $label)
                        <button type="submit" name="period" value="{{ $val }}"
                            class="period-btn {{ $period == $val ? 'active' : '' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </form>
            <a href="{{ url('admin/sms-log') }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-list mr-1"></i>View Logs</a>
            <a href="{{ url('admin/sms') }}" class="btn btn-primary btn-sm"><i class="fas fa-comment-dots mr-1"></i>SMS Centre</a>
        </div>
    </div>

    @include('admin.alert_message')

    {{-- Stats Grid --}}
    <div class="analytics-grid crm-animate-in delay-1">
        <div class="analytics-card ac-blue">
            <div class="ac-accent"></div>
            <div class="ac-icon"><i class="fas fa-paper-plane"></i></div>
            <div class="ac-value">{{ number_format($data['total_sent']) }}</div>
            <div class="ac-label">Total Sent</div>
        </div>
        <div class="analytics-card ac-green">
            <div class="ac-accent"></div>
            <div class="ac-icon"><i class="fas fa-check-circle"></i></div>
            <div class="ac-value">{{ number_format($data['successful_sent']) }}</div>
            <div class="ac-label">Delivered</div>
        </div>
        <div class="analytics-card ac-red">
            <div class="ac-accent"></div>
            <div class="ac-icon"><i class="fas fa-times-circle"></i></div>
            <div class="ac-value">{{ number_format($data['failed_sent']) }}</div>
            <div class="ac-label">Failed</div>
        </div>
        <div class="analytics-card ac-purple">
            <div class="ac-accent"></div>
            <div class="ac-icon"><i class="fas fa-percentage"></i></div>
            <div class="ac-value">{{ $data['delivery_rate'] }}%</div>
            <div class="ac-label">Delivery Rate</div>
        </div>
        <div class="analytics-card ac-amber">
            <div class="ac-accent"></div>
            <div class="ac-icon"><i class="fas fa-bullhorn"></i></div>
            <div class="ac-value">{{ number_format($data['bulk_campaigns']) }}</div>
            <div class="ac-label">Campaigns</div>
        </div>
        <div class="analytics-card ac-teal">
            <div class="ac-accent"></div>
            <div class="ac-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="ac-value">{{ number_format($data['follow_ups_created'] ?? 0) }}</div>
            <div class="ac-label">Follow-ups Created</div>
        </div>
    </div>

    <div class="row">
        {{-- Messages by Type --}}
        <div class="col-lg-5">
            <div class="crm-info-card crm-animate-in delay-2" style="margin-bottom:20px;">
                <div class="crm-info-card-header">
                    <div style="display:flex;align-items:center;gap:var(--space-3);">
                        <div class="info-icon" style="background:var(--accent-500);"><i class="fas fa-chart-pie"></i></div>
                        <h6>Messages by Type</h6>
                    </div>
                </div>
                <div class="crm-info-card-body" style="padding:20px;">
                    @php $typeTotal = array_sum($data['messages_by_type']) ?: 1; @endphp
                    @php $typeColors = ['bulk' => '#3B82F6', 'single' => '#8B5CF6', 'automation' => '#F59E0B', 'transactional' => '#14B8A6']; @endphp
                    @forelse($data['messages_by_type'] as $type => $count)
                        <div class="type-bar-wrap">
                            <div class="type-bar-header">
                                <span class="type-bar-name">
                                    <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:{{ $typeColors[$type] ?? '#6B7280' }};margin-right:6px;"></span>
                                    {{ ucfirst($type) }}
                                </span>
                                <span class="type-bar-count">{{ number_format($count) }} ({{ round(($count / $typeTotal) * 100, 1) }}%)</span>
                            </div>
                            <div class="type-bar-track">
                                <div class="type-bar-fill" style="width:{{ ($count / $typeTotal) * 100 }}%;background:{{ $typeColors[$type] ?? '#6B7280' }};"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted text-center" style="font-size:13px;">No data for this period.</p>
                    @endforelse
                </div>
            </div>

            {{-- Follow-up Progress --}}
            <div class="crm-info-card crm-animate-in delay-3" style="margin-bottom:20px;">
                <div class="crm-info-card-header">
                    <div style="display:flex;align-items:center;gap:var(--space-3);">
                        <div class="info-icon" style="background:var(--success-500);"><i class="fas fa-phone-alt"></i></div>
                        <h6>Follow-up Completion</h6>
                    </div>
                </div>
                <div class="crm-info-card-body" style="padding:20px;">
                    @php
                        $fuTotal = ($data['follow_ups_pending'] ?? 0) + ($data['follow_ups_completed'] ?? 0);
                        $fuPct = $fuTotal > 0 ? round(($data['follow_ups_completed'] ?? 0) / $fuTotal * 100) : 0;
                        $circumference = 2 * 3.14159 * 32;
                        $dashoffset = $circumference * (1 - $fuPct / 100);
                    @endphp
                    <div class="fu-progress">
                        <div class="fu-ring">
                            <svg width="80" height="80">
                                <circle class="fu-ring-bg" cx="40" cy="40" r="32"></circle>
                                <circle class="fu-ring-fill" cx="40" cy="40" r="32"
                                    stroke-dasharray="{{ $circumference }}"
                                    stroke-dashoffset="{{ $dashoffset }}"></circle>
                            </svg>
                            <span class="fu-ring-text">{{ $fuPct }}%</span>
                        </div>
                        <div class="fu-stats">
                            <div class="fu-stat-row">
                                <span class="fu-stat-label"><i class="fas fa-circle mr-1" style="color:var(--success-500);font-size:8px;"></i>Completed</span>
                                <span class="fu-stat-value" style="color:var(--success-600);">{{ $data['follow_ups_completed'] ?? 0 }}</span>
                            </div>
                            <div class="fu-stat-row">
                                <span class="fu-stat-label"><i class="fas fa-circle mr-1" style="color:var(--warning-500);font-size:8px;"></i>Pending</span>
                                <span class="fu-stat-value" style="color:var(--warning-600);">{{ $data['follow_ups_pending'] ?? 0 }}</span>
                            </div>
                            <div class="fu-stat-row" style="border-top:1px solid var(--gray-100);margin-top:4px;padding-top:4px;">
                                <span class="fu-stat-label">Total</span>
                                <span class="fu-stat-value">{{ $fuTotal }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent Campaigns --}}
        <div class="col-lg-7">
            <div class="crm-info-card crm-animate-in delay-2" style="margin-bottom:20px;">
                <div class="crm-info-card-header">
                    <div style="display:flex;align-items:center;gap:var(--space-3);">
                        <div class="info-icon" style="background:var(--warning-500);"><i class="fas fa-bullhorn"></i></div>
                        <h6>Recent Campaigns</h6>
                        <span style="background:var(--warning-50);color:var(--warning-600);font-size:11px;font-weight:600;padding:2px 10px;border-radius:20px;">
                            {{ $campaigns->count() }}
                        </span>
                    </div>
                </div>
                <div class="crm-info-card-body" style="padding:12px 20px;">
                    @forelse($campaigns as $campaign)
                        <div class="campaign-row">
                            <div class="campaign-name">
                                <i class="fas fa-envelope mr-1" style="color:var(--accent-400);"></i>
                                {{ $campaign->name }}
                                @if($campaign->create_follow_up)
                                    <span style="display:inline-block;margin-left:4px;background:var(--success-100);color:var(--success-700);font-size:9px;font-weight:700;padding:1px 6px;border-radius:4px;">+FU</span>
                                @endif
                            </div>
                            <div class="campaign-stat">
                                <div class="campaign-stat-val" style="color:var(--page-text);">{{ $campaign->total_recipients }}</div>
                                <div class="campaign-stat-label">Recipients</div>
                            </div>
                            <div class="campaign-stat">
                                <div class="campaign-stat-val" style="color:var(--success-600);">{{ $campaign->sent_count }}</div>
                                <div class="campaign-stat-label">Sent</div>
                            </div>
                            <div class="campaign-stat">
                                <div class="campaign-stat-val" style="color:var(--danger-600);">{{ $campaign->failed_count }}</div>
                                <div class="campaign-stat-label">Failed</div>
                            </div>
                            <div>
                                @php
                                    $statusVal = $campaign->status instanceof \App\Enums\SmsCampaignStatus ? $campaign->status->value : $campaign->status;
                                    $statusColors = ['completed'=>'background:var(--success-100);color:var(--success-700);','sending'=>'background:var(--info-100,#DBEAFE);color:var(--info-600,#2563EB);','draft'=>'background:var(--gray-100);color:var(--page-text-muted);','failed'=>'background:var(--danger-100);color:var(--danger-700);'];
                                @endphp
                                <span class="campaign-badge" style="{{ $statusColors[$statusVal] ?? 'background:var(--gray-100);color:var(--page-text-muted);' }}">
                                    {{ ucfirst($statusVal) }}
                                </span>
                            </div>
                            <div class="campaign-date">{{ $campaign->created_at->format('d M Y') }}</div>
                        </div>
                    @empty
                        <div style="text-align:center;padding:30px;color:var(--page-text-muted);">
                            <i class="fas fa-bullhorn" style="font-size:28px;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                            No campaigns yet
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
