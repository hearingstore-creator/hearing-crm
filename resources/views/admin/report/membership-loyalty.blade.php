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
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Membership & Loyalty Report</h5>
                            </div>
                            <div class="col-md-6 text-right">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-success dropdown-toggle" data-toggle="dropdown">
                                        <i class="fas fa-file-excel"></i> Export Excel
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="{{ route('report.membership-loyalty.export', ['type' => 'full', 'format' => 'xlsx']) }}?{{ http_build_query(request()->query()) }}">
                                            <i class="fas fa-file-excel text-success"></i> Table Report
                                        </a>
                                        <a class="dropdown-item" href="{{ route('report.membership-loyalty.export', ['type' => 'summary', 'format' => 'xlsx']) }}?{{ http_build_query(request()->query()) }}">
                                            <i class="fas fa-file-excel text-primary"></i> Summary Report
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Search Filters --}}
                        <div class="row pb-3 mt-3">
                            <div class="col-md-12">
                                <form method="GET">
                                    <div class="row">
                                        {{-- Tier Filter --}}
                                        <div class="col-md-3">
                                            <label class="form-label small">Tier</label>
                                            <select class="form-control select" name="tier_id">
                                                <option value="">All Tiers</option>
                                                @foreach ($tiers ?? [] as $tier)
                                                    <option value="{{ $tier->id }}" {{ $tier_id == $tier->id ? 'selected' : '' }}>
                                                        {{ $tier->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Vendor Filter --}}
                                        <div class="col-md-3">
                                            <label class="form-label small">Vendor</label>
                                            <select class="form-control select" name="vendor_id">
                                                <option value="">All Vendors</option>
                                                @foreach ($vendors ?? [] as $vendor)
                                                    <option value="{{ $vendor->id }}" {{ $vendor_id == $vendor->id ? 'selected' : '' }}>
                                                        {{ $vendor->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Points Status Filter --}}
                                        <div class="col-md-3">
                                            <label class="form-label small">Points Status</label>
                                            <select class="form-control select" name="points_status">
                                                <option value="">All Points</option>
                                                <option value="issued" {{ $points_status === 'issued' ? 'selected' : '' }}>Issued</option>
                                                <option value="expiring" {{ $points_status === 'expiring' ? 'selected' : '' }}>Expiring</option>
                                            </select>
                                        </div>

                                        {{-- From Date --}}
                                        <div class="col-md-3">
                                            <label class="form-label small">From</label>
                                            <input type="date" class="form-control" name="start_date" value="{{ $start_date ?? '' }}">
                                        </div>

                                        {{-- To Date --}}
                                        <div class="col-md-3">
                                            <label class="form-label small">To</label>
                                            <div class="input-group">
                                                <input type="date" class="form-control" name="end_date" value="{{ $end_date ?? '' }}">
                                                <div class="input-group-append">
                                                    <button type="submit" class="btn btn-info">
                                                        <i class="fas fa-search"></i>
                                                    </button>
                                                    <a href="{{ url('admin/report/membership-loyalty') }}" class="btn btn-primary">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    @include('admin.alert_message')

                    <div class="card-body table-responsive" id="print-area">
                        <h3 class="text-center pt-2 title report-title">Membership & Loyalty Report</h3>
                        <h5 class="text-center title pb-2 report-title">
                            @if(isset($start_date) && isset($end_date))
                                <strong>Date Range:</strong> {{ $start_date }} To {{ $end_date }}
                            @endif
                        </h5>

                        {{-- Summary Cards --}}
                        @isset($reportData)
                        <div class="row mb-3">
                            <div class="col-6 col-md-3 mb-2">
                                <div class="card bg-primary text-white">
                                    <div class="card-body py-2 text-center">
                                        <h6 class="card-title small mb-1">Total Members</h6>
                                        <h3 class="mb-0">{{ number_format($totalMembers ?? 0) }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-2">
                                <div class="card bg-success text-white">
                                    <div class="card-body py-2 text-center">
                                        <h6 class="card-title small mb-1">Total Revenue</h6>
                                        <h3 class="mb-0">{{ number_format($totalRevenue ?? 0, 2) }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-2">
                                <div class="card bg-info text-white">
                                    <div class="card-body py-2 text-center">
                                        <h6 class="card-title small mb-1">Points Issued</h6>
                                        <h3 class="mb-0">{{ number_format($totalPointsIssued ?? 0, 0) }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-2">
                                <div class="card bg-warning text-white">
                                    <div class="card-body py-2 text-center">
                                        <h6 class="card-title small mb-1">Points Redeemed</h6>
                                        <h3 class="mb-0">{{ number_format($totalPointsRedeemed ?? 0, 0) }}</h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- Main Report Table --}}
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0" id="membership-report-table">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width: 50px;">SL</th>
                                        <th>Tier</th>
                                        <th class="text-center">Members</th>
                                        <th class="text-right">Revenue</th>
                                        <th class="text-right">Points Issued</th>
                                        <th class="text-right">Points Redeemed</th>
                                        <th class="text-right">Points Balance</th>
                                    </tr>
                                </thead>

                            <tbody>
                                @isset($reportData)
                                    @foreach ($reportData as $key => $row)
                                        <tr>
                                            <td class="text-center">{{ ++$key }}</td>
                                            <td><strong>{{ $row->tier ?? 'N/A' }}</strong></td>
                                            <td class="text-center">{{ number_format($row->members) }}</td>
                                            <td class="text-right">{{ number_format($row->revenue, 2) }}</td>
                                            <td class="text-right">{{ number_format($row->points_issued, 0) }}</td>
                                            <td class="text-right">{{ number_format($row->points_redeemed, 0) }}</td>
                                            <td class="text-right"><strong>{{ number_format($row->points_balance, 0) }}</strong></td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <p class="text-muted mb-0">No membership data found</p>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>

                            @isset($reportData)
                            <tfoot>
                                <tr class="font-weight-bold bg-light">
                                    <td colspan="2" class="text-right">Total:</td>
                                    <td class="text-center">{{ number_format($totalMembers ?? 0) }}</td>
                                    <td class="text-right">{{ number_format($totalRevenue ?? 0, 2) }}</td>
                                    <td class="text-right">{{ number_format($totalPointsIssued ?? 0, 0) }}</td>
                                    <td class="text-right">{{ number_format($totalPointsRedeemed ?? 0, 0) }}</td>
                                    <td class="text-right">{{ number_format(($totalPointsIssued ?? 0) - ($totalPointsRedeemed ?? 0), 0) }}</td>
                                </tr>
                            </tfoot>
                            @endif
                        </table>
                        </div>

                        {{-- Referral Summary Section --}}
                        @isset($referralSummary)
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card card-info card-outline">
                                    <div class="card-header">
                                        <h5 class="card-title"><i class="fas fa-users mr-2"></i>Referral Summary</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-6 col-md-3 mb-3">
                                                <div class="description-block border-right border-bottom-0">
                                                    <h5 class="description-header text-primary">{{ number_format($referralSummary->totalReferrals ?? 0) }}</h5>
                                                    <span class="description-text small">Total Referrals</span>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-3 mb-3">
                                                <div class="description-block border-right border-bottom-0">
                                                    <h5 class="description-header text-success">{{ number_format($referralSummary->successfulReferrals ?? 0) }}</h5>
                                                    <span class="description-text small">Successful Referrals</span>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-3 mb-3">
                                                <div class="description-block border-right border-bottom-0">
                                                    <h5 class="description-header text-warning">{{ number_format($referralSummary->pendingReferrals ?? 0) }}</h5>
                                                    <span class="description-text small">Pending Referrals</span>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-3 mb-3">
                                                <div class="description-block">
                                                    <h5 class="description-header text-info">{{ number_format($referralSummary->totalReferralPointsIssued ?? 0, 0) }}</h5>
                                                    <span class="description-text small">Referral Points Issued</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        @isset($reportData)
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="alert alert-info mb-0">
                                    <h6 class="mb-2"><i class="fas fa-info-circle"></i> Summary</h6>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <strong>Total Tiers:</strong> {{ $reportData->count() }}
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Total Members:</strong> {{ number_format($totalMembers ?? 0) }}
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Total Revenue:</strong> {{ number_format($totalRevenue ?? 0, 2) }}
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Net Points Balance:</strong> {{ number_format(($totalPointsIssued ?? 0) - ($totalPointsRedeemed ?? 0), 0) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
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
        // Initialize Select2
        $('.select').select2({
            placeholder: "Select an option",
            allowClear: true,
            width: '100%'
        });

        // Print functionality
        $('.print-btn').on('click', function(e) {
            e.preventDefault();
            var printArea = $('#print-area').html();
            var originalContents = $('body').html();

            $('body').html('<div class="container">' + printArea + '</div>');
            window.print();
            $('body').html(originalContents);
            location.reload();
        });
    });
    </script>
@endpush
@endsection
