@extends('admin.layouts.master')
@section('page_title', 'Customer Lookup')
@section('breadcrumb_parent', 'Support')
@section('breadcrumb_parent_url', url('admin/customer-lookup'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <style>
        .lookup-search { display: flex; gap: 8px; max-width: 720px; }
        .lookup-search input { font-size: 15px; height: 44px; }
        .lookup-hint { font-size: 12px; color: var(--page-text-muted); margin-top: 6px; }
        .lookup-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin: 16px 0; }
        .lookup-kpi { background: var(--card-bg, var(--page-surface)); border: 1px solid var(--page-border); border-radius: 12px; padding: 14px 16px; }
        .lookup-kpi .k-label { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: var(--page-text-muted); }
        .lookup-kpi .k-value { font-size: 20px; font-weight: 700; margin-top: 4px; }
        .lookup-kpi .k-sub { font-size: 11px; color: var(--page-text-muted); }
        .lookup-pill { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .lookup-pill.ok { background: var(--success-50); color: var(--success-600); }
        .lookup-pill.soon { background: var(--warning-50); color: var(--warning-600); }
        .lookup-pill.over { background: var(--danger-50); color: var(--danger-600); }
        .lookup-section-title { font-weight: 600; display: flex; align-items: center; gap: 8px; }
    </style>
@endpush

@php
    // Date badge: green = valid, amber = ends within 30 days, red = ended
    $dateBadge = function ($date) {
        if (!$date) return '<span style="color: var(--page-text-muted);">-</span>';
        $cls = $date->isPast() ? 'over' : ($date->lte(now()->addDays(30)) ? 'soon' : 'ok');
        return '<span class="lookup-pill ' . $cls . '">' . e($date->format('d M Y')) . '</span>';
    };
@endphp

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-headset" style="color: var(--accent-500); margin-right: 8px;"></i>Customer Lookup</h1>
            <p>Hotline support: invoices, devices, warranty and history in one place (read-only)</p>
        </div>
    </div>

    <form method="GET" action="{{ url('admin/customer-lookup') }}" class="crm-animate-in delay-1" style="margin-bottom: 16px;">
        <div class="lookup-search">
            <input type="text" name="q" id="lookup-q" class="form-control" value="{{ $q }}" autofocus
                   placeholder="Phone, card no., customer ID, invoice no. or serial no.">
            <button type="submit" class="btn btn-primary" id="lookup-submit"><i class="fas fa-search mr-1"></i>Search</button>
        </div>
        <div class="lookup-hint">Examples: 01711000000 · MEM-0001 · INV invoice number · device serial</div>
    </form>

    @if (!$profile && $q !== '')
        <div class="crm-table-card crm-animate-in delay-2">
            <div class="crm-table-header"><div class="table-title"><i class="fas fa-list" style="color: var(--page-text-muted);"></i> Results for "{{ $q }}"</div></div>
            <div style="overflow-x: auto;">
                <table class="crm-table">
                    <thead><tr><th>Name</th><th>Phone</th><th>Type</th><th>Branch</th><th style="text-align: center;">Open</th></tr></thead>
                    <tbody>
                        @foreach ($customers as $c)
                            <tr>
                                <td><div class="crm-name-cell"><div class="crm-avatar">{{ strtoupper(substr($c->lead->full_name ?? 'C', 0, 1)) }}</div><div class="crm-name-text"><span class="name">{{ $c->lead->full_name ?? '-' }}</span><span style="font-size: 11px; color: var(--page-text-muted);">{{ $c->customer_uid }}</span></div></div></td>
                                <td>{{ $c->lead->phone_number ?? '-' }}</td>
                                <td><span class="crm-status-badge badge-status-won"><span class="dot"></span>Customer{{ $c->membershipTier ? ' · ' . $c->membershipTier->name : '' }}</span></td>
                                <td>{{ $c->lead->vendor->name ?? 'Head Office' }}</td>
                                <td style="text-align: center;"><a href="{{ url('admin/customer-lookup?customer=' . $c->id) }}" class="btn btn-primary btn-sm">View</a></td>
                            </tr>
                        @endforeach
                        @foreach ($leads as $l)
                            <tr>
                                <td><div class="crm-name-cell"><div class="crm-avatar">{{ strtoupper(substr($l->full_name ?? 'L', 0, 1)) }}</div><div class="crm-name-text"><span class="name">{{ $l->full_name }}</span><span style="font-size: 11px; color: var(--page-text-muted);">{{ $l->lead_uid }}</span></div></div></td>
                                <td>{{ $l->phone_number }}</td>
                                <td><span class="crm-status-badge badge-status-{{ $l->lead_status }}"><span class="dot"></span>Lead</span></td>
                                <td>{{ \App\Classes\Helper::branchOptions()[$l->vendor_id] ?? 'Head Office' }}</td>
                                <td style="text-align: center;">
                                    @can('permission', 'lead_list.view')
                                        <a href="{{ url('admin/leads/' . $l->id) }}" class="btn btn-secondary btn-sm">Lead</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                        @if ($customers->isEmpty() && $leads->isEmpty())
                            <tr><td colspan="5" style="text-align: center; padding: 40px !important; color: var(--page-text-muted);"><i class="fas fa-search" style="font-size: 28px; display: block; margin-bottom: 10px;"></i>No customer or lead found.</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($profile)
        @php $customer = $profile['customer']; $lead = $customer->lead; @endphp

        <div class="crm-hero-card crm-animate-in delay-2">
            <div class="crm-hero-top">
                <div class="crm-hero-identity">
                    <div class="crm-hero-avatar">{{ strtoupper(substr($lead->full_name ?? 'C', 0, 1)) }}</div>
                    <div class="crm-hero-info">
                        <h2>{{ $lead->full_name ?? '-' }}</h2>
                        <div class="crm-hero-meta">
                            <span><i class="fas fa-hashtag"></i> {{ $customer->customer_uid }}</span>
                            <span><i class="fas fa-phone"></i> {{ $lead->phone_number ?? '-' }}</span>
                            <span><i class="fas fa-store"></i> {{ $lead->vendor->name ?? 'Head Office' }}</span>
                            @if ($customer->membershipTier)
                                <span class="crm-status-badge badge-status-won"><span class="dot"></span>{{ $customer->membershipTier->name }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    @can('permission', 'add_ticket.create')
                        <a href="{{ url('admin/ticket/create?customer_id=' . $customer->id) }}" class="btn btn-primary btn-sm" id="lookup-create-ticket"><i class="fas fa-ticket-alt mr-1"></i>Create Ticket</a>
                    @endcan
                    @can('permission', 'add_appointment.create')
                        <a href="{{ url('admin/appointments/create') }}" class="btn btn-secondary btn-sm" id="lookup-book-appointment"><i class="fas fa-calendar-plus mr-1"></i>Book Appointment</a>
                    @endcan
                </div>
            </div>
        </div>

        <div class="lookup-grid crm-animate-in delay-2">
            <div class="lookup-kpi"><div class="k-label">Card No.</div><div class="k-value">{{ $customer->membership_id ?: '-' }}</div></div>
            <div class="lookup-kpi"><div class="k-label">Points Balance</div><div class="k-value">{{ number_format($profile['points'], 0) }}</div></div>
            <div class="lookup-kpi"><div class="k-label">Expiring in 30 days</div><div class="k-value">{{ number_format($profile['expiringPoints'], 0) }}</div>
                @if ($profile['firstExpiry'])<div class="k-sub">from {{ \Carbon\Carbon::parse($profile['firstExpiry'])->format('d M Y') }}</div>@endif</div>
            <div class="lookup-kpi"><div class="k-label">Invoices</div><div class="k-value">{{ $profile['sales']->count() }}</div><div class="k-sub">৳{{ number_format($profile['sales']->sum('total_amount'), 0) }} total</div></div>
            <div class="lookup-kpi"><div class="k-label">Due</div><div class="k-value">৳{{ number_format(max(0, $profile['sales']->sum('total_amount') - $profile['sales']->sum('paid')), 0) }}</div></div>
        </div>

        {{-- Devices & warranty --}}
        <div class="crm-table-card crm-animate-in delay-3" style="margin-bottom: 16px;">
            <div class="crm-table-header"><div class="table-title lookup-section-title"><i class="fas fa-shield-alt" style="color: var(--page-text-muted);"></i> Devices &amp; Warranty</div></div>
            <div style="overflow-x: auto;">
                <table class="crm-table">
                    <thead><tr><th>Product</th><th>Serial No.</th><th>Qty</th><th>Invoice</th><th>Branch</th><th>Sold</th><th>Warranty Until</th><th>Battery / Usage Due</th></tr></thead>
                    <tbody>
                        @forelse ($profile['devices'] as $d)
                            <tr>
                                <td style="font-weight: 600;">{{ $d->product }}</td>
                                <td style="font-size: 12px;">{{ $d->serials ?: '-' }}</td>
                                <td>{{ $d->qty }}</td>
                                <td>{{ $d->invoice }}</td>
                                <td style="font-size: 12px;">{{ $d->branch }}</td>
                                <td style="font-size: 12px;">{{ $d->sold_on?->format('d M Y') }}</td>
                                <td>{!! $dateBadge($d->warranty_until) !!}</td>
                                <td>{!! $dateBadge($d->usage_until) !!}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" style="text-align: center; padding: 24px !important; color: var(--page-text-muted);">No devices purchased yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Invoices --}}
        <div class="crm-table-card crm-animate-in delay-3" style="margin-bottom: 16px;">
            <div class="crm-table-header"><div class="table-title lookup-section-title"><i class="fas fa-file-invoice" style="color: var(--page-text-muted);"></i> Purchase History</div></div>
            <div style="overflow-x: auto;">
                <table class="crm-table">
                    <thead><tr><th>Invoice</th><th>Date</th><th>Branch</th><th>Total</th><th>Paid</th><th>Due</th><th style="text-align: center;">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($profile['sales'] as $s)
                            <tr>
                                <td style="font-weight: 600; color: var(--accent-500);">{{ $s->sale_invoice_no }}</td>
                                <td style="font-size: 12px;">{{ $s->date?->format('d M Y') }}</td>
                                <td style="font-size: 12px;">{{ $s->vendor->name ?? 'Head Office' }}</td>
                                <td>৳{{ number_format($s->total_amount, 2) }}</td>
                                <td>৳{{ number_format($s->paid, 2) }}</td>
                                <td>৳{{ number_format(max(0, $s->total_amount - $s->paid), 2) }}</td>
                                <td><div class="crm-actions" style="justify-content: center;">
                                    @can('permission', 'sale_list.view')
                                        <a href="{{ url('admin/sale/' . $s->id) }}" class="action-btn btn-view" title="View invoice" target="_blank"><i class="fas fa-eye"></i></a>
                                        <a href="{{ route('sale.pos-print', $s->id) }}" class="action-btn btn-edit" title="Print" target="_blank"><i class="fas fa-print"></i></a>
                                    @endcan
                                </div></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" style="text-align: center; padding: 24px !important; color: var(--page-text-muted);">No invoices.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row crm-animate-in delay-3">
            <div class="col-lg-6">
                <div class="crm-table-card" style="margin-bottom: 16px;">
                    <div class="crm-table-header"><div class="table-title lookup-section-title"><i class="fas fa-ticket-alt" style="color: var(--page-text-muted);"></i> Tickets</div></div>
                    <table class="crm-table">
                        <thead><tr><th>Subject</th><th>Status</th><th>Created</th></tr></thead>
                        <tbody>
                            @forelse ($profile['tickets'] as $t)
                                <tr>
                                    <td>{{ $t->subject }}</td>
                                    <td><span class="lookup-pill ok">{{ ucfirst(str_replace('_', ' ', $t->status?->value ?? '-')) }}</span></td>
                                    <td style="font-size: 12px;">{{ $t->created_at?->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" style="text-align: center; padding: 20px !important; color: var(--page-text-muted);">No tickets.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="crm-table-card" style="margin-bottom: 16px;">
                    <div class="crm-table-header"><div class="table-title lookup-section-title"><i class="fas fa-calendar-check" style="color: var(--page-text-muted);"></i> Appointments</div></div>
                    <table class="crm-table">
                        <thead><tr><th>Date</th><th>Service</th><th>Professional</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($profile['appointments'] as $a)
                                <tr>
                                    <td style="font-size: 12px;">{{ $a->appointment_date?->format('d M Y') }}</td>
                                    <td>{{ $a->service->name ?? '-' }}</td>
                                    <td>{{ $a->user->name ?? '-' }}</td>
                                    <td>{{ $a->status?->label() ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" style="text-align: center; padding: 20px !important; color: var(--page-text-muted);">No appointments.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if (!$profile && $q === '')
        <div class="crm-table-card crm-animate-in delay-2" style="padding: 48px; text-align: center; color: var(--page-text-muted);">
            <i class="fas fa-headset" style="font-size: 36px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>
            Search a caller by phone, membership card, invoice or device serial number.
        </div>
    @endif
</div>
@endsection
