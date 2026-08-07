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
                        <h5><i class="fas fa-level-up-alt"></i> Tier Upgrades History</h5>
                    </div>

                    {{-- Search Filters --}}
                    <div class="card-header">
                        <form method="GET">
                            <div class="row">
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
                                    <a href="{{ url('admin/membership/upgrades') }}" class="btn btn-primary">
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
                                    <th>From Tier</th>
                                    <th>To Tier</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($upgrades ?? [] as $key => $upgrade)
                                <tr>
                                    <td>{{ $upgrades->firstItem() + $key }}</td>
                                    <td>{{ $upgrade->upgraded_at->format('d M Y, h:i A') }}</td>
                                    <td>
                                        @if($upgrade->customer && $upgrade->customer->lead)
                                            {{ $upgrade->customer->lead->full_name }}
                                            <br>
                                            <small class="text-muted">{{ $upgrade->customer->customer_uid ?? '' }}</small>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($upgrade->oldTier)
                                            <span class="badge
                                                @if($upgrade->oldTier->name == 'Bronze') badge-secondary
                                                @elseif($upgrade->oldTier->name == 'Silver') badge-info
                                                @elseif($upgrade->oldTier->name == 'Gold') badge-warning
                                                @else badge-primary
                                                @endif">
                                                {{ $upgrade->oldTier->name }}
                                            </span>
                                        @else
                                            <span class="badge badge-secondary">None</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($upgrade->newTier)
                                            <span class="badge
                                                @if($upgrade->newTier->name == 'Bronze') badge-secondary
                                                @elseif($upgrade->newTier->name == 'Silver') badge-info
                                                @elseif($upgrade->newTier->name == 'Gold') badge-warning
                                                @else badge-primary
                                                @endif">
                                                {{ $upgrade->newTier->name }}
                                            </span>
                                        @else
                                            <span class="badge badge-secondary">None</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center">No upgrades found</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer">
                        {{ $upgrades?->appends(request()->query())->links() }}
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
