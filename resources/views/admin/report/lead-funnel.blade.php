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
                                <h5>Lead Funnel & Conversion Report</h5>
                            </div>
                            <div class="col-md-6 text-right">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-success dropdown-toggle" data-toggle="dropdown">
                                        <i class="fas fa-file-excel"></i> Export Excel
                                    </button>
                                    <div class="dropdown-menu">
                                        <a class="dropdown-item" href="{{ route('report.lead-funnel.export', ['type' => 'full', 'format' => 'xlsx']) }}?{{ http_build_query(request()->query()) }}">
                                            <i class="fas fa-file-excel text-success"></i> Table Report
                                        </a>
                                        <a class="dropdown-item" href="{{ route('report.lead-funnel.export', ['type' => 'summary', 'format' => 'xlsx']) }}?{{ http_build_query(request()->query()) }}">
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
                                        {{-- Source Filter --}}
                                        <div class="col-md-2">
                                            <label class="form-label small">Source</label>
                                            <select class="form-control select" name="source_of_entry">
                                                <option value="">All Sources</option>
                                                @foreach ($sources ?? [] as $source)
                                                    <option value="{{ $source->value }}" {{ $source_of_entry === $source->value ? 'selected' : '' }}>
                                                        {{ $source->label() }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Staff Filter --}}
                                        <div class="col-md-2">
                                            <label class="form-label small">Staff</label>
                                            <select class="form-control select" name="staff_id">
                                                <option value="">All Staff</option>
                                                @foreach ($staffs ?? [] as $staff)
                                                    <option value="{{ $staff->id }}" {{ $staff_id == $staff->id ? 'selected' : '' }}>
                                                        {{ $staff->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Vendor Filter --}}
                                        <div class="col-md-2">
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

                                        {{-- Stage Filter --}}
                                        <div class="col-md-2">
                                            <label class="form-label small">Stage</label>
                                            <select class="form-control select" name="stage">
                                                <option value="">All Stages</option>
                                                @foreach ($stages ?? [] as $stage)
                                                    <option value="{{ $stage->value }}" {{ $stage === $stage->value ? 'selected' : '' }}>
                                                        {{ $stage->label() }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- From Date --}}
                                        <div class="col-md-2">
                                            <label class="form-label small">From</label>
                                            <input type="date" class="form-control" name="start_date" value="{{ $start_date ?? '' }}">
                                        </div>

                                        {{-- To Date --}}
                                        <div class="col-md-2">
                                            <label class="form-label small">To</label>
                                            <div class="input-group">
                                                <input type="date" class="form-control" name="end_date" value="{{ $end_date ?? '' }}">
                                                <div class="input-group-append">
                                                    <button type="submit" class="btn btn-info">
                                                        <i class="fas fa-search"></i>
                                                    </button>
                                                    <a href="{{ url('admin/report/lead-funnel') }}" class="btn btn-primary">
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
                        <h3 class="text-center pt-2 title report-title">Lead Funnel & Conversion Report</h3>
                        <h5 class="text-center title pb-2 report-title">
                            @if(isset($start_date) && isset($end_date))
                                <strong>Date Range:</strong> {{ $start_date }} To {{ $end_date }}
                            @endif
                        </h5>

                        {{-- Summary Cards --}}
                        <div class="row mb-3">
                            <div class="col-6 col-md-3 mb-2">
                                <div class="card bg-primary text-white">
                                    <div class="card-body py-2 text-center">
                                        <h6 class="card-title small mb-1">Total Leads</h6>
                                        <h3 class="mb-0">{{ number_format($totalLeads ?? 0) }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-2">
                                <div class="card bg-success text-white">
                                    <div class="card-body py-2 text-center">
                                        <h6 class="card-title small mb-1">Won Leads</h6>
                                        <h3 class="mb-0">{{ number_format($wonLeads ?? 0) }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-2">
                                <div class="card bg-danger text-white">
                                    <div class="card-body py-2 text-center">
                                        <h6 class="card-title small mb-1">Lost Leads</h6>
                                        <h3 class="mb-0">{{ number_format($lostLeads ?? 0) }}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-2">
                                <div class="card bg-info text-white">
                                    <div class="card-body py-2 text-center">
                                        <h6 class="card-title small mb-1">Conversion Rate</h6>
                                        <h3 class="mb-0">{{ number_format($overallConversionRate ?? 0, 2) }}%</h3>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Funnel Visualization --}}
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="card card-info card-outline">
                                    <div class="card-header">
                                        <h5 class="card-title"><i class="fas fa-filter mr-2"></i>Lead Funnel Visualization</h5>
                                    </div>
                                    <div class="card-body">
                                        @isset($reportData)
                                            @foreach ($reportData as $row)
                                                @if($row->count > 0)
                                                <div class="row align-items-center mb-2">
                                                    <div class="col-md-3">
                                                        <strong>{{ $row->stage }}</strong>
                                                        <span class="badge badge-primary ml-2">{{ $row->count }}</span>
                                                    </div>
                                                    <div class="col-md-7">
                                                        <div class="progress">
                                                            <div class="progress-bar progress-bar-striped bg-{{ $row->stage_value === 'won' ? 'success' : ($row->stage_value === 'lost' ? 'danger' : 'primary') }}"
                                                                 role="progressbar"
                                                                 style="width: {{ $row->overall_conversion }}%"
                                                                 aria-valuenow="{{ $row->overall_conversion }}"
                                                                 aria-valuemin="0"
                                                                 aria-valuemax="100">
                                                                {{ $row->overall_conversion }}%
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2 text-right">
                                                        <small class="text-muted">{{ $row->conversion_percent }}% conv.</small>
                                                    </div>
                                                </div>
                                                @endif
                                            @endforeach
                                        @endisset
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Main Report Table --}}
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0" id="lead-funnel-table">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width: 50px;">SL</th>
                                        <th>Stage</th>
                                        <th class="text-center">Count</th>
                                        <th class="text-center">Conversion %</th>
                                        <th class="text-center">Overall Conversion %</th>
                                        <th class="text-center">Avg Time (Days)</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @isset($reportData)
                                        @foreach ($reportData as $key => $row)
                                            <tr>
                                                <td class="text-center">{{ ++$key }}</td>
                                                <td><strong>{{ $row->stage }}</strong></td>
                                                <td class="text-center">
                                                    <span class="badge badge-{{ $row->stage_value === 'won' ? 'success' : ($row->stage_value === 'lost' ? 'danger' : 'primary') }}">
                                                        {{ number_format($row->count) }}
                                                    </span>
                                                </td>
                                                <td class="text-center">{{ number_format($row->conversion_percent, 2) }}%</td>
                                                <td class="text-center">{{ number_format($row->overall_conversion, 2) }}%</td>
                                                <td class="text-center">
                                                    @if($row->avg_time_days)
                                                        {{ number_format($row->avg_time_days, 1) }} days
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <p class="text-muted mb-0">No lead data found</p>
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>

                        @isset($reportData)
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="alert alert-info mb-0">
                                    <h6 class="mb-2"><i class="fas fa-info-circle"></i> Summary</h6>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <strong>Total Leads:</strong> {{ number_format($totalLeads ?? 0) }}
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Conversion Rate:</strong> {{ number_format($overallConversionRate ?? 0, 2) }}%
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Won:</strong> {{ number_format($wonLeads ?? 0) }}
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Lost:</strong> {{ number_format($lostLeads ?? 0) }}
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
    });
    </script>
@endpush
@endsection
