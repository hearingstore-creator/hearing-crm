@extends('admin.layouts.master')
@section('page_title', 'Suppliers')
@section('breadcrumb_parent', 'CRM')
@section('breadcrumb_parent_url', url('admin/suppliers'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/kpi-cards.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-building" style="color: var(--accent-500); margin-right: 8px;"></i>Suppliers</h1>
            <p>Manage supplier contacts, purchases, payments, and payable statements</p>
        </div>
        <div class="header-actions">
            @can('permission', 'supplier_payment.create')
                <a href="{{ url('admin/suppliers-payment/create') }}" class="btn btn-outline-secondary btn-sm mr-1">
                    <i class="fas fa-money-bill-wave mr-1"></i>Pay Supplier
                </a>
            @endcan
            @can('permission', 'add_supplier.create')
                <a href="{{ url('admin/suppliers/create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus mr-1"></i>Add Supplier
                </a>
            @endcan
        </div>
    </div>

    @include('admin.alert_message')

    {{-- Summary Cards --}}
    @if(isset($summary))
    <div class="kpi-row crm-animate-in">
        @include('admin.partials.kpi-card', [
            'color' => 'indigo', 'icon' => 'fas fa-building', 'id' => 'kpi-supplier-total',
            'label' => 'Total Suppliers', 'value' => number_format($summary['total']),
            'sub' => '<b>' . $summary['active'] . '</b> active' . ($summary['inactive'] > 0 ? ', ' . $summary['inactive'] . ' inactive' : ''),
        ])
        @include('admin.partials.kpi-card', [
            'color' => 'blue', 'icon' => 'fas fa-shopping-cart', 'id' => 'kpi-supplier-purchases',
            'label' => 'Total Purchases', 'value' => '৳' . number_format($summary['total_purchased'], 2),
            'sub' => '<b>' . number_format($summary['total_invoices']) . '</b> invoices recorded',
        ])
        @include('admin.partials.kpi-card', [
            'color' => 'green', 'icon' => 'fas fa-money-bill-wave', 'id' => 'kpi-supplier-paid',
            'label' => 'Total Paid', 'value' => '৳' . number_format($summary['total_paid'], 2),
            'sub' => 'Disbursements to suppliers',
        ])
        @include('admin.partials.kpi-card', [
            'color' => $summary['total_due'] > 0 ? 'rose' : 'teal', 'icon' => 'fas fa-balance-scale', 'id' => 'kpi-supplier-due',
            'label' => 'Payables Due', 'value' => '৳' . number_format($summary['total_due'], 2),
            'sub' => $summary['total_due'] > 0 ? 'Outstanding balance owed' : 'All accounts settled',
        ])
    </div>
    @endif

    {{-- Data Table --}}
    <div class="crm-table-card crm-animate-in delay-1">
        <div class="crm-table-header">
            <div class="table-title">
                <i class="fas fa-list" style="color: var(--page-text-muted);"></i>
                All Suppliers
                <span class="table-count">{{ count($suppliers) }}</span>
            </div>
            <div class="table-actions">
                <a href="{{ route('report.supplier-statement') }}" class="btn btn-outline-secondary btn-sm" title="Open full supplier statement report">
                    <i class="fas fa-file-invoice mr-1"></i>Statement Report
                </a>
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table class="crm-table" data-datatable="supplier-list">
                <thead>
                    <tr>
                        <th style="width: 45px;">#</th>
                        <th>Supplier</th>
                        <th>Contact</th>
                        <th>Location</th>
                        <th style="text-align: right;">Purchases</th>
                        <th style="text-align: right;">Total Paid</th>
                        <th style="text-align: right;">Payable Due</th>
                        <th style="text-align: center;">Status</th>
                        <th style="width: 150px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($suppliers as $key => $item)
                    <tr>
                        <td style="color: var(--page-text-muted); font-size: 12px;">{{ $key + 1 }}</td>
                        <td>
                            <div class="crm-name-cell">
                                <div class="crm-avatar" style="background: var(--info-50); color: var(--info-600);">{{ strtoupper(substr($item->supplier_name, 0, 1)) }}</div>
                                <div class="crm-name-text">
                                    <span class="name">{{ $item->supplier_name }}</span>
                                    <span class="uid">{{ $item->address1 ?? '' }}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-size: 12px; line-height: 1.4;">
                                <div><i class="fas fa-phone-alt text-muted mr-1" style="font-size: 10px;"></i>{{ $item->mobile_no ?: ($item->phone ?: '—') }}</div>
                                @if($item->email1)
                                    <div style="color: var(--page-text-muted);"><i class="far fa-envelope text-muted mr-1" style="font-size: 10px;"></i>{{ $item->email1 }}</div>
                                @endif
                            </div>
                        </td>
                        <td>
                            <span style="font-size: 12px; color: var(--page-text-muted);">
                                {{ implode(', ', array_filter([$item->city, $item->state, $item->country])) ?: '—' }}
                            </span>
                        </td>
                        <td>
                            <div style="text-align: right;">
                                <span style="font-weight: 600; font-size: 13px;">৳{{ number_format($item->purchases_total ?? 0, 2) }}</span>
                                <div style="font-size: 11px; color: var(--page-text-muted);">{{ $item->purchases_count ?? 0 }} invoice{{ ($item->purchases_count ?? 0) == 1 ? '' : 's' }}</div>
                            </div>
                        </td>
                        <td>
                            <div style="text-align: right;">
                                <span style="font-weight: 500; font-size: 13px; color: var(--success-600);">৳{{ number_format($item->total_debit ?? 0, 2) }}</span>
                            </div>
                        </td>
                        <td>
                            <div style="text-align: right;">
                                @if(($item->due_balance ?? 0) > 0)
                                    <span style="font-weight: 700; font-size: 13px; color: var(--danger-600);">৳{{ number_format($item->due_balance, 2) }}</span>
                                    <div style="font-size: 10px; color: var(--danger-500); text-transform: uppercase; font-weight: 600;">Payable</div>
                                @else
                                    <span style="font-size: 12px; color: var(--page-text-muted);">৳0.00</span>
                                @endif
                            </div>
                        </td>
                        <td style="text-align: center;">
                            @if($item->status == 1)
                                <span class="crm-status-badge badge-status-won"><span class="dot"></span>Active</span>
                            @else
                                <span class="crm-status-badge badge-status-lost"><span class="dot"></span>Inactive</span>
                            @endif
                        </td>
                        <td>
                            <div class="crm-actions" style="justify-content: center;">
                                <div class="dropdown d-inline-block">
                                    <button class="action-btn" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Supplier Statements" style="color: var(--indigo-600); background: var(--indigo-50);">
                                        <i class="fas fa-file-invoice"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right" style="min-width: 210px; font-size: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.12); border-radius: 8px;">
                                        <h6 class="dropdown-header font-weight-bold text-uppercase" style="font-size: 10px; color: #64748b;">Statements & Ledger</h6>
                                        <a class="dropdown-item py-2" href="{{ route('report.supplier-statement', ['supplier_id' => $item->id, 'format' => 'standard']) }}">
                                            <i class="fas fa-file-invoice mr-2 text-primary"></i>Standard Ledger
                                        </a>
                                        <a class="dropdown-item py-2" href="{{ route('report.supplier-statement', ['supplier_id' => $item->id, 'format' => 'itemized']) }}">
                                            <i class="fas fa-boxes mr-2 text-info"></i>Itemized (Products & SN)
                                        </a>
                                        <a class="dropdown-item py-2" href="{{ route('report.supplier-statement', ['supplier_id' => $item->id, 'format' => 'aging']) }}">
                                            <i class="fas fa-history mr-2 text-warning"></i>Bill-by-Bill Aging
                                        </a>
                                    </div>
                                </div>
                                @can('permission', 'supplier_payment.create')
                                    <a href="{{ route('suppliers.payment.create', ['supplier_id' => $item->id]) }}" class="action-btn" title="Record Payment" style="color: var(--success-600); background: var(--success-50);">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </a>
                                @endcan
                                @can('permission', 'add_purchase.create')
                                    <a href="{{ route('purchase.create', ['supplier_id' => $item->id]) }}" class="action-btn" title="New Purchase" style="color: var(--blue-600); background: var(--blue-50);">
                                        <i class="fas fa-shopping-cart"></i>
                                    </a>
                                @endcan
                                @can('permission', 'supplier_list.edit')
                                    <a href="{{ route('suppliers.edit', $item->id) }}" class="action-btn btn-edit" title="Edit Supplier">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                @endcan
                                @can('permission', 'supplier_list.delete')
                                    <form action="{{ route('suppliers.destroy', $item->id) }}" method="POST" style="display:inline;">
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
                            No suppliers found.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
