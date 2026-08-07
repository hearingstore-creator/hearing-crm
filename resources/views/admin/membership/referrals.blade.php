@extends('admin.layouts.master')
@push('css')
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
@endpush

@section('content')
<section class="content pt-3">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card card-outline card-primary">
                    <div class="card-header">
                        <h5><i class="fas fa-user-plus"></i> Referrals</h5>
                    </div>

                    {{-- Search Filters --}}
                    <div class="card-header">
                        <form method="GET">
                            <div class="row">
                                <div class="col-md-3">
                                    <select class="form-control select2" name="status">
                                        <option value="">Select Status</option>
                                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <select class="form-control select2" name="referrer_id">
                                        <option value="">Select Referrer</option>
                                        @foreach ($customers ?? [] as $customer)
                                            <option value="{{ $customer->id }}"
                                                {{ request('referrer_id') == $customer->id ? 'selected' : '' }}>
                                                {{ $customer->lead->full_name ?? 'N/A' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <input type="date" name="start_date"
                                        class="form-control"
                                        value="{{ request('start_date') }}">
                                </div>

                                <div class="col-md-2">
                                    <input type="date" name="end_date"
                                        class="form-control"
                                        value="{{ request('end_date') }}">
                                </div>

                                <div class="col-md-2">
                                    <button class="btn btn-info" type="submit">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                    <a href="{{ url('admin/membership/referrals') }}" class="btn btn-primary">
                                        <i class="fas fa-sync-alt"></i>
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>

                    @include('admin.alert_message')

                    <div class="card-body table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>SL</th>
                                    <th>Date</th>
                                    <th>Referrer</th>
                                    <th>Referral Code</th>
                                    <th>Referred Customer</th>
                                    <th>Status</th>
                                    <th>Reward Points</th>
                                    <th>Rewarded Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($referrals ?? [] as $key => $referral)
                                <tr>
                                    <td>{{ $referrals->firstItem() + $key }}</td>
                                    <td>{{ $referral->created_at->format('Y-m-d') ?? 'N/A' }}</td>
                                    <td>
                                        @if($referral->referrer && $referral->referrer->lead)
                                            {{ $referral->referrer->lead->full_name }}
                                            @if($referral->referrer->membershipTier)
                                                <br>
                                                <span class="badge badge-sm
                                                    @if($referral->referrer->membershipTier->name == 'Bronze') badge-secondary
                                                    @elseif($referral->referrer->membershipTier->name == 'Silver') badge-info
                                                    @elseif($referral->referrer->membershipTier->name == 'Gold') badge-warning
                                                    @else badge-primary
                                                    @endif">
                                                    {{ $referral->referrer->membershipTier->name }}
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($referral->referrer)
                                            <code>{{ $referral->referrer->referral_code ?? 'N/A' }}</code>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($referral->referredCustomer && $referral->referredCustomer->lead)
                                            {{ $referral->referredCustomer->lead->full_name }}
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge
                                            @if($referral->status == 'pending') badge-warning
                                            @elseif($referral->status == 'completed') badge-success
                                            @elseif($referral->status == 'cancelled') badge-danger
                                            @else badge-secondary
                                            @endif">
                                            {{ ucfirst($referral->status->value) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($referral->status->value == \App\Enums\ReferralStatus::COMPLETED->value)
                                            @php
                                                $rewardPoints = $referral->referrer->membershipTier->referral_reward_points ?? 0;
                                            @endphp
                                            <span class="text-success">
                                                <strong>+{{ number_format($rewardPoints) }}</strong>
                                            </span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($referral->rewarded_at)
                                            {{ $referral->rewarded_at->format('Y-m-d') }}
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($referral->status->value == \App\Enums\ReferralStatus::PENDING->value)
                                        <form action="{{ route('membership.referrals.delete', $referral->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this referral?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="9" class="text-center">No referrals found</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer">
                        {{ $referrals?->appends(request()->query())->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@push('scripts')
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            $('.select2').select2();
        });
    </script>
@endpush
@endsection
