@extends('admin.layouts.master')

@section('content')
<section class="content pt-3">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card card-outline card-primary">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-6">
                                <h5><i class="fas fa-wallet"></i> Wallet Details</h5>
                            </div>
                            <div class="col-md-6 text-right">
                                <a href="{{ url('admin/membership/wallets') }}" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-arrow-left"></i> Back
                                </a>
                            </div>
                        </div>
                    </div>

                    @include('admin.alert_message')

                    <div class="card-body">
                        {{-- Customer Information --}}
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="callout callout-info">
                                    <h5><i class="fas fa-user"></i> Customer Information</h5>
                                    <dl class="row">
                                        <dt class="col-sm-2">Name:</dt>
                                        <dd class="col-sm-4">
                                            @if($wallet->customer && $wallet->customer->lead)
                                                {{ $wallet->customer->lead->full_name }}
                                            @else
                                                N/A
                                            @endif
                                        </dd>

                                        <dt class="col-sm-2">Customer UID:</dt>
                                        <dd class="col-sm-4">
                                            @if($wallet->customer)
                                                <code>{{ $wallet->customer->customer_uid ?? 'N/A' }}</code>
                                            @else
                                                N/A
                                            @endif
                                        </dd>

                                        <dt class="col-sm-2">Membership Tier:</dt>
                                        <dd class="col-sm-4">
                                            @if($wallet->customer && $wallet->customer->membershipTier)
                                                <span class="badge
                                                    @if($wallet->customer->membershipTier->name == 'Bronze') badge-secondary
                                                    @elseif($wallet->customer->membershipTier->name == 'Silver') badge-info
                                                    @elseif($wallet->customer->membershipTier->name == 'Gold') badge-warning
                                                    @else badge-primary
                                                    @endif">
                                                    {{ $wallet->customer->membershipTier->name }}
                                                </span>
                                            @else
                                                <span class="badge badge-secondary">None</span>
                                            @endif
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>

                        {{-- Wallet Statistics --}}
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="info-box bg-success">
                                    <span class="info-box-icon"><i class="fas fa-plus-circle"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Total Earned</span>
                                        <span class="info-box-number">{{ number_format($wallet->total_earned) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="info-box bg-warning">
                                    <span class="info-box-icon"><i class="fas fa-minus-circle"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Total Redeemed</span>
                                        <span class="info-box-number">{{ number_format($wallet->total_redeemed) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="info-box bg-danger">
                                    <span class="info-box-icon"><i class="fas fa-times-circle"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Total Expired</span>
                                        <span class="info-box-number">{{ number_format($wallet->total_expired) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="info-box bg-primary">
                                    <span class="info-box-icon"><i class="fas fa-wallet"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Available Balance</span>
                                        <span class="info-box-number">{{ number_format($wallet->real_available_balance) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if($expiring_points > 0)
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="alert alert-warning">
                                    <h5><i class="fas fa-exclamation-triangle"></i> Expiring Points Alert</h5>
                                    <p class="mb-0">
                                        <strong>{{ number_format($expiring_points) }} points</strong> will expire within the next 7 days.
                                    </p>
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- Transaction History --}}
                        <div class="row">
                            <div class="col-md-12">
                                <h5 class="mb-3"><i class="fas fa-history"></i> Recent Transactions</h5>

                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Type</th>
                                                <th>Points</th>
                                                <th>Reference</th>
                                                <th>Expiry Date</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            @forelse ($recent_transactions ?? [] as $transaction)
                                            <tr>
                                                <td>{{ $transaction->created_at->format('Y-m-d H:i') }}</td>
                                                <td>
                                                    <span class="badge
                                                        @if($transaction->type == 'earn' || (is_object($transaction->type) && $transaction->type->value == 'earn')) badge-success
                                                        @elseif($transaction->type == 'redeem' || (is_object($transaction->type) && $transaction->type->value == 'redeem')) badge-warning
                                                        @elseif($transaction->type == 'expire' || (is_object($transaction->type) && $transaction->type->value == 'expire')) badge-danger
                                                        @elseif($transaction->type == 'referral' || (is_object($transaction->type) && $transaction->type->value == 'referral')) badge-info
                                                        @elseif($transaction->type == 'milestone' || (is_object($transaction->type) && $transaction->type->value == 'milestone')) badge-primary
                                                        @else badge-secondary
                                                        @endif">
                                                        {{ is_object($transaction->type) ? $transaction->type->label() : ucfirst($transaction->type) }}
                                                    </span>
                                                </td>
                                                <td class="@if($transaction->points > 0) text-success @else text-danger @endif">
                                                    @if($transaction->points > 0) +@endif
                                                    {{ number_format($transaction->points) }}
                                                </td>
                                                <td>
                                                    @if($transaction->sale_master_id)
                                                        Sale: {{ $transaction->saleMaster->sale_invoice_no ?? 'N/A' }}
                                                    @elseif($transaction->referral_id)
                                                        Referral: #{{ $transaction->referral_id }}
                                                    @else
                                                        {{ $transaction->batch_reference ?? 'N/A' }}
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($transaction->expiry_date)
                                                        @if($transaction->expiry_date->isPast())
                                                            <span class="text-danger">
                                                                {{ $transaction->expiry_date->format('Y-m-d') }} (Expired)
                                                            </span>
                                                        @elseif($transaction->expiry_date->between(now(), now()->addDays(7)))
                                                            <span class="text-warning">
                                                                {{ $transaction->expiry_date->format('Y-m-d') }} (Expiring)
                                                            </span>
                                                        @else
                                                            {{ $transaction->expiry_date->format('Y-m-d') }}
                                                        @endif
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                            </tr>
                                            @empty
                                            <tr>
                                                <td colspan="5" class="text-center">No transactions found</td>
                                            </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-3">
                                    {{ $recent_transactions?->appends(request()->query())->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
