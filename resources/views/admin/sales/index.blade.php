@extends('admin.layouts.master')
@section('page_title', 'Sales Management')
@section('breadcrumb_parent', 'Sales')
@section('breadcrumb_parent_url', url('admin/sale'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/kpi-cards.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-shopping-cart" style="color: var(--accent-500); margin-right: 8px;"></i>Sales Management</h1>
            <p>Track invoices, payments, and revenue</p>
        </div>
        <div class="header-actions">
            @if (request()->boolean('cancelled'))
                <a href="{{ url('admin/sale') }}" class="btn btn-outline-secondary btn-sm" id="btn-active-sales"><i class="fas fa-arrow-left mr-1"></i>Active invoices</a>
            @else
                <a href="{{ url('admin/sale?cancelled=1') }}" class="btn btn-outline-danger btn-sm" id="btn-cancelled-sales"><i class="fas fa-ban mr-1"></i>Cancelled invoices</a>
            @endif
            <a href="{{ url('admin/sales/fulfillment') }}" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-clipboard-check mr-1"></i>Report &amp; Delivery Hub
            </a>
            @can('permission','add_sale.create')
                <a href="{{ url('admin/sale/create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus mr-1"></i>Add Sale
                </a>
            @endcan
        </div>
    </div>

    {{-- Summary Cards --}}
    @php $ss = $saleStats; @endphp
    <div class="kpi-row crm-animate-in">
        @include('admin.partials.kpi-card', [
            'href' => url('admin/sale?start_date=' . today()->toDateString() . '&end_date=' . today()->toDateString()),
            'color' => 'green', 'icon' => 'fas fa-cash-register', 'id' => 'kpi-sale-today',
            'label' => "Today's Sales", 'value' => '৳' . number_format($ss['today_amount'], 2),
            'sub' => '<b>' . $ss['today_count'] . '</b> invoices today',
        ])
        @include('admin.partials.kpi-card', [
            'href' => url('admin/sale?start_date=' . now()->startOfMonth()->toDateString() . '&end_date=' . today()->toDateString()),
            'color' => 'blue', 'icon' => 'fas fa-calendar-alt', 'id' => 'kpi-sale-month',
            'label' => 'This Month', 'value' => '৳' . number_format($ss['month_amount'], 2),
            'sub' => '<b>' . $ss['month_count'] . '</b> invoices in ' . now()->format('F'),
        ])
        @include('admin.partials.kpi-card', [
            'href' => url('admin/sale'), 'color' => 'indigo', 'icon' => 'fas fa-chart-line', 'id' => 'kpi-sale-total',
            'label' => 'Total Revenue', 'value' => '৳' . number_format($ss['total_amount'], 2),
            'sub' => '<b>' . number_format($ss['total_count']) . '</b> invoices · ৳' . number_format($ss['total_paid'], 0) . ' collected',
            'active' => !request()->boolean('due_only') && !request()->filled('start_date'),
        ])
        @include('admin.partials.kpi-card', [
            'href' => url('admin/sale?due_only=1'), 'color' => 'rose', 'icon' => 'fas fa-file-invoice-dollar', 'id' => 'kpi-sale-due',
            'label' => 'Total Due', 'value' => '৳' . number_format($ss['due_amount'], 2),
            'sub' => '<b>' . $ss['due_count'] . '</b> invoices with balance', 'active' => request()->boolean('due_only'),
        ])
    </div>

    {{-- Fast Barcode Scanner Station --}}
    <div class="card mb-3 border-0 shadow-sm crm-animate-in delay-1" style="border-radius: 12px; background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: #fff; overflow: hidden;">
        <div style="padding: 12px 18px;">
            <div class="row align-items-center">
                <div class="col-md-5 mb-2 mb-md-0">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="width: 36px; height: 36px; border-radius: 8px; background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.4); display: flex; align-items: center; justify-content: center; font-size: 16px; color: #60a5fa;">
                            <i class="fas fa-barcode"></i>
                        </div>
                        <div>
                            <div style="font-weight: 700; font-size: 13.5px; letter-spacing: 0.3px; color: #fff;">Barcode Scanner Station</div>
                            <div style="font-size: 11px; color: #94a3b8;">
                                <i class="fas fa-magic mr-1 text-info"></i>Hands-free: pull scanner gun trigger anywhere on page
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.18); border-right: none; color: #38bdf8;">
                                <i class="fas fa-qrcode"></i>
                            </span>
                        </div>
                        <input type="text" 
                               id="index_barcode_input" 
                               class="form-control barcode-scan-input" 
                               placeholder="📷 Scan invoice barcode or type invoice # / S/N (Press Enter)..." 
                               autocomplete="off"
                               style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.18); color: #fff; font-weight: 600; font-size: 13px;">
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="button" id="btn_index_barcode_scan" style="background: #3b82f6; border: none; font-weight: 600; font-size: 12.5px; padding: 0 16px;">
                                <i class="fas fa-search mr-1"></i>Scan &amp; Update
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Panel --}}
    <div class="crm-filter-panel crm-animate-in delay-1">
        <button class="crm-filter-toggle {{ request()->hasAny(['customer_id','sale_invoice_no','serial_no','start_date','end_date']) ? 'active' : '' }}" 
                onclick="this.classList.toggle('active'); document.getElementById('filterBody').style.display = this.classList.contains('active') ? 'block' : 'none';">
            <i class="fas fa-sliders-h"></i>
            <span>Filters</span>
            <i class="fas fa-chevron-down" style="margin-left: auto;"></i>
        </button>
        <div id="filterBody" class="crm-filter-body" style="{{ request()->hasAny(['customer_id','sale_invoice_no','serial_no','start_date','end_date']) ? '' : 'display:none;' }}">
            <form method="GET"
                data-product-search="{{ url('/admin/product-search') }}"
                data-customer-search="{{ url('/admin/customer-search') }}">
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
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Customer</label>
                        <select class="form-control customer" name="customer_id">
                            <option value="">All Customers</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Invoice No</label>
                        <input type="text" name="sale_invoice_no" class="form-control" placeholder="Invoice number"
                            value="{{ request('sale_invoice_no') }}">
                    </div>
                    <div>
                        <label style="font-size: 11px; color: var(--page-text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Serial No</label>
                        <input type="text" name="serial_no" class="form-control" placeholder="Serial number"
                            value="{{ request('serial_no') }}">
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
                        <a href="{{ url('admin/sale') }}" class="btn btn-secondary btn-sm" title="Clear">
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
                <i class="fas fa-receipt" style="color: var(--page-text-muted);"></i>
                All Sales
                @if(isset($sales) && method_exists($sales, 'total'))
                    <span class="table-count">{{ $sales->total() }}</span>
                @endif
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Paid</th>
                        <th>Due</th>
                        <th style="width: 110px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sales ?? [] as $key => $item)
                        <tr>
                            <td style="color: var(--page-text-muted); font-size: 12px;">{{ ++$key }}</td>
                            <td>
                                <span style="font-weight: 600; color: var(--accent-500); font-size: 13px;">
                                    {{ $item->sale_invoice_no ?? '—' }}
                                </span>
                                @if (!empty($allBranches))
                                    <br><span style="font-size: 11px; color: var(--page-text-muted);"><i class="fas fa-store mr-1"></i>{{ $item->vendor->name ?? 'Head Office' }}</span>
                                @endif
                                @php
                                    $itemStatus = $item->deliveryStatusEnum();
                                    $isServiceItem = $item->isServiceSale();
                                    $itemDue = round($item->total_amount - $item->paid, 2);
                                @endphp
                                <div class="dropdown mt-1">
                                    <button class="btn btn-xs dropdown-toggle status-btn-live-{{ $item->id }}" 
                                            type="button" 
                                            data-toggle="dropdown" 
                                            aria-haspopup="true" 
                                            aria-expanded="false" 
                                            style="display:inline-flex; align-items:center; gap:4px; font-size:10.5px; font-weight:700; padding:2px 7px; border-radius:9999px; background:{{ $itemStatus->badgeBg() }}; color:{{ $itemStatus->badgeColor() }}; border:1px solid {{ $itemStatus->badgeColor() }}40; cursor:pointer;" 
                                            title="Click to quick change status">
                                        <i class="fas {{ $itemStatus->icon() }}"></i>
                                        <span>{{ $itemStatus->label($isServiceItem) }}</span>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-left shadow-sm p-1" style="border-radius:8px; font-size:11.5px; min-width:170px;">
                                        <div class="dropdown-header text-uppercase font-weight-bold py-1 px-2 text-muted" style="font-size:9.5px;">Update Status</div>
                                        @foreach (\App\Enums\DeliveryStatus::casesFrom($item->delivery_status, $isServiceItem) as $stCase)
                                            <a class="dropdown-item py-1 px-2 d-flex align-items-center quick-status-select-index {{ $item->delivery_status === $stCase->value ? 'active font-weight-bold' : '' }}" 
                                               href="#" 
                                               data-id="{{ $item->id }}" 
                                               data-status="{{ $stCase->value }}"
                                               data-due="{{ $itemDue }}">
                                                <span class="mr-2" style="width:7px; height:7px; border-radius:50%; background:{{ $stCase->badgeColor() }}; display:inline-block;"></span>
                                                {{ $stCase->label($isServiceItem) }}
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="crm-name-cell">
                                    <div class="crm-avatar">{{ strtoupper(substr($item->customer->lead->full_name ?? 'S', 0, 1)) }}</div>
                                    <div class="crm-name-text">
                                        <span class="name">{{ $item->customer->lead->full_name ?? '—' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span style="font-size: 12px; color: var(--page-text-muted);">
                                    {{ $item->date->format('d M Y') }}
                                </span>
                            </td>
                            <td>
                                <span style="font-weight: 600;">৳{{ number_format($item->total_amount, 2) }}</span>
                                @if($item->total_discount > 0)
                                    <br><span style="font-size: 11px; color: var(--success-600);">-৳{{ number_format($item->total_discount, 2) }} disc.</span>
                                @endif
                            </td>
                            <td>
                                <span style="color: var(--success-600); font-weight: 500;">৳{{ number_format($item->paid, 2) }}</span>
                            </td>
                            <td>
                                @php $due = $item->total_amount - $item->paid; $isCancelled = $item->trashed(); @endphp
                                @if($isCancelled)
                                    <span class="crm-status-badge badge-status-lost" title="{{ $item->cancel_reason }}"><span class="dot"></span>Cancelled</span>
                                @elseif($due > 0)
                                    <span class="crm-status-badge badge-status-lost">
                                        <span class="dot"></span>
                                        ৳{{ number_format($due, 2) }}
                                    </span>
                                    @if($item->due_date)
                                        <br><span style="font-size: 11px; color: #ef4444; font-weight: 500;" title="Promise Date"><i class="far fa-clock mr-1"></i>{{ $item->due_date->format('d M Y') }}</span>
                                    @endif
                                @else
                                    <span class="crm-status-badge badge-status-won">
                                        <span class="dot"></span>
                                        Paid
                                    </span>
                                @endif
                            </td>
                            <td>
                                <div class="crm-actions">
                                    @if($due > 0.009 && !$isCancelled)
                                        <a href="{{ url('admin/customer-payment?customer_id=' . $item->customer_id . '&invoice_no=' . $item->sale_invoice_no) }}" class="action-btn" style="color: #10b981;" title="Collect Due Payment">
                                            <i class="fas fa-hand-holding-usd"></i>
                                        </a>
                                    @endif
                                    @can('permission','sale_list.view')
                                        <a href="{{ url('admin/sale/'.$item->id) }}" class="action-btn btn-view" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('sale.pos-print', $item->id) }}" target="_blank" class="action-btn" style="color: #6366f1;" title="POS Receipt">
                                            <i class="fas fa-receipt"></i>
                                        </a>
                                    @endcan
                                    @php $ownScope = (int) $item->vendor_id === (int) \App\Classes\Helper::currentBranchId(); @endphp
                                    @if ($ownScope && !$isCancelled)
                                    @can('permission','sale_list.edit')
                                        <a href="{{ url('admin/sale/'.$item->id.'/edit') }}" class="action-btn btn-edit" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                    @endcan
                                    @if(auth()->user()->isSuperAdmin())
                                        <form action="{{ route('sale.destroy', $item->id) }}" method="POST" style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="action-btn btn-delete" title="Cancel invoice"
                                                onclick="confirmCancelWithReason(event, 'this sale invoice')">
                                                <i class="far fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px !important; color: var(--page-text-muted);">
                                <i class="fas fa-inbox" style="font-size: 32px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>
                                No sales found. Try adjusting your filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(isset($sales) && method_exists($sales, 'hasPages') && $sales->hasPages())
            <div class="crm-table-footer">
                <span>Showing {{ $sales->firstItem() }}–{{ $sales->lastItem() }} of {{ $sales->total() }} sales</span>
                {{ $sales->appends(request()->query())->links() }}
            </div>
        @endif
    </div>

</div>

{{-- Barcode Fast Scanner Modal & Global Hardware Listener --}}
@include('admin.sales.partials.barcode_scan_modal')

@endsection

@push('scripts')
    <script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('assets/sales.js') }}"></script>
    <script>
    $(document).ready(function() {
        $(document).on('click', '.quick-status-select-index', function(e) {
            e.preventDefault();
            var item = $(this);
            var id = item.data('id');
            var targetStatus = item.data('status');
            var due = parseFloat(item.data('due')) || 0;

            if (targetStatus === 'delivered' && due > 0.009) {
                window.location.href = '{{ url("admin/sale") }}/' + id + '?collect_due=1';
                return;
            }

            var btn = $('.status-btn-live-' + id);
            var origHtml = btn.html();
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>…');

            $.ajax({
                url: '{{ url("admin/sales") }}/' + id + '/update-delivery-status',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    delivery_status: targetStatus,
                    send_sms: (targetStatus === 'ready_for_delivery' ? 1 : 0)
                },
                success: function(res) {
                    btn.prop('disabled', false);
                    if (res.success) {
                        btn.css({
                            'background': res.badge_bg,
                            'color': res.badge_color,
                            'border-color': res.badge_color + '40'
                        }).html('<i class="fas fa-check-circle mr-1"></i><span>' + res.delivery_label + '</span>');
                        
                        item.closest('.dropdown-menu').find('.quick-status-select-index').removeClass('active font-weight-bold');
                        item.addClass('active font-weight-bold');

                        if (window.toastr) {
                            toastr.success(res.message);
                        }
                    } else {
                        btn.html(origHtml);
                        alert(res.message || 'Update failed');
                    }
                },
                error: function(xhr) {
                    btn.prop('disabled', false).html(origHtml);
                    var err = xhr.responseJSON ? xhr.responseJSON.message : 'Server error';
                    alert(err);
                }
            });
        });
    });
    </script>
@endpush
