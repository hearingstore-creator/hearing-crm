@extends('admin.layouts.master')
@section('page_title', 'Customer Details')
@section('breadcrumb_parent', 'Customers')
@section('breadcrumb_parent_url', url('admin/customers'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<style>
    .cust-kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    .cust-kpi-card {
        background: var(--card-bg, #fff);
        border: 1px solid var(--card-border, #e2e8f0);
        border-radius: 12px;
        padding: 18px 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .cust-kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0,0,0,0.06);
    }
    .cust-kpi-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }
    .cust-kpi-info {
        flex: 1;
        min-width: 0;
    }
    .cust-kpi-label {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--page-text-muted, #64748b);
        margin-bottom: 2px;
    }
    .cust-kpi-value {
        font-size: 20px;
        font-weight: 700;
        color: var(--page-text, #0f172a);
        line-height: 1.2;
    }
    .cust-kpi-sub {
        font-size: 11px;
        color: var(--page-text-secondary, #475569);
        margin-top: 2px;
    }
    .cust-ear-card {
        flex: 1;
        min-width: 200px;
        border: 1px solid var(--card-border, #e2e8f0);
        border-radius: 10px;
        overflow: hidden;
        background: var(--card-bg, #fff);
    }
    .cust-ear-header {
        padding: 10px 14px;
        font-size: 13px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        color: #fff;
    }
    .cust-ear-body {
        padding: 14px;
    }
    .nav-tabs .nav-link {
        font-weight: 500;
        font-size: 13px;
        color: var(--page-text-secondary, #64748b);
        border-radius: 8px 8px 0 0;
        padding: 10px 16px;
    }
    .nav-tabs .nav-link.active {
        color: var(--accent-600, #4f46e5);
        font-weight: 600;
        border-bottom-color: #fff;
    }
</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-user-circle" style="color: var(--accent-500); margin-right: 8px;"></i>Customer Profile</h1>
            <p>Viewing complete record, devices, and purchase journey for <strong>{{ $customer->lead->full_name ?? $customer->name }}</strong></p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/customers') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i>Customers
            </a>
            <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#newEnquiryModal">
                <i class="fas fa-plus-circle mr-1"></i>New Enquiry / Deal
            </button>
            <a href="{{ url('admin/appointments/create?customer_id=' . $customer->id) }}" class="btn btn-info btn-sm">
                <i class="fas fa-calendar-plus mr-1"></i>Book Appointment
            </a>
            <a href="{{ url('admin/customer-payment?customer_id=' . $customer->id) }}" class="btn btn-success btn-sm">
                <i class="fas fa-hand-holding-usd mr-1"></i>Collect Payment
            </a>
            <a href="{{ url('admin/customer-ledger/create?customer_id=' . $customer->id) }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-book mr-1"></i>Ledger
            </a>
            <a href="{{ url('admin/sales/create?customer_id=' . $customer->id) }}" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-shopping-cart mr-1"></i>New Sale
            </a>
            @can('permission', 'customer_list.edit')
                <a href="{{ route('customers.edit', $customer->id) }}" class="btn btn-outline-secondary btn-sm" title="Edit Customer">
                    <i class="fas fa-pen"></i>
                </a>
            @endcan
        </div>
    </div>

    @include('admin.alert_message')

    {{-- Hero Identity Card --}}
    <div class="crm-hero-card crm-animate-in delay-1" style="margin-bottom: 20px;">
        <div class="crm-hero-top">
            <div class="crm-hero-identity">
                <div class="crm-hero-avatar">{{ strtoupper(substr($customer->lead->full_name ?? 'C', 0, 1)) }}</div>
                <div class="crm-hero-info">
                    <h2>
                        {{ $customer->lead->full_name ?? 'Unnamed Customer' }}
                        @if($customer->membershipTier)
                            <span class="crm-status-badge badge-status-won" style="font-size: 12px; margin-left: 8px;">
                                <i class="fas fa-award mr-1"></i>{{ $customer->membershipTier->name }}
                            </span>
                        @endif
                    </h2>
                    <div class="crm-hero-meta">
                        <span><i class="fas fa-id-badge"></i> {{ $customer->customer_uid }}</span>
                        @if($customer->lead?->lead_uid)
                            <span><i class="fas fa-hashtag"></i> {{ $customer->lead->lead_uid }}</span>
                        @endif
                        <span><i class="fas fa-phone"></i> {{ $customer->lead->phone_number ?? 'No phone' }}</span>
                        @if($customer->lead?->email)
                            <span><i class="fas fa-envelope"></i> {{ $customer->lead->email }}</span>
                        @endif
                        @if($customer->lead?->vendor)
                            <span class="crm-status-badge badge-status-new"><span class="dot"></span>{{ $customer->lead->vendor->name }}</span>
                        @else
                            <span class="crm-status-badge badge-status-new"><span class="dot"></span>Head Office</span>
                        @endif
                    </div>
                </div>
            </div>

            @if($openDeal)
            <div style="background: var(--accent-50, #eef2ff); border: 1px solid var(--accent-200, #c7d2fe); border-radius: 10px; padding: 12px 18px; text-align: right;">
                <div style="font-size: 11px; text-transform: uppercase; font-weight: 600; color: var(--accent-700, #4338ca);">Active Opportunity</div>
                <div style="font-size: 14px; font-weight: 700; color: var(--accent-900, #1e1b4b); margin-top: 2px;">
                    {{ $openDeal->stage->name ?? 'Open Stage' }}
                    @if($openDeal->value) · ৳{{ number_format($openDeal->value) }} @endif
                </div>
                <a href="{{ url('admin/deals?q=' . urlencode($customer->lead->phone_number)) }}" style="font-size: 12px; font-weight: 600; color: var(--accent-600); display: inline-block; margin-top: 4px;">
                    View on Deals Board <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            @endif
        </div>
    </div>

    {{-- KPI / Summary Cards Row --}}
    <div class="cust-kpi-grid crm-animate-in delay-2">
        {{-- Total Spend --}}
        <div class="cust-kpi-card">
            <div class="cust-kpi-icon" style="background: rgba(99, 102, 241, 0.12); color: #6366f1;">
                <i class="fas fa-coins"></i>
            </div>
            <div class="cust-kpi-info">
                <div class="cust-kpi-label">Lifetime Spend</div>
                <div class="cust-kpi-value">৳{{ number_format($totalSpent, 2) }}</div>
                <div class="cust-kpi-sub">{{ $totalPurchases }} {{ \Illuminate\Support\Str::plural('purchase', $totalPurchases) }}</div>
            </div>
        </div>

        {{-- Due Balance --}}
        <div class="cust-kpi-card">
            <div class="cust-kpi-icon" style="background: {{ $dueBalance > 0 ? 'rgba(239, 68, 68, 0.12)' : 'rgba(16, 185, 129, 0.12)' }}; color: {{ $dueBalance > 0 ? '#ef4444' : '#10b981' }};">
                <i class="fas {{ $dueBalance > 0 ? 'fa-exclamation-circle' : 'fa-check-circle' }}"></i>
            </div>
            <div class="cust-kpi-info">
                <div class="cust-kpi-label">Due Balance</div>
                <div class="cust-kpi-value" style="color: {{ $dueBalance > 0 ? '#ef4444' : '#10b981' }};">
                    ৳{{ number_format(max(0, $dueBalance), 2) }}
                </div>
                <div class="cust-kpi-sub">{{ $dueBalance > 0 ? 'Payment pending' : 'Zero balance / Cleared' }}</div>
            </div>
        </div>

        {{-- Reward Points --}}
        <div class="cust-kpi-card">
            <div class="cust-kpi-icon" style="background: rgba(245, 158, 11, 0.12); color: #f59e0b;">
                <i class="fas fa-award"></i>
            </div>
            <div class="cust-kpi-info">
                <div class="cust-kpi-label">Reward Points</div>
                <div class="cust-kpi-value" style="color: #d97706;">{{ number_format($rewardPoints) }} pts</div>
                <div class="cust-kpi-sub">Tier: {{ $customer->membershipTier->name ?? 'Standard' }}</div>
            </div>
        </div>

        {{-- Last Purchase / Visit --}}
        <div class="cust-kpi-card">
            <div class="cust-kpi-icon" style="background: rgba(14, 165, 233, 0.12); color: #0ea5e9;">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="cust-kpi-info">
                <div class="cust-kpi-label">Last Purchase</div>
                <div class="cust-kpi-value" style="font-size: 16px;">
                    {{ $lastVisit ? \Carbon\Carbon::parse($lastVisit)->format('d M Y') : 'No purchase' }}
                </div>
                <div class="cust-kpi-sub">{{ $lastVisit ? \Carbon\Carbon::parse($lastVisit)->diffForHumans() : 'New customer' }}</div>
            </div>
        </div>

        {{-- Hearing Devices --}}
        <div class="cust-kpi-card">
            <div class="cust-kpi-icon" style="background: rgba(168, 85, 247, 0.12); color: #a855f7;">
                <i class="fas fa-ear-listen"></i>
            </div>
            <div class="cust-kpi-info">
                <div class="cust-kpi-label">Devices Owned</div>
                <div class="cust-kpi-value">{{ $devices->count() }}</div>
                <div class="cust-kpi-sub">
                    @php $activeWarranties = $devices->where('is_warranty_active', true)->count(); @endphp
                    {{ $activeWarranties }} {{ \Illuminate\Support\Str::plural('device', $activeWarranties) }} in warranty
                </div>
            </div>
        </div>

        {{-- Active Advance Balance --}}
        @php $activeAdvBal = $customer->activeAdvanceBalance(); @endphp
        @if ($activeAdvBal > 0)
        <div class="cust-kpi-card" style="border-color: #86efac; background: #f0fdf4;">
            <div class="cust-kpi-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
                <i class="fas fa-hand-holding-usd"></i>
            </div>
            <div class="cust-kpi-info">
                <div class="cust-kpi-label" style="color: #166534;">Advance Deposit</div>
                <div class="cust-kpi-value" style="color: #15803d;">৳{{ number_format($activeAdvBal, 2) }}</div>
                <div class="cust-kpi-sub" style="color: #166534;">Available credit balance</div>
            </div>
        </div>
        @endif
    </div>

    {{-- Main Profile Tabbed Container --}}
    <div class="crm-table-card crm-animate-in delay-3" style="padding: 0; margin-bottom: 24px;">
        <ul class="nav nav-tabs" id="customerProfileTabs" role="tablist" style="padding: 16px 20px 0; border-bottom: 1px solid var(--gray-200, #e2e8f0);">
            <li class="nav-item">
                <a class="nav-link active" id="overview-tab-link" data-toggle="tab" href="#overview-tab" role="tab">
                    <i class="fas fa-id-card mr-1"></i> Overview & Hearing
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="devices-tab-link" data-toggle="tab" href="#devices-tab" role="tab">
                    <i class="fas fa-ear-listen mr-1"></i> Devices & Warranties
                    @if($devices->count() > 0)
                        <span class="badge badge-pill badge-primary ml-1" style="font-size: 10px;">{{ $devices->count() }}</span>
                    @endif
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="sales-tab-link" data-toggle="tab" href="#sales-tab" role="tab">
                    <i class="fas fa-shopping-cart mr-1"></i> Sales History
                    @if($saleMasters->count() > 0)
                        <span class="badge badge-pill badge-secondary ml-1" style="font-size: 10px;">{{ $saleMasters->count() }}</span>
                    @endif
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="appointments-tab-link" data-toggle="tab" href="#appointments-tab" role="tab">
                    <i class="fas fa-calendar-alt mr-1"></i> Appointments
                    @if($appointments->count() > 0)
                        <span class="badge badge-pill badge-info ml-1" style="font-size: 10px;">{{ $appointments->count() }}</span>
                    @endif
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="advances-tab-link" data-toggle="tab" href="#advances-tab" role="tab">
                    <i class="fas fa-hand-holding-usd mr-1"></i> Advances &amp; Refunds
                    @if(isset($advances) && $advances->count() > 0)
                        <span class="badge badge-pill badge-success ml-1" style="font-size: 10px;">{{ $advances->count() }}</span>
                    @endif
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="timeline-tab-link" data-toggle="tab" href="#timeline-tab" role="tab">
                    <i class="fas fa-history mr-1"></i> Communication Timeline
                    @if(count($timelineItems) > 0)
                        <span class="badge badge-pill badge-light ml-1" style="font-size: 10px; border: 1px solid #cbd5e1;">{{ count($timelineItems) }}</span>
                    @endif
                </a>
            </li>
        </ul>

        <div class="tab-content" style="padding: 24px;">

            {{-- TAB 1: OVERVIEW & HEARING --}}
            <div class="tab-pane fade show active" id="overview-tab" role="tabpanel">
                <div class="row">
                    {{-- Personal Information --}}
                    <div class="col-lg-6 mb-4">
                        <div style="background: var(--table-header-bg, #f8fafc); border: 1px solid var(--card-border, #e2e8f0); border-radius: 12px; padding: 20px;">
                            <h6 style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--accent-600, #4f46e5); margin-bottom: 16px;">
                                <i class="fas fa-user mr-1"></i> Personal & Demographic Details
                            </h6>
                            <div style="display: grid; grid-template-columns: 140px 1fr; gap: 12px 16px; font-size: 13px;">
                                <span class="crm-form-label" style="margin:0;">Full Name</span>
                                <span style="font-weight: 600;">{{ $customer->lead->full_name ?? 'N/A' }}</span>

                                <span class="crm-form-label" style="margin:0;">Customer ID</span>
                                <span>{{ $customer->customer_uid }}</span>

                                <span class="crm-form-label" style="margin:0;">Lead UID</span>
                                <span>{{ $customer->lead->lead_uid ?? 'N/A' }}</span>

                                <span class="crm-form-label" style="margin:0;">Phone Number</span>
                                <span>{{ $customer->lead->phone_number ?? 'N/A' }}</span>

                                <span class="crm-form-label" style="margin:0;">Email</span>
                                <span>{{ $customer->lead->email ?? 'N/A' }}</span>

                                <span class="crm-form-label" style="margin:0;">Age / Gender</span>
                                <span>{{ $customer->lead->age ? $customer->lead->age . ' yrs' : 'N/A' }} · {{ $customer->lead->gender ? ucfirst($customer->lead->gender) : 'N/A' }}</span>

                                <span class="crm-form-label" style="margin:0;">Date of Birth</span>
                                <span>
                                    @if ($customer->lead?->date_of_birth)
                                        {{ $customer->lead->date_of_birth->format('d M Y') }}
                                        @if ($customer->lead->date_of_birth->isBirthday())
                                            <span class="badge badge-pill badge-primary ml-1" style="background:#9333ea; font-size:10.5px; padding:2px 7px;"><i class="fas fa-birthday-cake mr-1"></i>Today!</span>
                                        @endif
                                    @else
                                        <span style="color:var(--page-text-muted);">Not set</span>
                                    @endif
                                </span>

                                <span class="crm-form-label" style="margin:0;">District</span>
                                <span>{{ $customer->lead->district->name ?? 'N/A' }}</span>

                                <span class="crm-form-label" style="margin:0;">Address</span>
                                <span>{{ $customer->lead->address ?? 'N/A' }}</span>
                            </div>

                            <div style="margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--card-border, #e2e8f0); display: grid; grid-template-columns: 140px 1fr; gap: 10px 16px; font-size: 12px; color: var(--page-text-muted);">
                                <span>Branch:</span>
                                <span style="color: var(--page-text); font-weight: 500;">{{ $customer->lead->vendor->name ?? 'Head Office' }}</span>

                                <span>Created By:</span>
                                <span style="color: var(--page-text);">{{ optional($customer->creator)->name ?? 'System' }}</span>

                                <span>Registered At:</span>
                                <span style="color: var(--page-text);">{{ $customer->created_at->format('d M Y, h:i A') }}</span>
                            </div>
                        </div>

                        {{-- Contact Person & Referral --}}
                        <div style="background: var(--table-header-bg, #f8fafc); border: 1px solid var(--card-border, #e2e8f0); border-radius: 12px; padding: 20px; margin-top: 20px;">
                            <h6 style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--page-text, #0f172a); margin-bottom: 16px;">
                                <i class="fas fa-users mr-1"></i> Contact Person & Referrals
                            </h6>
                            <div style="display: grid; grid-template-columns: 140px 1fr; gap: 10px 16px; font-size: 13px;">
                                <span class="crm-form-label" style="margin:0;">Contact Person</span>
                                <span>{{ $customer->lead->contact_person_name ?? 'None' }}</span>

                                <span class="crm-form-label" style="margin:0;">Relationship</span>
                                <span>{{ $customer->lead->relation ? ucfirst($customer->lead->relation) : 'N/A' }}</span>

                                <span class="crm-form-label" style="margin:0;">Contact Phone</span>
                                <span>{{ $customer->lead->contact_person_phone ?? 'N/A' }}</span>

                                <span class="crm-form-label" style="margin:0;">Referral Code</span>
                                <span>
                                    @if($customer->referral_code)
                                        <span class="crm-status-badge badge-status-verified"><span class="dot"></span>{{ $customer->referral_code }}</span>
                                    @else
                                        N/A
                                    @endif
                                </span>

                                <span class="crm-form-label" style="margin:0;">Referred By</span>
                                <span>
                                    @if($customer->referredBy)
                                        <a href="{{ route('customers.show', $customer->referredBy->id) }}" style="color: var(--accent-600); font-weight: 500;">
                                            {{ $customer->referredBy->lead->full_name }} ({{ $customer->referredBy->customer_uid }})
                                        </a>
                                    @elseif($customer->lead?->ref_by)
                                        {{ $customer->lead->ref_by }}
                                    @else
                                        N/A
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Clinical Hearing Assessment & Active Deals --}}
                    <div class="col-lg-6 mb-4">
                        {{-- Hearing Assessment Cards --}}
                        <div style="background: var(--table-header-bg, #f8fafc); border: 1px solid var(--card-border, #e2e8f0); border-radius: 12px; padding: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                                <h6 style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #7c3aed; margin: 0;">
                                    <i class="fas fa-ear-listen mr-1"></i> Hearing Loss Profile
                                </h6>
                                @if($customer->lead?->interest_brand_product)
                                    <span class="crm-status-badge badge-status-verified" style="font-size: 11px;">
                                        Interested: {{ $customer->lead->interest_brand_product }}
                                    </span>
                                @endif
                            </div>

                            @if(count($hearingLoss) > 0)
                                <div style="display: flex; gap: 14px; flex-wrap: wrap;">
                                    @foreach($hearingLoss as $item)
                                        <div class="cust-ear-card">
                                            <div class="cust-ear-header" style="background: {{ strtolower($item['ear']) === 'right' ? '#ef4444' : '#3b82f6' }};">
                                                <i class="fas fa-ear-listen"></i> {{ $item['ear'] }} Ear
                                            </div>
                                            <div class="cust-ear-body">
                                                <div style="margin-bottom: 6px; font-size: 13px;">
                                                    <span style="color: var(--page-text-muted); font-size: 11px; text-transform: uppercase; display: block;">Degree</span>
                                                    <strong style="color: var(--page-text);">{{ $item['degree'] }}</strong>
                                                </div>
                                                <div style="font-size: 13px;">
                                                    <span style="color: var(--page-text-muted); font-size: 11px; text-transform: uppercase; display: block;">Type</span>
                                                    <strong style="color: var(--page-text);">{{ $item['type'] }}</strong>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div style="text-align: center; padding: 24px; color: var(--page-text-muted); background: #fff; border-radius: 10px; border: 1px dashed var(--gray-300);">
                                    <i class="fas fa-ear-listen" style="font-size: 24px; color: var(--gray-300); margin-bottom: 8px; display: block;"></i>
                                    No hearing loss degree or type recorded yet.
                                </div>
                            @endif

                            <div style="margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--card-border, #e2e8f0); display: grid; grid-template-columns: 140px 1fr; gap: 10px 16px; font-size: 13px;">
                                <span class="crm-form-label" style="margin:0;">Inquiry For</span>
                                <span>{{ $customer->lead->inquiry_for_label ?? 'N/A' }}</span>

                                <span class="crm-form-label" style="margin:0;">Approx Budget</span>
                                <span style="font-weight: 600;">{{ $customer->lead->approx_budget ? '৳' . number_format($customer->lead->approx_budget, 2) : 'N/A' }}</span>

                                @if($customer->lead?->notes)
                                <span class="crm-form-label" style="margin:0;">Clinical Notes</span>
                                <span style="color: var(--page-text-secondary); line-height: 1.5;">{{ $customer->lead->notes }}</span>
                                @endif
                            </div>
                        </div>

                        {{-- Pipeline Deals & Opportunities --}}
                        <div style="background: var(--table-header-bg, #f8fafc); border: 1px solid var(--card-border, #e2e8f0); border-radius: 12px; padding: 20px; margin-top: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                                <h6 style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--page-text, #0f172a); margin: 0;">
                                    <i class="fas fa-funnel-dollar mr-1"></i> Pipeline Opportunities & Enquiries ({{ $deals->count() }})
                                </h6>
                                <button type="button" class="btn btn-outline-primary btn-xs" data-toggle="modal" data-target="#newEnquiryModal">
                                    <i class="fas fa-plus mr-1"></i>Add Enquiry
                                </button>
                            </div>

                            @if($deals->count() > 0)
                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    @foreach($deals as $d)
                                        <div style="background: #fff; border: 1px solid var(--card-border, #e2e8f0); border-radius: 8px; padding: 12px 14px; display: flex; justify-content: space-between; align-items: center;">
                                            <div>
                                                <div style="font-weight: 600; font-size: 13px;">
                                                    {{ $d->title ?: ($customer->lead->full_name . ' Opportunity') }}
                                                    @if($d->status === 'open')
                                                        <span class="badge badge-pill badge-primary" style="font-size: 10px; margin-left: 6px;">Active</span>
                                                    @elseif($d->status === 'won')
                                                        <span class="badge badge-pill badge-success" style="font-size: 10px; margin-left: 6px;">Won</span>
                                                    @else
                                                        <span class="badge badge-pill badge-secondary" style="font-size: 10px; margin-left: 6px;">Closed</span>
                                                    @endif
                                                </div>
                                                <div style="font-size: 12px; color: var(--page-text-muted); margin-top: 2px;">
                                                    Stage: <strong>{{ $d->stage->name ?? 'N/A' }}</strong> · Owner: {{ $d->owner->name ?? 'Unassigned' }}
                                                </div>
                                            </div>
                                            <div style="text-align: right;">
                                                <div style="font-weight: 700; font-size: 13px; color: var(--accent-600);">
                                                    {{ $d->value ? '৳' . number_format($d->value) : '—' }}
                                                </div>
                                                <a href="{{ url('admin/deals?q=' . urlencode($customer->lead->phone_number)) }}" style="font-size: 11px; color: var(--page-text-muted);">
                                                    Open <i class="fas fa-external-link-alt ml-1"></i>
                                                </a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div style="text-align: center; padding: 20px; color: var(--page-text-muted); background: #fff; border-radius: 8px; border: 1px dashed var(--gray-300);">
                                    No active pipeline opportunities. Click "+ New Enquiry" to open a deal for repeat purchase or service.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- TAB 2: DEVICES & WARRANTIES --}}
            <div class="tab-pane fade" id="devices-tab" role="tabpanel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <div>
                        <h6 style="font-size: 14px; font-weight: 700; color: var(--page-text, #0f172a); margin: 0;">
                            <i class="fas fa-ear-listen mr-1" style="color: #6366f1;"></i> Purchased Hearing Aids & Equipment
                        </h6>
                        <p style="font-size: 12px; color: var(--page-text-muted); margin: 2px 0 0;">
                            Automatic record of all models, serial numbers, battery sizes and warranty expiry.
                        </p>
                    </div>
                    <a href="{{ url('admin/sales/create?customer_id=' . $customer->id) }}" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-plus mr-1"></i>Sell New Device
                    </a>
                </div>

                @if($devices->count() > 0)
                    <div style="overflow-x: auto;">
                        <table class="crm-table" style="font-size: 13px;">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">#</th>
                                    <th>Model & Description</th>
                                    <th>Brand / Category</th>
                                    <th>Serial Number(s)</th>
                                    <th style="text-align: center;">Battery Size</th>
                                    <th>Purchase Date</th>
                                    <th>Warranty Status</th>
                                    <th style="text-align: right;">Invoice</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($devices as $idx => $dev)
                                    <tr>
                                        <td style="color: var(--page-text-muted); font-size: 12px;">{{ $idx + 1 }}</td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--page-text);">{{ $dev['model'] }}</div>
                                            <div style="font-size: 11px; color: var(--page-text-muted);">{{ $dev['category'] }} · {{ $dev['type'] }}</div>
                                        </td>
                                        <td>
                                            <span class="crm-status-badge badge-status-new" style="font-size: 11px;">
                                                <span class="dot"></span>{{ $dev['brand'] }}
                                            </span>
                                        </td>
                                        <td>
                                            @if($dev['serial_no'] && $dev['serial_no'] !== 'N/A')
                                                <span style="font-family: monospace; font-weight: 600; background: var(--gray-100, #f1f5f9); padding: 2px 6px; border-radius: 4px;">
                                                    {{ $dev['serial_no'] }}
                                                </span>
                                            @else
                                                <span style="color: var(--page-text-muted); font-style: italic;">Not registered</span>
                                            @endif
                                        </td>
                                        <td style="text-align: center;">
                                            @if($dev['battery_size'])
                                                <span class="badge badge-warning" style="font-size: 11px; padding: 4px 8px;">
                                                    Size {{ $dev['battery_size'] }}
                                                </span>
                                            @else
                                                <span style="color: var(--page-text-muted);">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($dev['purchase_date'])
                                                <div>{{ $dev['purchase_date']->format('d M Y') }}</div>
                                                <div style="font-size: 11px; color: var(--page-text-muted);">{{ $dev['purchase_date']->diffForHumans() }}</div>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if($dev['warranty_expiry'])
                                                @if($dev['is_warranty_active'])
                                                    <span class="crm-status-badge badge-status-won" style="font-size: 11px;">
                                                        <i class="fas fa-shield-alt mr-1"></i>Valid until {{ $dev['warranty_expiry']->format('d M Y') }}
                                                        <small style="opacity: 0.85;">({{ $dev['days_left'] }} days left)</small>
                                                    </span>
                                                @else
                                                    <span class="crm-status-badge badge-status-lost" style="font-size: 11px;">
                                                        <i class="fas fa-times-circle mr-1"></i>Expired on {{ $dev['warranty_expiry']->format('d M Y') }}
                                                    </span>
                                                @endif
                                            @else
                                                <span style="color: var(--page-text-muted); font-size: 12px;">No warranty set</span>
                                            @endif
                                        </td>
                                        <td style="text-align: right;">
                                            <a href="{{ route('sale.show', $dev['sale_id']) }}" class="btn btn-secondary btn-xs" target="_blank" title="View Sale Invoice">
                                                <i class="fas fa-receipt mr-1"></i>{{ $dev['invoice_no'] }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div style="text-align: center; padding: 48px; color: var(--page-text-muted); background: var(--gray-50, #f8fafc); border-radius: 12px; border: 1px dashed var(--gray-300, #cbd5e1);">
                        <i class="fas fa-ear-listen" style="font-size: 36px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>
                        No hearing aids or devices purchased yet for this customer.
                        <div style="margin-top: 12px;">
                            <a href="{{ url('admin/sales/create?customer_id=' . $customer->id) }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-cart-plus mr-1"></i>Create First Sale
                            </a>
                        </div>
                    </div>
                @endif
            </div>

            {{-- TAB 3: SALES HISTORY --}}
            <div class="tab-pane fade" id="sales-tab" role="tabpanel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h6 style="font-size: 14px; font-weight: 700; color: var(--page-text, #0f172a); margin: 0;">
                        <i class="fas fa-shopping-cart mr-1" style="color: #10b981;"></i> All Sales & Invoices ({{ $saleMasters->count() }})
                    </h6>
                    <a href="{{ url('admin/sales/create?customer_id=' . $customer->id) }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus mr-1"></i>New Sale
                    </a>
                </div>

                @if($saleMasters->count() > 0)
                    @foreach($saleMasters as $saleMaster)
                    <div style="border: 1px solid var(--card-border, #e2e8f0); border-radius: 10px; margin-bottom: 16px; overflow: hidden;">
                        <div style="background: var(--table-header-bg, #f8fafc); border-bottom: 1px solid var(--card-border, #e2e8f0); padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                                <span class="crm-status-badge badge-status-verified" style="font-size: 12px;">
                                    <span class="dot"></span>{{ $saleMaster->sale_invoice_no }}
                                </span>
                                <span style="font-size: 13px; color: var(--page-text-muted);">
                                    <i class="far fa-calendar-alt mr-1"></i>{{ $saleMaster->date->format('d M Y') }}
                                </span>
                                @php $invDue = round($saleMaster->total_amount - $saleMaster->paid, 2); @endphp
                                @if($invDue > 0.009)
                                    <span class="crm-status-badge badge-status-lost" style="font-size: 11px; font-weight: 700;">
                                        Due: ৳{{ number_format($invDue, 2) }}
                                    </span>
                                    @if($saleMaster->due_date)
                                        <span class="crm-status-badge badge-status-followup" style="font-size: 11px; background:#fef3c7; color:#b45309; border-color:#fde68a;">
                                            <i class="far fa-clock mr-1"></i>Promise: {{ $saleMaster->due_date->format('d M Y') }}
                                        </span>
                                    @endif
                                @else
                                    <span class="crm-status-badge badge-status-won" style="font-size: 11px;">
                                        Paid
                                    </span>
                                @endif
                            </div>
                            <div>
                                @if($invDue > 0.009)
                                    <a href="{{ url('admin/customer-payment?customer_id=' . $customer->id . '&invoice_no=' . $saleMaster->sale_invoice_no) }}" class="btn btn-success btn-xs mr-1" title="Collect Due against this Invoice">
                                        <i class="fas fa-hand-holding-usd mr-1"></i>Collect Due
                                    </a>
                                @endif
                                <a href="{{ route('sale.pos-print', $saleMaster->sale_invoice_no) }}" class="btn btn-outline-secondary btn-xs mr-1" target="_blank">
                                    <i class="fas fa-print mr-1"></i>Print
                                </a>
                                <a href="{{ route('sale.show', $saleMaster->id) }}" class="btn btn-secondary btn-xs" target="_blank">
                                    <i class="fas fa-eye mr-1"></i>Details
                                </a>
                            </div>
                        </div>

                        <div style="padding: 16px 20px;">
                            {{-- Products Table --}}
                            @if($saleMaster->saleDetails && $saleMaster->saleDetails->count() > 0)
                            <div style="margin-bottom: 12px;">
                                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--success-600, #16a34a); margin-bottom: 6px;">
                                    <i class="fas fa-box mr-1"></i>Products
                                </div>
                                <table class="crm-table" style="font-size: 12px;">
                                    <thead><tr><th>Product</th><th style="text-align: center;">Qty</th><th style="text-align: right;">Unit Price</th><th style="text-align: right;">Discount %</th><th style="text-align: right;">Total</th></tr></thead>
                                    <tbody>
                                        @foreach($saleMaster->saleDetails as $detail)
                                        @php $totalPrice = ($detail->qty * $detail->mrp_price) - (($detail->qty * $detail->mrp_price / 100) * $detail->discount); @endphp
                                        <tr>
                                            <td>{{ $detail->product->category->category_name ?? 'N/A' }} - {{ $detail->product->model ?? 'N/A' }}</td>
                                            <td style="text-align: center;">{{ $detail->qty }}</td>
                                            <td style="text-align: right;">{{ number_format($detail->mrp_price, 2) }}</td>
                                            <td style="text-align: right;">{{ $detail->discount }}%</td>
                                            <td style="text-align: right; font-weight: 600;">৳{{ number_format($totalPrice, 2) }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @endif

                            {{-- Services Table --}}
                            @if($saleMaster->serviceDetails && $saleMaster->serviceDetails->count() > 0)
                            <div style="margin-bottom: 12px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                    <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--info-600, #0284c7);">
                                        <i class="fas fa-tools mr-1"></i>Services in this Sale
                                    </div>
                                    <a href="{{ route('sale.show', $saleMaster->id) }}" class="btn btn-xs btn-outline-primary" style="font-size: 11px; padding: 1px 8px;">
                                        <i class="fas fa-file-invoice mr-1"></i>Sale #{{ $saleMaster->sale_invoice_no }}
                                    </a>
                                </div>
                                <table class="crm-table" style="font-size: 12px;">
                                    <thead>
                                        <tr>
                                            <th>Service Type</th>
                                            <th style="text-align: center;">Qty</th>
                                            <th style="text-align: right;">Total</th>
                                            <th>Booking Type</th>
                                            <th>Status / Link</th>
                                            <th style="text-align: center;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($saleMaster->serviceDetails as $service)
                                        @php
                                            $serviceTotal = ($service->qty * $service->service_price) - (($service->qty * $service->service_price / 100) * ($service->discount ?? 0));
                                            $bType = $service->serviceType?->booking_type ?? 'either';
                                            $fStatus = $service->fulfillment_status ?? 'not_booked';
                                        @endphp
                                        <tr id="cust-svc-row-{{ $service->id }}">
                                            <td>
                                                <strong>{{ $service->serviceType->service_type_name ?? 'N/A' }}</strong>
                                                <div style="font-size: 11px; color: var(--page-text-muted);">৳{{ number_format($service->service_price, 2) }} each</div>
                                            </td>
                                            <td style="text-align: center;">{{ $service->qty }}</td>
                                            <td style="text-align: right; font-weight: 600;">৳{{ number_format($serviceTotal, 2) }}</td>
                                            <td>
                                                @if ($bType === 'walk_in')
                                                    <span class="badge badge-success"><i class="fas fa-walking mr-1"></i>Walk-in</span>
                                                @elseif ($bType === 'appointment_required')
                                                    <span class="badge badge-warning text-dark"><i class="fas fa-calendar-alt mr-1"></i>Appt req.</span>
                                                @else
                                                    <span class="badge badge-info"><i class="fas fa-exchange-alt mr-1"></i>Either</span>
                                                @endif
                                            </td>
                                            <td id="cust-sd-info-{{ $service->id }}">
                                                <span class="{{ $service->statusBadgeClass() }}" id="cust-sd-badge-{{ $service->id }}">
                                                    {{ $service->statusLabel() }}
                                                </span>
                                                @if ($fStatus === 'booked' && $service->appointment)
                                                    <div style="font-size: 11px; color: #0284c7; margin-top: 3px;">
                                                        <a href="{{ url('admin/appointments/' . $service->appointment_id . '/edit') }}" target="_blank" style="color: inherit; text-decoration: underline;">
                                                            <i class="far fa-calendar-check mr-1"></i>{{ $service->appointment->appointment_date?->format('d M Y') }}
                                                        </a>
                                                        @if ($service->appointment->user)
                                                            <span>({{ $service->appointment->user->name }})</span>
                                                        @endif
                                                    </div>
                                                @elseif ($fStatus === 'done')
                                                    <div style="font-size: 11px; color: #16a34a; margin-top: 3px;">
                                                        <i class="fas fa-check mr-1"></i>{{ $service->done_at ? $service->done_at->format('d M Y') : 'Done' }}
                                                        @if ($service->doneByUser) by {{ $service->doneByUser->name }} @endif
                                                    </div>
                                                @endif
                                            </td>
                                            <td style="text-align: center;" id="cust-sd-actions-{{ $service->id }}">
                                                @if ($fStatus === 'not_booked')
                                                    <div class="d-inline-flex" style="gap: 4px;">
                                                        @if ($bType === 'walk_in' || $bType === 'either')
                                                            <button type="button" class="btn btn-xs btn-success btn-done-modal" data-id="{{ $service->id }}" data-service="{{ $service->serviceType?->service_type_name }}" data-patient="{{ $customer->lead?->full_name ?? 'Customer' }}">
                                                                <i class="fas fa-check mr-1"></i>Done now
                                                            </button>
                                                        @endif
                                                        @if ($bType === 'appointment_required' || $bType === 'either')
                                                            <a href="{{ url('admin/appointments/create?customer_id=' . $customer->id . '&service_detail_id=' . $service->id . '&sale_master_id=' . $saleMaster->id . '&sale_invoice_no=' . $saleMaster->sale_invoice_no . '&service_id=' . ($service->serviceType?->appointment_service_id ?? '')) }}" class="btn btn-xs btn-primary">
                                                                <i class="fas fa-calendar-plus mr-1"></i>Book
                                                            </a>
                                                        @endif
                                                    </div>
                                                @elseif ($fStatus === 'booked')
                                                    <div class="d-inline-flex" style="gap: 4px;">
                                                        <button type="button" class="btn btn-xs btn-outline-success btn-done-modal" data-id="{{ $service->id }}" data-service="{{ $service->serviceType?->service_type_name }}" data-patient="{{ $customer->lead?->full_name ?? 'Customer' }}" title="Mark Delivered Today">
                                                            <i class="fas fa-check mr-1"></i>Done now
                                                        </button>
                                                        @if ($service->appointment_id)
                                                            <a href="{{ url('admin/appointments/' . $service->appointment_id . '/edit') }}" class="btn btn-xs btn-outline-info" target="_blank">
                                                                <i class="fas fa-external-link-alt"></i>
                                                            </a>
                                                        @endif
                                                    </div>
                                                @else
                                                    <span class="text-muted" style="font-size: 11px;"><i class="fas fa-check-double text-success mr-1"></i>Delivered</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @endif

                            {{-- Summary Footer --}}
                            <div style="display: flex; justify-content: flex-end; margin-top: 10px;">
                                <div style="min-width: 260px; background: var(--gray-50, #f8fafc); border-radius: 8px; padding: 10px 14px; font-size: 12px;">
                                    <div style="display: flex; justify-content: space-between; padding: 2px 0;">
                                        <span style="color: var(--page-text-secondary);">Grand Total:</span>
                                        <strong style="color: var(--page-text);">৳{{ number_format($saleMaster->total_amount, 2) }}</strong>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; padding: 2px 0; color: #16a34a;">
                                        <span>Paid Amount:</span>
                                        <strong>৳{{ number_format($saleMaster->paid, 2) }}</strong>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; padding: 2px 0; color: {{ ($saleMaster->total_amount - $saleMaster->paid) > 0 ? '#ef4444' : '#64748b' }};">
                                        <span>Due:</span>
                                        <strong>৳{{ number_format(max(0, $saleMaster->total_amount - $saleMaster->paid), 2) }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                @else
                    <div style="text-align: center; padding: 48px; color: var(--page-text-muted); background: var(--gray-50, #f8fafc); border-radius: 12px; border: 1px dashed var(--gray-300, #cbd5e1);">
                        <i class="fas fa-shopping-cart" style="font-size: 36px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>
                        No sales found for this customer.
                    </div>
                @endif
            </div>

            {{-- TAB 4: APPOINTMENTS & CLINICAL VISITS --}}
            <div class="tab-pane fade" id="appointments-tab" role="tabpanel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h6 style="font-size: 14px; font-weight: 700; color: var(--page-text, #0f172a); margin: 0;">
                        <i class="fas fa-calendar-check mr-1" style="color: #0ea5e9;"></i> Clinical & Audiology Appointments ({{ $appointments->count() }})
                    </h6>
                    <a href="{{ url('admin/appointments/create?customer_id=' . $customer->id) }}" class="btn btn-info btn-sm">
                        <i class="fas fa-calendar-plus mr-1"></i>Book Appointment
                    </a>
                </div>

                @if($appointments->count() > 0)
                    <div style="overflow-x: auto;">
                        <table class="crm-table" style="font-size: 13px;">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">#</th>
                                    <th>Date</th>
                                    <th>Service</th>
                                    <th>Doctor / Professional</th>
                                    <th>Advance</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($appointments as $idx => $apt)
                                    <tr>
                                        <td style="color: var(--page-text-muted); font-size: 12px;">{{ $idx + 1 }}</td>
                                        <td style="font-weight: 500;">
                                            {{ \Carbon\Carbon::parse($apt->appointment_date)->format('d M Y') }}
                                        </td>
                                        <td>{{ $apt->service->name ?? 'Clinical Session' }}</td>
                                        <td>{{ $apt->user->name ?? 'Assigned Professional' }}</td>
                                        <td>
                                            @if ($apt->customerAdvance)
                                                @php $adv = $apt->customerAdvance; @endphp
                                                <div style="font-size: 12px; font-weight: 600; color: #166534;">
                                                    ৳{{ number_format($adv->amount, 2) }}
                                                    @if ($adv->balance < $adv->amount)
                                                        <small class="text-muted">(Bal: ৳{{ number_format($adv->balance, 2) }})</small>
                                                    @endif
                                                </div>
                                                <div style="margin-top: 2px;">
                                                    <span class="{{ $adv->statusBadgeClass() }}" style="font-size: 10px; padding: 2px 6px;">{{ $adv->statusLabel() }}</span>
                                                    <a href="{{ route('appointments.advance-receipt', $apt->id) }}" target="_blank" class="ml-1 text-primary" title="Money Receipt">
                                                        <i class="fas fa-print"></i>
                                                    </a>
                                                    @if ($adv->status === 'active' && $adv->balance > 0)
                                                        @if(auth()->user()->can('advance.refund') || \App\Classes\Helper::hasPermission('advance.refund') || auth()->user()->type === 'super_admin' || auth()->user()->type === 'admin')
                                                            <button type="button" class="btn btn-xs btn-outline-danger btn-open-refund ml-1" 
                                                                data-id="{{ $adv->id }}" 
                                                                data-receipt="{{ $adv->receipt_no }}" 
                                                                data-balance="{{ $adv->balance }}"
                                                                title="Refund Advance">
                                                                <i class="fas fa-undo"></i>
                                                            </button>
                                                        @endif
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-muted" style="font-size: 12px;">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php $statusVal = is_object($apt->status) ? ($apt->status->value ?? 'scheduled') : $apt->status; @endphp
                                            <span class="crm-status-badge badge-status-{{ $statusVal }}">
                                                <span class="dot"></span>{{ ucfirst(str_replace('_', ' ', $statusVal)) }}
                                            </span>
                                        </td>
                                        <td style="color: var(--page-text-secondary); font-size: 12px;">{{ $apt->notes ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div style="text-align: center; padding: 48px; color: var(--page-text-muted); background: var(--gray-50, #f8fafc); border-radius: 12px; border: 1px dashed var(--gray-300, #cbd5e1);">
                        <i class="fas fa-calendar-times" style="font-size: 36px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>
                        No appointments found for this customer.
                        <div style="margin-top: 12px;">
                            <a href="{{ url('admin/appointments/create?customer_id=' . $customer->id) }}" class="btn btn-info btn-sm">
                                <i class="fas fa-calendar-plus mr-1"></i>Book First Appointment
                            </a>
                        </div>
                    </div>
                @endif
            </div>

            {{-- TAB 5: COMMUNICATION TIMELINE --}}
            <div class="tab-pane fade" id="timeline-tab" role="tabpanel">
                <div style="margin-bottom: 16px;">
                    <h6 style="font-size: 14px; font-weight: 700; color: var(--page-text, #0f172a); margin: 0;">
                        <i class="fas fa-history mr-1" style="color: var(--accent-500);"></i> Complete Journey & Communications
                    </h6>
                    <p style="font-size: 12px; color: var(--page-text-muted); margin: 2px 0 0;">
                        Chronological record of SMS messages sent, follow-ups, calls, and activities.
                    </p>
                </div>
                @include('admin.partials.communication-timeline', ['timelineItems' => $timelineItems])
            </div>

            {{-- TAB 6: ADVANCES & REFUNDS --}}
            <div class="tab-pane fade" id="advances-tab" role="tabpanel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <div>
                        <h6 style="font-size: 14px; font-weight: 700; color: var(--page-text, #0f172a); margin: 0;">
                            <i class="fas fa-hand-holding-usd mr-1" style="color: #059669;"></i> Appointment Advances &amp; Account Deposits ({{ isset($advances) ? $advances->count() : 0 }})
                        </h6>
                        <p style="font-size: 12px; color: var(--page-text-muted); margin: 2px 0 0;">
                            Advance payments collected for appointments, applications to sales, or refunds/cancellations.
                        </p>
                    </div>
                </div>

                @if(isset($advances) && $advances->count() > 0)
                    <div style="overflow-x: auto;">
                        <table class="crm-table" style="font-size: 13px;">
                            <thead>
                                <tr>
                                    <th>Receipt No</th>
                                    <th>Date</th>
                                    <th>Linked Appointment</th>
                                    <th>Method &amp; Ref</th>
                                    <th style="text-align: right;">Amount</th>
                                    <th style="text-align: right;">Balance</th>
                                    <th style="text-align: center;">Status</th>
                                    <th style="text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($advances as $adv)
                                    <tr>
                                        <td>
                                            <strong>{{ $adv->receipt_no }}</strong>
                                        </td>
                                        <td>{{ $adv->date?->format('d M Y') ?? '—' }}</td>
                                        <td>
                                            @if($adv->appointment)
                                                <a href="{{ url('admin/appointments/' . $adv->appointment_id . '/edit') }}" class="font-weight-500 text-primary">
                                                    <i class="far fa-calendar mr-1"></i>{{ $adv->appointment->appointment_date?->format('d M Y') }}
                                                    @if($adv->appointment->service)
                                                        ({{ $adv->appointment->service->name }})
                                                    @endif
                                                </a>
                                            @else
                                                <span class="text-muted">General Advance</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge-light border">{{ strtoupper($adv->payment_method) }}</span>
                                            @if($adv->reference)
                                                <small class="text-muted d-block">{{ $adv->reference }}</small>
                                            @endif
                                        </td>
                                        <td style="text-align: right; font-weight: 600; color: #166534;">
                                            ৳{{ number_format($adv->amount, 2) }}
                                        </td>
                                        <td style="text-align: right; font-weight: 700; color: {{ $adv->balance > 0 ? '#2563eb' : '#64748b' }};">
                                            ৳{{ number_format($adv->balance, 2) }}
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="{{ $adv->statusBadgeClass() }}" style="font-size: 11px; padding: 3px 8px;">
                                                {{ $adv->statusLabel() }}
                                            </span>
                                            @if($adv->status === 'refunded' && $adv->refund_receipt_no)
                                                <small class="d-block text-muted" style="font-size: 10px;">Ref: {{ $adv->refund_receipt_no }}</small>
                                            @endif
                                        </td>
                                        <td style="text-align: center;">
                                            <div class="d-inline-flex align-items-center" style="gap: 4px;">
                                                @if($adv->appointment_id)
                                                    <a href="{{ route('appointments.advance-receipt', $adv->appointment_id) }}" target="_blank" class="btn btn-xs btn-outline-success" title="Money Receipt">
                                                        <i class="fas fa-print mr-1"></i>Receipt
                                                    </a>
                                                @endif
                                                @if($adv->status === 'active' && $adv->balance > 0)
                                                    @if(auth()->user()->can('advance.refund') || \App\Classes\Helper::hasPermission('advance.refund') || auth()->user()->type === 'super_admin' || auth()->user()->type === 'admin')
                                                        <button type="button" class="btn btn-xs btn-danger btn-open-refund" 
                                                            data-id="{{ $adv->id }}" 
                                                            data-receipt="{{ $adv->receipt_no }}" 
                                                            data-balance="{{ $adv->balance }}">
                                                            <i class="fas fa-undo mr-1"></i>Refund
                                                        </button>
                                                    @endif
                                                @endif
                                                @if($adv->status === 'refunded' && $adv->refund_receipt_no)
                                                    <a href="{{ route('advances.refund-receipt', $adv->id) }}" target="_blank" class="btn btn-xs btn-outline-danger" title="Refund Receipt">
                                                        <i class="fas fa-file-invoice mr-1"></i>Refund Slip
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div style="text-align: center; padding: 48px; color: var(--page-text-muted); background: var(--gray-50, #f8fafc); border-radius: 12px; border: 1px dashed var(--gray-300, #cbd5e1);">
                        <i class="fas fa-hand-holding-usd" style="font-size: 36px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>
                        No advance payments recorded for this customer.
                    </div>
                @endif
            </div>

        </div>
    </div>

</div>

{{-- Modal: + New Enquiry / Deal --}}
<div class="modal fade" id="newEnquiryModal" tabindex="-1" role="dialog" aria-labelledby="newEnquiryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 14px; border: none; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background: var(--accent-600, #4f46e5); color: #fff; padding: 16px 22px;">
                <h5 class="modal-title" id="newEnquiryModalLabel" style="font-size: 16px; font-weight: 700;">
                    <i class="fas fa-plus-circle mr-1"></i> New Enquiry for {{ $customer->lead->full_name ?? $customer->name }}
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 0.85;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('customers.open-deal', $customer->id) }}" method="POST">
                @csrf
                <div class="modal-body" style="padding: 22px;">
                    <p style="font-size: 12px; color: var(--page-text-muted); margin-bottom: 18px;">
                        Opens a new opportunity on the Deals Board for repeat sales, new ear fitting, device upgrade, or repair.
                    </p>

                    <div class="form-group mb-3">
                        <label class="crm-form-label">Inquiry For</label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                            <label style="font-size: 13px; font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                                <input type="checkbox" name="inquiry_for[]" value="hearing_aid"> Hearing Aid
                            </label>
                            <label style="font-size: 13px; font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                                <input type="checkbox" name="inquiry_for[]" value="battery_accessory"> Accessories / Battery
                            </label>
                            <label style="font-size: 13px; font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                                <input type="checkbox" name="inquiry_for[]" value="service_repair"> Service / Repair
                            </label>
                            <label style="font-size: 13px; font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                                <input type="checkbox" name="inquiry_for[]" value="hearing_test"> Hearing Test
                            </label>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="crm-form-label">Estimated Value (৳)</label>
                        <input type="number" name="value" class="form-control" placeholder="e.g. 45000" min="0" step="100">
                    </div>

                    <div class="row">
                        <div class="col-6 form-group mb-3">
                            <label class="crm-form-label">First Activity</label>
                            <select name="activity_type" class="form-control">
                                <option value="call">Phone Call</option>
                                <option value="visit">Visit / Store Appointment</option>
                                <option value="task">Internal Task</option>
                            </select>
                        </div>
                        <div class="col-6 form-group mb-3">
                            <label class="crm-form-label">Due At</label>
                            <input type="datetime-local" name="due_at" class="form-control" value="{{ now()->addDay()->format('Y-m-d\T10:00') }}">
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label class="crm-form-label">Remarks / Requirement</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Describe customer's new requirement or enquiry reason..."></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background: var(--table-header-bg, #f8fafc); border-top: 1px solid var(--card-border, #e2e8f0); padding: 12px 22px;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4">
                        <i class="fas fa-check-circle mr-1"></i> Open Deal on Board
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Done Now Modal --}}
<div class="modal fade" id="modal-service-done" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background: #10b981; color: #ffffff; padding: 16px 20px;">
                <h5 class="modal-title" style="font-weight: 700; font-size: 16px;">
                    <i class="fas fa-check-circle mr-1"></i> Mark Service as Delivered
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.9;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="form-service-done">
                @csrf
                <input type="hidden" id="modal-done-detail-id" name="detail_id" value="">
                <div class="modal-body" style="padding: 20px;">
                    <div class="alert alert-light" style="border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; margin-bottom: 16px;">
                        <div><strong>Service:</strong> <span id="modal-done-service-name">-</span></div>
                        <div><strong>Patient:</strong> <span id="modal-done-patient-name">-</span></div>
                    </div>

                    <div class="form-group">
                        <label style="font-weight: 600; font-size: 13px;">Delivered By (Professional / Staff) <span class="text-danger">*</span></label>
                        <select class="form-control" name="done_by" id="modal-done-by" required>
                            <option value="">-- Select Professional / Staff --</option>
                            @foreach ($professionals as $prof)
                                <option value="{{ $prof->id }}" {{ auth()->id() == $prof->id ? 'selected' : '' }}>
                                    {{ $prof->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label style="font-weight: 600; font-size: 13px;">Delivered Date & Time</label>
                        <input type="datetime-local" class="form-control" name="done_at" id="modal-done-at" value="{{ now()->format('Y-m-d\TH:i') }}">
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="font-weight: 600; font-size: 13px;">Notes (Optional)</label>
                        <textarea class="form-control" name="notes" id="modal-done-notes" rows="2" placeholder="e.g. Service delivered successfully, patient satisfied"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 12px 20px;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm px-4" id="modal-btn-confirm-done">
                        <i class="fas fa-check mr-1"></i> Confirm Delivered
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal: Refund Advance (Part D) --}}
<div class="modal fade" id="modal-refund-advance" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 12px; overflow: hidden; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div class="modal-header" style="background: #dc2626; color: #ffffff;">
                <h5 class="modal-title font-weight-bold"><i class="fas fa-undo mr-1"></i> Refund Advance / Keep as Credit</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="form-advance-refund">
                @csrf
                <input type="hidden" id="rf-advance-id" name="advance_id" value="">
                <div class="modal-body p-3">
                    <div class="alert alert-light border mb-3" style="font-size: 13px;">
                        <div><strong>Advance Receipt:</strong> <span id="rf-receipt-no">-</span></div>
                        <div><strong>Available Balance:</strong> <span id="rf-balance-display" class="font-weight-bold text-primary">৳0.00</span></div>
                    </div>

                    {{-- Action Type Switch --}}
                    <div class="form-group mb-3">
                        <label class="font-weight-bold" style="font-size: 12.5px;">Resolution Option <span class="text-danger">*</span></label>
                        <div class="d-flex gap-3" style="gap: 16px;">
                            <label style="cursor: pointer; font-size: 13px;">
                                <input type="radio" name="action_type" value="refund" checked id="opt-action-refund">
                                <strong>Refund to Patient</strong> (Cash/Bank Out)
                            </label>
                            <label style="cursor: pointer; font-size: 13px;">
                                <input type="radio" name="action_type" value="credit_kept" id="opt-action-credit">
                                <strong>Keep as Customer Credit</strong>
                            </label>
                        </div>
                    </div>

                    <div id="wrap-refund-fields">
                        <div class="row">
                            <div class="col-md-6 form-group mb-2">
                                <label style="font-size: 12px; font-weight: 700;">Refund Amount (৳) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" name="refund_amount" id="rf-refund-amount" class="form-control" placeholder="0.00" required>
                                <small class="text-muted">Partial refund allowed</small>
                            </div>
                            <div class="col-md-6 form-group mb-2">
                                <label style="font-size: 12px; font-weight: 700;">Deduction / Cancellation Fee (৳)</label>
                                <input type="number" step="0.01" min="0" name="deduction_fee" id="rf-deduction-fee" class="form-control" value="0.00" placeholder="0.00">
                                <small class="text-muted">Recorded as cancellation income</small>
                            </div>
                            <div class="col-md-6 form-group mb-2">
                                <label style="font-size: 12px; font-weight: 700;">Refund Method <span class="text-danger">*</span></label>
                                <select name="refund_method" id="rf-refund-method" class="form-control">
                                    <option value="cash">Cash</option>
                                    <option value="bkash">bKash</option>
                                    <option value="nagad">Nagad</option>
                                    <option value="card">Card</option>
                                    <option value="bank">Bank Transfer</option>
                                </select>
                            </div>
                            <div class="col-md-6 form-group mb-2">
                                <label style="font-size: 12px; font-weight: 700;">Refund Reference / Trx</label>
                                <input type="text" name="reference" id="rf-reference" class="form-control" placeholder="Cheque / TrxID">
                            </div>
                        </div>
                    </div>

                    <div id="wrap-credit-hint" style="display: none;" class="alert alert-info py-2 px-3 mb-2" style="font-size: 12px;">
                        <i class="fas fa-info-circle mr-1"></i> Patient cannot attend. The advance will remain on the customer's account balance as credit for future sales or appointments.
                    </div>

                    <div class="form-group mb-0">
                        <label style="font-size: 12px; font-weight: 700;">Reason for Cancellation / Refund <span class="text-danger">*</span></label>
                        <textarea name="reason" id="rf-reason" class="form-control" rows="2" placeholder="e.g. Patient emergency / out of station" required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm px-4" id="btn-submit-refund">
                        <i class="fas fa-check-circle mr-1"></i> Confirm Process
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    $(document).on('click', '.btn-done-modal', function () {
        var id = $(this).data('id');
        var sName = $(this).data('service');
        var pName = $(this).data('patient');
        $('#modal-done-detail-id').val(id);
        $('#modal-done-service-name').text(sName || 'Service');
        $('#modal-done-patient-name').text(pName || 'Customer');
        $('#modal-service-done').modal('show');
    });

    $('#form-service-done').on('submit', function (e) {
        e.preventDefault();
        var id = $('#modal-done-detail-id').val();
        var $btn = $('#modal-btn-confirm-done');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving...');

        $.ajax({
            url: '{{ url("admin/sales/service-detail") }}/' + id + '/done-now',
            type: 'POST',
            data: $(this).serialize(),
            success: function (res) {
                $('#modal-service-done').modal('hide');
                $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i> Confirm Delivered');
                if (res.success) {
                    $('#cust-sd-badge-' + id).removeClass().addClass('badge badge-success').text('Done');
                    $('#cust-sd-info-' + id).html('<span class="badge badge-success">Done</span><div style="font-size: 11px; color: #16a34a; margin-top: 3px;"><i class="fas fa-check mr-1"></i>Delivered Today by ' + (res.data ? res.data.done_by_name : 'Staff') + '</div>');
                    $('#cust-sd-actions-' + id).html('<span class="text-muted" style="font-size: 11px;"><i class="fas fa-check-double text-success mr-1"></i>Delivered</span>');
                    if (window.toastr) {
                        toastr.success(res.message);
                    } else {
                        alert(res.message);
                    }
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i> Confirm Delivered');
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Error updating service status';
                if (window.toastr) { toastr.error(msg); } else { alert(msg); }
            }
        });
    });

    // Open Refund Modal
    $(document).on('click', '.btn-open-refund', function() {
        var id = $(this).data('id');
        var receipt = $(this).data('receipt');
        var balance = parseFloat($(this).data('balance') || 0);

        $('#rf-advance-id').val(id);
        $('#rf-receipt-no').text(receipt);
        $('#rf-balance-display').text('৳' + balance.toFixed(2));
        $('#rf-refund-amount').val(balance.toFixed(2)).attr('max', balance);
        $('#rf-deduction-fee').val('0.00');
        $('#opt-action-refund').prop('checked', true).trigger('change');
        $('#rf-reason').val('');
        $('#modal-refund-advance').modal('show');
    });

    // Toggle Action Type (Refund vs Keep Credit)
    $('input[name=action_type]').on('change', function() {
        if ($(this).val() === 'credit_kept') {
            $('#wrap-refund-fields').slideUp(150);
            $('#wrap-credit-hint').slideDown(150);
            $('#rf-refund-amount').prop('required', false);
            $('#rf-refund-method').prop('required', false);
            $('#btn-submit-refund').removeClass('btn-danger').addClass('btn-secondary').html('<i class="fas fa-wallet mr-1"></i> Confirm Retain Credit');
        } else {
            $('#wrap-refund-fields').slideDown(150);
            $('#wrap-credit-hint').slideUp(150);
            $('#rf-refund-amount').prop('required', true);
            $('#rf-refund-method').prop('required', true);
            $('#btn-submit-refund').removeClass('btn-secondary').addClass('btn-danger').html('<i class="fas fa-check-circle mr-1"></i> Confirm Process');
        }
    });

    // Submit Refund / Credit Kept
    $('#form-advance-refund').on('submit', function(e) {
        e.preventDefault();
        var advId = $('#rf-advance-id').val();
        var $btn = $('#btn-submit-refund');
        $btn.prop('disabled', true).prepend('<i class="fas fa-spinner fa-spin mr-1"></i>');

        $.ajax({
            url: '{{ url("admin/advances") }}/' + advId + '/refund',
            type: 'POST',
            data: $(this).serialize(),
            success: function(res) {
                $('#modal-refund-advance').modal('hide');
                if (res.success) {
                    if (res.refund_receipt_url) {
                        window.open(res.refund_receipt_url, '_blank');
                    }
                    alert(res.message);
                    location.reload();
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).find('.fa-spinner').remove();
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Error processing refund.';
                alert(msg);
            }
        });
    });
});
</script>
@endsection
