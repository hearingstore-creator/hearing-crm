@extends('admin.layouts.master')
@section('page_title', 'Products')
@section('breadcrumb_parent', 'Inventory')
@section('breadcrumb_parent_url', url('admin/product'))

@push('css')
@include('admin.partials.datatables-css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-box-open" style="color: var(--accent-500); margin-right: 8px;"></i>Products</h1>
            <p>Manage hearing aids, accessories, and product catalog</p>
        </div>
        <div class="header-actions">
            @can('permission', 'product.create')
                <a href="{{ url('admin/product/create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus mr-1"></i>Add Product
                </a>
            @endcan
        </div>
    </div>

    @include('admin.alert_message')

    {{-- Data Table --}}
    <div class="crm-table-card crm-animate-in delay-1">
        <div class="crm-table-header">
            <div class="table-title">
                <i class="fas fa-list" style="color: var(--page-text-muted);"></i>
                All Products
                <span class="table-count">{{ count($products) }}</span>
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table class="crm-table" data-datatable="products-list">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th>Brand</th>
                        <th>Model</th>
                        <th>Battery</th>
                        <th>Usage</th>
                        <th>Inv. Warranty</th>
                        <th>Sales Warranty</th>
                        <th style="width: 90px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $key => $product)
                        <tr>
                            <td style="color: var(--page-text-muted); font-size: 12px;">{{ ++$key }}</td>
                            <td>
                                <span class="crm-status-badge badge-status-verified">
                                    <span class="dot"></span>
                                    {{ $product->category->category_name ?? 'N/A' }}
                                </span>
                            </td>
                            <td style="font-size: 13px;">{{ $product->type->type_name ?? 'N/A' }}</td>
                            <td>
                                <span style="font-weight: 500;">{{ $product->brand->brand_name ?? 'N/A' }}</span>
                            </td>
                            <td>
                                <span style="font-weight: 600; color: var(--accent-500);">{{ $product->model }}</span>
                            </td>
                            <td style="font-size: 12px; color: var(--page-text-secondary);">{{ $product->battery_size ?? 'N/A' }}</td>
                            <td style="font-size: 12px; color: var(--page-text-secondary);">{{ $product->usage_months ? $product->usage_months . 'mo' : 'N/A' }}</td>
                            <td style="font-size: 12px;">{{ $product->warranty_inventory_months }}mo</td>
                            <td style="font-size: 12px;">{{ $product->warranty_sales_months ?? 'N/A' }}mo</td>
                            <td>
                                <div class="crm-actions">
                                    @can('permission', 'product.edit')
                                        <a href="{{ url('admin/product/'.$product->id.'/edit') }}" class="action-btn btn-edit" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 40px !important; color: var(--page-text-muted);">
                                <i class="fas fa-box-open" style="font-size: 32px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>
                                No products found.
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
@include('admin.partials.datatables-scripts')
@endpush