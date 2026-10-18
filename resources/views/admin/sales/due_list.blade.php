@extends('admin.layouts.master')
@section('page_title', 'Patient Due List')
@section('breadcrumb_parent', 'Sales')
@section('breadcrumb_parent_url', url('admin/sale'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/kpi-cards.css') }}">
    <style>
        .due-tab-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
            overflow-x: auto;
            padding-bottom: 4px;
        }
        .due-tab-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 600;
            color: var(--page-text-muted, #64748b);
            background: #fff;
            border: 1px solid var(--gray-200, #e2e8f0);
            text-decoration: none;
            transition: all 0.15s ease;
            white-space: nowrap;
        }
        .due-tab-btn:hover {
            color: var(--accent-600, #4f46e5);
            border-color: var(--accent-300, #a5b4fc);
            background: var(--accent-50, #eef2ff);
        }
        .due-tab-btn.active {
            color: #fff;
            background: var(--accent-600, #4f46e5);
            border-color: var(--accent-600, #4f46e5);
            box-shadow: 0 2px 4px rgba(79, 70, 229, 0.25);
        }
        .due-tab-btn .badge-pill-count {
            display: inline-block;
            padding: 2px 8px;
            font-size: 11px;
            border-radius: 9999px;
            background: rgba(0,0,0,0.07);
        }
        .due-tab-btn.active .badge-pill-count {
            background: rgba(255,255,255,0.25);
            color: #fff;
        }
        .btn-call-link, .btn-wa-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            border-radius: 6px;
            font-size: 12px;
            transition: all 0.15s ease;
            text-decoration: none;
        }
        .btn-call-link {
            background: rgba(14, 165, 233, 0.1);
            color: #0284c7;
        }
        .btn-call-link:hover {
            background: #0284c7;
            color: #fff;
        }
        .btn-wa-link {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
        }
        .btn-wa-link:hover {
            background: #059669;
            color: #fff;
        }
        .modal-finance-box {
            background: linear-gradient(135deg, rgba(248, 250, 252, 0.9) 0%, rgba(241, 245, 249, 0.9) 100%);
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .modal-finance-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            text-align: center;
        }
        .modal-finance-item .lbl {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--page-text-muted, #64748b);
            margin-bottom: 4px;
        }
        .modal-finance-item .val {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
        }
        .modal-finance-item .val.red {
            color: #ef4444;
            font-size: 18px;
        }
        .modal-finance-item .val.green {
            color: #10b981;
        }
    </style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-clock" style="color: #ef4444; margin-right: 8px;"></i>Patient Due List & Collection</h1>
            <p>Track unpaid invoices, follow up on promised dates, and collect due payments invoice-wise</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/customer-payment') }}" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-money-bill-wave mr-1"></i>Customer Payment
            </a>
            @can('permission','add_sale.create')
                <a href="{{ url('admin/sale/create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus mr-1"></i>Add Sale
                </a>
            @endcan
        </div>
    </div>

    {{-- Summary / KPI Cards --}}
    @php
        $st = $stats ?? [];
        $currStatus = request('status', 'all');
    @endphp
    <div class="kpi-row crm-animate-in">
        @include('admin.partials.kpi-card', [
            'href' => url('admin/sales/due-list?status=all'),
            'color' => 'indigo', 'icon' => 'fas fa-file-invoice-dollar', 'id' => 'kpi-due-total',
            'label' => 'Total Outstanding Due', 'value' => '৳' . number_format($st['total_amount'] ?? 0, 2),
            'sub' => '<b>' . ($st['total_count'] ?? 0) . '</b> invoices · <b>' . ($st['total_patients'] ?? 0) . '</b> patients',
            'active' => $currStatus === 'all',
        ])
        @include('admin.partials.kpi-card', [
            'href' => url('admin/sales/due-list?status=overdue'),
            'color' => 'rose', 'icon' => 'fas fa-exclamation-triangle', 'id' => 'kpi-due-overdue',
            'label' => 'Overdue Invoices', 'value' => '৳' . number_format($st['overdue_amount'] ?? 0, 2),
            'sub' => '<b>' . ($st['overdue_count'] ?? 0) . '</b> invoices past promise date',
            'active' => $currStatus === 'overdue',
        ])
        @include('admin.partials.kpi-card', [
            'href' => url('admin/sales/due-list?status=today'),
            'color' => 'amber', 'icon' => 'fas fa-clock', 'id' => 'kpi-due-today',
            'label' => 'Due Today', 'value' => '৳' . number_format($st['today_amount'] ?? 0, 2),
            'sub' => '<b>' . ($st['today_count'] ?? 0) . '</b> promised for today',
            'active' => $currStatus === 'today',
        ])
        @include('admin.partials.kpi-card', [
            'href' => url('admin/sales/due-list?status=upcoming'),
            'color' => 'blue', 'icon' => 'fas fa-calendar-alt', 'id' => 'kpi-due-upcoming',
            'label' => 'Upcoming Due', 'value' => '৳' . number_format($st['upcoming_amount'] ?? 0, 2),
            'sub' => '<b>' . ($st['upcoming_count'] ?? 0) . '</b> promised in future',
            'active' => $currStatus === 'upcoming',
        ])
        @include('admin.partials.kpi-card', [
            'href' => url('admin/sales/due-list?status=no_date'),
            'color' => 'slate', 'icon' => 'fas fa-calendar-times', 'id' => 'kpi-due-nodate',
            'label' => 'No Promise Date', 'value' => '৳' . number_format($st['nodate_amount'] ?? 0, 2),
            'sub' => '<b>' . ($st['nodate_count'] ?? 0) . '</b> without promise date',
            'active' => $currStatus === 'no_date',
        ])
    </div>

    {{-- Filter Panel --}}
    <div class="crm-filter-panel crm-animate-in delay-1">
        <button class="crm-filter-toggle {{ request()->hasAny(['q','branch','start_due_date','end_due_date','start_date','end_date']) ? 'active' : '' }}" 
                onclick="this.classList.toggle('active'); document.getElementById('filterBody').style.display = this.classList.contains('active') ? 'block' : 'none';">
            <i class="fas fa-sliders-h"></i>
            <span>Search & Date Filters</span>
            <i class="fas fa-chevron-down" style="margin-left: auto;"></i>
        </button>
        <div id="filterBody" class="crm-filter-body" style="{{ request()->hasAny(['q','branch','start_due_date','end_due_date','start_date','end_date']) ? '' : 'display:none;' }}">
            <form method="GET" action="{{ url('admin/sales/due-list') }}">
                <input type="hidden" name="status" value="{{ $currStatus }}">
                <div class="filter-row">
                    @if (!empty($allBranches))
                        <div>
                            <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Branch</label>
                            <select name="branch" class="form-control" id="filter-branch">
                                <option value="all" @selected(($branchFilter ?? 'all') === 'all')>All Branches</option>
                                <option value="hq" @selected(($branchFilter ?? '') === 'hq')>Head Office</option>
                                @foreach ($branches as $bid => $bname)
                                    <option value="{{ $bid }}" @selected((string) ($branchFilter ?? '') === (string) $bid)>{{ $bname }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Search Patient / Invoice</label>
                        <input type="text" name="q" class="form-control" placeholder="Invoice #, name, phone, UID"
                            value="{{ request('q') }}">
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Promise Date From</label>
                        <input type="date" name="start_due_date" class="form-control"
                            value="{{ request('start_due_date') }}">
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Promise Date To</label>
                        <input type="date" name="end_due_date" class="form-control"
                            value="{{ request('end_due_date') }}">
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Sale Date From</label>
                        <input type="date" name="start_date" class="form-control"
                            value="{{ request('start_date') }}">
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Sale Date To</label>
                        <input type="date" name="end_date" class="form-control"
                            value="{{ request('end_date') }}">
                    </div>
                    <div class="filter-actions">
                        <button class="btn btn-primary btn-sm" type="submit" title="Search">
                            <i class="fas fa-search mr-1"></i>Search
                        </button>
                        <a href="{{ url('admin/sales/due-list') }}" class="btn btn-secondary btn-sm" title="Clear">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @include('admin.alert_message')

    {{-- Status Tabs --}}
    <div class="due-tab-row crm-animate-in delay-1">
        <a href="{{ request()->fullUrlWithQuery(['status' => 'all']) }}" class="due-tab-btn {{ $currStatus === 'all' ? 'active' : '' }}">
            <i class="fas fa-list"></i> All Due
            <span class="badge-pill-count">{{ $st['total_count'] ?? 0 }}</span>
        </a>
        <a href="{{ request()->fullUrlWithQuery(['status' => 'overdue']) }}" class="due-tab-btn {{ $currStatus === 'overdue' ? 'active' : '' }}">
            <i class="fas fa-exclamation-triangle" style="color: {{ $currStatus === 'overdue' ? '#fff' : '#ef4444' }};"></i> Overdue
            <span class="badge-pill-count">{{ $st['overdue_count'] ?? 0 }}</span>
        </a>
        <a href="{{ request()->fullUrlWithQuery(['status' => 'today']) }}" class="due-tab-btn {{ $currStatus === 'today' ? 'active' : '' }}">
            <i class="fas fa-clock" style="color: {{ $currStatus === 'today' ? '#fff' : '#f59e0b' }};"></i> Due Today
            <span class="badge-pill-count">{{ $st['today_count'] ?? 0 }}</span>
        </a>
        <a href="{{ request()->fullUrlWithQuery(['status' => 'upcoming']) }}" class="due-tab-btn {{ $currStatus === 'upcoming' ? 'active' : '' }}">
            <i class="fas fa-calendar-check" style="color: {{ $currStatus === 'upcoming' ? '#fff' : '#0ea5e9' }};"></i> Upcoming
            <span class="badge-pill-count">{{ $st['upcoming_count'] ?? 0 }}</span>
        </a>
        <a href="{{ request()->fullUrlWithQuery(['status' => 'no_date']) }}" class="due-tab-btn {{ $currStatus === 'no_date' ? 'active' : '' }}">
            <i class="fas fa-calendar-times" style="color: {{ $currStatus === 'no_date' ? '#fff' : '#64748b' }};"></i> No Promise Date
            <span class="badge-pill-count">{{ $st['nodate_count'] ?? 0 }}</span>
        </a>
    </div>

    {{-- Due Table Card --}}
    <div class="crm-table-card crm-animate-in delay-2">
        <div class="crm-table-header">
            <div class="table-title">
                <i class="fas fa-hand-holding-usd" style="color: var(--page-text-muted);"></i>
                Patient Invoices With Due Balance
                @if(isset($sales) && method_exists($sales, 'total'))
                    <span class="table-count">{{ $sales->total() }}</span>
                @endif
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table class="crm-table" id="dueListTable">
                <thead>
                    <tr>
                        <th style="width: 45px;">#</th>
                        <th>Invoice No</th>
                        <th>Patient / Customer</th>
                        <th>Sale Date</th>
                        <th>Total Bill</th>
                        <th>Paid</th>
                        <th>Due Amount</th>
                        <th>Promise Date</th>
                        <th style="width: 130px; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @php $today = today(); @endphp
                    @forelse ($sales ?? [] as $key => $item)
                        @php
                            $due = round($item->total_amount - $item->paid, 2);
                            $lead = $item->customer?->lead;
                            $patientName = $lead?->full_name ?? $item->customer?->name ?? 'Customer';
                            $phone = $lead?->phone_number ?? '';
                            $dueDate = $item->due_date;
                        @endphp
                        <tr id="row-invoice-{{ $item->sale_invoice_no }}">
                            <td style="color: var(--page-text-muted); font-size: 12px;">{{ ++$key }}</td>
                            <td>
                                <a href="{{ route('sale.show', $item->id) }}" style="font-weight: 700; color: var(--accent-600); font-size: 13px;" title="View Invoice">
                                    {{ $item->sale_invoice_no }}
                                </a>
                                @if (!empty($allBranches))
                                    <br><span style="font-size: 11px; color: var(--page-text-muted);"><i class="fas fa-store mr-1"></i>{{ $item->vendor->name ?? 'Head Office' }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="crm-name-cell">
                                    <div class="crm-avatar" style="background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);">
                                        {{ strtoupper(substr($patientName, 0, 1)) }}
                                    </div>
                                    <div class="crm-name-text">
                                        @if($item->customer_id)
                                            <a href="{{ route('customers.show', $item->customer_id) }}" class="name" style="color: var(--page-text); font-weight: 600;">
                                                {{ $patientName }}
                                            </a>
                                        @else
                                            <span class="name">{{ $patientName }}</span>
                                        @endif
                                        @if($phone)
                                            <div style="display: flex; align-items: center; gap: 6px; margin-top: 3px;">
                                                <span style="font-size: 11px; color: var(--page-text-muted);">{{ $phone }}</span>
                                                <a href="tel:{{ $phone }}" class="btn-call-link" title="Call Patient"><i class="fas fa-phone"></i></a>
                                                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $phone) }}" target="_blank" class="btn-wa-link" title="Chat on WhatsApp"><i class="fab fa-whatsapp"></i></a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span style="font-size: 12px; color: var(--page-text-muted);">
                                    {{ $item->date ? $item->date->format('d M Y') : '—' }}
                                </span>
                            </td>
                            <td>
                                <span style="font-weight: 600;">৳{{ number_format($item->total_amount, 2) }}</span>
                            </td>
                            <td>
                                <span style="color: var(--success-600); font-weight: 500;">৳{{ number_format($item->paid, 2) }}</span>
                            </td>
                            <td>
                                <span class="crm-status-badge badge-status-lost" style="font-size: 12px; font-weight: 700;">
                                    <span class="dot"></span>
                                    ৳{{ number_format($due, 2) }}
                                </span>
                            </td>
                            <td>
                                @if ($dueDate)
                                    @php
                                        $diffDays = (int) $today->diffInDays($dueDate, false);
                                    @endphp
                                    @if ($diffDays < 0)
                                        <span class="crm-status-badge badge-status-lost" title="Overdue by {{ abs($diffDays) }} days" style="background:#fee2e2; color:#b91c1c; border-color:#fca5a5;">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>Overdue ({{ abs($diffDays) }}d) · {{ $dueDate->format('d M') }}
                                        </span>
                                    @elseif ($diffDays === 0)
                                        <span class="crm-status-badge badge-status-followup" title="Due Today!" style="background:#fef3c7; color:#b45309; border-color:#fde68a;">
                                            <i class="fas fa-clock mr-1"></i>Due Today · {{ $dueDate->format('d M') }}
                                        </span>
                                    @else
                                        <span class="crm-status-badge badge-status-verified" title="Due in {{ $diffDays }} days">
                                            <i class="fas fa-calendar-alt mr-1"></i>In {{ $diffDays }}d · {{ $dueDate->format('d M') }}
                                        </span>
                                    @endif
                                @else
                                    <span class="crm-status-badge badge-status-closed" style="color: #64748b;">
                                        No Date Set
                                    </span>
                                @endif
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; justify-content: center; gap: 4px;">
                                    <button type="button" class="btn btn-success btn-xs" title="Collect Due Payment"
                                            onclick="openCollectModal('{{ $item->sale_invoice_no }}', '{{ addslashes($patientName) }}', '{{ $phone }}', {{ $item->total_amount }}, {{ $item->paid }}, {{ $due }}, '{{ $dueDate ? $dueDate->format('Y-m-d') : '' }}')">
                                        <i class="fas fa-hand-holding-usd mr-1"></i>Collect
                                    </button>
                                    <a href="{{ route('sale.show', $item->id) }}" class="btn btn-outline-secondary btn-xs" title="View Invoice">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 48px !important; color: var(--page-text-muted);">
                                <i class="fas fa-check-circle" style="font-size: 36px; display: block; margin-bottom: 12px; color: #10b981;"></i>
                                <span style="font-size: 15px; font-weight: 600;">No outstanding dues found!</span>
                                <p style="margin-top: 4px; font-size: 13px;">All invoices match your filter or are completely paid up.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(isset($sales) && method_exists($sales, 'hasPages') && $sales->hasPages())
            <div class="crm-table-footer">
                <span>Showing {{ $sales->firstItem() }}–{{ $sales->lastItem() }} of {{ $sales->total() }} due invoices</span>
                {{ $sales->appends(request()->query())->links() }}
            </div>
        @endif
    </div>

</div>

{{-- Quick Collect Due Modal --}}
<div class="modal fade" id="collectDueModal" tabindex="-1" role="dialog" aria-labelledby="collectDueModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 520px;">
        <div class="modal-content" style="border-radius: 14px; overflow: hidden; border: none; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);">
            <div class="modal-header" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff; padding: 16px 20px;">
                <h5 class="modal-title" id="collectDueModalLabel" style="font-weight: 700; font-size: 16px;">
                    <i class="fas fa-hand-holding-usd mr-2"></i>Collect Due Payment
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.85;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="collectDueForm" method="POST" action="{{ route('sale.collect-due') }}">
                @csrf
                <input type="hidden" name="sale_invoice_no" id="modal_invoice_no">

                <div class="modal-body" style="padding: 20px;">
                    {{-- Patient & Invoice Banner --}}
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px solid #e2e8f0;">
                        <div>
                            <div style="font-size: 11px; text-transform: uppercase; color: var(--page-text-muted); font-weight: 600;">Patient</div>
                            <div style="font-size: 15px; font-weight: 700; color: #0f172a;" id="modal_patient_name">—</div>
                            <div style="font-size: 12px; color: var(--page-text-muted);" id="modal_patient_phone">—</div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 11px; text-transform: uppercase; color: var(--page-text-muted); font-weight: 600;">Invoice</div>
                            <div style="font-size: 14px; font-weight: 700; color: #4f46e5;" id="modal_invoice_badge">—</div>
                        </div>
                    </div>

                    {{-- Financial Status Box --}}
                    <div class="modal-finance-box">
                        <div class="modal-finance-grid">
                            <div class="modal-finance-item">
                                <div class="lbl">Total Bill</div>
                                <div class="val" id="modal_total_bill">৳0.00</div>
                            </div>
                            <div class="modal-finance-item">
                                <div class="lbl">Already Paid</div>
                                <div class="val green" id="modal_already_paid">৳0.00</div>
                            </div>
                            <div class="modal-finance-item">
                                <div class="lbl">Current Due</div>
                                <div class="val red" id="modal_current_due">৳0.00</div>
                            </div>
                        </div>
                    </div>

                    {{-- Form Fields --}}
                    <div class="form-group">
                        <label class="crm-form-label">Payment Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="date" value="{{ date('Y-m-d') }}" required>
                    </div>

                    <div class="form-group">
                        <label class="crm-form-label">Amount Collected (৳) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" class="form-control" name="paid_amount" id="modal_paid_amount" required style="font-size: 16px; font-weight: 700; color: #0f172a;">
                        <small class="text-muted">Enter full or partial due collection amount.</small>
                    </div>

                    {{-- New Promise Date (Shown if partial payment) --}}
                    <div class="form-group" id="wrap_modal_new_due_date" style="display: none;">
                        <label class="crm-form-label">
                            <i class="fas fa-calendar-alt mr-1" style="color: #4f46e5;"></i>New Promise / Due Date (For Remaining Balance)
                        </label>
                        <input type="date" class="form-control" name="new_due_date" id="modal_new_due_date" min="{{ date('Y-m-d') }}">
                        <small class="text-muted">Since this is a partial collection, set the next promised date.</small>
                    </div>

                    <div class="form-group">
                        <label class="crm-form-label">Notes / Remarks</label>
                        <textarea class="form-control" name="details" id="modal_details" rows="2" placeholder="e.g. Received via Cash / Bank / bKash"></textarea>
                    </div>

                    <div id="modal-alert-error" class="alert alert-danger" style="display: none; font-size: 13px; margin-top: 10px;"></div>
                </div>

                <div class="modal-footer" style="padding: 14px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm" id="btn-save-collection">
                        <i class="fas fa-check mr-1"></i>Confirm & Save Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
let currentModalDue = 0;

function openCollectModal(invoiceNo, patientName, phone, total, paid, due, currentDueDate) {
    currentModalDue = parseFloat(due);

    $('#modal_invoice_no').val(invoiceNo);
    $('#modal_invoice_badge').text('#' + invoiceNo);
    $('#modal_patient_name').text(patientName || 'Customer');
    $('#modal_patient_phone').text(phone || 'No phone number');

    $('#modal_total_bill').text('৳' + parseFloat(total).toLocaleString(undefined, {minimumFractionDigits: 2}));
    $('#modal_already_paid').text('৳' + parseFloat(paid).toLocaleString(undefined, {minimumFractionDigits: 2}));
    $('#modal_current_due').text('৳' + currentModalDue.toLocaleString(undefined, {minimumFractionDigits: 2}));

    $('#modal_paid_amount').val(currentModalDue.toFixed(2)).attr('max', currentModalDue.toFixed(2));
    $('#modal_new_due_date').val(currentDueDate || '');
    $('#modal_details').val('');
    $('#modal-alert-error').hide();

    checkModalPartial(currentModalDue);

    $('#collectDueModal').modal('show');
}

function checkModalPartial(entered) {
    if (entered < (currentModalDue - 0.009)) {
        $('#wrap_modal_new_due_date').slideDown(150);
    } else {
        $('#wrap_modal_new_due_date').slideUp(150);
    }
}

$('#modal_paid_amount').on('input', function() {
    var val = parseFloat($(this).val()) || 0;
    checkModalPartial(val);
});

$('#collectDueForm').on('submit', function(e) {
    e.preventDefault();
    var $form = $(this);
    var $btn = $('#btn-save-collection');
    var $err = $('#modal-alert-error');

    $err.hide();
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Saving...');

    $.ajax({
        url: $form.attr('action'),
        type: "POST",
        data: $form.serialize(),
        dataType: "json",
        success: function(resp) {
            $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i>Confirm & Save Payment');
            if (resp.success) {
                $('#collectDueModal').modal('hide');
                window.location.reload();
            } else {
                $err.text(resp.message || 'Error collecting payment').show();
            }
        },
        error: function(xhr) {
            $btn.prop('disabled', false).html('<i class="fas fa-check mr-1"></i>Confirm & Save Payment');
            var msg = 'Error collecting due payment.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            }
            $err.text(msg).show();
        }
    });
});
</script>
@endpush
