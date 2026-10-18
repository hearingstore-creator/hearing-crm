@extends('admin.layouts.master')
@section('page_title', 'Purchases')
@section('breadcrumb_parent', 'Inventory')
@section('breadcrumb_parent_url', url('admin/purchase'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-truck" style="color: var(--accent-500); margin-right: 8px;"></i>Purchases</h1>
            <p>Track supplier purchases and inventory intake</p>
        </div>
        <div class="header-actions">
            @can('permission','add_purchase.create')
                <a href="{{ url('admin/purchase/create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus mr-1"></i>Add Purchase
                </a>
            @endcan
        </div>
    </div>

    {{-- Filter Panel --}}
    <div class="crm-filter-panel crm-animate-in delay-1">
        <button class="crm-filter-toggle {{ request()->hasAny(['supplier_id','purchase_invoice_no','start_date','end_date','branch']) ? 'active' : '' }}" 
                onclick="this.classList.toggle('active'); document.getElementById('filterBody').style.display = this.classList.contains('active') ? 'block' : 'none';">
            <i class="fas fa-sliders-h"></i>
            <span>Filters</span>
            <i class="fas fa-chevron-down" style="margin-left: auto;"></i>
        </button>
        <div id="filterBody" class="crm-filter-body" style="{{ request()->hasAny(['supplier_id','purchase_invoice_no','start_date','end_date','branch']) ? '' : 'display:none;' }}">
            <form method="GET" data-supplier-search="{{ url('/admin/supplier-search') }}">
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
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Supplier</label>
                        <select class="form-control supplier select" name="supplier_id">
                            <option value="">All Suppliers</option>
                            @foreach ($suppliers ?? [] as $supplier)
                                <option value="{{ $supplier->id }}"
                                    {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                    {{ $supplier->supplier_name }}
                                </option>
                            @endforeach    
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Invoice No</label>
                        <input type="text" name="purchase_invoice_no" class="form-control" placeholder="Invoice number"
                            value="{{ request('purchase_invoice_no') }}">
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Supplier Memo / Bill</label>
                        <input type="text" name="supplier_memo_no" class="form-control" placeholder="Supplier Memo / Bill #"
                            value="{{ request('supplier_memo_no') }}">
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">From</label>
                        <input type="date" name="start_date" class="form-control"
                            value="{{ request('start_date') }}">
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">To</label>
                        <input type="date" name="end_date" class="form-control"
                            value="{{ request('end_date') }}">
                    </div>
                    <div class="filter-actions">
                        <button class="btn btn-primary btn-sm" type="submit" title="Search">
                            <i class="fas fa-search mr-1"></i>Search
                        </button>
                        <a href="{{ url('admin/purchase') }}" class="btn btn-secondary btn-sm" title="Clear">
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
                All Purchases
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Invoice</th>
                        <th>Supplier</th>
                        <th>Date</th>
                        <th>Discount</th>
                        <th>Total Amount</th>
                        <th>Created</th>
                        <th style="width: 110px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchases ?? [] as $key => $item)
                        <tr>
                            <td style="color: var(--page-text-muted); font-size: 12px;">{{ ++$key }}</td>
                            <td>
                                <a href="{{ route('purchase.show', $item->id) }}" style="font-weight: 600; color: var(--accent-500); font-size: 13px;">
                                    {{ $item->purchase_invoice_no ?? '—' }}
                                </a>
                                @if(!empty($item->supplier_memo_no))
                                    <div style="font-size: 11px; color: var(--page-text-muted); margin-top: 2px;">
                                        <i class="fas fa-file-invoice text-primary mr-1"></i>Supplier Memo: <b style="color: var(--page-text-main);">{{ $item->supplier_memo_no }}</b>
                                    </div>
                                @endif
                                @if (!empty($allBranches))
                                    <div style="font-size: 11px; color: var(--page-text-muted);"><i class="fas fa-store mr-1"></i>{{ $item->vendor->name ?? 'Head Office' }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="crm-name-cell">
                                    <div class="crm-avatar" style="background: var(--warning-50); color: var(--warning-600);">{{ strtoupper(substr($item->supplier->supplier_name ?? 'S', 0, 1)) }}</div>
                                    <div class="crm-name-text">
                                        <span class="name">{{ $item->supplier->supplier_name ?? '—' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span style="font-size: 13px;">{{ $item->date->format('d M Y') }}</span>
                            </td>
                            <td>
                                @if($item->discount > 0)
                                    <span style="font-size: 12px; color: var(--success-600);">৳{{ number_format($item->discount, 2) }}</span>
                                @else
                                    <span style="color: var(--page-text-muted);">—</span>
                                @endif
                            </td>
                            <td>
                                <span style="font-weight: 600;">৳{{ number_format($item->total_amount, 2) }}</span>
                            </td>
                            <td>
                                <span style="font-size: 12px; color: var(--page-text-muted);">
                                    {{ $item->created_at->format('d M Y') }}
                                </span>
                            </td>
                            <td>
                                <div class="crm-actions">
                                    <a href="{{ route('purchase.show', $item->id) }}" class="action-btn" title="View Invoice" style="color: var(--indigo-600); background: var(--indigo-50);">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @can('permission', 'purchase_return.create')
                                        <a href="{{ url('admin/purchase-returns/create?purchase_id='.$item->id) }}" class="action-btn" title="Return to Supplier (Debit Note)" style="color: var(--danger-600); background: var(--danger-50);">
                                            <i class="fas fa-undo"></i>
                                        </a>
                                    @endcan
                                    @php $ownScope = (int) $item->vendor_id === (int) \App\Classes\Helper::currentBranchId(); @endphp
                                    @if ($ownScope)
                                    @can('permission','purchase_list.edit')
                                        <a href="{{ url('admin/purchase/'.$item->id.'/edit') }}" class="action-btn btn-edit" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                    @endcan
                                    @can('permission', 'purchase_list.delete')
                                        <form action="{{ route('purchase.destroy', $item->id) }}" method="POST" style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="action-btn btn-delete" title="Delete"
                                                onclick="confirmDelete(event)">
                                                <i class="far fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px !important; color: var(--page-text-muted);">
                                <i class="fas fa-inbox" style="font-size: 32px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>
                                No purchases found. Try adjusting your filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('assets/purchase.js') }}"></script>
@endpush
