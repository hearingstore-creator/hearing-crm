@extends('admin.layouts.master')
@section('page_title', 'Purchase & Stock Report')
@section('breadcrumb_parent', 'Reports')
@section('breadcrumb_parent_url', url('admin/reports'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
<style>
/* Report Filter Bar */
.report-filter-bar {
    display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;
    padding: 20px; background: var(--gray-50); border-bottom: 1px solid var(--gray-200);
    border-radius: 12px 12px 0 0;
}
.report-filter-group { flex: 1; min-width: 140px; display: flex; flex-direction: column; gap: 6px; }
.report-filter-group label {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.05em; color: var(--page-text-muted); margin: 0;
}
.report-filter-group select, .report-filter-group input {
    font-size: 13px; border-radius: 6px; border: 1px solid var(--gray-200);
    padding: 8px 12px; background: var(--card-bg); color: var(--page-text);
    width: 100%; height: 38px;
}
.report-filter-actions { display: flex; gap: 8px; margin-left: auto; }

/* Stat Cards */
.stat-card-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin: 24px 20px;
}
.stat-card {
    border-radius: 12px; padding: 20px; display: flex; flex-direction: column; position: relative; overflow: hidden;
}
.stat-card .sc-icon {
    width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center;
    font-size: 18px; margin-bottom: 12px;
}
.stat-card .sc-value { font-size: 24px; font-weight: 800; line-height: 1; margin-bottom: 6px; }
.stat-card .sc-label { font-size: 12px; font-weight: 700; text-transform: uppercase; }

.sc-primary { background: #e0e7ff; border: 1px solid #c7d2fe; }
.sc-primary .sc-icon { background: #c7d2fe; color: #3730a3; }
.sc-primary .sc-value { color: #312e81; }
.sc-primary .sc-label { color: #4338ca; }

.sc-success { background: #dcfce7; border: 1px solid #bbf7d0; }
.sc-success .sc-icon { background: #bbf7d0; color: #166534; }
.sc-success .sc-value { color: #14532d; }
.sc-success .sc-label { color: #15803d; }

.sc-danger { background: #fee2e2; border: 1px solid #fecaca; }
.sc-danger .sc-icon { background: #fecaca; color: #991b1b; }
.sc-danger .sc-value { color: #7f1d1d; }
.sc-danger .sc-label { color: #b91c1c; }

.sc-warning { background: #fef3c7; border: 1px solid #fde68a; }
.sc-warning .sc-icon { background: #fde68a; color: #92400e; }
.sc-warning .sc-value { color: #78350f; }
.sc-warning .sc-label { color: #b45309; }

.sc-info { background: #e0f2fe; border: 1px solid #bae6fd; }
.sc-info .sc-icon { background: #bae6fd; color: #075985; }
.sc-info .sc-value { color: #0c4a6e; }
.sc-info .sc-label { color: #0369a1; }

.sc-purple { background: #ede9fe; border: 1px solid #ddd6fe; }
.sc-purple .sc-icon { background: #ddd6fe; color: #5b21b6; }
.sc-purple .sc-value { color: #4c1d95; }
.sc-purple .sc-label { color: #6d28d9; }

/* Select2 overrides */
.select2-container .select2-selection--single {
    height: 38px !important; border-color: var(--gray-200) !important; border-radius: 6px !important; display: flex; align-items: center;
}
.select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px !important; }
</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-boxes" style="color: var(--accent-500); margin-right: 8px;"></i>Purchase & Stock</h1>
            <p>Track branch purchases and current stock levels</p>
        </div>
        <div class="header-actions">
            <a href="{{ route('report.purchase-stock.export', ['type' => 'raw', 'format' => 'xlsx']) }}?{{ http_build_query(request()->query()) }}" class="btn btn-success btn-sm" style="border-radius:6px; font-weight:600;">
                <i class="fas fa-file-excel mr-1"></i> Export Excel
            </a>
        </div>
    </div>

    @include('admin.alert_message')

    <div class="crm-table-card crm-animate-in delay-1">
        
        {{-- Filters --}}
        <form method="GET" action="{{ route('report.purchase-stock') }}">
            <div class="report-filter-bar">
                <div class="report-filter-group">
                    <label>Branch</label>
                    @php $isVendorUser = auth()->user()->isVendor(); $selVendor = (string) ($vendorId ?? ''); @endphp
                    <select class="select" name="vendor_id">
                        @unless($isVendorUser)
                            <option value="">All Branches</option>
                            <option value="hq" {{ $selVendor === 'hq' ? 'selected' : '' }}>Head Office</option>
                        @endunless
                        @foreach ($vendors ?? [] as $vendor)
                            <option value="{{ $vendor->id }}" {{ $selVendor === (string) $vendor->id ? 'selected' : '' }}>
                                {{ $vendor->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="report-filter-group">
                    <label>Product</label>
                    <select class="select" name="product_id">
                        <option value="">All Products</option>
                        @foreach ($products ?? [] as $product)
                            <option value="{{ $product->id }}" @isset($productId) {{ $productId == $product->id ? 'selected' : '' }} @endisset>
                                {{ $product->model ?? 'N/A' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="report-filter-group">
                    <label>From Date</label>
                    <input type="date" name="start_date" value="{{ $startDate ?? '' }}">
                </div>
                <div class="report-filter-group">
                    <label>To Date</label>
                    <input type="date" name="end_date" value="{{ $endDate ?? '' }}">
                </div>
                <div class="report-filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm" style="border-radius:6px; height: 38px; padding: 0 16px;"><i class="fas fa-search mr-1"></i>Filter</button>
                    <a href="{{ url('admin/report/purchase-stock') }}" class="btn btn-outline-secondary btn-sm" style="border-radius:6px; height: 38px; display:flex; align-items:center; justify-content:center; width:38px;"><i class="fas fa-sync-alt"></i></a>
                </div>
            </div>
        </form>

        @php
            $totalQtyPurchased = 0;
            $totalPurchaseValue = 0;
            $totalQtySold = 0;
            $totalStockBalance = 0;
            
            if(isset($reportData) && count($reportData) > 0) {
                foreach ($reportData as $row) {
                    $totalQtyPurchased += $row['qty_purchased'];
                    $totalPurchaseValue += floatval(str_replace(',', '', $row['purchase_value']));
                    $totalQtySold += $row['qty_sold'];
                    $totalStockBalance += $row['stock_balance'];
                }
            }
        @endphp

        {{-- Stats --}}
        @isset($reportData)
        <div class="stat-card-grid">
            <div class="stat-card sc-primary">
                <div class="sc-icon"><i class="fas fa-shopping-cart"></i></div>
                <div class="sc-value">{{ number_format($totalQtyPurchased) }}</div>
                <div class="sc-label">Total Qty Purchased</div>
            </div>
            <div class="stat-card sc-success">
                <div class="sc-icon"><i class="fas fa-dollar-sign"></i></div>
                <div class="sc-value">{{ number_format($totalPurchaseValue, 2) }}</div>
                <div class="sc-label">Total Purchase Value</div>
            </div>
            <div class="stat-card sc-warning">
                <div class="sc-icon"><i class="fas fa-cash-register"></i></div>
                <div class="sc-value">{{ number_format($totalQtySold) }}</div>
                <div class="sc-label">Total Qty Sold</div>
            </div>
            <div class="stat-card sc-info">
                <div class="sc-icon"><i class="fas fa-warehouse"></i></div>
                <div class="sc-value">{{ number_format($totalStockBalance) }}</div>
                <div class="sc-label">Current Stock Balance</div>
            </div>
        </div>
        @endisset

        {{-- Data Table --}}
        <div class="crm-table-header" style="border-top: 1px solid var(--gray-200);">
            <div class="table-title">
                <i class="fas fa-table" style="color: var(--page-text-muted);"></i>
                Purchase Details
            </div>
        </div>
        
        <div style="overflow-x: auto;">
            <table class="crm-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Branch</th>
                        <th>Supplier</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Brand</th>
                        <th style="text-align: center;">Qty Purchased</th>
                        <th style="text-align: right;">Purchase Value</th>
                        <th style="text-align: center;">Qty Sold</th>
                        <th style="text-align: center;">Stock Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @if(isset($reportData) && count($reportData) > 0)
                        @foreach ($reportData as $key => $row)
                            <tr>
                                <td style="color:var(--page-text-muted);">{{ ++$key }}</td>
                                <td style="font-weight:600; color:var(--accent-600);">{{ $row['vendor'] }}</td>
                                <td>{{ $row['supplier'] ?? '—' }}</td>
                                <td style="font-weight:600;">{{ $row['product'] }}</td>
                                <td><span style="background:var(--gray-100); padding:2px 8px; border-radius:4px; font-size:11px;">{{ $row['category'] }}</span></td>
                                <td>{{ $row['brand'] }}</td>
                                <td style="text-align: center;"><span style="font-weight:600; color:var(--page-text);">{{ number_format($row['qty_purchased']) }}</span></td>
                                <td style="text-align: right;">{{ $row['purchase_value'] }}</td>
                                <td style="text-align: center;"><span style="color:var(--warning-600); font-weight:600;">{{ number_format($row['qty_sold']) }}</span></td>
                                <td style="text-align: center;"><span style="background:var(--info-50); color:var(--info-700); padding:2px 8px; border-radius:99px; font-size:12px; font-weight:700;">{{ number_format($row['stock_balance']) }}</span></td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="10" style="text-align:center; padding: 40px; color:var(--page-text-muted);">
                                <i class="fas fa-folder-open" style="font-size:32px; color:var(--gray-300); margin-bottom:12px; display:block;"></i>
                                No purchase data found
                            </td>
                        </tr>
                    @endif
                </tbody>
                @if(isset($reportData) && count($reportData) > 0)
                <tfoot>
                    <tr style="background:var(--gray-50); font-weight:700;">
                        <td colspan="5"></td>
                        <td style="text-align: right; padding-right: 16px;">Grand Total:</td>
                        <td style="text-align: center;">{{ number_format($totalQtyPurchased) }}</td>
                        <td style="text-align: right;">{{ number_format($totalPurchaseValue, 2) }}</td>
                        <td style="text-align: center; color:var(--warning-600);">{{ number_format($totalQtySold) }}</td>
                        <td style="text-align: center; color:var(--info-700);">{{ number_format($totalStockBalance) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
<script>
$(document).ready(function() {
    $('.select').select2({
        width: '100%'
    });
});
</script>
@endpush


