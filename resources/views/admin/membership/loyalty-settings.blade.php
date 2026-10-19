@extends('admin.layouts.master')
@section('page_title', 'Loyalty Settings')
@section('breadcrumb_parent', 'Membership & Rewards')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<style>
    .settings-card {
        background: var(--card-bg, #fff);
        border: 1px solid var(--card-border, #e2e8f0);
        border-radius: 14px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        overflow: hidden;
        max-width: 800px;
    }
    .settings-section-title {
        font-size: 15px;
        font-weight: 700;
        color: var(--page-text, #1e293b);
        margin-bottom: 6px;
    }
    .settings-hint {
        font-size: 12px;
        color: var(--page-text-muted, #64748b);
        margin-top: 4px;
    }
    .settings-row {
        padding: 20px 24px;
        border-bottom: 1px solid #f1f5f9;
    }
    .settings-row:last-child {
        border-bottom: none;
    }
</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in" style="margin-bottom: 24px;">
        <div class="page-title-group">
            <h1><i class="fas fa-sliders-h" style="color: var(--accent-500, #6366f1); margin-right: 8px;"></i>Loyalty Settings</h1>
            <p>Configure global redemption value, expiration period, and redemption restrictions</p>
        </div>
    </div>

    @include('admin.alert_message')

    @if ($errors->any())
    <div class="alert alert-danger crm-animate-in" style="border-radius: 10px; max-width: 800px;">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="settings-card crm-animate-in delay-1" id="move-settings" style="margin-bottom: 18px;">
        <div class="settings-row">
            <div class="settings-section-title"><i class="fas fa-exchange-alt text-primary mr-1"></i> Move membership amounts between sites</div>
            <div class="settings-hint">Amounts (tiers, milestones, loyalty settings) are saved in each site's own database, so changing them on a test copy does not change the live site. Export them here, then import the file on the other site. You see what would change before anything is saved. Customers, points and sales are never touched.</div>
            <div class="mt-3 d-flex flex-wrap" style="gap:10px; align-items:center;">
                <a href="{{ route('membership.settings.export') }}" class="btn btn-secondary btn-sm" id="btn-export-settings"><i class="fas fa-download mr-1"></i>Export this site's amounts</a>
                <form action="{{ route('membership.settings.import') }}" method="POST" enctype="multipart/form-data" class="d-flex" style="gap:8px; align-items:center;">
                    @csrf
                    <input type="file" name="settings_file" accept=".json,application/json" required class="form-control-file" style="max-width:260px;">
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-preview-import"><i class="fas fa-upload mr-1"></i>Review import</button>
                </form>
            </div>
        </div>
    </div>

    <div class="settings-card crm-animate-in delay-1">
        <form action="{{ route('membership.settings.update') }}" method="POST">
            @csrf

            {{-- Point Value (Taka per Point) --}}
            <div class="settings-row">
                <div class="row align-items-center">
                    <div class="col-md-7">
                        <div class="settings-section-title">
                            <i class="fas fa-coins text-warning mr-1"></i> Point Conversion Value
                        </div>
                        <div class="settings-hint">
                            How much 1 loyalty point is worth when redeemed on a sale. Default is ৳1.00 per point.
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text font-weight-bold">৳</span>
                            </div>
                            <input type="number" step="0.01" min="0.01" max="1000" name="point_value" class="form-control font-weight-bold text-right" value="{{ old('point_value', $setting->point_value) }}" required>
                            <div class="input-group-append">
                                <span class="input-group-text text-muted">/ pt</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Expiry Months --}}
            <div class="settings-row">
                <div class="row align-items-center">
                    <div class="col-md-7">
                        <div class="settings-section-title">
                            <i class="fas fa-calendar-alt text-info mr-1"></i> Point Expiry Duration
                        </div>
                        <div class="settings-hint">
                            Number of months after which newly earned and bonus points expire (1 - 60 months). Default is 12 months. Existing ledger rows keep their old expiry.
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="input-group">
                            <input type="number" step="1" min="1" max="60" name="expiry_months" class="form-control font-weight-bold text-right" value="{{ old('expiry_months', $setting->expiry_months) }}" required>
                            <div class="input-group-append">
                                <span class="input-group-text text-muted">months</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Minimum Redeem Points --}}
            <div class="settings-row">
                <div class="row align-items-center">
                    <div class="col-md-7">
                        <div class="settings-section-title">
                            <i class="fas fa-lock text-primary mr-1"></i> Minimum Points Required to Redeem
                        </div>
                        <div class="settings-hint">
                            Minimum points a patient must hold in their wallet before redemption is allowed on a sale. Set to 0 for no threshold.
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="input-group">
                            <input type="number" step="1" min="0" name="min_redeem_points" class="form-control font-weight-bold text-right" value="{{ old('min_redeem_points', $setting->min_redeem_points) }}" required>
                            <div class="input-group-append">
                                <span class="input-group-text text-muted">pts</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Maximum Invoice Redeem Percentage --}}
            <div class="settings-row">
                <div class="row align-items-center">
                    <div class="col-md-7">
                        <div class="settings-section-title">
                            <i class="fas fa-percentage text-success mr-1"></i> Maximum Redemption Share
                        </div>
                        <div class="settings-hint">
                            The largest percentage of an invoice gross total that points may pay (1 - 100%). Default is 100%.
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="input-group">
                            <input type="number" step="0.01" min="1" max="100" name="max_redeem_percent" class="form-control font-weight-bold text-right" value="{{ old('max_redeem_percent', $setting->max_redeem_percent) }}" required>
                            <div class="input-group-append">
                                <span class="input-group-text font-weight-bold">%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div style="background: #f8fafc; padding: 18px 24px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end;">
                @if(\App\Classes\Helper::hasPermission('loyalty_settings.edit') && (!auth()->user()->branchId() || auth()->user()->isSuperAdmin()))
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fas fa-save mr-1"></i> Save Loyalty Settings
                </button>
                @endif
            </div>
        </form>
    </div>

</div>
@endsection
