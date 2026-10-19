@extends('admin.layouts.master')
@section('page_title', 'Membership Tiers')
@section('breadcrumb_parent', 'Membership & Rewards')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<style>
    /* =========================================================
       BOARD VALUATION & METRICS SUMMARY STRIP
       ========================================================= */
    .valuation-summary-strip {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .valuation-kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 16px 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        transition: all 0.25s ease;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
    }

    .valuation-kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
    }

    .valuation-kpi-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }

    .valuation-kpi-content {
        flex: 1;
        min-width: 0;
    }

    .valuation-kpi-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 2px;
    }

    .valuation-kpi-value {
        font-size: 18px;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.2;
    }

    .valuation-kpi-sub {
        font-size: 11px;
        color: #94a3b8;
        margin-top: 2px;
    }

    /* =========================================================
       PREMIUM TIER CARDS GRID (MINIMAL & LUXURY)
       ========================================================= */
    .tier-board-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 24px;
        margin-bottom: 28px;
    }

    @media (max-width: 1280px) {
        .tier-board-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 640px) {
        .tier-board-grid {
            grid-template-columns: 1fr;
        }
    }

    .tier-luxury-card {
        border-radius: 20px;
        position: relative;
        overflow: hidden;
        transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 460px;
    }

    .tier-luxury-card:hover {
        transform: translateY(-8px);
    }

    /* Card Top Bar */
    .tier-card-top-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 22px 24px 10px;
        position: relative;
        z-index: 2;
    }

    .tier-index-chip {
        font-size: 10px;
        font-weight: 800;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        padding: 4px 10px;
        border-radius: 100px;
    }

    .tier-privilege-badge {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        padding: 4px 12px;
        border-radius: 100px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    /* Card Emblem & Title */
    .tier-emblem-wrap {
        text-align: center;
        padding: 10px 24px 16px;
        position: relative;
        z-index: 2;
    }

    .tier-emblem-circle {
        width: 68px;
        height: 68px;
        border-radius: 50%;
        margin: 0 auto 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        position: relative;
        transition: transform 0.3s ease;
    }

    .tier-luxury-card:hover .tier-emblem-circle {
        transform: scale(1.08) rotate(2deg);
    }

    .tier-emblem-wrap h3 {
        font-size: 24px;
        font-weight: 800;
        margin: 0 0 4px;
        letter-spacing: -0.02em;
    }

    .tier-spend-limit-box {
        margin: 8px auto 0;
        padding: 8px 16px;
        border-radius: 12px;
        display: inline-block;
    }

    .tier-spend-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-weight: 700;
        opacity: 0.8;
        display: block;
    }

    .tier-spend-amount {
        font-size: 17px;
        font-weight: 800;
        letter-spacing: -0.01em;
    }

    /* Benefits Strip inside Card */
    .tier-benefits-strip {
        padding: 16px 20px;
        margin: 0 16px;
        border-radius: 14px;
        position: relative;
        z-index: 2;
    }

    .benefit-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 7px 0;
        font-size: 12.5px;
    }

    .benefit-item:not(:last-child) {
        border-bottom: 1px dashed rgba(0, 0, 0, 0.08);
    }

    .benefit-label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 600;
        opacity: 0.85;
    }

    .benefit-value {
        font-weight: 700;
    }

    .benefit-value-pill {
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 700;
    }

    /* Live Card Metrics (Bottom) */
    .tier-card-metrics {
        padding: 16px 20px 22px;
        position: relative;
        z-index: 2;
    }

    .metrics-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        text-align: center;
    }

    .metric-col {
        padding: 10px 6px;
        border-radius: 10px;
    }

    .metric-val {
        font-size: 16px;
        font-weight: 800;
        display: block;
        line-height: 1.2;
    }

    .metric-lbl {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-weight: 600;
        opacity: 0.75;
        margin-top: 2px;
        display: block;
    }

    /* =========================================================
       SPECIFIC CARD THEMES: SILVER, BRONZE, GOLD, PLATINUM
       ========================================================= */

    /* 1. SILVER (Frosted Brushed Platinum) */
    .tier-theme-silver {
        background: linear-gradient(165deg, #ffffff 0%, #f8fafc 40%, #e2e8f0 100%);
        border: 1px solid #cbd5e1;
        box-shadow: 0 10px 30px rgba(100, 116, 139, 0.08), inset 0 1px 1px #ffffff;
        color: #1e293b;
    }
    .tier-theme-silver:hover {
        box-shadow: 0 18px 40px rgba(100, 116, 139, 0.18), inset 0 1px 1px #ffffff;
        border-color: #94a3b8;
    }
    .tier-theme-silver .tier-index-chip {
        background: #e2e8f0;
        color: #475569;
    }
    .tier-theme-silver .tier-privilege-badge {
        background: #f1f5f9;
        color: #334155;
        border: 1px solid #cbd5e1;
    }
    .tier-theme-silver .tier-emblem-circle {
        background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
        color: #ffffff;
        box-shadow: 0 8px 22px rgba(100, 116, 139, 0.3), inset 0 2px 2px rgba(255,255,255,0.4);
    }
    .tier-theme-silver .tier-spend-limit-box {
        background: rgba(255, 255, 255, 0.8);
        border: 1px solid #cbd5e1;
        color: #1e293b;
    }
    .tier-theme-silver .tier-benefits-strip {
        background: rgba(255, 255, 255, 0.7);
        border: 1px solid #e2e8f0;
    }
    .tier-theme-silver .benefit-value-pill {
        background: #e2e8f0;
        color: #0f172a;
    }
    .tier-theme-silver .metric-col {
        background: rgba(255, 255, 255, 0.6);
        border: 1px solid #e2e8f0;
    }

    /* 2. BRONZE (Warm Brushed Copper) */
    .tier-theme-bronze {
        background: linear-gradient(165deg, #ffffff 0%, #fff7ed 40%, #fed7aa 100%);
        border: 1px solid #fdba74;
        box-shadow: 0 10px 30px rgba(194, 65, 12, 0.08), inset 0 1px 1px #ffffff;
        color: #7c2d12;
    }
    .tier-theme-bronze:hover {
        box-shadow: 0 18px 40px rgba(194, 65, 12, 0.18), inset 0 1px 1px #ffffff;
        border-color: #f97316;
    }
    .tier-theme-bronze .tier-index-chip {
        background: #fed7aa;
        color: #9a3412;
    }
    .tier-theme-bronze .tier-privilege-badge {
        background: #ffedd5;
        color: #7c2d12;
        border: 1px solid #fdba74;
    }
    .tier-theme-bronze .tier-emblem-circle {
        background: linear-gradient(135deg, #ea580c 0%, #9a3412 100%);
        color: #ffffff;
        box-shadow: 0 8px 22px rgba(194, 65, 12, 0.35), inset 0 2px 2px rgba(255,255,255,0.4);
    }
    .tier-theme-bronze .tier-spend-limit-box {
        background: rgba(255, 255, 255, 0.8);
        border: 1px solid #fed7aa;
        color: #7c2d12;
    }
    .tier-theme-bronze .tier-benefits-strip {
        background: rgba(255, 255, 255, 0.7);
        border: 1px solid #fed7aa;
    }
    .tier-theme-bronze .benefit-value-pill {
        background: #ffedd5;
        color: #9a3412;
    }
    .tier-theme-bronze .metric-col {
        background: rgba(255, 255, 255, 0.6);
        border: 1px solid #fed7aa;
    }

    /* 3. GOLD (Champagne Bullion 24K) */
    .tier-theme-gold {
        background: linear-gradient(165deg, #ffffff 0%, #fefce8 40%, #fef08a 100%);
        border: 1px solid #fde047;
        box-shadow: 0 10px 32px rgba(202, 138, 4, 0.12), inset 0 1px 1px #ffffff;
        color: #713f12;
    }
    .tier-theme-gold:hover {
        box-shadow: 0 20px 44px rgba(202, 138, 4, 0.22), inset 0 1px 1px #ffffff;
        border-color: #eab308;
    }
    .tier-theme-gold .tier-index-chip {
        background: #fef08a;
        color: #854d0e;
    }
    .tier-theme-gold .tier-privilege-badge {
        background: #fef9c3;
        color: #713f12;
        border: 1px solid #fde047;
    }
    .tier-theme-gold .tier-emblem-circle {
        background: linear-gradient(135deg, #f59e0b 0%, #b45309 100%);
        color: #ffffff;
        box-shadow: 0 8px 24px rgba(217, 119, 6, 0.38), inset 0 2px 2px rgba(255,255,255,0.45);
    }
    .tier-theme-gold .tier-spend-limit-box {
        background: rgba(255, 255, 255, 0.85);
        border: 1px solid #fde047;
        color: #713f12;
    }
    .tier-theme-gold .tier-benefits-strip {
        background: rgba(255, 255, 255, 0.75);
        border: 1px solid #fef08a;
    }
    .tier-theme-gold .benefit-value-pill {
        background: #fef08a;
        color: #854d0e;
    }
    .tier-theme-gold .metric-col {
        background: rgba(255, 255, 255, 0.65);
        border: 1px solid #fef08a;
    }

    /* 4. PLATINUM (Midnight Obsidian & Titanium VIP) */
    .tier-theme-platinum {
        background: linear-gradient(165deg, #090d16 0%, #111827 50%, #1e1b4b 100%);
        border: 1px solid #6366f1;
        box-shadow: 0 12px 36px rgba(15, 23, 42, 0.35), inset 0 1px 1px rgba(255, 255, 255, 0.15);
        color: #f8fafc;
    }
    .tier-theme-platinum:hover {
        box-shadow: 0 22px 50px rgba(99, 102, 241, 0.35), inset 0 1px 1px rgba(255, 255, 255, 0.25);
        border-color: #818cf8;
    }
    .tier-theme-platinum .tier-index-chip {
        background: rgba(99, 102, 241, 0.25);
        color: #c7d2fe;
        border: 1px solid rgba(99, 102, 241, 0.4);
    }
    .tier-theme-platinum .tier-privilege-badge {
        background: rgba(255, 255, 255, 0.1);
        color: #e0e7ff;
        border: 1px solid rgba(165, 180, 252, 0.3);
    }
    .tier-theme-platinum .tier-emblem-circle {
        background: linear-gradient(135deg, #818cf8 0%, #4338ca 100%);
        color: #ffffff;
        box-shadow: 0 8px 24px rgba(99, 102, 241, 0.5), inset 0 2px 2px rgba(255,255,255,0.4);
    }
    .tier-theme-platinum .tier-spend-limit-box {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.15);
        color: #ffffff;
    }
    .tier-theme-platinum .tier-spend-label {
        color: #94a3b8;
    }
    .tier-theme-platinum .tier-spend-amount {
        color: #38bdf8;
    }
    .tier-theme-platinum .tier-benefits-strip {
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(255, 255, 255, 0.12);
        color: #f1f5f9;
    }
    .tier-theme-platinum .benefit-item:not(:last-child) {
        border-bottom-color: rgba(255, 255, 255, 0.1);
    }
    .tier-theme-platinum .benefit-label {
        color: #cbd5e1;
    }
    .tier-theme-platinum .benefit-value-pill {
        background: rgba(99, 102, 241, 0.3);
        color: #e0e7ff;
        border: 1px solid rgba(129, 140, 248, 0.3);
    }
    .tier-theme-platinum .metric-col {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .tier-theme-platinum .metric-val {
        color: #ffffff;
    }
    .tier-theme-platinum .metric-lbl {
        color: #94a3b8;
    }

    /* Fallback default theme */
    .tier-theme-default {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
        color: #1e293b;
    }
    .tier-theme-default .tier-emblem-circle {
        background: linear-gradient(135deg, #64748b, #334155);
        color: #fff;
    }
    .tier-theme-default .tier-benefits-strip,
    .tier-theme-default .metric-col {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }

    /* Policy & Rules Card */
    .policy-rules-panel {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        overflow: hidden;
        margin-top: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
    }

    .policy-rules-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 24px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }

    .policy-rules-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 700;
        font-size: 14px;
        color: #0f172a;
        margin: 0;
    }

    .policy-rules-body {
        padding: 20px 24px;
    }

    .rules-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 16px;
    }

    .rule-box {
        display: flex;
        gap: 12px;
        align-items: flex-start;
        padding: 12px 14px;
        background: #f8fafc;
        border-radius: 10px;
        border: 1px solid #edf2f7;
    }

    .rule-box i {
        font-size: 16px;
        color: #3b82f6;
        margin-top: 2px;
        flex-shrink: 0;
    }

    .rule-box-title {
        font-size: 13px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 2px;
    }

    .rule-box-desc {
        font-size: 12px;
        color: #64748b;
        line-height: 1.45;
        margin: 0;
    }
</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div class="page-title-group">
            <h1><i class="fas fa-crown" style="color: #f59e0b; margin-right: 8px;"></i>Membership Tiers Board</h1>
            <p>Qualification thresholds, earning multipliers & loyalty point valuation</p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            @if(\App\Classes\Helper::hasPermission('loyalty_settings.index') && (!auth()->user()->branchId() || auth()->user()->isSuperAdmin()))
            <a href="{{ route('membership.settings') }}" class="btn btn-outline-secondary" style="border-radius: 8px; font-weight: 600;">
                <i class="fas fa-sliders-h mr-1"></i> Point Valuation & Rules
            </a>
            @endif

            @if(\App\Classes\Helper::hasPermission('membership_tiers.edit') && (!auth()->user()->branchId() || auth()->user()->isSuperAdmin()))
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#editTiersModal" style="border-radius: 8px; font-weight: 600; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.25);">
                <i class="fas fa-edit mr-1"></i> Edit Tiers
            </button>
            @endif
        </div>
    </div>

    @include('admin.alert_message')

    @php
        $setting = $loyaltySetting ?? \App\Models\LoyaltySetting::current();
        $pointVal = (float) ($setting->point_value ?? 1.00);
    @endphp

    {{-- Executive Valuation & Rules Board Strip --}}
    <div class="valuation-summary-strip crm-animate-in">
        <div class="valuation-kpi-card">
            <div class="valuation-kpi-icon" style="background: #eff6ff; color: #3b82f6;">
                <i class="fas fa-coins"></i>
            </div>
            <div class="valuation-kpi-content">
                <div class="valuation-kpi-label">Point Valuation</div>
                <div class="valuation-kpi-value">1 pt = ৳{{ number_format($pointVal, 2) }} BDT</div>
                <div class="valuation-kpi-sub">Redemption exchange rate</div>
            </div>
        </div>

        <div class="valuation-kpi-card">
            <div class="valuation-kpi-icon" style="background: #fefce8; color: #ca8a04;">
                <i class="fas fa-clock"></i>
            </div>
            <div class="valuation-kpi-content">
                <div class="valuation-kpi-label">Point Validity</div>
                <div class="valuation-kpi-value">{{ $setting->expiry_months ?? 12 }} Months</div>
                <div class="valuation-kpi-sub">From the date of award</div>
            </div>
        </div>

        <div class="valuation-kpi-card">
            <div class="valuation-kpi-icon" style="background: #f0fdf4; color: #16a34a;">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="valuation-kpi-content">
                <div class="valuation-kpi-label">Redeem Threshold</div>
                <div class="valuation-kpi-value">Min {{ number_format($setting->min_redeem_points ?? 0) }} pts</div>
                <div class="valuation-kpi-sub">Required wallet balance</div>
            </div>
        </div>

        <div class="valuation-kpi-card">
            <div class="valuation-kpi-icon" style="background: #faf5ff; color: #9333ea;">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="valuation-kpi-content">
                <div class="valuation-kpi-label">Invoice Coverage</div>
                <div class="valuation-kpi-value">Up to {{ number_format($setting->max_redeem_percent ?? 100, 0) }}%</div>
                <div class="valuation-kpi-sub">Max bill share payable by pts</div>
            </div>
        </div>
    </div>

    {{-- Premium 4 Tier Cards Grid --}}
    <div class="tier-board-grid crm-animate-in delay-1">
        @foreach ($tiers as $index => $tier)
        @php
            $tierNameLower = strtolower(trim($tier->name));
            $tierTheme = 'tier-theme-default';
            $privilegeTitle = 'Standard Member';
            $tierIndexNumber = sprintf('%02d', $index + 1);

            if ($tierNameLower === 'silver') {
                $tierTheme = 'tier-theme-silver';
                $privilegeTitle = 'Silver Club';
                $tierIcon = 'fas fa-shield-alt';
            } elseif ($tierNameLower === 'bronze') {
                $tierTheme = 'tier-theme-bronze';
                $privilegeTitle = 'Bronze Select';
                $tierIcon = 'fas fa-award';
            } elseif ($tierNameLower === 'gold') {
                $tierTheme = 'tier-theme-gold';
                $privilegeTitle = 'Gold Elite';
                $tierIcon = 'fas fa-crown';
            } elseif ($tierNameLower === 'platinum') {
                $tierTheme = 'tier-theme-platinum';
                $privilegeTitle = 'Prestige Black';
                $tierIcon = 'fas fa-gem';
            } else {
                $tierIcon = 'fas fa-crown';
            }
        @endphp

        <div class="tier-luxury-card {{ $tierTheme }}">
            {{-- Top Bar --}}
            <div class="tier-card-top-bar">
                <span class="tier-index-chip">TIER {{ $tierIndexNumber }}</span>
                <span class="tier-privilege-badge">
                    <i class="{{ $tierIcon }}"></i> {{ $privilegeTitle }}
                </span>
            </div>

            {{-- Emblem & Title --}}
            <div class="tier-emblem-wrap">
                <div class="tier-emblem-circle">
                    <i class="{{ $tierIcon }}"></i>
                </div>
                <h3>{{ $tier->name }}</h3>

                <div class="tier-spend-limit-box">
                    <span class="tier-spend-label">Lifetime Spend Qualification</span>
                    <span class="tier-spend-amount">
                        @if($tier->min_purchase == 0 && $tier->max_purchase)
                            Up to ৳{{ number_format($tier->max_purchase, 0) }} BDT
                        @elseif($tier->max_purchase)
                            ৳{{ number_format($tier->min_purchase, 0) }} – ৳{{ number_format($tier->max_purchase, 0) }} BDT
                        @else
                            Starts from ৳{{ number_format($tier->min_purchase, 0) }} BDT
                        @endif
                    </span>
                </div>
            </div>

            {{-- Benefits Strip --}}
            <div class="tier-benefits-strip">
                <div class="benefit-item">
                    <span class="benefit-label"><i class="fas fa-percentage"></i> Earn Rate</span>
                    <span class="benefit-value-pill">{{ $tier->earn_percentage }}% Cashback</span>
                </div>
                <div class="benefit-item">
                    <span class="benefit-label"><i class="fas fa-coins"></i> Point Valuation</span>
                    <span class="benefit-value">1 pt = ৳{{ number_format((float) ($tier->point_value > 0 ? $tier->point_value : $pointVal), 2) }}</span>
                </div>
                <div class="benefit-item">
                    <span class="benefit-label"><i class="fas fa-user-friends"></i> Referral Bonus</span>
                    <span class="benefit-value">+{{ number_format($tier->referral_reward_points) }} pts</span>
                </div>
            </div>

            {{-- Live Operational Metrics --}}
            <div class="tier-card-metrics">
                <div class="metrics-row">
                    <div class="metric-col">
                        <span class="metric-val">{{ $tier->customers_count }}</span>
                        <span class="metric-lbl">Patients</span>
                    </div>
                    <div class="metric-col">
                        <span class="metric-val">{{ $tier->active_wallets ?? 0 }}</span>
                        <span class="metric-lbl">Wallets</span>
                    </div>
                    <div class="metric-col">
                        <span class="metric-val">{{ number_format($tier->total_points_issued ?? 0) }}</span>
                        <span class="metric-lbl">Points</span>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Policy & Governance Board Panel --}}
    <div class="policy-rules-panel crm-animate-in delay-2">
        <div class="policy-rules-header">
            <h6 class="policy-rules-title">
                <i class="fas fa-shield-alt text-primary"></i> Membership & Loyalty Governance Rules
            </h6>
            <span class="badge badge-light" style="font-size: 11px; padding: 6px 10px; font-weight: 600;">
                Live Policy v2.0
            </span>
        </div>
        <div class="policy-rules-body">
            <div class="rules-grid">
                <div class="rule-box">
                    <i class="fas fa-chart-line"></i>
                    <div>
                        <div class="rule-box-title">Automatic Tier Re-Evaluation</div>
                        <p class="rule-box-desc">Patients are automatically upgraded to higher tiers upon completing sales that cross the cumulative lifetime spend thresholds.</p>
                    </div>
                </div>

                <div class="rule-box">
                    <i class="fas fa-calculator"></i>
                    <div>
                        <div class="rule-box-title">Point Valuation & Spending</div>
                        <p class="rule-box-desc">Every 1 point is worth exactly <strong>৳{{ number_format($pointVal, 2) }} BDT</strong>. Points can pay up to <strong>{{ number_format($setting->max_redeem_percent ?? 100, 0) }}%</strong> of an invoice.</p>
                    </div>
                </div>

                <div class="rule-box">
                    <i class="fas fa-hourglass-half"></i>
                    <div>
                        <div class="rule-box-title">Expiry & Wallet Balance</div>
                        <p class="rule-box-desc">Points expire after <strong>{{ $setting->expiry_months ?? 12 }} months</strong>. A minimum balance of <strong>{{ number_format($setting->min_redeem_points ?? 0) }} pts</strong> is required to unlock redemption.</p>
                    </div>
                </div>

                <div class="rule-box">
                    <i class="fas fa-gift"></i>
                    <div>
                        <div class="rule-box-title">Milestones & Referral Rewards</div>
                        <p class="rule-box-desc">Referrals and lifetime spending milestones award instant one-time bonus points credited directly to patient reward ledgers.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(\App\Classes\Helper::hasPermission('membership_tiers.edit') && (!auth()->user()->branchId() || auth()->user()->isSuperAdmin()))
    {{-- Edit Tiers Modal --}}
    <div class="modal fade @if(isset($errors) && $errors->any()) show @endif" id="editTiersModal" tabindex="-1" role="dialog" aria-labelledby="editTiersModalLabel" aria-hidden="true" @if(isset($errors) && $errors->any()) style="display: block; background: rgba(0,0,0,0.5);" @endif>
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content" style="border-radius: 16px; overflow: hidden; border: none; box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
                <form action="{{ route('membership.tiers.update') }}" method="POST">
                    @csrf
                    <div class="modal-header" style="background: linear-gradient(135deg, #1e293b, #0f172a); color: #fff; padding: 20px 24px;">
                        <h5 class="modal-title" id="editTiersModalLabel" style="font-weight: 700; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-crown" style="color: #f59e0b;"></i> Edit Membership Tiers
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" onclick="$('#editTiersModal').modal('hide').hide();">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body" style="padding: 24px;">
                        {{-- Required Form Note --}}
                        <div class="alert alert-warning" style="border-left: 4px solid #f59e0b; background: #fffbeb; color: #92400e; border-radius: 8px; padding: 14px 18px; margin-bottom: 20px;">
                            <div style="font-weight: 600; margin-bottom: 4px;"><i class="fas fa-exclamation-triangle mr-1"></i> Important Policy</div>
                            <div>Changing a tier does not change existing patients today. A patient's tier is re-checked on their next sale.</div>
                        </div>

                        @if (isset($errors) && $errors->any())
                        <div class="alert alert-danger" style="border-radius: 8px;">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle">
                                <thead style="background: #f8fafc; font-size: 13px; text-transform: uppercase; letter-spacing: 0.04em;">
                                    <tr>
                                        <th style="width: 19%;">Tier Name</th>
                                        <th style="width: 17%;">Lifetime Spend From (৳)</th>
                                        <th style="width: 17%;">Up To (৳)</th>
                                        <th style="width: 13%;">Earn Rate (%)</th>
                                        <th style="width: 17%;">1 Point Is Worth (৳)</th>
                                        <th style="width: 17%;">Referral Bonus (pts)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($tiers as $tier)
                                    <tr>
                                        <td>
                                            <input type="hidden" name="tiers[{{ $tier->id }}][id]" value="{{ $tier->id }}">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"><i class="fas fa-tag"></i></span>
                                                </div>
                                                <input type="text" name="tiers[{{ $tier->id }}][name]" class="form-control font-weight-bold" value="{{ old('tiers.'.$tier->id.'.name', $tier->name) }}" required>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">৳</span>
                                                </div>
                                                <input type="number" step="0.01" min="0" name="tiers[{{ $tier->id }}][min_purchase]" class="form-control" value="{{ old('tiers.'.$tier->id.'.min_purchase', $tier->min_purchase) }}" required>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">৳</span>
                                                </div>
                                                <input type="number" step="0.01" min="0" name="tiers[{{ $tier->id }}][max_purchase]" class="form-control" value="{{ old('tiers.'.$tier->id.'.max_purchase', $tier->max_purchase) }}" placeholder="{{ $loop->last ? 'No limit (blank)' : 'Max amount' }}">
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group">
                                                <input type="number" step="0.01" min="0" max="100" name="tiers[{{ $tier->id }}][earn_percentage]" class="form-control text-right" value="{{ old('tiers.'.$tier->id.'.earn_percentage', $tier->earn_percentage) }}" required>
                                                <div class="input-group-append">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group">
                                                <input type="number" step="0.01" min="0.01" max="1000" name="tiers[{{ $tier->id }}][point_value]" class="form-control text-right" placeholder="{{ number_format($pointVal, 2) }}" title="Leave empty to use the loyalty setting ({{ number_format($pointVal, 2) }})" value="{{ old('tiers.'.$tier->id.'.point_value', $tier->point_value) }}">
                                                <div class="input-group-append">
                                                    <span class="input-group-text">৳/pt</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="input-group">
                                                <input type="number" step="1" min="0" name="tiers[{{ $tier->id }}][referral_reward_points]" class="form-control text-right" value="{{ old('tiers.'.$tier->id.'.referral_reward_points', $tier->referral_reward_points) }}" required>
                                                <div class="input-group-append">
                                                    <span class="input-group-text">pts</span>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="text-muted" style="font-size: 12px; margin-top: 8px;">
                            <i class="fas fa-info-circle mr-1"></i>
                            Tiers must be contiguous without gaps or overlaps (e.g. 0 to 100,000, then 100,000.01 to 300,000). The lowest tier starts at 0, and the highest tier must leave maximum purchase blank.
                        </div>
                    </div>
                    <div class="modal-footer" style="background: #f8fafc; padding: 16px 24px;">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal" onclick="$('#editTiersModal').modal('hide').hide();">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save mr-1"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

</div>
@endsection
