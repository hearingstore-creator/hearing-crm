@extends('admin.layouts.master')
@section('page_title', 'Membership Tiers')
@section('breadcrumb_parent', 'Membership & Rewards')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<style>
    /* Tier Cards */
    .tier-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }

    .tier-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 16px;
        overflow: hidden;
        transition: all 0.3s ease;
        position: relative;
    }

    .tier-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
    }

    .tier-card-header {
        padding: 24px 24px 20px;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .tier-card-header::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        opacity: 0.08;
        background: radial-gradient(circle at top right, currentColor 0%, transparent 70%);
    }

    .tier-icon {
        width: 56px;
        height: 56px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        margin: 0 auto 12px;
        position: relative;
        z-index: 1;
    }

    .tier-card-header h3 {
        font-size: 20px;
        font-weight: 700;
        margin: 0;
        letter-spacing: -0.02em;
        position: relative;
        z-index: 1;
    }

    .tier-card-header .tier-range {
        font-size: 12px;
        margin-top: 6px;
        opacity: 0.7;
        position: relative;
        z-index: 1;
    }

    /* Tier-specific colors */
    .tier-bronze .tier-card-header { color: #92400e; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); }
    .tier-bronze .tier-icon { background: #92400e; color: #fff; }

    .tier-silver .tier-card-header { color: #475569; background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%); }
    .tier-silver .tier-icon { background: #64748b; color: #fff; }

    .tier-gold .tier-card-header { color: #92400e; background: linear-gradient(135deg, #fef3c7 0%, #fbbf24 50%, #f59e0b 100%); }
    .tier-gold .tier-icon { background: linear-gradient(135deg, #d97706, #b45309); color: #fff; }

    .tier-platinum .tier-card-header { color: #312e81; background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 50%, #a5b4fc 100%); }
    .tier-platinum .tier-icon { background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff; }

    .tier-default .tier-card-header { color: #374151; background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%); }
    .tier-default .tier-icon { background: #6b7280; color: #fff; }

    /* Tier Stats */
    .tier-stats {
        padding: 20px 24px;
    }

    .tier-stat-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid var(--gray-100);
    }

    .tier-stat-row:last-child {
        border-bottom: none;
    }

    .tier-stat-label {
        font-size: 12px;
        font-weight: 500;
        color: var(--page-text-muted);
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .tier-stat-label i {
        font-size: 11px;
        width: 16px;
        text-align: center;
    }

    .tier-stat-value {
        font-size: 14px;
        font-weight: 600;
        color: var(--page-text);
    }

    .tier-stat-value.earn-rate {
        color: var(--success-600);
        background: var(--success-50);
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 13px;
    }

    .tier-stat-value.referral-pts {
        color: var(--info-600);
        background: var(--info-50);
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 13px;
    }

    .tier-stat-value .count-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 28px;
        height: 24px;
        padding: 0 8px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
    }

    .count-badge.customers { background: var(--gray-100); color: var(--gray-700); }
    .count-badge.wallets { background: var(--success-50); color: var(--success-700); }
    .count-badge.points { background: var(--accent-50); color: var(--accent-700); }

    /* Info Panel */
    .info-panel {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 12px;
        padding: 0;
        overflow: hidden;
        margin-top: 20px;
    }

    .info-panel-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 20px;
        background: var(--info-50);
        border-bottom: 1px solid var(--info-100);
    }

    .info-panel-header i {
        color: var(--info-600);
        font-size: 16px;
    }

    .info-panel-header h6 {
        margin: 0;
        font-size: 14px;
        font-weight: 600;
        color: var(--info-700);
    }

    .info-panel-body {
        padding: 16px 20px;
    }

    .info-panel-body ul {
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .info-panel-body li {
        padding: 6px 0;
        font-size: 13px;
        color: var(--page-text-secondary);
        display: flex;
        align-items: flex-start;
        gap: 8px;
        line-height: 1.5;
    }

    .info-panel-body li::before {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: var(--accent-400);
        flex-shrink: 0;
        margin-top: 7px;
    }
</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-crown" style="color: var(--warning-500); margin-right: 8px;"></i>Membership Tiers</h1>
            <p>Manage reward tiers and point earning structures</p>
        </div>
    </div>

    @include('admin.alert_message')

    {{-- Tier Cards Grid --}}
    <div class="tier-grid crm-animate-in delay-1">
        @foreach ($tiers as $tier)
        @php
            $tierClass = 'tier-default';
            if(strtolower($tier->name) == 'silver') $tierClass = 'tier-silver';
            elseif(strtolower($tier->name) == 'gold') $tierClass = 'tier-gold';
            elseif(strtolower($tier->name) == 'platinum') $tierClass = 'tier-platinum';
            elseif(strtolower($tier->name) == 'bronze') $tierClass = 'tier-bronze';
        @endphp
        <div class="tier-card {{ $tierClass }}">
            <div class="tier-card-header">
                <div class="tier-icon"><i class="fas fa-crown"></i></div>
                <h3>{{ $tier->name }}</h3>
                <div class="tier-range">
                    ৳{{ number_format($tier->min_purchase, 0) }}
                    —
                    @if($tier->max_purchase)
                        ৳{{ number_format($tier->max_purchase, 0) }}
                    @else
                        ∞
                    @endif
                </div>
            </div>
            <div class="tier-stats">
                <div class="tier-stat-row">
                    <span class="tier-stat-label"><i class="fas fa-percentage"></i> Earn Rate</span>
                    <span class="tier-stat-value earn-rate">{{ $tier->earn_percentage }}%</span>
                </div>
                <div class="tier-stat-row">
                    <span class="tier-stat-label"><i class="fas fa-gift"></i> Referral Reward</span>
                    <span class="tier-stat-value referral-pts">{{ number_format($tier->referral_reward_points) }} pts</span>
                </div>
                <div class="tier-stat-row">
                    <span class="tier-stat-label"><i class="fas fa-users"></i> Customers</span>
                    <span class="tier-stat-value"><span class="count-badge customers">{{ $tier->customers_count }}</span></span>
                </div>
                <div class="tier-stat-row">
                    <span class="tier-stat-label"><i class="fas fa-wallet"></i> Active Wallets</span>
                    <span class="tier-stat-value"><span class="count-badge wallets">{{ $tier->active_wallets ?? 0 }}</span></span>
                </div>
                <div class="tier-stat-row">
                    <span class="tier-stat-label"><i class="fas fa-coins"></i> Points Issued</span>
                    <span class="tier-stat-value"><span class="count-badge points">{{ number_format($tier->total_points_issued ?? 0) }}</span></span>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Info Panel --}}
    <div class="info-panel crm-animate-in delay-2">
        <div class="info-panel-header">
            <i class="fas fa-lightbulb"></i>
            <h6>How Points Work</h6>
        </div>
        <div class="info-panel-body">
            <ul>
                <li>Customers earn points based on their membership tier's earn percentage</li>
                <li>Points expire after 12 months from earning date</li>
                <li>Maximum 30% of invoice can be paid using points</li>
                <li>Tiers automatically upgrade based on cumulative purchases</li>
                <li>Referral rewards are based on referrer's tier level</li>
            </ul>
        </div>
    </div>

</div>
@endsection
