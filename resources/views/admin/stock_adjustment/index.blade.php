@extends('admin.layouts.master')
@section('page_title', 'Stock Adjustments')
@section('breadcrumb_parent', 'Inventory')
@section('breadcrumb_parent_url', url('admin/product'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-balance-scale-left" style="color: var(--accent-500); margin-right: 8px;"></i>Stock Adjustments</h1>
            <p>Damaged, lost, found, count corrections and opening stock. Posted adjustments are never edited: cancel to undo.</p>
        </div>
        <div class="header-actions">
            @can('permission', 'stock_adjustment.create')
                <a href="{{ route('stock-adjustments.create') }}" class="btn btn-primary btn-sm" id="btn-new-adjustment"><i class="fas fa-plus mr-1"></i>New Adjustment</a>
            @endcan
        </div>
    </div>

    @include('admin.alert_message')

    <div class="crm-filter-panel crm-animate-in delay-1">
        <div class="crm-filter-body" style="display:block;">
            <form method="GET">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="f-location">Location</label>
                        <select name="location" id="f-location" class="form-control">
                            <option value="">All</option>
                            @foreach ($locations as $k => $name)
                                <option value="{{ $k }}" @selected((string) request('location') === (string) $k)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="f-reason">Reason</label>
                        <select name="reason" id="f-reason" class="form-control">
                            <option value="">All</option>
                            @foreach ($reasons as $k => $name)
                                <option value="{{ $k }}" @selected(request('reason') === $k)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="f-status">Status</label>
                        <select name="status" id="f-status" class="form-control">
                            <option value="">All</option>
                            <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                            <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="f-start">From</label>
                        <input type="date" name="start_date" id="f-start" class="form-control" value="{{ request('start_date') }}">
                    </div>
                    <div class="filter-group">
                        <label for="f-end">To</label>
                        <input type="date" name="end_date" id="f-end" class="form-control" value="{{ request('end_date') }}">
                    </div>
                    <div class="filter-group" style="align-self:flex-end;">
                        <button type="submit" class="btn btn-primary btn-sm" id="btn-filter-adjustments"><i class="fas fa-search mr-1"></i>Filter</button>
                        <a href="{{ route('stock-adjustments.index') }}" class="btn btn-secondary btn-sm">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="crm-table-card crm-animate-in delay-2">
        <div class="crm-table-header"><div class="table-title"><i class="fas fa-list"></i> {{ $adjustments->total() }} adjustment(s)</div></div>
        <div style="overflow-x:auto;">
            <table class="crm-table">
                <thead>
                    <tr><th>No.</th><th>Date</th><th>Location</th><th>Reason</th><th>Products</th><th>Added</th><th>Removed</th><th>Status</th><th>By</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($adjustments as $a)
                        <tr>
                            <td><b>{{ $a->adjustment_no }}</b></td>
                            <td>{{ $a->date->format('d M Y') }}</td>
                            <td>{{ $a->location }}</td>
                            <td>{{ $a->reason_label }}</td>
                            <td>{{ $a->details->map(fn ($d) => $d->product->model ?? '#' . $d->product_id)->unique()->take(3)->implode(', ') }}{{ $a->details->pluck('product_id')->unique()->count() > 3 ? ' …' : '' }}</td>
                            <td>{{ rtrim(rtrim(number_format($a->details->where('direction', 'in')->sum('qty'), 2), '0'), '.') ?: '0' }}</td>
                            <td>{{ rtrim(rtrim(number_format($a->details->where('direction', 'out')->sum('qty'), 2), '0'), '.') ?: '0' }}</td>
                            <td>
                                @if ($a->status === 'cancelled')<span class="badge badge-secondary">Cancelled</span>@else<span class="badge badge-success">Completed</span>@endif
                            </td>
                            <td>{{ $a->creator->name ?? '—' }}</td>
                            <td>@can('permission', 'stock_adjustment.view')<a href="{{ route('stock-adjustments.show', $a->id) }}" class="btn btn-secondary btn-sm" id="btn-view-{{ $a->id }}">View</a>@endcan</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" style="text-align:center; padding:24px; color:var(--page-text-muted);">No adjustments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="padding:12px 16px;">{{ $adjustments->links() }}</div>
    </div>
</div>
@endsection
