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
                        <h5><i class="fas fa-wallet"></i> Reward Wallets</h5>
                    </div>

                    {{-- Search Filters --}}
                    <div class="card-header">
                        <form method="GET">
                            <div class="row">
                                <div class="col-md-3">
                                    <select class="form-control select2" name="tier_id">
                                        <option value="">Select Tier</option>
                                        @foreach ($tiers ?? [] as $tier)
                                            <option value="{{ $tier->id }}"
                                                {{ request('tier_id') == $tier->id ? 'selected' : '' }}>
                                                {{ $tier->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <select class="form-control select2" name="customer_id">
                                        <option value="">Select Customer</option>
                                        @foreach ($customers ?? [] as $customer)
                                            <option value="{{ $customer->id }}"
                                                {{ request('customer_id') == $customer->id ? 'selected' : '' }}>
                                                {{ $customer->lead->full_name ?? 'N/A' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <button class="btn btn-info" type="submit">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                    <a href="{{ url('admin/membership/wallets') }}" class="btn btn-primary">
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
                                    <th>Customer</th>
                                    <th>Customer UID</th>
                                    <th>Tier</th>
                                    <th>Total Earned</th>
                                    <th>Total Redeemed</th>
                                    <th>Expired</th>
                                    <th>Available Balance</th>
                                    <th>Expiring Soon</th>
                                    {{-- <th>Action</th> --}}
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($wallets ?? [] as $key => $customer)
                                <tr>
                                    <td>{{ $wallets->firstItem() + $key }}</td>
                                    <td>
                                        @if($customer && $customer->lead)
                                            {{ $customer->lead->full_name }}
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($customer)
                                            <code>{{ $customer->customer_uid ?? 'N/A' }}</code>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($customer && $customer->membershipTier)
                                            <span class="badge
                                                @if($customer->membershipTier->name == 'Bronze') badge-secondary
                                                @elseif($customer->membershipTier->name == 'Silver') badge-info
                                                @elseif($customer->membershipTier->name == 'Gold') badge-warning
                                                @else badge-primary
                                                @endif">
                                                {{ $customer->membershipTier->name }}
                                            </span>
                                        @else
                                            <span class="badge badge-secondary">None</span>
                                        @endif
                                    </td>
                                    <td>{{ number_format($customer->total_earned ?? 0) }}</td>
                                    <td>{{ number_format($customer->total_redeemed ?? 0) }}</td>
                                    <td class="text-danger">{{ number_format($customer->total_expired ?? 0) }}</td>
                                    <td>
                                        <strong class="text-success" style="font-size: 16px;">
                                            {{ number_format($customer->real_available_balance ?? 0) }}
                                        </strong>
                                    </td>
                                    <td>
                                        @if(($customer->expiring_soon ?? 0) > 0)
                                            <span class="badge badge-danger">{{ number_format($customer->expiring_soon) }}</span>
                                        @else
                                            <span class="badge badge-secondary">0</span>
                                        @endif
                                    </td>
                                    {{-- <td>
                                        <a href="{{ route('admin.membership.wallet-details', $customer->id) }}"
                                           class="btn btn-sm btn-info" title="View Details">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td> --}}
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="10" class="text-center">No wallets found</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer">
                        {{ $wallets?->appends(request()->query())->links() }}
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
