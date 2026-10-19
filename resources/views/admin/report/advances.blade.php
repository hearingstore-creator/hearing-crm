@extends('admin.layouts.master')
@section('page_title', 'Customer Advances & Refund Report')
@section('breadcrumb_parent', 'Reports')
@section('breadcrumb_parent_url', url('admin/report/advances'))

@push('css')
    @include('admin.report.partials.report-styles')
    <style>
        .kpi-grid-6 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .badge-status-active { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-status-applied { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .badge-status-partially_applied { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .badge-status-refunded { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .badge-status-credit_kept { background: #f3e8ff; color: #7e22ce; border: 1px solid #e9d5ff; }
    </style>
@endpush

@php
    $money = fn ($v) => '৳' . number_format((float) $v, 2);
    $query = request()->except(['page']);
@endphp

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-hand-holding-usd" style="color: var(--accent-500); margin-right: 8px;"></i>Customer Advances &amp; Refunds Report</h1>
            <p>Comprehensive tracking of appointment deposits, sale deductions, refunds, and retained customer credits</p>
        </div>
        <div class="header-actions">
            @include('admin.report.partials.export-buttons', ['formats' => ['csv']])
            <button type="button" onclick="window.print()" class="btn btn-primary btn-sm" id="btn-print">
                <i class="fas fa-print mr-1"></i>Print Report
            </button>
        </div>
    </div>

    {{-- Filters Card --}}
    <div class="rpt-filter crm-animate-in delay-1 mb-4" style="background: var(--card-bg, #fff); border: 1px solid var(--card-border, #e2e8f0); border-radius: 12px; padding: 18px 20px;">
        <form method="GET" action="{{ route('report.advances') }}">
            <div class="row align-items-end">
                @if ($allBranches)
                    <div class="col-md-2 form-group mb-2">
                        <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--page-text-muted);">Branch</label>
                        <select name="branch" class="form-control form-control-sm">
                            <option value="all" {{ $branchFilter === 'all' || !$branchFilter ? 'selected' : '' }}>All Branches</option>
                            <option value="hq" {{ $branchFilter === 'hq' ? 'selected' : '' }}>Head Office</option>
                            @foreach ($branches as $id => $name)
                                @if ($id !== 'all' && $id !== 'hq')
                                    <option value="{{ $id }}" {{ (string) $branchFilter === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-md-2 form-group mb-2">
                    <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--page-text-muted);">Status</label>
                    <select name="status" class="form-control form-control-sm">
                        <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All Statuses</option>
                        <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active / Unused</option>
                        <option value="applied" {{ $status === 'applied' ? 'selected' : '' }}>Fully Applied</option>
                        <option value="partially_applied" {{ $status === 'partially_applied' ? 'selected' : '' }}>Partially Applied</option>
                        <option value="refunded" {{ $status === 'refunded' ? 'selected' : '' }}>Refunded</option>
                        <option value="credit_kept" {{ $status === 'credit_kept' ? 'selected' : '' }}>Credit Kept</option>
                    </select>
                </div>

                <div class="col-md-2 form-group mb-2">
                    <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--page-text-muted);">Start Date</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}">
                </div>

                <div class="col-md-2 form-group mb-2">
                    <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--page-text-muted);">End Date</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}">
                </div>

                <div class="col-md-2 form-group mb-2">
                    <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--page-text-muted);">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Receipt / Patient / Phone" value="{{ $search }}">
                </div>

                <div class="col-md-2 form-group mb-2 text-right">
                    <button type="submit" class="btn btn-primary btn-sm px-3"><i class="fas fa-search mr-1"></i>Filter</button>
                    <a href="{{ route('report.advances') }}" class="btn btn-outline-secondary btn-sm ml-1" title="Reset"><i class="fas fa-undo"></i></a>
                </div>
            </div>
        </form>
    </div>

    {{-- KPI Summary Row --}}
    <div class="kpi-grid-6 crm-animate-in delay-2">
        @include('admin.partials.kpi-card', [
            'color' => 'indigo',
            'icon'  => 'fas fa-hand-holding-usd',
            'label' => 'Total Received',
            'value' => $money($aggregates->total_received ?? 0),
            'sub'   => '<b>' . number_format($aggregates->total_count ?? 0) . '</b> advances collected'
        ])
        @include('admin.partials.kpi-card', [
            'color' => 'green',
            'icon'  => 'fas fa-shopping-bag',
            'label' => 'Applied to Sales',
            'value' => $money($aggregates->total_applied ?? 0),
            'sub'   => 'deducted from patient invoices'
        ])
        @include('admin.partials.kpi-card', [
            'color' => 'red',
            'icon'  => 'fas fa-undo',
            'label' => 'Refunded Payouts',
            'value' => $money($aggregates->total_refunded ?? 0),
            'sub'   => 'returned to patients'
        ])
        @include('admin.partials.kpi-card', [
            'color' => 'purple',
            'icon'  => 'fas fa-wallet',
            'label' => 'Kept as Credit',
            'value' => $money($aggregates->total_credit_kept ?? 0),
            'sub'   => 'retained on customer accounts'
        ])
        @include('admin.partials.kpi-card', [
            'color' => 'blue',
            'icon'  => 'fas fa-clock',
            'label' => 'Active Available',
            'value' => $money($aggregates->total_active_balance ?? 0),
            'sub'   => 'unused advance on hold'
        ])
        @include('admin.partials.kpi-card', [
            'color' => 'orange',
            'icon'  => 'fas fa-receipt',
            'label' => 'Cancellation Fees',
            'value' => $money($aggregates->total_deduction_fee ?? 0),
            'sub'   => 'cancellation revenue retained'
        ])
    </div>

    {{-- Advances Table --}}
    <div class="crm-table-card crm-animate-in delay-3" style="background: var(--card-bg, #fff); border: 1px solid var(--card-border, #e2e8f0); border-radius: 12px; overflow: hidden; margin-bottom: 30px;">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--card-border, #e2e8f0); display: flex; justify-content: space-between; align-items: center;">
            <h6 style="font-size: 14px; font-weight: 700; margin: 0; color: var(--page-text, #0f172a);">
                <i class="fas fa-list-alt mr-1 text-primary"></i> Advance Records ({{ $advances->total() }})
            </h6>
            <span class="text-muted" style="font-size: 12px;">Showing {{ $advances->firstItem() ?? 0 }} to {{ $advances->lastItem() ?? 0 }} of {{ $advances->total() }}</span>
        </div>

        <div style="overflow-x: auto;">
            <table class="crm-table" style="font-size: 13px;">
                <thead>
                    <tr>
                        <th>Receipt No</th>
                        <th>Date</th>
                        <th>Customer / Patient</th>
                        <th>Branch</th>
                        <th>Appointment Details</th>
                        <th>Method &amp; Ref</th>
                        <th style="text-align: right;">Amount (৳)</th>
                        <th style="text-align: right;">Applied (৳)</th>
                        <th style="text-align: right;">Refunded (৳)</th>
                        <th style="text-align: right;">Balance (৳)</th>
                        <th style="text-align: center;">Status</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($advances as $adv)
                        @php
                            $lead = $adv->customer?->lead;
                            $custName = $lead ? $lead->full_name : ($adv->customer?->name ?? 'Customer #' . $adv->customer_id);
                            $custPhone = $lead ? $lead->phone_number : '—';
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $adv->receipt_no }}</strong>
                                @if($adv->refund_receipt_no)
                                    <div style="font-size: 11px; color: #dc2626;">Ref: {{ $adv->refund_receipt_no }}</div>
                                @endif
                            </td>
                            <td>{{ $adv->date?->format('d M Y') ?? '—' }}</td>
                            <td>
                                <a href="{{ route('customers.show', $adv->customer_id) }}" class="font-weight-600 text-primary">
                                    {{ $custName }}
                                </a>
                                <div style="font-size: 11px; color: var(--page-text-muted);">{{ $custPhone }}</div>
                            </td>
                            <td>
                                <span class="badge badge-light border">
                                    {{ $adv->vendorUser?->name ?? 'Head Office' }}
                                </span>
                            </td>
                            <td>
                                @if ($adv->appointment)
                                    <a href="{{ url('admin/appointments/' . $adv->appointment_id . '/edit') }}" class="text-dark font-weight-500">
                                        <i class="far fa-calendar-check mr-1 text-info"></i>{{ $adv->appointment->appointment_date?->format('d M Y') }}
                                        @if ($adv->appointment->service)
                                            <div style="font-size: 11.5px; color: #475569;">{{ $adv->appointment->service->name }}</div>
                                        @endif
                                    </a>
                                @else
                                    <span class="text-muted">Account Deposit</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-light border font-weight-bold">{{ strtoupper($adv->payment_method) }}</span>
                                @if ($adv->reference)
                                    <div style="font-size: 11px; color: var(--page-text-muted);">Trx: {{ $adv->reference }}</div>
                                @endif
                            </td>
                            <td style="text-align: right; font-weight: 700; color: #166534;">
                                {{ number_format($adv->amount, 2) }}
                            </td>
                            <td style="text-align: right; font-weight: 600; color: #0284c7;">
                                {{ number_format($adv->applied_amount, 2) }}
                            </td>
                            <td style="text-align: right; font-weight: 600; color: #dc2626;">
                                {{ number_format($adv->refunded_amount, 2) }}
                                @if ($adv->deduction_fee > 0)
                                    <div style="font-size: 10.5px; color: #d97706;">Fee: ৳{{ number_format($adv->deduction_fee, 2) }}</div>
                                @endif
                            </td>
                            <td style="text-align: right; font-weight: 700; color: {{ $adv->balance > 0 ? '#4f46e5' : '#64748b' }};">
                                {{ number_format($adv->balance, 2) }}
                            </td>
                            <td style="text-align: center;">
                                <span class="crm-status-badge badge-status-{{ $adv->status }}" style="font-size: 11px; padding: 3px 8px;">
                                    {{ $adv->statusLabel() }}
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <div class="d-inline-flex" style="gap: 4px;">
                                    @if ($adv->appointment_id)
                                        <a href="{{ route('appointments.advance-receipt', $adv->appointment_id) }}" target="_blank" class="btn btn-xs btn-outline-success" title="Money Receipt">
                                            <i class="fas fa-print mr-1"></i>Receipt
                                        </a>
                                    @endif
                                    @if ($adv->status === 'refunded' && $adv->refund_receipt_no)
                                        <a href="{{ route('advances.refund-receipt', $adv->id) }}" target="_blank" class="btn btn-xs btn-outline-danger" title="Refund Receipt">
                                            <i class="fas fa-file-invoice mr-1"></i>Refund Slip
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="text-center py-5 text-muted">
                                <i class="fas fa-folder-open" style="font-size: 32px; display: block; margin-bottom: 10px; color: #cbd5e1;"></i>
                                No advance records found for the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($advances->hasPages())
            <div style="padding: 14px 20px; border-top: 1px solid var(--card-border, #e2e8f0);">
                {{ $advances->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
