@extends('admin.layouts.master')
@section('page_title', 'Services Status Report')
@section('breadcrumb_parent', 'Reports')
@section('breadcrumb_parent_url', url('admin/report/services-status'))

@push('css')
    @include('admin.report.partials.report-styles')
    <style>
        .kpi-grid-5 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .days-pill {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
        }
        .days-pill-ok { background: #dcfce7; color: #166534; }
        .days-pill-warn { background: #fef3c7; color: #b45309; }
        .days-pill-danger { background: #fee2e2; color: #b91c1c; }
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
            <h1><i class="fas fa-stethoscope" style="color: var(--accent-500); margin-right: 8px;"></i>Services Status Report</h1>
            <p>Track delivery, fulfillment status, appointment scheduling, and patient waiting times for sold services</p>
        </div>
        <div class="header-actions">
            <button type="button" onclick="window.print()" class="btn btn-primary btn-sm" id="btn-print">
                <i class="fas fa-print mr-1"></i>Print Report
            </button>
        </div>
    </div>

    {{-- Filters Card --}}
    <div class="rpt-filter crm-animate-in delay-1 mb-4" style="background: var(--card-bg, #fff); border: 1px solid var(--card-border, #e2e8f0); border-radius: 12px; padding: 18px 20px;">
        <form method="GET" action="{{ route('report.services-status') }}">
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
                    <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--page-text-muted);">Fulfillment Status</label>
                    <select name="status" class="form-control form-control-sm">
                        <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All Statuses</option>
                        <option value="not_booked" {{ $status === 'not_booked' ? 'selected' : '' }}>Not Booked / Pending</option>
                        <option value="booked" {{ $status === 'booked' ? 'selected' : '' }}>Booked (Appointment Set)</option>
                        <option value="done" {{ $status === 'done' ? 'selected' : '' }}>Delivered / Done</option>
                        <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </div>

                <div class="col-md-2 form-group mb-2">
                    <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--page-text-muted);">Booking Type</label>
                    <select name="booking_type" class="form-control form-control-sm">
                        <option value="all" {{ $bookingType === 'all' ? 'selected' : '' }}>All Booking Types</option>
                        <option value="walk_in" {{ $bookingType === 'walk_in' ? 'selected' : '' }}>Walk-in</option>
                        <option value="appointment_required" {{ $bookingType === 'appointment_required' ? 'selected' : '' }}>Appointment Required</option>
                        <option value="either" {{ $bookingType === 'either' ? 'selected' : '' }}>Either</option>
                    </select>
                </div>

                <div class="col-md-2 form-group mb-2">
                    <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--page-text-muted);">Service Type</label>
                    <select name="service_type_id" class="form-control form-control-sm">
                        <option value="">All Services</option>
                        @foreach ($serviceTypes as $st)
                            <option value="{{ $st->id }}" {{ (string)$serviceTypeId === (string)$st->id ? 'selected' : '' }}>
                                {{ $st->service_type_name }}
                            </option>
                        @endforeach
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

                <div class="col-md-3 form-group mb-2">
                    <label style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--page-text-muted);">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Invoice / Patient / Phone / Service" value="{{ $search }}">
                </div>

                <div class="col-md-3 form-group mb-2 text-right">
                    <button type="submit" class="btn btn-primary btn-sm px-3"><i class="fas fa-search mr-1"></i>Filter</button>
                    <a href="{{ route('report.services-status') }}" class="btn btn-outline-secondary btn-sm ml-1" title="Reset"><i class="fas fa-undo"></i></a>
                </div>
            </div>
        </form>
    </div>

    {{-- KPI Summary Row --}}
    <div class="kpi-grid-5 crm-animate-in delay-2">
        @include('admin.partials.kpi-card', [
            'color' => 'indigo',
            'icon'  => 'fas fa-stethoscope',
            'label' => 'Total Services Sold',
            'value' => number_format($totalSold),
            'sub'   => 'Revenue: <b>' . $money($totalRevenue) . '</b>'
        ])
        @include('admin.partials.kpi-card', [
            'color' => 'green',
            'icon'  => 'fas fa-check-double',
            'label' => 'Delivered / Done',
            'value' => number_format($doneCount),
            'sub'   => '<b>' . ($totalSold > 0 ? round($doneCount / $totalSold * 100) : 0) . '%</b> fulfillment rate'
        ])
        @include('admin.partials.kpi-card', [
            'color' => 'blue',
            'icon'  => 'fas fa-calendar-check',
            'label' => 'Booked (Scheduled)',
            'value' => number_format($bookedCount),
            'sub'   => 'awaiting patient appointment'
        ])
        @include('admin.partials.kpi-card', [
            'color' => 'amber',
            'icon'  => 'fas fa-exclamation-circle',
            'label' => 'Not Booked / Pending',
            'value' => number_format($notBookedCount),
            'sub'   => 'action required (call patient)'
        ])
        @include('admin.partials.kpi-card', [
            'color' => 'rose',
            'icon'  => 'fas fa-clock',
            'label' => 'Avg Waiting Days',
            'value' => $avgWaitingDays . ' days',
            'sub'   => 'for active unfulfilled services'
        ])
    </div>

    {{-- Services Table --}}
    <div class="crm-table-card crm-animate-in delay-3" style="background: var(--card-bg, #fff); border: 1px solid var(--card-border, #e2e8f0); border-radius: 12px; overflow: hidden; margin-bottom: 30px;">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--card-border, #e2e8f0); display: flex; justify-content: space-between; align-items: center;">
            <h6 style="font-size: 14px; font-weight: 700; margin: 0; color: var(--page-text, #0f172a);">
                <i class="fas fa-list-ul mr-1 text-primary"></i> Service Fulfillment Lines ({{ $services->total() }})
            </h6>
            <span class="text-muted" style="font-size: 12px;">Showing {{ $services->firstItem() ?? 0 }} to {{ $services->lastItem() ?? 0 }} of {{ $services->total() }}</span>
        </div>

        <div style="overflow-x: auto;">
            <table class="crm-table" style="font-size: 13px;">
                <thead>
                    <tr>
                        <th>Sale Date &amp; Invoice</th>
                        <th>Patient / Customer</th>
                        <th>Branch</th>
                        <th>Service Type</th>
                        <th>Booking Type</th>
                        <th>Status</th>
                        <th>Waiting Days</th>
                        <th>Attending / Performed By</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($services as $svc)
                        @php
                            $sale = $svc->saleMaster;
                            $lead = $sale?->customer?->lead;
                            $custName = $lead ? $lead->full_name : ($sale?->customer?->name ?? 'Patient');
                            $custPhone = $lead ? $lead->phone_number : '—';
                            $bType = $svc->serviceType?->booking_type ?? 'either';
                            $waitDays = $svc->daysWaiting();
                            $waitClass = $waitDays > 14 ? 'days-pill-danger' : ($waitDays > 7 ? 'days-pill-warn' : 'days-pill-ok');
                        @endphp
                        <tr>
                            <td>
                                @if ($sale)
                                    <a href="{{ route('sale.show', $sale->id) }}" class="font-weight-600 text-primary">
                                        {{ $sale->sale_invoice_no }}
                                    </a>
                                    <div style="font-size: 11px; color: var(--page-text-muted);">
                                        {{ $sale->date?->format('d M Y') }}
                                    </div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($sale && $sale->customer_id)
                                    <a href="{{ route('customers.show', $sale->customer_id) }}" class="font-weight-600 text-dark">
                                        {{ $custName }}
                                    </a>
                                @else
                                    <span>{{ $custName }}</span>
                                @endif
                                <div style="font-size: 11px; color: var(--page-text-muted);">{{ $custPhone }}</div>
                            </td>
                            <td>
                                <span class="badge badge-light border">
                                    {{ $sale?->vendor?->name ?? 'Head Office' }}
                                </span>
                            </td>
                            <td>
                                <strong>{{ $svc->serviceType?->service_type_name ?? 'Service' }}</strong>
                                <div style="font-size: 11px; color: var(--page-text-muted);">
                                    Qty: {{ $svc->qty }} &bull; ৳{{ number_format($svc->service_price, 2) }}
                                </div>
                            </td>
                            <td>
                                @if ($bType === 'walk_in')
                                    <span class="badge badge-success"><i class="fas fa-walking mr-1"></i>Walk-in</span>
                                @elseif ($bType === 'appointment_required')
                                    <span class="badge badge-warning text-dark"><i class="fas fa-calendar-alt mr-1"></i>Appt Req.</span>
                                @else
                                    <span class="badge badge-info"><i class="fas fa-exchange-alt mr-1"></i>Either</span>
                                @endif
                            </td>
                            <td>
                                <span class="{{ $svc->statusBadgeClass() }}" style="font-size: 11px; padding: 3px 8px;">
                                    {{ $svc->statusLabel() }}
                                </span>
                                @if ($svc->fulfillment_status === 'booked' && $svc->appointment)
                                    <div style="font-size: 11px; margin-top: 2px;">
                                        <a href="{{ url('admin/appointments/' . $svc->appointment_id . '/edit') }}" class="text-primary font-weight-500">
                                            <i class="far fa-calendar-check mr-1"></i>{{ $svc->appointment->appointment_date?->format('d M Y') }}
                                        </a>
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if ($svc->fulfillment_status === 'done')
                                    <span class="text-muted" style="font-size: 12px;">Delivered</span>
                                @else
                                    <span class="days-pill {{ $waitClass }}">
                                        {{ $waitDays }} {{ \Illuminate\Support\Str::plural('day', $waitDays) }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if ($svc->fulfillment_status === 'done')
                                    <div style="color: #16a34a; font-weight: 500; font-size: 12px;">
                                        <i class="fas fa-check-circle mr-1"></i>{{ $svc->doneByUser?->name ?? 'Staff' }}
                                    </div>
                                    <small class="text-muted">{{ $svc->done_at?->format('d M Y H:i') }}</small>
                                @elseif ($svc->fulfillment_status === 'booked' && $svc->appointment)
                                    <div style="font-size: 12px;">
                                        <i class="fas fa-user-md mr-1 text-info"></i>{{ $svc->appointment->user?->name ?? 'Doctor Assigned' }}
                                    </div>
                                @else
                                    <span class="text-muted" style="font-size: 12px;">Not Assigned</span>
                                @endif
                            </td>
                            <td style="text-align: center;">
                                <div class="d-inline-flex" style="gap: 4px;">
                                    @if ($sale)
                                        <a href="{{ route('sale.show', $sale->id) }}" class="btn btn-xs btn-outline-secondary" title="View Sale">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    @endif
                                    @if ($svc->fulfillment_status === 'not_booked')
                                        @if ($sale && $sale->customer_id)
                                            <a href="{{ url('admin/appointments/create?customer_id=' . $sale->customer_id . '&service_type_id=' . $svc->service_type_id . '&sale_id=' . $sale->id) }}" class="btn btn-xs btn-outline-primary" title="Book Appointment">
                                                <i class="fas fa-calendar-plus mr-1"></i>Book
                                            </a>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fas fa-clipboard-check" style="font-size: 32px; display: block; margin-bottom: 10px; color: #cbd5e1;"></i>
                                No service fulfillment records match your filter criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($services->hasPages())
            <div style="padding: 14px 20px; border-top: 1px solid var(--card-border, #e2e8f0);">
                {{ $services->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
