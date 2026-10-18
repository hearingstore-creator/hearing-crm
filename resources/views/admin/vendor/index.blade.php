@extends('admin.layouts.master')
@section('page_title', 'Branches')
@section('breadcrumb_parent', 'Partners')
@section('breadcrumb_parent_url', url('admin/vendors'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/kpi-cards.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-store" style="color: var(--accent-500); margin-right: 8px;"></i>Branches</h1>
            <p>Manage your branches (outlets) — each branch has its own staff, leads, sales and stock</p>
        </div>
        <div class="header-actions">
            @can('permission', 'add_vendor.create')
                <a href="{{ url('admin/vendors/create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus mr-1"></i>Add Branch
                </a>
            @endcan
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="kpi-row crm-animate-in">
        @include('admin.partials.kpi-card', [
            'color' => 'indigo', 'icon' => 'fas fa-store', 'id' => 'kpi-vendor-total',
            'label' => 'Total Branches', 'value' => number_format($summary['total']),
            'sub' => '<b>' . $summary['active'] . '</b> active',
        ])
        @include('admin.partials.kpi-card', [
            'color' => 'green', 'icon' => 'fas fa-chart-line', 'id' => 'kpi-vendor-sales',
            'label' => 'Branch Sales (' . now()->format('M') . ')', 'value' => '৳' . number_format($summary['sales_month'], 2),
            'sub' => 'Sales recorded under a branch',
        ])
        @include('admin.partials.kpi-card', [
            'color' => 'rose', 'icon' => 'fas fa-hand-holding-usd', 'id' => 'kpi-vendor-due',
            'label' => 'Branch Dues', 'value' => '৳' . number_format($summary['due'], 2),
            'sub' => 'Outstanding patient dues',
        ])
    </div>

    {{-- Filter Panel --}}
    <div class="crm-filter-panel crm-animate-in delay-1">
        <button class="crm-filter-toggle {{ request()->hasAny(['name','email','mobile_no']) ? 'active' : '' }}" 
                onclick="this.classList.toggle('active'); document.getElementById('filterBody').style.display = this.classList.contains('active') ? 'block' : 'none';">
            <i class="fas fa-sliders-h"></i>
            <span>Filters</span>
            <i class="fas fa-chevron-down" style="margin-left: auto;"></i>
        </button>
        <div id="filterBody" class="crm-filter-body" style="{{ request()->hasAny(['name','email','mobile_no']) ? '' : 'display:none;' }}">
            <form method="GET">
                <div class="filter-row">
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Name</label>
                        <input type="text" name="name" class="form-control" placeholder="Filter by name"
                            value="{{ request('name') }}">
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Email</label>
                        <input type="text" name="email" class="form-control" placeholder="Filter by email"
                            value="{{ request('email') }}">
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Mobile</label>
                        <input type="text" name="mobile_no" class="form-control" placeholder="Filter by mobile"
                            value="{{ request('mobile_no') }}">
                    </div>
                    <div class="filter-actions">
                        <button class="btn btn-primary btn-sm" type="submit" title="Search">
                            <i class="fas fa-search mr-1"></i>Search
                        </button>
                        <a href="{{ url('admin/vendors') }}" class="btn btn-secondary btn-sm" title="Clear">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @include('admin.alert_message')

    {{-- Data Table --}}
    <div class="crm-table-card crm-animate-in delay-2">
        <div class="crm-table-header">
            <div class="table-title">
                <i class="fas fa-list" style="color: var(--page-text-muted);"></i>
                All Branches
                <span class="table-count">{{ count($vendors) }}</span>
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Branch</th>
                        <th>Contact</th>
                        <th style="text-align:center;">Staff</th>
                        <th style="text-align:center;">Leads</th>
                        <th style="text-align:right;">Sales ({{ now()->format('M') }})</th>
                        <th style="text-align:right;">Due</th>
                        <th>Status</th>
                        <th style="width: 110px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($vendors as $key => $item)
                    <tr>
                        <td style="color: var(--page-text-muted); font-size: 12px;">{{ $key + 1 }}</td>
                        <td>
                            <div class="crm-name-cell">
                                @if($item->logo)
                                    <img src="{{ asset($item->logo) }}" alt="{{ $item->name }} logo" class="crm-avatar" style="object-fit: contain; background: #fff; border: 1px solid #e2e8f0;">
                                @else
                                    <div class="crm-avatar" style="background: var(--warning-50); color: var(--warning-600);">{{ strtoupper(substr($item->name, 0, 1)) }}</div>
                                @endif
                                <div class="crm-name-text">
                                    <span class="name">{{ $item->name }}</span>
                                    <span class="uid">{{ \Illuminate\Support\Str::limit($item->address, 50) ?: 'No address' }}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-size: 13px;">{{ $item->mobile_no ?: '—' }}</div>
                            <div style="font-size: 11px; color: var(--page-text-muted);">{{ $item->email }}</div>
                        </td>
                        <td style="text-align:center;">{{ number_format($stats['staff'][$item->id] ?? 0) }}</td>
                        <td style="text-align:center;">{{ number_format($stats['leads'][$item->id] ?? 0) }}</td>
                        <td style="text-align:right;">৳{{ number_format($stats['sales_month'][$item->id] ?? 0, 2) }}</td>
                        <td style="text-align:right;">
                            @php $branchDue = (float) ($stats['due'][$item->id] ?? 0); @endphp
                            @if($branchDue > 0.009)
                                <span style="color: var(--danger-600); font-weight: 600;">৳{{ number_format($branchDue, 2) }}</span>
                            @else
                                <span style="color: var(--page-text-muted);">—</span>
                            @endif
                        </td>
                        <td>
                            @if($item->status == 1)
                                <span class="crm-status-badge badge-status-won"><span class="dot"></span>Active</span>
                            @else
                                <span class="crm-status-badge badge-status-lost"><span class="dot"></span>Inactive</span>
                            @endif
                        </td>
                        <td>
                            <div class="crm-actions">
                                @can('permission', 'vendor_list.edit')
                                    <a href="{{ route('vendors.edit', $item->id) }}" class="action-btn btn-edit" title="Edit">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                @endcan
                                @can('permission', 'vendor_list.delete')
                                    <form action="{{ route('vendors.destroy', $item->id) }}" method="POST" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="action-btn btn-delete" title="Delete" onclick="confirmDelete(event)">
                                            <i class="far fa-trash-alt"></i>
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px !important; color: var(--page-text-muted);">
                            <i class="fas fa-inbox" style="font-size: 32px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>
                            No branches found.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
