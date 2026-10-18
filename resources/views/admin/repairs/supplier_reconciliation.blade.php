@extends('admin.layouts.master')
@section('page_title', 'Monthly Supplier Reconciliation')
@section('breadcrumb_parent', 'Operations')
@section('breadcrumb_parent_url', route('repairs.index'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/kpi-cards.css') }}">
    <style>
        .recon-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .recon-badge.zero { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
        .recon-badge.paid { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .recon-badge.memo { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }

        .audit-row.checked-audit {
            background-color: #f0fdf4 !important;
        }
    </style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Header --}}
    <div class="crm-page-header crm-animate-in mb-3">
        <div class="page-title-group">
            <h1 class="h3 font-weight-bold text-gray-800 mb-1">
                <i class="fas fa-file-invoice-dollar text-primary mr-2"></i>Supplier Statement Reconciliation
            </h1>
            <p class="text-muted small mb-0">Cross-examine supplier cash memos, BDT 0.00 warranty replacements, and paid lab repair invoices line-by-line</p>
        </div>
        <div class="header-actions">
            <a href="{{ route('repairs.index') }}" class="btn btn-outline-secondary btn-sm shadow-sm font-weight-bold mr-2">
                <i class="fas fa-arrow-left mr-1"></i>Back to Pipeline
            </a>
            <button type="button" class="btn btn-primary btn-sm shadow-sm font-weight-bold" onclick="window.print()">
                <i class="fas fa-print mr-1"></i>Print Statement
            </button>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="card shadow-sm border-0 mb-4" style="border-radius: 8px;">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('repairs.supplier-reconciliation') }}" class="row align-items-end g-3">
                <div class="col-md-4">
                    <label class="font-weight-bold small text-muted text-uppercase">Select Supplier / Manufacturer</label>
                    <select name="supplier_id" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">All Suppliers</option>
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}" {{ (string)$supplierId === (string)$s->id ? 'selected' : '' }}>
                                {{ $s->supplier_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="font-weight-bold small text-muted text-uppercase">Reconciliation Month</label>
                    <input type="month" name="month" class="form-control form-control-sm" value="{{ $month }}" onchange="this.form.submit()">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold px-3">
                        <i class="fas fa-filter mr-1"></i>Filter Statement
                    </button>
                    <a href="{{ route('repairs.supplier-reconciliation') }}" class="btn btn-outline-secondary btn-sm ml-1">Reset</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Selected Supplier Gross Balance & Quick Payment Banner --}}
    @if($selectedSupplier)
    <div class="card shadow-sm border-0 mb-4 bg-white" style="border-radius: 8px; border-left: 5px solid #2563eb !important;">
        <div class="card-body p-3 d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <h5 class="font-weight-bold text-dark mb-1">
                    <i class="fas fa-building text-primary mr-2"></i>{{ $selectedSupplier->supplier_name }}
                </h5>
                <div class="text-muted small">
                    Showing repair challans &amp; replacement memos for <b>{{ Carbon\Carbon::parse($month . '-01')->format('F Y') }}</b> against gross supplier payments.
                </div>
            </div>
            <div class="d-flex align-items-center flex-wrap mt-2 mt-md-0" style="gap: 15px;">
                <div class="text-right mr-3">
                    <span class="text-muted small d-block">Gross Paid ({{ Carbon\Carbon::parse($month . '-01')->format('M Y') }}):</span>
                    <span class="font-weight-bold text-success" style="font-size: 15px;">৳{{ number_format($monthlyGrossPaid, 2) }}</span>
                </div>
                <div class="text-right mr-3">
                    <span class="text-muted small d-block">Total Running Net Due:</span>
                    <span class="font-weight-bold {{ $supplierNetBalance > 0 ? 'text-danger' : 'text-success' }}" style="font-size: 17px;">
                        ৳{{ number_format($supplierNetBalance, 2) }}
                    </span>
                </div>
                <a href="{{ route('suppliers.payment.create') }}?supplier_id={{ $selectedSupplier->id }}" class="btn btn-primary btn-sm font-weight-bold shadow-sm">
                    <i class="fas fa-money-bill-wave mr-1"></i>Record Gross Payment
                </a>
            </div>
        </div>
    </div>
    @endif

    {{-- KPI Cards --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-left-success py-2" style="border-radius: 8px; border-left: 4px solid #10b981;">
                <div class="card-body py-2">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Warranty Replacements (BDT 0)</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $totalReplacements }} Units</div>
                    <div class="text-muted small mt-1">Recorded at ৳0.00 on supplier memo</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-left-primary py-2" style="border-radius: 8px; border-left: 4px solid #3b82f6;">
                <div class="card-body py-2">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Lab Repair Bills</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">৳{{ number_format($totalLabBills, 2) }}</div>
                    <div class="text-muted small mt-1">Supplier billed repair liability</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-left-warning py-2" style="border-radius: 8px; border-left: 4px solid #f59e0b;">
                <div class="card-body py-2">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Jobs in Month</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $jobs->count() }} Records</div>
                    <div class="text-muted small mt-1">{{ Carbon\Carbon::parse($month . '-01')->format('F Y') }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-left-info py-2" style="border-radius: 8px; border-left: 4px solid #06b6d4;">
                <div class="card-body py-2">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Ledger Cross-Check</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $ledgerEntries->count() }} Ledger Rows</div>
                    <div class="text-muted small mt-1">Synced to supplier statement</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Statement Match Table --}}
    <div class="card shadow-sm border-0 mb-4" style="border-radius: 8px;">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list-check mr-2"></i>Line-by-Line Service &amp; Replacement Audit ({{ Carbon\Carbon::parse($month . '-01')->format('F Y') }})
            </h6>
            <span class="badge badge-light border text-muted">Check off items as verified against physical memo</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                    <thead class="bg-light text-muted text-uppercase" style="font-size: 11px;">
                        <tr>
                            <th style="width: 40px;" class="text-center pl-3">Audit</th>
                            <th>Date</th>
                            <th>Supplier Memo #</th>
                            <th>Job Card #</th>
                            <th>Defective S/N</th>
                            <th>Replaced / New S/N</th>
                            <th>Type / Operation</th>
                            <th>Stage</th>
                            <th class="text-right pr-3">Supplier Bill (BDT)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($jobs as $job)
                        <tr class="audit-row" id="row-{{ $job->id }}">
                            <td class="text-center pl-3">
                                <input type="checkbox" class="audit-check" onchange="toggleAuditRow(this, {{ $job->id }})">
                            </td>
                            <td>
                                <div>{{ $job->supplier_memo_date ? $job->supplier_memo_date->format('d M Y') : ($job->dispatched_at ? $job->dispatched_at->format('d M Y') : $job->created_at->format('d M Y')) }}</div>
                            </td>
                            <td>
                                @if($job->lab_ref_no)
                                    <span class="recon-badge memo font-monospace">{{ $job->lab_ref_no }}</span>
                                @else
                                    <span class="text-muted italic small">No memo #</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('repairs.show', $job->id) }}" class="font-weight-bold text-primary">
                                    {{ $job->job_no }}
                                </a>
                            </td>
                            <td>
                                <span class="font-monospace font-weight-bold text-dark">{{ $job->original_serial_no }}</span>
                                <div class="text-muted small">{{ Str::limit($job->product_display_name, 25) }}</div>
                            </td>
                            <td>
                                @if($job->replaced_serial_no)
                                    <span class="font-monospace font-weight-bold text-success">{{ $job->replaced_serial_no }}</span>
                                @else
                                    <span class="text-muted small">&mdash;</span>
                                @endif
                            </td>
                            <td>
                                @if($job->replaced_serial_no)
                                    <span class="recon-badge zero"><i class="fas fa-exchange-alt mr-1"></i>Warranty Replacement</span>
                                @elseif($job->pipeline_path === 'supplier_warranty')
                                    <span class="recon-badge zero"><i class="fas fa-shield-alt mr-1"></i>Free Warranty Repair</span>
                                @else
                                    <span class="recon-badge paid"><i class="fas fa-tools mr-1"></i>Paid Lab Repair</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-light border">{{ $job->stage_name }}</span>
                            </td>
                            <td class="text-right pr-3 font-weight-bold">
                                @if($job->total_supplier_cost > 0)
                                    <span class="text-dark">৳{{ number_format($job->total_supplier_cost, 2) }}</span>
                                @else
                                    <span class="text-success">৳0.00</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fas fa-check-circle fa-3x text-gray-300 mb-3 d-block"></i>
                                <h6>No supplier repair or replacement transactions found for this month</h6>
                                <p class="small">Try selecting another month or supplier above.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                    @if($jobs->count() > 0)
                    <tfoot class="bg-light font-weight-bold" style="font-size: 13px;">
                        <tr>
                            <td colspan="8" class="text-right">Total Supplier Liability for Month:</td>
                            <td class="text-right pr-3 text-primary font-weight-bold" style="font-size: 14px;">
                                ৳{{ number_format($totalLabBills, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

</div>
@endsection

@push('js')
<script>
    function toggleAuditRow(checkbox, jobId) {
        const row = document.getElementById('row-' + jobId);
        if (checkbox.checked) {
            row.classList.add('checked-audit');
        } else {
            row.classList.remove('checked-audit');
        }
    }
</script>
@endpush
