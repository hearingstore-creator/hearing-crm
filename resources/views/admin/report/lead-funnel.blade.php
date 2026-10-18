@extends('admin.layouts.master')
@section('page_title', 'Lead Funnel Report')
@section('breadcrumb_parent', 'Reports')
@section('breadcrumb_parent_url', url('admin/reports'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
<style>
/* Report Filter Bar */
.report-filter-bar {
    display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;
    padding: 20px; background: var(--gray-50); border-bottom: 1px solid var(--gray-200);
    border-radius: 12px 12px 0 0;
}
.report-filter-group { flex: 1; min-width: 140px; display: flex; flex-direction: column; gap: 6px; }
.report-filter-group label {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.05em; color: var(--page-text-muted); margin: 0;
}
.report-filter-group select, .report-filter-group input {
    font-size: 13px; border-radius: 6px; border: 1px solid var(--gray-200);
    padding: 8px 12px; background: var(--card-bg); color: var(--page-text);
    width: 100%; height: 38px;
}
.report-filter-actions { display: flex; gap: 8px; margin-left: auto; }

/* Stat Cards */
.stat-card-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin: 24px 20px;
}
.stat-card {
    border-radius: 12px; padding: 20px; display: flex; flex-direction: column; position: relative; overflow: hidden;
}
.stat-card .sc-icon {
    width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center;
    font-size: 18px; margin-bottom: 12px;
}
.stat-card .sc-value { font-size: 24px; font-weight: 800; line-height: 1; margin-bottom: 6px; }
.stat-card .sc-label { font-size: 12px; font-weight: 700; text-transform: uppercase; }

.sc-primary { background: #e0e7ff; border: 1px solid #c7d2fe; }
.sc-primary .sc-icon { background: #c7d2fe; color: #3730a3; }
.sc-primary .sc-value { color: #312e81; }
.sc-primary .sc-label { color: #4338ca; }

.sc-success { background: #dcfce7; border: 1px solid #bbf7d0; }
.sc-success .sc-icon { background: #bbf7d0; color: #166534; }
.sc-success .sc-value { color: #14532d; }
.sc-success .sc-label { color: #15803d; }

.sc-danger { background: #fee2e2; border: 1px solid #fecaca; }
.sc-danger .sc-icon { background: #fecaca; color: #991b1b; }
.sc-danger .sc-value { color: #7f1d1d; }
.sc-danger .sc-label { color: #b91c1c; }

.sc-warning { background: #fef3c7; border: 1px solid #fde68a; }
.sc-warning .sc-icon { background: #fde68a; color: #92400e; }
.sc-warning .sc-value { color: #78350f; }
.sc-warning .sc-label { color: #b45309; }

.sc-info { background: #e0f2fe; border: 1px solid #bae6fd; }
.sc-info .sc-icon { background: #bae6fd; color: #075985; }
.sc-info .sc-value { color: #0c4a6e; }
.sc-info .sc-label { color: #0369a1; }

.sc-purple { background: #ede9fe; border: 1px solid #ddd6fe; }
.sc-purple .sc-icon { background: #ddd6fe; color: #5b21b6; }
.sc-purple .sc-value { color: #4c1d95; }
.sc-purple .sc-label { color: #6d28d9; }

/* Funnel Visuals */
.funnel-container {
    padding: 0 20px 24px;
}
.funnel-stage-row {
    display: flex; align-items: center; gap: 16px; margin-bottom: 12px;
}
.fs-name { width: 140px; font-weight: 700; font-size: 13px; color: var(--page-text); display: flex; align-items: center; justify-content: space-between; }
.fs-count { background: var(--gray-100); padding: 2px 8px; border-radius: 99px; font-size: 11px; }
.fs-bar-wrap { flex: 1; height: 16px; background: #f3f4f6; border-radius: 99px; overflow: hidden; min-width: 0; }
.fs-bar { height: 100%; border-radius: 99px; transition: width 0.5s ease; }
.fs-won .fs-bar { background: #22c55e; }
.fs-lost .fs-bar { background: #ef4444; }
.fs-default .fs-bar { background: #3b82f6; }
.fs-pct { width: 60px; text-align: right; font-size: 12px; font-weight: 600; color: var(--page-text-muted); }

/* Select2 overrides */
.select2-container .select2-selection--single {
    height: 38px !important; border-color: var(--gray-200) !important; border-radius: 6px !important; display: flex; align-items: center;
}
.select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-filter" style="color: var(--accent-500); margin-right: 8px;"></i>Lead Funnel & Conversion</h1>
            <p>Analyze lead stages and conversion rates</p>
        </div>
        <div class="header-actions">
            <div class="dropdown">
                <button class="btn btn-success btn-sm dropdown-toggle" type="button" data-toggle="dropdown" style="border-radius:6px; font-weight:600;">
                    <i class="fas fa-file-excel mr-1"></i> Export Excel
                </button>
                <div class="dropdown-menu dropdown-menu-right" style="border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.1); border-color:var(--gray-200);">
                    <a class="dropdown-item" style="font-weight:600; font-size:13px;" href="{{ route('report.lead-funnel.export', ['type' => 'full', 'format' => 'xlsx']) }}?{{ http_build_query(request()->query()) }}">
                        <i class="fas fa-table text-success mr-2"></i> Table Report
                    </a>
                    <a class="dropdown-item" style="font-weight:600; font-size:13px;" href="{{ route('report.lead-funnel.export', ['type' => 'summary', 'format' => 'xlsx']) }}?{{ http_build_query(request()->query()) }}">
                        <i class="fas fa-chart-pie text-primary mr-2"></i> Summary Report
                    </a>
                </div>
            </div>
        </div>
    </div>

    @include('admin.alert_message')

    <div class="crm-table-card crm-animate-in delay-1">
        
        {{-- Filters --}}
        <form method="GET">
            <div class="report-filter-bar">
                <div class="report-filter-group">
                    <label>Source</label>
                    <select class="select" name="source_of_entry">
                        <option value="">All Sources</option>
                        @foreach ($sources ?? [] as $source)
                            <option value="{{ $source->value }}" {{ $source_of_entry === $source->value ? 'selected' : '' }}>
                                {{ $source->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="report-filter-group">
                    <label>Staff</label>
                    <select class="select" name="staff_id">
                        <option value="">All Staff</option>
                        @foreach ($staffs ?? [] as $staff)
                            <option value="{{ $staff->id }}" {{ $staff_id == $staff->id ? 'selected' : '' }}>{{ $staff->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="report-filter-group">
                    <label>Branch</label>
                    <select class="select" name="vendor_id">
                        <option value="">All Branches</option>
                        @foreach ($vendors ?? [] as $vendor)
                            <option value="{{ $vendor->id }}" {{ $vendor_id == $vendor->id ? 'selected' : '' }}>{{ $vendor->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="report-filter-group">
                    <label>Stage</label>
                    <select class="select" name="stage">
                        <option value="">All Stages</option>
                        @foreach ($stages ?? [] as $stage)
                            <option value="{{ $stage->value }}" {{ $stage === $stage->value ? 'selected' : '' }}>{{ $stage->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="report-filter-group">
                    <label>From Date</label>
                    <input type="date" name="start_date" value="{{ $start_date ?? '' }}">
                </div>
                <div class="report-filter-group">
                    <label>To Date</label>
                    <input type="date" name="end_date" value="{{ $end_date ?? '' }}">
                </div>
                <div class="report-filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm" style="border-radius:6px; height: 38px; padding: 0 16px;"><i class="fas fa-search mr-1"></i>Filter</button>
                    <a href="{{ url('admin/report/lead-funnel') }}" class="btn btn-outline-secondary btn-sm" style="border-radius:6px; height: 38px; display:flex; align-items:center; justify-content:center; width:38px;"><i class="fas fa-sync-alt"></i></a>
                </div>
            </div>
        </form>

        {{-- Stats --}}
        <div class="stat-card-grid">
            <div class="stat-card sc-primary">
                <div class="sc-icon"><i class="fas fa-users"></i></div>
                <div class="sc-value">{{ number_format($totalLeads ?? 0) }}</div>
                <div class="sc-label">Total Leads</div>
            </div>
            <div class="stat-card sc-success">
                <div class="sc-icon"><i class="fas fa-check-circle"></i></div>
                <div class="sc-value">{{ number_format($wonLeads ?? 0) }}</div>
                <div class="sc-label">Won Leads</div>
            </div>
            <div class="stat-card sc-danger">
                <div class="sc-icon"><i class="fas fa-times-circle"></i></div>
                <div class="sc-value">{{ number_format($lostLeads ?? 0) }}</div>
                <div class="sc-label">Lost Leads</div>
            </div>
            <div class="stat-card sc-info">
                <div class="sc-icon"><i class="fas fa-percentage"></i></div>
                <div class="sc-value">{{ number_format($overallConversionRate ?? 0, 2) }}%</div>
                <div class="sc-label">Conversion Rate</div>
            </div>
        </div>

        {{-- Funnel Visual --}}
        @isset($reportData)
        <div class="funnel-container">
            <div style="font-size:13px; font-weight:700; color:var(--page-text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:16px;">
                <i class="fas fa-chart-bar mr-2"></i> Stage Visualization
            </div>
            @foreach ($reportData as $row)
                @if($row->count > 0)
                <div class="funnel-stage-row fs-{{ $row->stage_value === 'won' ? 'won' : ($row->stage_value === 'lost' ? 'lost' : 'default') }}">
                    <div class="fs-name">
                        {{ $row->stage }}
                        <span class="fs-count">{{ $row->count }}</span>
                    </div>
                    <div class="fs-bar-wrap">
                        <div class="fs-bar" style="width: {{ $row->overall_conversion ?: 0 }}%; min-width: 4px;"></div>
                    </div>
                    <div class="fs-pct">{{ $row->overall_conversion }}%</div>
                </div>
                @endif
            @endforeach
        </div>
        @endisset

        {{-- Data Table --}}
        <div class="crm-table-header" style="border-top: 1px solid var(--gray-200);">
            <div class="table-title">
                <i class="fas fa-table" style="color: var(--page-text-muted);"></i>
                Detailed Breakdown
            </div>
        </div>
        <div style="overflow-x: auto;">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Stage</th>
                        <th style="text-align: center;">Count</th>
                        <th style="text-align: right;">Conversion %</th>
                        <th style="text-align: right;">Overall Conversion %</th>
                        <th style="text-align: right;">Avg Time (Days)</th>
                    </tr>
                </thead>
                <tbody>
                    @isset($reportData)
                        @forelse ($reportData as $key => $row)
                            <tr>
                                <td style="color:var(--page-text-muted);">{{ ++$key }}</td>
                                <td style="font-weight:600;">{{ $row->stage }}</td>
                                <td style="text-align: center;">
                                    <span style="font-weight:700; background:var(--{{ $row->stage_value === 'won' ? 'success' : ($row->stage_value === 'lost' ? 'danger' : 'primary') }}-50); color:var(--{{ $row->stage_value === 'won' ? 'success' : ($row->stage_value === 'lost' ? 'danger' : 'primary') }}-700); padding:2px 8px; border-radius:4px; font-size:12px;">
                                        {{ number_format($row->count) }}
                                    </span>
                                </td>
                                <td style="text-align: right; font-weight:500;">{{ number_format($row->conversion_percent, 2) }}%</td>
                                <td style="text-align: right; font-weight:500;">{{ number_format($row->overall_conversion, 2) }}%</td>
                                <td style="text-align: right; color:var(--page-text-muted);">
                                    @if($row->avg_time_days)
                                        {{ number_format($row->avg_time_days, 1) }} d
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align:center; padding: 40px; color:var(--page-text-muted);">
                                    <i class="fas fa-folder-open" style="font-size:32px; color:var(--gray-300); margin-bottom:12px; display:block;"></i>
                                    No lead data found
                                </td>
                            </tr>
                        @endforelse
                    @else
                        <tr>
                            <td colspan="6" style="text-align:center; padding: 40px; color:var(--page-text-muted);">
                                <i class="fas fa-folder-open" style="font-size:32px; color:var(--gray-300); margin-bottom:12px; display:block;"></i>
                                No lead data found
                            </td>
                        </tr>
                    @endisset
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
<script>
$(document).ready(function() {
    $('.select').select2({
        width: '100%'
    });
});
</script>
@endpush


