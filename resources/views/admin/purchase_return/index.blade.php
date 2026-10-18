@extends('admin.layouts.master')
@section('page_title', 'Purchase Returns (Debit Notes)')
@section('breadcrumb_parent', 'Purchases')
@section('breadcrumb_parent_url', url('admin/purchase'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/kpi-cards.css') }}">
<style>
.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 8px;
    border-radius: 9999px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}
.status-pill.pending {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
}
.status-pill.confirmed {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}
.status-pill.cancelled {
    background: #f1f5f9;
    color: #64748b;
    border: 1px solid #e2e8f0;
}
.days-pending-badge {
    display: inline-block;
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fca5a5;
    border-radius: 4px;
    padding: 1px 5px;
    font-size: 10px;
    font-weight: 700;
    margin-left: 4px;
}
.filter-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.filter-tab {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    color: var(--page-text-muted);
    background: #fff;
    border: 1px solid var(--gray-300);
    text-decoration: none !important;
    transition: all 0.2s ease;
}
.filter-tab:hover {
    background: var(--gray-100);
    color: var(--page-text-main);
}
.filter-tab.active {
    background: var(--primary-600);
    color: #fff;
    border-color: var(--primary-600);
}
.filter-tab .badge {
    border-radius: 10px;
    font-size: 10px;
    padding: 2px 6px;
}
.filter-tab.active .badge {
    background: rgba(255, 255, 255, 0.25);
    color: #fff;
}
</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px 40px 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-undo" style="color: var(--danger-500); margin-right: 8px;"></i>Purchase Returns (Debit Notes)</h1>
            <p>Track goods returned to suppliers, pending confirmations, accounts reconciliation & losses</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/purchase') }}" class="btn btn-outline-secondary btn-sm mr-1">
                <i class="fas fa-shopping-cart mr-1"></i>Purchases List
            </a>
            @can('permission', 'purchase_return.create')
                <a href="{{ route('purchase-returns.create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus mr-1"></i>New Return / Debit Note
                </a>
            @endcan
        </div>
    </div>

    @include('admin.alert_message')

    {{-- KPI Cards --}}
    <div class="kpi-row crm-animate-in">
        @include('admin.partials.kpi-card', [
            'color' => 'indigo', 'icon' => 'fas fa-undo', 'id' => 'kpi-return-total',
            'label' => 'Total Returns', 'value' => number_format($summary['total_count'] ?? 0),
            'sub' => 'Value: ৳' . number_format($summary['total_amount'] ?? 0, 2),
        ])
        @include('admin.partials.kpi-card', [
            'color' => 'amber', 'icon' => 'fas fa-clock', 'id' => 'kpi-return-pending',
            'label' => 'Pending Confirmation', 'value' => number_format($summary['pending_count'] ?? 0),
            'sub' => 'Value: ৳' . number_format($summary['pending_amount'] ?? 0, 2),
        ])
        @include('admin.partials.kpi-card', [
            'color' => 'emerald', 'icon' => 'fas fa-check-circle', 'id' => 'kpi-return-confirmed',
            'label' => 'Confirmed by Supplier', 'value' => number_format($summary['confirmed_count'] ?? 0),
            'sub' => 'Accepted: ৳' . number_format($summary['accepted_amount'] ?? 0, 2),
        ])
        @include('admin.partials.kpi-card', [
            'color' => 'rose', 'icon' => 'fas fa-chart-line-down', 'id' => 'kpi-return-loss',
            'label' => 'Purchase Return Loss', 'value' => '৳' . number_format($summary['loss_amount'] ?? 0, 2),
            'sub' => 'Disallowed / short-credit loss',
        ])
    </div>

    {{-- Quick Status Filter Tabs --}}
    <div class="filter-tabs crm-animate-in delay-1">
        <a href="{{ route('purchase-returns.index', array_merge(request()->except(['status', 'page']))) }}"
           class="filter-tab {{ !request()->filled('status') ? 'active' : '' }}">
            <span>All Returns</span>
            <span class="badge badge-light">{{ $summary['total_count'] ?? 0 }}</span>
        </a>
        <a href="{{ route('purchase-returns.index', array_merge(request()->except(['page']), ['status' => 'pending'])) }}"
           class="filter-tab {{ request('status') === 'pending' ? 'active' : '' }}"
           style="{{ request('status') === 'pending' ? '' : 'border-color: #fde68a; color: #92400e;' }}">
            <i class="fas fa-exclamation-circle text-warning"></i>
            <span>Pending Supplier Confirmation</span>
            <span class="badge {{ request('status') === 'pending' ? 'badge-light' : 'badge-warning' }}">{{ $summary['pending_count'] ?? 0 }}</span>
        </a>
        <a href="{{ route('purchase-returns.index', array_merge(request()->except(['page']), ['status' => 'confirmed'])) }}"
           class="filter-tab {{ request('status') === 'confirmed' ? 'active' : '' }}">
            <i class="fas fa-check-circle text-success"></i>
            <span>Confirmed</span>
            <span class="badge {{ request('status') === 'confirmed' ? 'badge-light' : 'badge-success' }}">{{ $summary['confirmed_count'] ?? 0 }}</span>
        </a>
        <a href="{{ route('purchase-returns.index', array_merge(request()->except(['page']), ['status' => 'cancelled'])) }}"
           class="filter-tab {{ request('status') === 'cancelled' ? 'active' : '' }}">
            <i class="fas fa-ban text-muted"></i>
            <span>Cancelled</span>
        </a>
    </div>

    {{-- Filter Panel --}}
    <div class="crm-filter-panel crm-animate-in delay-1">
        <div class="crm-filter-body" style="display: block;">
            <form method="GET" action="{{ route('purchase-returns.index') }}">
                @if(request()->filled('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                <div class="filter-row">
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Supplier</label>
                        <select class="form-control select" name="supplier_id">
                            <option value="">All Suppliers</option>
                            @foreach ($suppliers as $s)
                                <option value="{{ $s->id }}" {{ request('supplier_id') == $s->id ? 'selected' : '' }}>
                                    {{ $s->supplier_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Debit Note No</label>
                        <input type="text" name="return_no" class="form-control" placeholder="DN-YYYYMM..." value="{{ request('return_no') }}">
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">From Date</label>
                        <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">To Date</label>
                        <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                    </div>
                    <div class="filter-actions">
                        <button class="btn btn-primary btn-sm" type="submit" title="Search">
                            <i class="fas fa-search mr-1"></i>Search
                        </button>
                        <a href="{{ route('purchase-returns.index') }}" class="btn btn-secondary btn-sm" title="Clear">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Table Card --}}
    <div class="crm-table-card crm-animate-in delay-2">
        <div class="crm-table-header">
            <div class="table-title">
                <i class="fas fa-list" style="color: var(--page-text-muted);"></i>
                Purchase Returns List
                <span class="table-count">{{ $returns->total() }}</span>
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th style="width: 45px;">#</th>
                        <th>Debit Note No</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Status</th>
                        <th>Ref Purchase / Memo</th>
                        <th style="text-align: right;">Return Val (৳)</th>
                        <th style="text-align: right;">Accepted (৳)</th>
                        <th style="text-align: right;">Loss (৳)</th>
                        <th style="width: 140px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($returns as $key => $item)
                    <tr>
                        <td style="color: var(--page-text-muted); font-size: 12px;">{{ $returns->firstItem() + $key }}</td>
                        <td>
                            <a href="{{ route('purchase-returns.show', $item->id) }}" style="font-weight: 700; color: var(--danger-600); font-size: 13px;">
                                {{ $item->return_no }}
                            </a>
                            @if($item->vendor)
                                <div style="font-size: 11px; color: var(--page-text-muted);"><i class="fas fa-store mr-1"></i>{{ $item->vendor->name }}</div>
                            @endif
                        </td>
                        <td style="font-size: 13px;">
                            {{ $item->date ? $item->date->format('d M Y') : '—' }}
                        </td>
                        <td>
                            <div class="crm-name-cell">
                                <div class="crm-avatar" style="background: var(--warning-50); color: var(--warning-600);">
                                    {{ strtoupper(substr($item->supplier->supplier_name ?? 'S', 0, 1)) }}
                                </div>
                                <div class="crm-name-text">
                                    <span class="name">{{ $item->supplier->supplier_name ?? '—' }}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($item->status === 'pending')
                                <span class="status-pill pending">
                                    <i class="fas fa-clock"></i> Pending
                                </span>
                                @if($item->days_pending > 0)
                                    <span class="days-pending-badge" title="{{ $item->days_pending }} days since goods dispatched">
                                        {{ $item->days_pending }}d pending
                                    </span>
                                @endif
                            @elseif($item->status === 'confirmed')
                                <span class="status-pill confirmed">
                                    <i class="fas fa-check-circle"></i> Confirmed
                                </span>
                            @elseif($item->status === 'cancelled')
                                <span class="status-pill cancelled">
                                    <i class="fas fa-ban"></i> Cancelled
                                </span>
                            @else
                                <span class="status-pill cancelled">{{ ucfirst($item->status) }}</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $linked = $item->linked_purchases;
                            @endphp
                            @if($linked->count() > 1)
                                <span class="badge badge-info" style="font-size: 11px; padding: 4px 6px;">
                                    <i class="fas fa-layer-group mr-1"></i>Multi-Bill ({{ $linked->count() }})
                                </span>
                            @elseif($linked->count() === 1)
                                <a href="{{ route('purchase.show', $linked->first()->purchase_invoice_no) }}" style="font-size: 12px; font-weight: 500;">
                                    {{ $linked->first()->purchase_invoice_no }}
                                </a>
                            @else
                                <span style="color: var(--page-text-muted); font-size: 12px;">Direct Return</span>
                            @endif
                            @if(!empty($item->supplier_memo_no))
                                <div style="font-size: 11px; color: var(--page-text-muted);">
                                    <i class="fas fa-file-invoice text-primary mr-1"></i>Memo: <b>{{ $item->supplier_memo_no }}</b>
                                </div>
                            @endif
                        </td>
                        <td style="text-align: right;">
                            <span style="font-weight: 700; font-size: 13px; color: var(--page-text-main);">
                                ৳{{ number_format($item->total_amount, 2) }}
                            </span>
                        </td>
                        <td style="text-align: right;">
                            @if($item->isConfirmed())
                                <span style="font-weight: 700; font-size: 13px; color: var(--emerald-600);">
                                    ৳{{ number_format($item->accepted_amount, 2) }}
                                </span>
                            @else
                                <span style="color: var(--page-text-muted); font-size: 12px;">—</span>
                            @endif
                        </td>
                        <td style="text-align: right;">
                            @if($item->isConfirmed() && (float) $item->loss_amount > 0)
                                <span style="font-weight: 700; font-size: 13px; color: var(--danger-600);">
                                    ৳{{ number_format($item->loss_amount, 2) }}
                                </span>
                            @else
                                <span style="color: var(--page-text-muted); font-size: 12px;">৳0.00</span>
                            @endif
                        </td>
                        <td>
                            <div class="crm-actions" style="justify-content: center; gap: 4px;">
                                {{-- View & Print --}}
                                <a href="{{ route('purchase-returns.show', $item->id) }}" class="action-btn" title="View & Print Debit Note" style="color: var(--indigo-600); background: var(--indigo-50);">
                                    <i class="fas fa-eye"></i>
                                </a>

                                {{-- Super Admin Actions --}}
                                @if(auth()->check() && auth()->user()->isSuperAdmin())
                                    @if($item->isPending())
                                        {{-- Edit (Super Admin, Pending only) --}}
                                        <a href="{{ route('purchase-returns.edit', $item->id) }}" class="action-btn" title="Edit Pending Return" style="color: var(--warning-700); background: var(--warning-50);">
                                            <i class="fas fa-pencil-alt"></i>
                                        </a>

                                        {{-- Delete / Void (Super Admin, Pending only) --}}
                                        <button type="button" class="action-btn btn-delete" title="Void Return" onclick="openDeleteModal({{ $item->id }}, '{{ $item->return_no }}')">
                                            <i class="far fa-trash-alt"></i>
                                        </button>
                                    @elseif($item->isConfirmed())
                                        <span class="action-btn" title="Confirmed return: Unconfirm first to edit/delete" style="color: var(--gray-400); background: var(--gray-100); cursor: not-allowed;">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 40px !important; color: var(--page-text-muted);">
                            <i class="fas fa-inbox" style="font-size: 32px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>
                            No purchase returns found for the selected criteria.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($returns->hasPages())
            <div class="crm-table-footer">
                {{ $returns->links() }}
            </div>
        @endif
    </div>

</div>

{{-- Delete Modal for Super Admin --}}
@if(auth()->check() && auth()->user()->isSuperAdmin())
<div class="modal fade" id="deleteReturnModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form method="POST" id="deleteReturnForm" action="">
            @csrf
            @method('DELETE')
            <div class="modal-content" style="border-radius: 8px;">
                <div class="modal-header bg-danger text-white" style="border-radius: 8px 8px 0 0;">
                    <h5 class="modal-title" style="font-size: 16px; font-weight: 700;">
                        <i class="fas fa-exclamation-triangle mr-2"></i>Void Purchase Return
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" style="padding: 20px;">
                    <p>Are you sure you want to void return <strong id="deleteReturnNo" class="text-danger"></strong>?</p>
                    <div class="alert alert-warning" style="font-size: 12px;">
                        <i class="fas fa-info-circle mr-1"></i> Stock movement will be reversed (inventory returned to warehouse). A reason is required for the audit trail.
                    </div>
                    <div class="form-group mb-0">
                        <label for="delete_reason" style="font-size: 12px; font-weight: 600;">Reason for Deletion <span class="text-danger">*</span></label>
                        <textarea name="delete_reason" id="delete_reason" class="form-control" rows="3" required placeholder="Provide a detailed explanation for voiding this return..."></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8fafc; border-radius: 0 0 8px 8px;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="fas fa-trash-alt mr-1"></i>Confirm Void & Restore Stock
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

@push('js')
<script>
function openDeleteModal(id, returnNo) {
    var form = document.getElementById('deleteReturnForm');
    form.action = '{{ url("admin/purchase-returns") }}/' + id;
    document.getElementById('deleteReturnNo').innerText = returnNo;
    $('#deleteReturnModal').modal('show');
}
</script>
@endpush
@endsection
