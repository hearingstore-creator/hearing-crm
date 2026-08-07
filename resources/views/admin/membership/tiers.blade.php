@extends('admin.layouts.master')

@section('content')
<section class="content pt-3">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card card-outline card-primary">
                    <div class="card-header">
                        <h5><i class="fas fa-crown"></i> Membership Tiers</h5>
                    </div>

                    @include('admin.alert_message')

                    <div class="card-body">
                        <div class="row">
                            @foreach ($tiers as $tier)
                            @php
                                $borderColor = 'border-secondary';
                                $bgColor = 'bg-secondary';
                                if($tier->name == 'Silver') {
                                    $borderColor = 'border-info';
                                    $bgColor = 'bg-info';
                                } elseif($tier->name == 'Gold') {
                                    $borderColor = 'border-warning';
                                    $bgColor = 'bg-warning';
                                } elseif($tier->name == 'Platinum') {
                                    $borderColor = 'border-primary';
                                    $bgColor = 'bg-primary';
                                }
                            @endphp
                            <div class="col-md-3 mb-4">
                                <div class="card {{ $borderColor }}">
                                    <div class="card-header {{ $bgColor }} text-white">
                                        <h5 class="mb-0 text-center">{{ $tier->name }}</h5>
                                    </div>
                                    <div class="card-body">
                                        <dl class="row">
                                            <dt class="col-sm-7">Purchase Range:</dt>
                                            <dd class="col-sm-5">
                                                ${{ number_format($tier->min_purchase, 2) }} -
                                                @if($tier->max_purchase)
                                                    ${{ number_format($tier->max_purchase, 2) }}
                                                @else
                                                    ∞
                                                @endif
                                            </dd>

                                            <dt class="col-sm-7">Earn Rate:</dt>
                                            <dd class="col-sm-5 text-success">
                                                <strong>{{ $tier->earn_percentage }}%</strong>
                                            </dd>

                                            <dt class="col-sm-7">Referral Reward:</dt>
                                            <dd class="col-sm-5 text-info">
                                                <strong>{{ number_format($tier->referral_reward_points) }} pts</strong>
                                            </dd>

                                            <dt class="col-sm-7">Total Customers:</dt>
                                            <dd class="col-sm-5">
                                                <span class="badge badge-lg badge-dark">{{ $tier->customers_count }}</span>
                                            </dd>

                                            <dt class="col-sm-7">Active Wallets:</dt>
                                            <dd class="col-sm-5">
                                                <span class="badge badge-lg badge-success">{{ $tier->active_wallets ?? 0 }}</span>
                                            </dd>

                                            <dt class="col-sm-7">Points Issued:</dt>
                                            <dd class="col-sm-5">
                                                <span class="badge badge-lg badge-primary">{{ number_format($tier->total_points_issued ?? 0) }}</span>
                                            </dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="callout callout-info">
                                    <h5><i class="fas fa-info-circle"></i> How Points Work</h5>
                                    <ul class="mb-0">
                                        <li>Customers earn points based on their membership tier's earn percentage</li>
                                        <li>Points expire after 12 months from earning date</li>
                                        <li>Maximum 30% of invoice can be paid using points</li>
                                        <li>Tiers automatically upgrade based on cumulative purchases</li>
                                        <li>Referral rewards are based on referrer's tier level</li>
                                    </ul>
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
