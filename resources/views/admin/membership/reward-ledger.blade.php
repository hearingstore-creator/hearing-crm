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
                        <h5><i class="fas fa-list-alt"></i> Reward Ledger</h5>
                    </div>

                    {{-- Search Filters --}}
                    <div class="card-header">
                        <form method="GET">
                            <div class="row">
                                <div class="col-md-2">
                                    <select class="form-control select2" name="type">
                                        <option value="">Select Type</option>
                                        <option value="earn" {{ request('type') == 'earn' ? 'selected' : '' }}>Earn</option>
                                        <option value="redeem" {{ request('type') == 'redeem' ? 'selected' : '' }}>Redeem</option>
                                        <option value="referral" {{ request('type') == 'referral' ? 'selected' : '' }}>Referral</option>
                                        <option value="milestone" {{ request('type') == 'milestone' ? 'selected' : '' }}>Milestone</option>
                                    </select>
                                </div>

                                <div class="col-md-2">
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

                                <div class="col-md-1">
                                    <button class="btn btn-info" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <a href="{{ url('admin/membership/reward-ledger') }}" class="btn btn-primary">
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
                                    <th>Customer</th>
                                    <th>Type</th>
                                    <th>Points</th>
                                    <th>Reference</th>
                                    <th>Expiry Date</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($ledgers ?? [] as $key => $ledger)
                                <tr>
                                    <td>{{ $ledgers->firstItem() + $key }}</td>
                                    <td>{{ $ledger->created_at->format('Y-m-d H:i') }}</td>
                                    <td>
                                        @if($ledger->customer && $ledger->customer->lead)
                                            {{ $ledger->customer->lead->full_name }}
                                            @if($ledger->customer->membershipTier)
                                                <br>
                                                <span class="badge badge-sm
                                                    @if($ledger->customer->membershipTier->name == 'Bronze') badge-secondary
                                                    @elseif($ledger->customer->membershipTier->name == 'Silver') badge-info
                                                    @elseif($ledger->customer->membershipTier->name == 'Gold') badge-warning
                                                    @else badge-primary
                                                    @endif">
                                                    {{ $ledger->customer->membershipTier->name }}
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge
                                            @if($ledger->type == 'earn' || (is_object($ledger->type) && $ledger->type->value == 'earn')) badge-success
                                            @elseif($ledger->type == 'redeem' || (is_object($ledger->type) && $ledger->type->value == 'redeem')) badge-warning
                                            @elseif($ledger->type == 'expire' || (is_object($ledger->type) && $ledger->type->value == 'expire')) badge-danger
                                            @elseif($ledger->type == 'referral' || (is_object($ledger->type) && $ledger->type->value == 'referral')) badge-info
                                            @elseif($ledger->type == 'milestone' || (is_object($ledger->type) && $ledger->type->value == 'milestone')) badge-primary
                                            @else badge-secondary
                                            @endif">
                                            {{ is_object($ledger->type) ? $ledger->type->label() : ucfirst($ledger->type) }}
                                        </span>
                                    </td>
                                    <td class="@if($ledger->points > 0) text-success @else text-danger @endif">
                                        <strong>
                                            @if($ledger->points > 0) +@endif
                                            {{ number_format($ledger->points) }}
                                        </strong>
                                    </td>
                                    <td>
                                        @if($ledger->sale_master_id)
                                            <span class="text-info">
                                                <i class="fas fa-shopping-cart"></i>
                                                {{ $ledger->saleMaster->sale_invoice_no ?? 'N/A' }}
                                            </span>
                                        @elseif($ledger->referral_id)
                                            <span class="text-primary">
                                                <i class="fas fa-user-plus"></i>
                                                Referral #{{ $ledger->referral_id }}
                                            </span>
                                        @else
                                            {{ $ledger->batch_reference ?? 'N/A' }}
                                        @endif
                                    </td>
                                    <td>
                                        @if($ledger->expiry_date)
                                            @if($ledger->expiry_date->isPast())
                                                <span class="text-danger">
                                                    {{ $ledger->expiry_date->format('Y-m-d') }}
                                                    <i class="fas fa-times-circle"></i> Expired
                                                </span>
                                            @elseif($ledger->expiry_date->between(now(), now()->addDays(7)))
                                                <span class="text-warning">
                                                    {{ $ledger->expiry_date->format('Y-m-d') }}
                                                    <i class="fas fa-exclamation-triangle"></i> Expiring
                                                </span>
                                            @else
                                                {{ $ledger->expiry_date->format('Y-m-d') }}
                                            @endif
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center">No transactions found</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer">
                        {{ $ledgers?->appends(request()->query())->links() }}
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
