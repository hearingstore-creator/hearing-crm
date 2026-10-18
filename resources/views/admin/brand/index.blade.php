@extends('admin.layouts.master')
@section('page_title', 'Brands')
@section('breadcrumb_parent', 'Inventory')
@section('breadcrumb_parent_url', url('admin/brand'))

@push('css')
@include('admin.partials.datatables-css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-tag" style="color: var(--accent-500); margin-right: 8px;"></i>Brands</h1>
            <p>Manage product brands</p>
        </div>
        <div class="header-actions">
            @can('permission', 'brand.create')
                <a href="{{ url('admin/brand/create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>Add Brand</a>
            @endcan
        </div>
    </div>

    @include('admin.alert_message')

    <div class="crm-table-card crm-animate-in delay-1">
        <div class="crm-table-header">
            <div class="table-title"><i class="fas fa-list" style="color: var(--page-text-muted);"></i> All Brands <span class="table-count">{{ count($brands) }}</span></div>
        </div>
        <div style="overflow-x: auto;">
            <table class="crm-table" data-datatable="brand-list">
                <thead><tr><th style="width: 50px;">#</th><th>Brand Name</th><th>Status</th><th style="width: 110px; text-align: center;">Actions</th></tr></thead>
                <tbody>
                    @foreach ($brands as $key => $val)
                    <tr>
                        <td style="color: var(--page-text-muted); font-size: 12px;">{{ ++$key }}</td>
                        <td><span style="font-weight: 500;">{{ $val->brand_name }}</span></td>
                        <td>@if($val->status==1)<span class="crm-status-badge badge-status-won"><span class="dot"></span>Active</span>@else<span class="crm-status-badge badge-status-lost"><span class="dot"></span>Inactive</span>@endif</td>
                        <td><div class="crm-actions">@can('permission', 'brand.edit')<a href="{{ route('brand.edit', $val->id) }}" class="action-btn btn-edit" title="Edit"><i class="fas fa-pen"></i></a>@endcan @can('permission', 'brand.delete')<form action="{{ route('brand.destroy', $val->id) }}" method="POST" style="display:inline;">@csrf @method('DELETE')<button type="submit" class="action-btn btn-delete" title="Delete" onclick="confirmDelete(event)"><i class="far fa-trash-alt"></i></button></form>@endcan</div></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('admin.partials.datatables-scripts')
@endpush
