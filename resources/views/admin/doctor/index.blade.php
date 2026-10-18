@extends('admin.layouts.master')
@section('page_title', 'Doctors')
@section('breadcrumb_parent', 'Partners')
@section('breadcrumb_parent_url', url('admin/doctor'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/kpi-cards.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-user-md" style="color: var(--accent-500); margin-right: 8px;"></i>Doctors & Referrals</h1>
            <p>Manage referring doctors, practice chambers, and tiered commission rules</p>
        </div>
        <div class="header-actions">
            @can('permission','add_doctor.create')
                <a href="{{ url('admin/doctor/create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>Add Doctor</a>
            @endcan
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="kpi-row crm-animate-in">
        @include('admin.partials.kpi-card', [
            'href' => url('admin/doctor'), 'color' => 'indigo', 'icon' => 'fas fa-user-md', 'id' => 'kpi-doc-total',
            'label' => 'Total Doctors', 'value' => number_format($doctorStats['total']),
            'sub' => '<b>' . $doctorStats['active'] . '</b> active', 'active' => !request()->boolean('has_due'),
        ])
        @include('admin.partials.kpi-card', [
            'href' => url('admin/doctor?has_due=1'), 'color' => 'rose', 'icon' => 'fas fa-hand-holding-usd', 'id' => 'kpi-doc-due',
            'label' => 'Total Commission Due', 'value' => '৳' . number_format($doctorStats['due'], 2),
            'sub' => '<b>' . $doctorStats['due_count'] . '</b> doctors to be paid', 'active' => request()->boolean('has_due'),
        ])
        @include('admin.partials.kpi-card', [
            'color' => 'green', 'icon' => 'fas fa-chart-line', 'id' => 'kpi-doc-month-sales',
            'label' => 'Referral Sales (' . now()->format('M') . ')', 'value' => '৳' . number_format($doctorStats['month_sales'], 2),
            'sub' => '<b>' . $doctorStats['month_sales_count'] . '</b> referred invoices this month',
        ])
        @include('admin.partials.kpi-card', [
            'color' => 'amber', 'icon' => 'fas fa-coins', 'id' => 'kpi-doc-month-commission',
            'label' => 'Commission (' . now()->format('M') . ')', 'value' => '৳' . number_format($doctorStats['month_commission'], 2),
            'sub' => '<b>৳' . number_format($doctorStats['month_paid'], 2) . '</b> paid this month',
        ])
    </div>

    {{-- Filter Panel --}}
    <div class="crm-filter-panel crm-animate-in delay-1">
        <button class="crm-filter-toggle {{ request()->hasAny(['name','contact_number','email','status','start_date','end_date']) ? 'active' : '' }}" 
                onclick="this.classList.toggle('active'); document.getElementById('filterBody').style.display = this.classList.contains('active') ? 'block' : 'none';">
            <i class="fas fa-sliders-h"></i><span>Filters</span><i class="fas fa-chevron-down" style="margin-left: auto;"></i>
        </button>
        <div id="filterBody" class="crm-filter-body" style="{{ request()->hasAny(['name','contact_number','email','status','start_date','end_date']) ? '' : 'display:none;' }}">
            <form method="GET">
                <div class="filter-row">
                    <div><label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Name</label><input type="text" name="name" class="form-control" placeholder="Name" value="{{ request('name') }}"></div>
                    <div><label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Contact</label><input type="text" name="contact_number" class="form-control" placeholder="Contact Number" value="{{ request('contact_number') }}"></div>
                    <div><label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Email</label><input type="text" name="email" class="form-control" placeholder="Email" value="{{ request('email') }}"></div>
                    <div><label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Status</label><select class="form-control" name="status"><option value="">All</option><option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option><option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option></select></div>
                    <div><label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">From</label><input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}"></div>
                    <div><label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">To</label><input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}"></div>
                    <div class="filter-actions">
                        <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-search mr-1"></i>Search</button>
                        <a href="{{ url('admin/doctor') }}" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i></a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @include('admin.alert_message')

    <div class="crm-table-card crm-animate-in delay-2">
        <div class="crm-table-header">
            <div class="table-title"><i class="fas fa-list" style="color: var(--page-text-muted);"></i> All Doctors</div>
        </div>
        <div style="overflow-x: auto;">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Doctor</th>
                        <th>Chambers & Locations</th>
                        <th>Product Comm.</th>
                        <th>Service Comm.</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th style="width: 90px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($doctors ?? [] as $key => $item)
                    @php
                        $primaryChamber = $item->primaryChamber ?? $item->chambers->first();
                        $totalChambers = $item->chambers->count();
                    @endphp
                    <tr>
                        <td style="color: var(--page-text-muted); font-size: 12px;">{{ ++$key }}</td>
                        <td>
                            <div class="crm-name-cell">
                                <div class="crm-avatar" style="background: var(--info-50); color: var(--info-600);">{{ strtoupper(substr($item->name, 0, 1)) }}</div>
                                <div class="crm-name-text">
                                    <span class="name">{{ $item->name }}</span>
                                    <span class="uid">{{ $item->qualification ?? ($item->contact_number ?? $item->email) }}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($primaryChamber)
                                <div style="font-size: 12px; font-weight: 600; color: #0f172a; line-height: 1.3;">
                                    <i class="fas fa-hospital-alt mr-1 text-primary"></i>{{ $primaryChamber->chamber_name }}
                                    @if($totalChambers > 1)
                                        <span class="badge badge-light border text-muted ml-1" style="font-size: 10px; font-weight: 600;">+{{ $totalChambers - 1 }} more</span>
                                    @endif
                                </div>
                                <div style="font-size: 11px; color: var(--page-text-muted); max-width: 240px; white-space: normal;">
                                    {{ Str::limit($primaryChamber->address, 55) }}
                                </div>
                                @if($primaryChamber->visiting_days || $primaryChamber->visiting_time)
                                    <div style="font-size: 10px; color: #64748b; margin-top: 2px;">
                                        <i class="far fa-clock mr-1"></i>{{ $primaryChamber->visiting_days }} {{ $primaryChamber->visiting_time ? '('.$primaryChamber->visiting_time.')' : '' }}
                                    </div>
                                @endif
                            @else
                                <span style="color: var(--page-text-muted); font-size: 12px;">{{ $item->address ?: '—' }}</span>
                            @endif
                        </td>
                        <td style="font-size: 12px;">
                            <div>
                                <span>{{ number_format($item->product_commission_percent, 1) }}%</span>
                                <span style="color: var(--page-text-muted);">/ ৳{{ number_format($item->product_commission_amount, 0) }}</span>
                            </div>
                            @if($item->categoryCommissions && $item->categoryCommissions->count() > 0)
                                <div style="margin-top: 2px;">
                                    <span class="badge badge-success" style="font-size: 10px; font-weight: 500;" title="Overrides: {{ $item->categoryCommissions->map(fn($c) => ($c->category->category_name ?? 'Cat').' ('.($c->commission_type == 'percent' ? $c->commission_value.'%' : '৳'.$c->commission_value).')')->join(', ') }}">
                                        +{{ $item->categoryCommissions->count() }} cat override{{ $item->categoryCommissions->count() > 1 ? 's' : '' }}
                                    </span>
                                </div>
                            @endif
                        </td>
                        <td style="font-size: 12px;">
                            <div>
                                <span>{{ number_format($item->service_commission_percent, 1) }}%</span>
                                <span style="color: var(--page-text-muted);">/ ৳{{ number_format($item->service_commission_amount, 0) }}</span>
                            </div>
                            @if($item->serviceCommissions && $item->serviceCommissions->count() > 0)
                                <div style="margin-top: 2px;">
                                    <span class="badge badge-info" style="font-size: 10px; font-weight: 500;" title="Overrides: {{ $item->serviceCommissions->map(fn($s) => ($s->serviceType->service_type_name ?? 'Srv').' ('.($s->commission_type == 'percent' ? $s->commission_value.'%' : '৳'.$s->commission_value).')')->join(', ') }}">
                                        +{{ $item->serviceCommissions->count() }} srv override{{ $item->serviceCommissions->count() > 1 ? 's' : '' }}
                                    </span>
                                </div>
                            @endif
                        </td>
                        <td>
                            @php $dueAmount = $item->due_amount; @endphp
                            @if($dueAmount['due'] > 0)
                                <span class="crm-status-badge badge-status-lost"><span class="dot"></span>৳{{ number_format($dueAmount['due'], 2) }}</span>
                            @else
                                <span class="crm-status-badge badge-status-won"><span class="dot"></span>Settled</span>
                            @endif
                        </td>
                        <td>@if($item->status)<span class="crm-status-badge badge-status-won"><span class="dot"></span>Active</span>@else<span class="crm-status-badge badge-status-lost"><span class="dot"></span>Inactive</span>@endif</td>
                        <td>
                            <div class="crm-actions">
                                @can('permission','doctor_list.edit')
                                    <a href="{{ url('admin/doctor/'.$item->id.'/edit') }}" class="action-btn btn-edit" title="Edit Doctor & Rules"><i class="fas fa-pen"></i></a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px !important; color: var(--page-text-muted);">
                            <i class="fas fa-user-md" style="font-size: 32px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>
                            No doctors found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
