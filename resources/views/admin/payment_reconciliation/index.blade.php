@extends('admin.layouts.master')

@section('page_title', 'Payment Gateway & POS Reconciliation')
@section('breadcrumb_parent', 'Finance')
@section('breadcrumb_parent_url', url('admin/payment-reconciliation'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
<style>
.rec-kpi-card {
    background: #fff;
    border-radius: 12px;
    padding: 16px 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 6px rgba(0,0,0,0.03);
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: all 0.2s ease;
}
.rec-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}
.rec-kpi-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}
.rec-kpi-val {
    font-size: 22px;
    font-weight: 800;
    line-height: 1.1;
    margin-top: 4px;
}
.rec-kpi-lbl {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
}

.crm-table-card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    overflow: hidden;
}
.crm-table-header {
    padding: 14px 20px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}
.crm-table {
    width: 100%;
    margin-bottom: 0;
    border-collapse: collapse;
}
.crm-table th {
    background: #f1f5f9;
    color: #475569;
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 10px 14px;
    border-bottom: 1px solid #cbd5e1;
    vertical-align: middle;
}
.crm-table td {
    padding: 11px 14px;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
    font-size: 13px;
}
.crm-table tr:hover td {
    background-color: #f8fafc;
}

.badge-channel {
    font-size: 11px;
    font-weight: 700;
    padding: 4px 8px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.badge-card { background: #e0e7ff; color: #3730a3; }
.badge-bkash { background: #fce7f3; color: #9d174d; }
.badge-nagad { background: #fef3c7; color: #92400e; }
.badge-rocket { background: #e0f2fe; color: #0369a1; }
.badge-bank { background: #f1f5f9; color: #334155; }
.badge-other { background: #ede9fe; color: #5b21b6; }

.btn-quick-pct {
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    padding: 3px 8px;
}
.batch-toolbar {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    padding: 10px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
}
</style>
@endpush

@section('content')
<div class="content-header p-0 mb-3">
    <div class="container-fluid pl-0 pr-0">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h1 class="m-0 text-dark font-weight-bold" style="font-size: 24px;">
                    <i class="fas fa-balance-scale text-primary mr-2"></i>Payment Gateway & POS Reconciliation
                </h1>
                <p class="text-muted small mb-0 mt-1">
                    Reconcile Card POS terminals, bKash & MFS payments, adjust transaction charges (MDR), and auto-post to General Ledger.
                </p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('report.profit-statement') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-chart-pie mr-1"></i>Profit Statement
                </a>
                <a href="{{ url('admin/expense') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-receipt mr-1"></i>Expenses
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid pl-0 pr-0">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle mr-2"></i>{{ session('warning') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    {{-- Nav Tabs --}}
    <ul class="nav nav-pills mb-3" id="recTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link font-weight-bold {{ $tab === 'pending' ? 'active' : '' }}" 
               id="pending-tab" data-toggle="pill" href="#pending-panel" role="tab">
                <i class="fas fa-clock mr-1.5"></i>Pending Settlements
                <span class="badge {{ $pendingCount > 0 ? 'badge-danger' : 'badge-light' }} ml-1.5">
                    {{ $pendingCount }}
                </span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link font-weight-bold {{ $tab === 'history' ? 'active' : '' }}" 
               id="history-tab" data-toggle="pill" href="#history-panel" role="tab">
                <i class="fas fa-history mr-1.5"></i>Reconciliation History & Batches
                <span class="badge badge-light ml-1.5">{{ $historyCount }}</span>
            </a>
        </li>
    </ul>

    <div class="tab-content" id="recTabContent">
        {{-- ======================================================== --}}
        {{-- TAB 1: PENDING SETTLEMENTS                               --}}
        {{-- ======================================================== --}}
        <div class="tab-pane fade {{ $tab === 'pending' ? 'show active' : '' }}" id="pending-panel" role="tabpanel">
            
            {{-- KPI Summary Cards --}}
            <div class="row mb-3">
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="rec-kpi-card">
                        <div>
                            <div class="rec-kpi-lbl">Unsettled Sales</div>
                            <div class="rec-kpi-val text-dark">{{ number_format($pendingCount) }}</div>
                            <small class="text-muted">Awaiting bank/MFS match</small>
                        </div>
                        <div class="rec-kpi-icon" style="background: #eff6ff; color: #2563eb;">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="rec-kpi-card">
                        <div>
                            <div class="rec-kpi-lbl">Unsettled Gross</div>
                            <div class="rec-kpi-val text-primary">৳{{ number_format($pendingGross, 2) }}</div>
                            <small class="text-muted">Collected from patients</small>
                        </div>
                        <div class="rec-kpi-icon" style="background: #eef2ff; color: #4f46e5;">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="rec-kpi-card">
                        <div>
                            <div class="rec-kpi-lbl">POS Cards In-Transit</div>
                            <div class="rec-kpi-val" style="color: #0d9488;">৳{{ number_format($cardPendingGross, 2) }}</div>
                            <small class="text-muted">Visa / Master / Amex</small>
                        </div>
                        <div class="rec-kpi-icon" style="background: #f0fdfa; color: #0d9488;">
                            <i class="fas fa-credit-card"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <div class="rec-kpi-card">
                        <div>
                            <div class="rec-kpi-lbl">bKash & MFS In-Transit</div>
                            <div class="rec-kpi-val" style="color: #db2777;">৳{{ number_format($mfsPendingGross, 2) }}</div>
                            <small class="text-muted">bKash / Nagad / Rocket</small>
                        </div>
                        <div class="rec-kpi-icon" style="background: #fdf2f8; color: #db2777;">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Filter Bar --}}
            <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px;">
                <div class="card-body p-3">
                    <form method="GET" action="{{ route('payment-reconciliation.index') }}" class="form-row align-items-end">
                        <input type="hidden" name="tab" value="pending">
                        
                        @if(!auth()->user()->isVendor())
                        <div class="col-md-2 col-sm-6 mb-2">
                            <label class="small font-weight-bold text-muted mb-1"><i class="fas fa-store mr-1"></i>Branch</label>
                            <select name="vendor_id" class="form-control form-control-sm">
                                <option value="all">All Branches</option>
                                <option value="hq" {{ $vendorFilter === 'hq' ? 'selected' : '' }}>Head Office</option>
                                @foreach($vendors as $v)
                                    <option value="{{ $v->id }}" {{ (string)$vendorFilter === (string)$v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif

                        <div class="col-md-2 col-sm-6 mb-2">
                            <label class="small font-weight-bold text-muted mb-1"><i class="fas fa-layer-group mr-1"></i>Channel</label>
                            <select name="channel" class="form-control form-control-sm">
                                <option value="">All Channels</option>
                                <option value="card" {{ request('channel') === 'card' ? 'selected' : '' }}>POS Card Terminal</option>
                                <option value="bkash" {{ request('channel') === 'bkash' ? 'selected' : '' }}>bKash Merchant</option>
                                <option value="nagad" {{ request('channel') === 'nagad' ? 'selected' : '' }}>Nagad</option>
                                <option value="rocket" {{ request('channel') === 'rocket' ? 'selected' : '' }}>Rocket</option>
                                <option value="bank_transfer" {{ request('channel') === 'bank_transfer' ? 'selected' : '' }}>Bank / Online</option>
                            </select>
                        </div>

                        <div class="col-md-2 col-sm-6 mb-2">
                            <label class="small font-weight-bold text-muted mb-1"><i class="far fa-calendar mr-1"></i>From Date</label>
                            <input type="date" name="start_date" class="form-control form-control-sm" value="{{ request('start_date') }}">
                        </div>

                        <div class="col-md-2 col-sm-6 mb-2">
                            <label class="small font-weight-bold text-muted mb-1"><i class="far fa-calendar mr-1"></i>To Date</label>
                            <input type="date" name="end_date" class="form-control form-control-sm" value="{{ request('end_date') }}">
                        </div>

                        <div class="col-md-2 col-sm-6 mb-2">
                            <label class="small font-weight-bold text-muted mb-1"><i class="fas fa-search mr-1"></i>Search</label>
                            <input type="text" name="q" class="form-control form-control-sm" placeholder="Invoice, patient, slip #" value="{{ request('q') }}">
                        </div>

                        <div class="col-md-2 col-sm-6 mb-2 d-flex">
                            <button type="submit" class="btn btn-primary btn-sm mr-2 flex-grow-1 font-weight-bold">
                                <i class="fas fa-filter mr-1"></i>Filter
                            </button>
                            <a href="{{ route('payment-reconciliation.index', ['tab' => 'pending']) }}" class="btn btn-outline-secondary btn-sm" title="Reset Filters">
                                <i class="fas fa-redo"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Batch Selection Toolbar (hidden until checkboxes clicked) --}}
            <div id="batch-action-bar" class="batch-toolbar" style="display: none;">
                <div class="d-flex align-items-center">
                    <i class="fas fa-check-double text-primary mr-2" style="font-size: 18px;"></i>
                    <span class="font-weight-bold text-dark">
                        <span id="selected-count">0</span> transactions selected
                    </span>
                    <span class="text-muted ml-3">
                        Total Gross: <b class="text-primary" id="selected-gross">৳0.00</b>
                    </span>
                </div>
                <div>
                    <button type="button" class="btn btn-primary btn-sm font-weight-bold px-3" id="btn-open-batch-modal">
                        <i class="fas fa-receipt mr-1"></i>Settle Selected Batch (POS Terminal Slip)
                    </button>
                </div>
            </div>

            {{-- Pending Sales Table Card --}}
            <div class="crm-table-card">
                <div class="crm-table-header d-flex justify-content-between align-items-center">
                    <div class="font-weight-bold text-dark" style="font-size: 14px;">
                        <i class="fas fa-list text-warning mr-1.5"></i>
                        Digital Payments Awaiting Reconciliation ({{ $pendingSales->total() }})
                    </div>
                    <div class="small text-muted">
                        <i class="fas fa-info-circle text-info mr-1"></i>Select multiple card sales to settle in one daily terminal batch
                    </div>
                </div>

                <div style="overflow-x: auto;">
                    <table class="crm-table">
                        <thead>
                            <tr>
                                <th style="width: 35px; text-align: center;">
                                    <input type="checkbox" id="check-all-pending" style="cursor: pointer;">
                                </th>
                                <th style="width: 45px;">#</th>
                                <th style="width: 95px;">Date</th>
                                <th>Invoice #</th>
                                <th>Branch</th>
                                <th>Patient</th>
                                <th>Payment Method</th>
                                <th>Slip / Trx #</th>
                                <th class="num" style="width: 120px; text-align: right;">Gross Paid</th>
                                <th style="width: 130px; text-align: center;">Status</th>
                                <th style="width: 110px; text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($pendingSales as $idx => $sale)
                            @php
                                $pMethod = $sale->payment_method ?: Helper::channelKeyFromPaymentId($sale->payment_id);
                                $badgeClass = match($pMethod) {
                                    'card' => 'badge-card',
                                    'bkash' => 'badge-bkash',
                                    'nagad' => 'badge-nagad',
                                    'rocket' => 'badge-rocket',
                                    'bank_transfer' => 'badge-bank',
                                    default => 'badge-other'
                                };
                                $channelLabel = match($pMethod) {
                                    'card' => 'POS Card',
                                    'bkash' => 'bKash',
                                    'nagad' => 'Nagad',
                                    'rocket' => 'Rocket',
                                    'bank_transfer' => 'Bank / Online',
                                    default => 'Online'
                                };
                                $patientName = $sale->customer->lead->full_name ?? ($sale->customer->name ?? 'Patient');
                                $patientPhone = $sale->customer->lead->phone_number ?? ($sale->customer->mobile ?? '');
                            @endphp
                            <tr>
                                <td style="text-align: center;">
                                    <input type="checkbox" class="sale-checkbox" 
                                           data-id="{{ $sale->id }}" 
                                           data-gross="{{ (float) $sale->paid }}"
                                           data-channel="{{ $pMethod }}"
                                           data-invoice="{{ $sale->sale_invoice_no }}"
                                           style="cursor: pointer;">
                                </td>
                                <td class="text-muted" style="font-size: 12px;">{{ $pendingSales->firstItem() + $idx }}</td>
                                <td style="font-weight: 500;">
                                    {{ $sale->date ? \Carbon\Carbon::parse($sale->date)->format('d M Y') : '—' }}
                                </td>
                                <td>
                                    <a href="{{ url('admin/sale/' . $sale->id) }}" target="_blank" class="font-weight-bold text-dark">
                                        {{ $sale->sale_invoice_no }}
                                    </a>
                                </td>
                                <td>
                                    <span class="badge badge-light border text-muted" style="font-size: 11px;">
                                        {{ $sale->vendor->name ?? 'Head Office' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="font-weight-bold text-dark" style="font-size: 13px;">{{ $patientName }}</div>
                                    @if($patientPhone)
                                        <small class="text-muted" style="font-size: 11px;">{{ $patientPhone }}</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge-channel {{ $badgeClass }}">
                                        @if($pMethod === 'card')
                                            <i class="fas fa-credit-card"></i>
                                        @elseif($pMethod === 'bkash' || $pMethod === 'nagad' || $pMethod === 'rocket')
                                            <i class="fas fa-mobile-alt"></i>
                                        @else
                                            <i class="fas fa-globe"></i>
                                        @endif
                                        {{ $channelLabel }}
                                    </span>
                                    @if($sale->pos_terminal_name)
                                        <small class="text-muted d-block" style="font-size: 11px;">{{ $sale->pos_terminal_name }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($sale->payment_trx_no)
                                        <span class="badge badge-light border text-dark font-weight-bold" style="font-size: 11px;">
                                            {{ $sale->payment_trx_no }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td style="text-align: right;">
                                    <span class="font-weight-bold text-dark" style="font-size: 13.5px;">
                                        ৳{{ number_format((float) $sale->paid, 2) }}
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge badge-warning px-2 py-1" style="font-size: 11px; font-weight: 700;">
                                        <i class="fas fa-clock mr-1"></i>Unsettled
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    @can('permission', 'payment_reconciliation.create')
                                    <button type="button" class="btn btn-sm btn-primary px-2.5 py-1 btn-open-single-modal"
                                            data-id="{{ $sale->id }}"
                                            data-invoice="{{ $sale->sale_invoice_no }}"
                                            data-patient="{{ $patientName }}"
                                            data-gross="{{ (float) $sale->paid }}"
                                            data-channel="{{ $pMethod }}"
                                            data-channel-label="{{ $channelLabel }}"
                                            data-badge-class="{{ $badgeClass }}"
                                            data-terminal="{{ $sale->pos_terminal_name }}"
                                            data-trx="{{ $sale->payment_trx_no }}"
                                            data-date="{{ $sale->date ? \Carbon\Carbon::parse($sale->date)->format('Y-m-d') : date('Y-m-d') }}"
                                            style="border-radius: 6px; font-weight: 700; font-size: 11.5px;">
                                        <i class="fas fa-check-circle mr-1"></i>Settle
                                    </button>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="11" style="text-align: center; padding: 45px 15px; color: #94a3b8;">
                                    <i class="fas fa-check-circle text-success" style="font-size: 36px; display: block; margin-bottom: 12px;"></i>
                                    All digital payments are reconciled and settled! No pending items found.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($pendingSales->hasPages())
                <div class="p-3 border-top bg-light">
                    {{ $pendingSales->appends(request()->all())->links() }}
                </div>
                @endif
            </div>
        </div>

        {{-- ======================================================== --}}
        {{-- TAB 2: RECONCILIATION HISTORY & BATCHES                  --}}
        {{-- ======================================================== --}}
        <div class="tab-pane fade {{ $tab === 'history' ? 'show active' : '' }}" id="history-panel" role="tabpanel">
            
            {{-- History KPI Summary Cards --}}
            <div class="row mb-3">
                <div class="col-md-4 col-sm-6 mb-2">
                    <div class="rec-kpi-card">
                        <div>
                            <div class="rec-kpi-lbl">Reconciled Batches</div>
                            <div class="rec-kpi-val text-dark">{{ number_format($historyCount) }}</div>
                            <small class="text-muted">Completed settlements</small>
                        </div>
                        <div class="rec-kpi-icon" style="background: #eff6ff; color: #2563eb;">
                            <i class="fas fa-receipt"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6 mb-2">
                    <div class="rec-kpi-card">
                        <div>
                            <div class="rec-kpi-lbl">Total Charges Deducted</div>
                            <div class="rec-kpi-val text-danger">৳{{ number_format($historyTotalFees, 2) }}</div>
                            <small class="text-muted">Debited to Bank &amp; Gateway Charges</small>
                        </div>
                        <div class="rec-kpi-icon" style="background: #fef2f2; color: #dc2626;">
                            <i class="fas fa-percentage"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6 mb-2">
                    <div class="rec-kpi-card">
                        <div>
                            <div class="rec-kpi-lbl">Total Net Banked</div>
                            <div class="rec-kpi-val text-success">৳{{ number_format($historyTotalNet, 2) }}</div>
                            <small class="text-muted">Net cash credited to bank</small>
                        </div>
                        <div class="rec-kpi-icon" style="background: #f0fdf4; color: #16a34a;">
                            <i class="fas fa-piggy-bank"></i>
                        </div>
                    </div>
                </div>
            </div>

            {{-- History Filter Card --}}
            <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px;">
                <div class="card-body p-3">
                    <form method="GET" action="{{ route('payment-reconciliation.index') }}" class="form-row align-items-end">
                        <input type="hidden" name="tab" value="history">

                        @if(!auth()->user()->isVendor())
                        <div class="col-md-2 col-sm-6 mb-2">
                            <label class="small font-weight-bold text-muted mb-1"><i class="fas fa-store mr-1"></i>Branch</label>
                            <select name="vendor_id" class="form-control form-control-sm">
                                <option value="all">All Branches</option>
                                <option value="hq" {{ $vendorFilter === 'hq' ? 'selected' : '' }}>Head Office</option>
                                @foreach($vendors as $v)
                                    <option value="{{ $v->id }}" {{ (string)$vendorFilter === (string)$v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif

                        <div class="col-md-2 col-sm-6 mb-2">
                            <label class="small font-weight-bold text-muted mb-1"><i class="fas fa-layer-group mr-1"></i>Channel</label>
                            <select name="history_channel" class="form-control form-control-sm">
                                <option value="">All Channels</option>
                                <option value="card" {{ request('history_channel') === 'card' ? 'selected' : '' }}>POS Card Terminal</option>
                                <option value="bkash" {{ request('history_channel') === 'bkash' ? 'selected' : '' }}>bKash Merchant</option>
                                <option value="nagad" {{ request('history_channel') === 'nagad' ? 'selected' : '' }}>Nagad</option>
                                <option value="rocket" {{ request('history_channel') === 'rocket' ? 'selected' : '' }}>Rocket</option>
                                <option value="bank_transfer" {{ request('history_channel') === 'bank_transfer' ? 'selected' : '' }}>Bank / Online</option>
                            </select>
                        </div>

                        <div class="col-md-2 col-sm-6 mb-2">
                            <label class="small font-weight-bold text-muted mb-1"><i class="far fa-calendar mr-1"></i>From Date</label>
                            <input type="date" name="history_start" class="form-control form-control-sm" value="{{ request('history_start') }}">
                        </div>

                        <div class="col-md-2 col-sm-6 mb-2">
                            <label class="small font-weight-bold text-muted mb-1"><i class="far fa-calendar mr-1"></i>To Date</label>
                            <input type="date" name="history_end" class="form-control form-control-sm" value="{{ request('history_end') }}">
                        </div>

                        <div class="col-md-2 col-sm-6 mb-2">
                            <label class="small font-weight-bold text-muted mb-1"><i class="fas fa-search mr-1"></i>Search</label>
                            <input type="text" name="history_q" class="form-control form-control-sm" placeholder="Rec #, batch #, notes" value="{{ request('history_q') }}">
                        </div>

                        <div class="col-md-2 col-sm-6 mb-2 d-flex">
                            <button type="submit" class="btn btn-primary btn-sm mr-2 flex-grow-1 font-weight-bold">
                                <i class="fas fa-filter mr-1"></i>Filter
                            </button>
                            <a href="{{ route('payment-reconciliation.index', ['tab' => 'history']) }}" class="btn btn-outline-secondary btn-sm" title="Reset Filters">
                                <i class="fas fa-redo"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            {{-- History Table Card --}}
            <div class="crm-table-card">
                <div class="crm-table-header d-flex justify-content-between align-items-center">
                    <div class="font-weight-bold text-dark" style="font-size: 14px;">
                        <i class="fas fa-check-circle text-success mr-1.5"></i>
                        Reconciliation &amp; Settlement Records ({{ $reconciliations->total() }})
                    </div>
                    <div class="small text-muted">
                        <i class="fas fa-shield-alt text-success mr-1"></i>Gateway charges automatically post to General Ledger &amp; Operating Expenses
                    </div>
                </div>

                <div style="overflow-x: auto;">
                    <table class="crm-table">
                        <thead>
                            <tr>
                                <th style="width: 45px;">#</th>
                                <th style="width: 100px;">Settled Date</th>
                                <th>Reconciliation #</th>
                                <th>Batch / Trx #</th>
                                <th>Channel &amp; Terminal</th>
                                <th>Branch</th>
                                <th class="num" style="width: 110px; text-align: right;">Gross (৳)</th>
                                <th class="num" style="width: 120px; text-align: right;">Fee (৳)</th>
                                <th class="num" style="width: 120px; text-align: right;">Net Banked (৳)</th>
                                <th style="width: 80px; text-align: center;">Sales</th>
                                <th style="width: 120px; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reconciliations as $idx => $rec)
                            @php
                                $badgeClass = match($rec->channel) {
                                    'card' => 'badge-card',
                                    'bkash' => 'badge-bkash',
                                    'nagad' => 'badge-nagad',
                                    'rocket' => 'badge-rocket',
                                    'bank_transfer' => 'badge-bank',
                                    default => 'badge-other'
                                };
                            @endphp
                            <tr>
                                <td class="text-muted" style="font-size: 12px;">{{ $reconciliations->firstItem() + $idx }}</td>
                                <td style="font-weight: 500;">
                                    {{ $rec->settlement_date ? $rec->settlement_date->format('d M Y') : '—' }}
                                </td>
                                <td>
                                    <span class="font-weight-bold text-primary">{{ $rec->reconciliation_no }}</span>
                                    @if($rec->ledger)
                                        <small class="text-muted d-block" style="font-size: 10.5px;">Voucher: {{ $rec->ledger->invoice_no }}</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="font-weight-bold text-dark">{{ $rec->batch_no ?: '—' }}</span>
                                </td>
                                <td>
                                    <span class="badge-channel {{ $badgeClass }}">
                                        {{ $rec->channel_label }}
                                    </span>
                                    @if($rec->pos_terminal_name)
                                        <small class="text-muted d-block" style="font-size: 11px;">{{ $rec->pos_terminal_name }}</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-light border text-muted" style="font-size: 11px;">
                                        {{ $rec->vendor->name ?? 'Head Office' }}
                                    </span>
                                </td>
                                <td style="text-align: right; font-weight: 600; color: #1e293b;">
                                    ৳{{ number_format($rec->gross_amount, 2) }}
                                </td>
                                <td style="text-align: right; font-weight: 700; color: #dc2626;">
                                    ৳{{ number_format($rec->fee_amount, 2) }}
                                    @if($rec->fee_rate)
                                        <small class="text-muted d-block" style="font-size: 10.5px;">({{ number_format($rec->fee_rate, 2) }}%)</small>
                                    @endif
                                </td>
                                <td style="text-align: right; font-weight: 800; color: #16a34a;">
                                    ৳{{ number_format($rec->net_amount, 2) }}
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge badge-secondary" style="font-size: 11px;">{{ $rec->total_transactions }}</span>
                                </td>
                                <td style="text-align: center;">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-primary btn-sm btn-view-slip" data-id="{{ $rec->id }}" title="View Voucher Slip">
                                            <i class="fas fa-file-invoice"></i> View
                                        </button>
                                        @can('permission', 'payment_reconciliation.delete')
                                        <button type="button" class="btn btn-outline-danger btn-sm btn-delete-rec" 
                                                data-id="{{ $rec->id }}" 
                                                data-no="{{ $rec->reconciliation_no }}" 
                                                title="Rollback Settlement">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="11" style="text-align: center; padding: 45px 15px; color: #94a3b8;">
                                    <i class="fas fa-inbox text-muted" style="font-size: 36px; display: block; margin-bottom: 12px;"></i>
                                    No past reconciliation records found.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($reconciliations->hasPages())
                <div class="p-3 border-top bg-light">
                    {{ $reconciliations->appends(request()->all())->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ======================================================== --}}
{{-- MODAL 1: SINGLE SETTLE MODAL                             --}}
{{-- ======================================================== --}}
<div class="modal fade" id="singleSettleModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 520px;">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title font-weight-bold" style="font-size: 16px; color: #0f172a;">
                    <i class="fas fa-check-circle text-primary mr-2"></i>Reconcile Single Transaction
                </h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="singleSettleForm" method="POST" action="">
                @csrf
                <div class="modal-body p-4">
                    {{-- Summary Box --}}
                    <div class="p-3 mb-3" style="background: #f1f5f9; border-radius: 8px;">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted small">Invoice #:</span>
                            <span class="font-weight-bold text-dark" id="modal-single-invoice">—</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted small">Patient:</span>
                            <span class="font-weight-bold text-dark" id="modal-single-patient">—</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted small">Payment Method:</span>
                            <span id="modal-single-badge" class="badge badge-primary">POS Card</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                            <span class="font-weight-bold text-dark">Gross Paid by Patient:</span>
                            <span class="font-weight-extrabold text-primary" style="font-size: 18px;" id="modal-single-gross">৳0.00</span>
                        </div>
                    </div>

                    {{-- Form Inputs --}}
                    <div class="form-row">
                        <div class="col-md-6 mb-3">
                            <label class="small font-weight-bold text-muted mb-1">Settlement Date <i class="text-danger">*</i></label>
                            <input type="date" name="settlement_date" id="single-settlement-date" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="small font-weight-bold text-muted mb-1">Slip # / Approval Trx #</label>
                            <input type="text" name="batch_no" id="single-batch-no" class="form-control form-control-sm" placeholder="e.g. Appr 04519">
                        </div>
                    </div>

                    <div class="form-group mb-2">
                        <label class="small font-weight-bold text-muted mb-1 d-flex justify-content-between">
                            <span>Gateway / Card Charge (৳) <i class="text-danger">*</i></span>
                            <span class="text-primary font-weight-normal">Quick % presets:</span>
                        </label>
                        <div class="d-flex gap-1 mb-2">
                            <button type="button" class="btn btn-outline-secondary btn-xs btn-quick-pct mr-1" data-pct="1.2">1.2%</button>
                            <button type="button" class="btn btn-outline-primary btn-xs btn-quick-pct mr-1" data-pct="1.5">1.5% (Card)</button>
                            <button type="button" class="btn btn-outline-info btn-xs btn-quick-pct mr-1" data-pct="1.85">1.85% (bKash)</button>
                            <button type="button" class="btn btn-outline-warning btn-xs btn-quick-pct mr-1" data-pct="2.0">2.0%</button>
                            <button type="button" class="btn btn-outline-dark btn-xs btn-quick-pct" data-pct="2.5">2.5% (Amex)</button>
                        </div>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text font-weight-bold">৳</span>
                            </div>
                            <input type="number" step="0.01" min="0" name="fee_amount" id="single-fee-amount" class="form-control font-weight-bold" placeholder="0.00" required>
                            <input type="hidden" name="fee_rate" id="single-fee-rate">
                        </div>
                    </div>

                    {{-- Net Received Live Display --}}
                    <div class="p-2.5 mb-3 d-flex justify-content-between align-items-center" style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px;">
                        <span class="text-success small font-weight-bold">Net Credited to Bank Account:</span>
                        <span class="font-weight-extrabold text-success" style="font-size: 16px;" id="single-net-display">৳0.00</span>
                    </div>

                    <div class="form-group mb-0">
                        <label class="small font-weight-bold text-muted mb-1">Notes / Remarks (Optional)</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="e.g. City Bank POS Batch #12">
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; border-radius: 0 0 12px 12px;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold px-3">
                        <i class="fas fa-check-circle mr-1"></i>Confirm &amp; Post to Ledger
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ======================================================== --}}
{{-- MODAL 2: BATCH SETTLE MODAL                              --}}
{{-- ======================================================== --}}
<div class="modal fade" id="batchSettleModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 560px;">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title font-weight-bold" style="font-size: 16px; color: #0f172a;">
                    <i class="fas fa-receipt text-primary mr-2"></i>Daily POS Batch Settlement
                </h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="batchSettleForm" method="POST" action="{{ route('payment-reconciliation.settle-batch') }}">
                @csrf
                <div id="batch-hidden-inputs"></div>

                <div class="modal-body p-4">
                    {{-- Summary Box --}}
                    <div class="p-3 mb-3" style="background: #f1f5f9; border-radius: 8px;">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted small">Transactions Selected:</span>
                            <span class="font-weight-bold text-dark" id="modal-batch-count">0</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                            <span class="font-weight-bold text-dark">Total Gross from Terminal Slip:</span>
                            <span class="font-weight-extrabold text-primary" style="font-size: 18px;" id="modal-batch-gross">৳0.00</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-md-6 mb-3">
                            <label class="small font-weight-bold text-muted mb-1">Terminal Batch # <i class="text-danger">*</i></label>
                            <input type="text" name="batch_no" class="form-control form-control-sm" placeholder="e.g. Batch #0045" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="small font-weight-bold text-muted mb-1">Settlement Date <i class="text-danger">*</i></label>
                            <input type="date" name="settlement_date" value="{{ date('Y-m-d') }}" class="form-control form-control-sm" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-md-6 mb-3">
                            <label class="small font-weight-bold text-muted mb-1">Channel <i class="text-danger">*</i></label>
                            <select name="channel" id="batch-channel" class="form-control form-control-sm" required>
                                <option value="card" selected>POS Card Terminal</option>
                                <option value="bkash">bKash Merchant</option>
                                <option value="nagad">Nagad</option>
                                <option value="other">Other Gateway</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="small font-weight-bold text-muted mb-1">POS Terminal Machine</label>
                            <input type="text" name="pos_terminal_name" class="form-control form-control-sm" placeholder="e.g. City Bank POS #1" list="pos_machines_list">
                            <datalist id="pos_machines_list">
                                <option value="City Bank POS">
                                <option value="BRAC Bank POS">
                                <option value="Eastern Bank (EBL) POS">
                                <option value="bKash Merchant QR">
                            </datalist>
                        </div>
                    </div>

                    <div class="form-group mb-2">
                        <label class="small font-weight-bold text-muted mb-1 d-flex justify-content-between">
                            <span>Total Batch Fee / Charge (৳) <i class="text-danger">*</i></span>
                            <span class="text-primary font-weight-normal">Quick presets:</span>
                        </label>
                        <div class="d-flex gap-1 mb-2">
                            <button type="button" class="btn btn-outline-secondary btn-xs btn-batch-quick-pct mr-1" data-pct="1.2">1.2%</button>
                            <button type="button" class="btn btn-outline-primary btn-xs btn-batch-quick-pct mr-1" data-pct="1.5">1.5% (Card)</button>
                            <button type="button" class="btn btn-outline-info btn-xs btn-batch-quick-pct mr-1" data-pct="1.85">1.85% (bKash)</button>
                            <button type="button" class="btn btn-outline-dark btn-xs btn-batch-quick-pct" data-pct="2.5">2.5% (Amex)</button>
                        </div>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text font-weight-bold">৳</span>
                            </div>
                            <input type="number" step="0.01" min="0" name="batch_fee" id="batch-fee-amount" class="form-control font-weight-bold" placeholder="0.00" required>
                        </div>
                    </div>

                    {{-- Batch Net Display --}}
                    <div class="p-2.5 mb-3 d-flex justify-content-between align-items-center" style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px;">
                        <span class="text-success small font-weight-bold">Net Deposit Credited to Bank Account:</span>
                        <span class="font-weight-extrabold text-success" style="font-size: 16px;" id="batch-net-display">৳0.00</span>
                    </div>

                    <div class="form-group mb-0">
                        <label class="small font-weight-bold text-muted mb-1">Notes / Closing Remarks (Optional)</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="e.g. Settled at evening closing">
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; border-radius: 0 0 12px 12px;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold px-3">
                        <i class="fas fa-check-circle mr-1"></i>Confirm Batch Settlement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ======================================================== --}}
{{-- MODAL 3: VIEW VOUCHER / SLIP MODAL                       --}}
{{-- ======================================================== --}}
<div class="modal fade" id="slipModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title font-weight-bold" style="font-size: 16px; color: #0f172a;">
                    <i class="fas fa-receipt text-primary mr-2"></i>Reconciliation Voucher &amp; Statement
                </h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-4" id="slip-modal-content">
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                </div>
            </div>
            <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; border-radius: 0 0 12px 12px;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Hidden Form for Rollback --}}
<form id="rollbackForm" method="POST" action="" style="display: none;">
    @csrf
    @method('DELETE')
</form>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    var singleGross = 0;
    var batchGross = 0;

    // ─────────────────────────────────────────────────────────────
    // SINGLE SETTLE MODAL HANDLERS
    // ─────────────────────────────────────────────────────────────
    $('.btn-open-single-modal').on('click', function() {
        var btn = $(this);
        var saleId = btn.data('id');
        var invoice = btn.data('invoice');
        var patient = btn.data('patient');
        singleGross = parseFloat(btn.data('gross') || 0);
        var channel = btn.data('channel');
        var channelLabel = btn.data('channel-label');
        var badgeClass = btn.data('badge-class') || 'badge-primary';
        var terminal = btn.data('terminal') || '';
        var trx = btn.data('trx') || '';
        var date = btn.data('date') || '{{ date("Y-m-d") }}';

        $('#singleSettleForm').attr('action', '{{ url("admin/payment-reconciliation/settle-single") }}/' + saleId);
        $('#modal-single-invoice').text(invoice);
        $('#modal-single-patient').text(patient);
        $('#modal-single-gross').text('৳' + singleGross.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#modal-single-badge').text(channelLabel).attr('class', 'badge-channel ' + badgeClass);
        $('#single-settlement-date').val(date);
        $('#single-batch-no').val(trx);
        $('#single-fee-amount').val('').focus();
        $('#single-net-display').text('৳' + singleGross.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));

        $('#singleSettleModal').modal('show');
    });

    // Single Settle: Quick % buttons
    $('.btn-quick-pct').on('click', function() {
        var pct = parseFloat($(this).data('pct') || 0);
        if (singleGross > 0 && pct > 0) {
            var fee = (singleGross * pct / 100).toFixed(2);
            $('#single-fee-amount').val(fee).trigger('input');
            $('#single-fee-rate').val(pct);
        }
    });

    // Single Settle: Live Net calculation
    $('#single-fee-amount').on('input', function() {
        var fee = parseFloat($(this).val()) || 0;
        var net = Math.max(0, singleGross - fee);
        $('#single-net-display').text('৳' + net.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
    });

    // ─────────────────────────────────────────────────────────────
    // BATCH CHECKBOXES & SELECTION LOGIC
    // ─────────────────────────────────────────────────────────────
    function updateBatchSelection() {
        var selectedCheckboxes = $('.sale-checkbox:checked');
        var count = selectedCheckboxes.length;
        batchGross = 0;
        var selectedIds = [];

        selectedCheckboxes.each(function() {
            selectedIds.push($(this).data('id'));
            batchGross += parseFloat($(this).data('gross') || 0);
        });

        if (count > 0) {
            $('#selected-count').text(count);
            $('#selected-gross').text('৳' + batchGross.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
            $('#batch-action-bar').slideDown(150);
        } else {
            $('#batch-action-bar').slideUp(150);
        }
    }

    $('#check-all-pending').on('change', function() {
        $('.sale-checkbox').prop('checked', $(this).is(':checked'));
        updateBatchSelection();
    });

    $('.sale-checkbox').on('change', function() {
        updateBatchSelection();
        if (!$(this).is(':checked')) {
            $('#check-all-pending').prop('checked', false);
        }
    });

    // Open Batch Modal
    $('#btn-open-batch-modal').on('click', function() {
        var selectedCheckboxes = $('.sale-checkbox:checked');
        var count = selectedCheckboxes.length;
        if (count === 0) return;

        $('#modal-batch-count').text(count + ' transactions');
        $('#modal-batch-gross').text('৳' + batchGross.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#batch-net-display').text('৳' + batchGross.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#batch-fee-amount').val('');

        // Populate hidden inputs
        var container = $('#batch-hidden-inputs').empty();
        selectedCheckboxes.each(function() {
            container.append($('<input>', {
                type: 'hidden',
                name: 'sale_ids[]',
                value: $(this).data('id')
            }));
        });

        $('#batchSettleModal').modal('show');
    });

    // Batch Quick % buttons
    $('.btn-batch-quick-pct').on('click', function() {
        var pct = parseFloat($(this).data('pct') || 0);
        if (batchGross > 0 && pct > 0) {
            var fee = (batchGross * pct / 100).toFixed(2);
            $('#batch-fee-amount').val(fee).trigger('input');
        }
    });

    // Batch Live Net calculation
    $('#batch-fee-amount').on('input', function() {
        var fee = parseFloat($(this).val()) || 0;
        var net = Math.max(0, batchGross - fee);
        $('#batch-net-display').text('৳' + net.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
    });

    // ─────────────────────────────────────────────────────────────
    // VIEW VOUCHER / SLIP DETAILS
    // ─────────────────────────────────────────────────────────────
    $('.btn-view-slip').on('click', function() {
        var id = $(this).data('id');
        var content = $('#slip-modal-content');
        content.html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i></div>');
        $('#slipModal').modal('show');

        $.get('{{ url("admin/payment-reconciliation/slip") }}/' + id, function(res) {
            if (res.success && res.rec) {
                var r = res.rec;
                var salesHtml = '';
                if (r.sales && r.sales.length > 0) {
                    salesHtml = '<table class="table table-sm table-bordered mt-3 mb-0" style="font-size: 12.5px;">' +
                        '<thead class="thead-light"><tr><th>Invoice #</th><th>Date</th><th>Patient</th><th class="text-right">Gross (৳)</th><th class="text-right">Fee (৳)</th><th class="text-right">Net (৳)</th></tr></thead><tbody>';
                    r.sales.forEach(function(s) {
                        salesHtml += '<tr>' +
                            '<td class="font-weight-bold">' + s.invoice_no + '</td>' +
                            '<td>' + s.date + '</td>' +
                            '<td>' + s.patient + '</td>' +
                            '<td class="text-right">৳' + s.gross + '</td>' +
                            '<td class="text-right text-danger">৳' + s.fee + '</td>' +
                            '<td class="text-right font-weight-bold text-success">৳' + s.net + '</td>' +
                            '</tr>';
                    });
                    salesHtml += '</tbody></table>';
                }

                var html = '<div class="row mb-3">' +
                    '<div class="col-md-6">' +
                        '<div class="font-weight-bold text-dark" style="font-size: 16px;">' + r.reconciliation_no + '</div>' +
                        '<div class="text-muted small">Batch/Slip #: ' + r.batch_no + ' · Channel: <b>' + r.channel_label + '</b></div>' +
                        '<div class="text-muted small">Branch: ' + r.branch_name + ' · Settled Date: <b>' + r.settlement_date + '</b></div>' +
                    '</div>' +
                    '<div class="col-md-6 text-md-right">' +
                        (r.ledger_invoice ? '<span class="badge badge-success px-2 py-1"><i class="fas fa-shield-alt mr-1"></i>Ledger Voucher: ' + r.ledger_invoice + '</span><br>' : '') +
                        '<small class="text-muted">Reconciled by: ' + r.reconciled_by + ' on ' + r.created_at + '</small>' +
                    '</div>' +
                '</div>' +
                '<div class="row text-center p-3 mb-3" style="background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">' +
                    '<div class="col-4">' +
                        '<div class="small text-muted font-weight-bold uppercase">GROSS COLLECTED</div>' +
                        '<div class="font-weight-extrabold text-dark" style="font-size: 17px;">৳' + r.gross_amount + '</div>' +
                    '</div>' +
                    '<div class="col-4">' +
                        '<div class="small text-muted font-weight-bold uppercase">TOTAL CHARGE (' + r.fee_rate + ')</div>' +
                        '<div class="font-weight-extrabold text-danger" style="font-size: 17px;">৳' + r.fee_amount + '</div>' +
                    '</div>' +
                    '<div class="col-4">' +
                        '<div class="small text-muted font-weight-bold uppercase">NET CREDITED</div>' +
                        '<div class="font-weight-extrabold text-success" style="font-size: 17px;">৳' + r.net_amount + '</div>' +
                    '</div>' +
                '</div>' +
                '<h6 class="font-weight-bold text-dark mt-3 mb-2"><i class="fas fa-list mr-1"></i>Included Sales Transactions (' + r.total_transactions + ')</h6>' +
                salesHtml +
                (r.notes !== 'None' ? '<div class="mt-3 p-2 bg-light rounded small text-muted"><strong>Notes:</strong> ' + r.notes + '</div>' : '');

                content.html(html);
            } else {
                content.html('<div class="alert alert-warning mb-0">Failed to load voucher details.</div>');
            }
        });
    });

    // Rollback confirmation
    $('.btn-delete-rec').on('click', function() {
        var id = $(this).data('id');
        var no = $(this).data('no');
        if (confirm('Are you sure you want to rollback reconciliation ' + no + '? This will reverse the fee ledger entry and return all included sales to pending.')) {
            var form = $('#rollbackForm');
            form.attr('action', '{{ url("admin/payment-reconciliation") }}/' + id);
            form.submit();
        }
    });
});
</script>
@endpush
