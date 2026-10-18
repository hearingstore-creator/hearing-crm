@extends('admin.layouts.master')
@section('page_title', 'Repair History')
@section('breadcrumb_parent', 'Repair & Service')
@section('breadcrumb_parent_url', route('repairs.index'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/kpi-cards.css') }}">
    <style>
        .history-kpis {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 20px;
        }
        @media (max-width: 992px) {
            .history-kpis {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 576px) {
            .history-kpis {
                grid-template-columns: 1fr;
            }
        }
        .history-kpi-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .history-kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.06);
        }
        .history-kpi-card .val {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
        }
        .history-kpi-card .lbl {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }
        .history-kpi-card .icon-wrap {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .status-tabs {
            display: flex;
            gap: 8px;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }
        .status-tab {
            padding: 9px 18px;
            font-size: 13.5px;
            font-weight: 600;
            color: #64748b;
            text-decoration: none !important;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.15s ease;
        }
        .status-tab:hover {
            color: #0f172a;
        }
        .status-tab.active {
            color: #2563eb;
            border-bottom-color: #2563eb;
        }
        .status-tab .badge {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 12px;
        }

        .filter-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }

        .history-table {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .history-table table {
            margin-bottom: 0;
            font-size: 13px;
        }
        .history-table thead th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 14px;
            white-space: nowrap;
        }
        .history-table tbody td {
            padding: 12px 14px;
            vertical-align: middle;
            border-top: 1px solid #f1f5f9;
        }
        .history-table tbody tr {
            cursor: pointer;
            transition: background 0.15s ease;
        }
        .history-table tbody tr:hover {
            background: #f8fafc;
        }
        .job-link {
            font-weight: 700;
            color: #2563eb;
            text-decoration: none;
        }
        .job-link:hover {
            text-decoration: underline;
        }
        .font-mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }
    </style>
@endpush

@section('content')
<div class="content-header pt-3 pb-2">
    <div class="container-fluid">
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div>
                <h4 class="m-0 text-dark font-weight-bold">
                    <i class="fas fa-history mr-2 text-primary"></i>Repair History
                </h4>
                <p class="text-muted small mb-0">Closed, delivered, declined and cancelled service jobs repository</p>
            </div>
            <div class="d-flex align-items-center flex-wrap gap-2 mt-2 mt-md-0">
                <a href="{{ route('repairs.history.export', request()->all()) }}" class="btn btn-outline-success btn-sm font-weight-bold shadow-sm mr-2">
                    <i class="fas fa-file-excel mr-1"></i>Export to Excel
                </a>
                <a href="{{ route('repairs.create') }}" class="btn btn-primary btn-sm font-weight-bold shadow-sm mr-2">
                    <i class="fas fa-plus-circle mr-1"></i>New Intake
                </a>
                <a href="{{ route('repairs.index') }}" class="btn btn-outline-secondary btn-sm font-weight-bold shadow-sm">
                    <i class="fas fa-columns mr-1"></i>Pipeline Board
                </a>
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">

        {{-- Top KPI Cards --}}
        <div class="history-kpis">
            <div class="history-kpi-card">
                <div>
                    <div class="val text-success">{{ number_format($counts['delivered']) }}</div>
                    <div class="lbl">Delivered to Patient</div>
                </div>
                <div class="icon-wrap bg-light text-success">
                    <i class="fas fa-check-double"></i>
                </div>
            </div>

            <div class="history-kpi-card">
                <div>
                    <div class="val text-warning">{{ number_format($counts['declined']) }}</div>
                    <div class="lbl">Returned Unrepaired</div>
                </div>
                <div class="icon-wrap bg-light text-warning">
                    <i class="fas fa-undo-alt"></i>
                </div>
            </div>

            <div class="history-kpi-card">
                <div>
                    <div class="val text-danger">{{ number_format($counts['cancelled']) }}</div>
                    <div class="lbl">Cancelled Jobs</div>
                </div>
                <div class="icon-wrap bg-light text-danger">
                    <i class="fas fa-times-circle"></i>
                </div>
            </div>

            <div class="history-kpi-card">
                <div>
                    <div class="val text-primary">৳{{ number_format((float)($financialTotals->total_customer_paid ?? 0), 2) }}</div>
                    <div class="lbl">Filtered Paid Revenue</div>
                </div>
                <div class="icon-wrap bg-light text-primary">
                    <i class="fas fa-receipt"></i>
                </div>
            </div>
        </div>

        {{-- Serial Search Chronology Alert Banner --}}
        @if ($isSerialSearch)
            <div class="alert alert-info border-info d-flex align-items-center justify-content-between mb-3 shadow-sm" style="border-left: 5px solid #0284c7;">
                <div class="d-flex align-items-center">
                    <i class="fas fa-clock-rotate-left fa-2x text-info mr-3"></i>
                    <div>
                        <h6 class="font-weight-bold mb-1 text-dark">
                            Full Device Service History for S/N: <span class="badge badge-dark font-mono px-2 py-1">{{ $search }}</span>
                        </h6>
                        <p class="mb-0 small text-muted">
                            Displaying all recorded jobs for this unit in chronological order (oldest to newest) to trace repair and replacement lineage.
                        </p>
                    </div>
                </div>
                <a href="{{ route('repairs.history') }}" class="btn btn-outline-info btn-sm font-weight-bold">
                    <i class="fas fa-times mr-1"></i>Clear Serial Search
                </a>
            </div>
        @endif

        {{-- Status Filter Tabs --}}
        <div class="status-tabs">
            <a href="{{ route('repairs.history', array_merge(request()->except('page'), ['status' => 'delivered'])) }}"
               class="status-tab {{ $status === 'delivered' ? 'active' : '' }}">
                <i class="fas fa-check-circle text-success"></i> Delivered
                <span class="badge {{ $status === 'delivered' ? 'badge-primary' : 'badge-light' }}">{{ $counts['delivered'] }}</span>
            </a>
            <a href="{{ route('repairs.history', array_merge(request()->except('page'), ['status' => 'declined'])) }}"
               class="status-tab {{ in_array($status, ['declined', 'returned_unrepaired', 'patient_declined']) ? 'active' : '' }}">
                <i class="fas fa-ban text-warning"></i> Returned Unrepaired (Declined)
                <span class="badge {{ in_array($status, ['declined', 'returned_unrepaired', 'patient_declined']) ? 'badge-primary' : 'badge-light' }}">{{ $counts['declined'] }}</span>
            </a>
            <a href="{{ route('repairs.history', array_merge(request()->except('page'), ['status' => 'cancelled'])) }}"
               class="status-tab {{ $status === 'cancelled' ? 'active' : '' }}">
                <i class="fas fa-times-circle text-danger"></i> Cancelled
                <span class="badge {{ $status === 'cancelled' ? 'badge-primary' : 'badge-light' }}">{{ $counts['cancelled'] }}</span>
            </a>
            <a href="{{ route('repairs.history', array_merge(request()->except('page'), ['status' => 'all'])) }}"
               class="status-tab {{ $status === 'all' ? 'active' : '' }}">
                <i class="fas fa-layer-group text-secondary"></i> All Closed
                <span class="badge {{ $status === 'all' ? 'badge-primary' : 'badge-light' }}">{{ $counts['all'] }}</span>
            </a>
        </div>

        {{-- Search & Filters Card --}}
        <div class="filter-card">
            <form method="GET" action="{{ route('repairs.history') }}" id="historyFilterForm">
                <input type="hidden" name="status" value="{{ $status }}">

                <div class="row g-2 align-items-end">
                    {{-- Unified Multi-Field Search Box --}}
                    <div class="col-lg-4 col-md-6 mb-2">
                        <label class="small font-weight-bold text-muted mb-1">
                            <i class="fas fa-search mr-1"></i>Search Anything
                        </label>
                        <div class="input-group">
                            <input type="text"
                                   name="search"
                                   class="form-control form-control-sm"
                                   placeholder="Job #, Serial, Customer, Phone, Memo #, Tracking #..."
                                   value="{{ $search }}">
                            @if (!empty($search))
                                <div class="input-group-append">
                                    <a href="{{ route('repairs.history', request()->except(['search', 'page'])) }}" class="btn btn-sm btn-outline-secondary" title="Clear search">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Outcome Filter --}}
                    <div class="col-lg-2 col-md-3 col-sm-6 mb-2">
                        <label class="small font-weight-bold text-muted mb-1">Outcome</label>
                        <select name="outcome" class="form-control form-control-sm">
                            <option value="">All Outcomes</option>
                            <option value="warranty_repair" {{ $outcome === 'warranty_repair' ? 'selected' : '' }}>Warranty Repair</option>
                            <option value="warranty_replacement" {{ $outcome === 'warranty_replacement' ? 'selected' : '' }}>Warranty Replacement</option>
                            <option value="paid_repair" {{ $outcome === 'paid_repair' ? 'selected' : '' }}>Paid Repair</option>
                            <option value="paid_replacement" {{ $outcome === 'paid_replacement' ? 'selected' : '' }}>Paid Replacement</option>
                            <option value="in_house" {{ $outcome === 'in_house' ? 'selected' : '' }}>In-house</option>
                            <option value="declined" {{ $outcome === 'declined' ? 'selected' : '' }}>Declined</option>
                            <option value="cancelled" {{ $outcome === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                    </div>

                    {{-- Warranty Status Filter --}}
                    <div class="col-lg-2 col-md-3 col-sm-6 mb-2">
                        <label class="small font-weight-bold text-muted mb-1">Warranty Status</label>
                        <select name="warranty_status" class="form-control form-control-sm">
                            <option value="">All Warranties</option>
                            <option value="under_product_warranty" {{ $warrantyStatus === 'under_product_warranty' ? 'selected' : '' }}>Product Warranty</option>
                            <option value="under_repair_warranty" {{ $warrantyStatus === 'under_repair_warranty' ? 'selected' : '' }}>Repair Guarantee</option>
                            <option value="out_of_warranty" {{ $warrantyStatus === 'out_of_warranty' ? 'selected' : '' }}>Out of Warranty</option>
                            <option value="void" {{ $warrantyStatus === 'void' ? 'selected' : '' }}>Void</option>
                        </select>
                    </div>

                    {{-- Branch Filter --}}
                    <div class="col-lg-2 col-md-3 col-sm-6 mb-2">
                        <label class="small font-weight-bold text-muted mb-1">Branch</label>
                        <select name="vendor_id" class="form-control form-control-sm">
                            <option value="">All Branches</option>
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}" {{ $vendorId == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Supplier Filter --}}
                    <div class="col-lg-2 col-md-3 col-sm-6 mb-2">
                        <label class="small font-weight-bold text-muted mb-1">Supplier Lab</label>
                        <select name="supplier_id" class="form-control form-control-sm">
                            <option value="">All Suppliers</option>
                            @foreach ($suppliers as $s)
                                <option value="{{ $s->id }}" {{ $supplierId == $s->id ? 'selected' : '' }}>{{ $s->supplier_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Date Type --}}
                    <div class="col-lg-2 col-md-3 col-sm-6 mb-2">
                        <label class="small font-weight-bold text-muted mb-1">Date Filter Field</label>
                        <select name="date_type" class="form-control form-control-sm">
                            <option value="delivered" {{ $dateType === 'delivered' ? 'selected' : '' }}>Delivered / Closed Date</option>
                            <option value="received" {{ $dateType === 'received' ? 'selected' : '' }}>Received Date</option>
                        </select>
                    </div>

                    {{-- Start Date --}}
                    <div class="col-lg-2 col-md-3 col-sm-6 mb-2">
                        <label class="small font-weight-bold text-muted mb-1">Date From</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}">
                    </div>

                    {{-- End Date --}}
                    <div class="col-lg-2 col-md-3 col-sm-6 mb-2">
                        <label class="small font-weight-bold text-muted mb-1">Date To</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}">
                    </div>

                    {{-- Filter Action Buttons --}}
                    <div class="col-lg-6 col-md-6 mb-2 text-md-right">
                        <button type="submit" class="btn btn-primary btn-sm font-weight-bold px-3 shadow-sm mr-1">
                            <i class="fas fa-filter mr-1"></i>Filter
                        </button>
                        <a href="{{ route('repairs.history', ['status' => $status]) }}" class="btn btn-outline-secondary btn-sm font-weight-bold px-3">
                            <i class="fas fa-undo mr-1"></i>Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        {{-- History Table --}}
        <div class="history-table">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Job No</th>
                            <th>Received</th>
                            <th>Delivered/Closed</th>
                            <th>Customer &amp; Phone</th>
                            <th>Product / Model</th>
                            <th>Original S/N</th>
                            <th>New S/N (Replaced)</th>
                            <th>Outcome</th>
                            <th>Warranty</th>
                            <th class="text-right">Customer Paid</th>
                            <th class="text-right">Supplier Cost</th>
                            <th>Supplier Memo #</th>
                            <th>Branch</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($jobs as $job)
                            @php
                                $isReplaced = !empty($job->replaced_serial_no) && $job->replaced_serial_no !== $job->original_serial_no;
                                $outcome = $job->outcome;
                            @endphp
                            <tr onclick="if(!event.target.closest('a')) window.location='{{ route('repairs.show', $job->id) }}';">
                                <td>
                                    <a href="{{ route('repairs.show', $job->id) }}" class="job-link">
                                        {{ $job->job_no }}
                                    </a>
                                </td>
                                <td class="text-nowrap text-muted">
                                    {{ $job->created_at ? $job->created_at->format('d M Y') : '—' }}
                                </td>
                                <td class="text-nowrap font-weight-bold text-dark">
                                    {{ $job->delivered_at ? $job->delivered_at->format('d M Y') : ($job->updated_at ? $job->updated_at->format('d M Y') : '—') }}
                                </td>
                                <td>
                                    <div class="font-weight-bold text-dark">
                                        {{ $job->customer?->lead?->full_name ?? ($job->custom_brand ? 'Walk-in Customer' : 'Customer') }}
                                    </div>
                                    @if ($job->customer?->lead?->phone_number)
                                        <div class="small text-muted font-mono">
                                            <i class="fas fa-phone mr-1" style="font-size: 10px;"></i>{{ $job->customer->lead->phone_number }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <div class="font-weight-bold text-dark">{{ $job->device_name }}</div>
                                    @if ($job->product && $job->product->product_code)
                                        <div class="small text-muted">{{ $job->product->product_code }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="font-mono font-weight-bold text-dark bg-light px-2 py-1 border rounded" style="font-size: 12px;">
                                        {{ $job->original_serial_no }}
                                    </span>
                                </td>
                                <td>
                                    @if ($isReplaced)
                                        <span class="font-mono font-weight-bold text-success bg-light px-2 py-1 border border-success rounded" style="font-size: 12px;" title="Replaced Unit Serial">
                                            <i class="fas fa-arrow-right mr-1 text-muted" style="font-size: 10px;"></i>{{ $job->replaced_serial_no }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($outcome === 'Warranty repair')
                                        <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i>Warranty Repair</span>
                                    @elseif ($outcome === 'Warranty replacement')
                                        <span class="badge badge-success px-2 py-1"><i class="fas fa-exchange-alt mr-1"></i>Warranty Replacement</span>
                                    @elseif ($outcome === 'Paid repair')
                                        <span class="badge badge-primary px-2 py-1"><i class="fas fa-wrench mr-1"></i>Paid Repair</span>
                                    @elseif ($outcome === 'Paid replacement')
                                        <span class="badge badge-info px-2 py-1"><i class="fas fa-sync-alt mr-1"></i>Paid Replacement</span>
                                    @elseif ($outcome === 'In-house')
                                        <span class="badge badge-secondary px-2 py-1"><i class="fas fa-bolt mr-1"></i>In-house</span>
                                    @elseif ($outcome === 'Declined')
                                        <span class="badge badge-warning text-dark px-2 py-1"><i class="fas fa-times-circle mr-1"></i>Declined</span>
                                    @elseif ($outcome === 'Cancelled')
                                        <span class="badge badge-danger px-2 py-1"><i class="fas fa-ban mr-1"></i>Cancelled</span>
                                    @else
                                        <span class="badge badge-light px-2 py-1">{{ $outcome }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($job->warranty_status === 'under_product_warranty')
                                        <span class="badge badge-light border text-success">Product Warranty</span>
                                    @elseif ($job->warranty_status === 'under_repair_warranty')
                                        <span class="badge badge-light border text-info">Repair Guarantee</span>
                                    @elseif ($job->warranty_status === 'void')
                                        <span class="badge badge-light border text-danger">Void</span>
                                    @else
                                        <span class="badge badge-light border text-muted">Out of Warranty</span>
                                    @endif
                                </td>
                                <td class="text-right font-weight-bold text-dark font-mono">
                                    ৳{{ number_format((float)$job->total_customer_paid, 2) }}
                                </td>
                                <td class="text-right text-muted font-mono">
                                    ৳{{ number_format((float)$job->total_supplier_cost, 2) }}
                                </td>
                                <td class="text-nowrap">
                                    {{ $job->lab_ref_no ?: '—' }}
                                </td>
                                <td class="text-nowrap small text-muted">
                                    {{ $job->vendor?->name ?? 'Head Office' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="text-center py-5 text-muted">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                    <h6 class="font-weight-bold text-dark">No Repair History Found</h6>
                                    <p class="small mb-0">No closed jobs matched the current filter criteria.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($jobs->hasPages())
                <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap">
                    <div class="small text-muted mb-2 mb-md-0">
                        Showing {{ $jobs->firstItem() }} to {{ $jobs->lastItem() }} of {{ $jobs->total() }} closed repair jobs
                    </div>
                    <div>
                        {{ $jobs->links() }}
                    </div>
                </div>
            @endif
        </div>

    </div>
</div>
@endsection
