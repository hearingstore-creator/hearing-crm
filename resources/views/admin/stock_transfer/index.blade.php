@extends('admin.layouts.master')
@section('page_title', 'Stock Transfers')
@section('breadcrumb_parent', 'Inventory')
@section('breadcrumb_parent_url', url('admin/product'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-exchange-alt" style="color: var(--accent-500); margin-right: 8px;"></i>Stock Transfers</h1>
            <p>Move stock between head office and branches. Posted transfers are never edited: cancel to undo.</p>
        </div>
        <div class="header-actions">
            @can('permission', 'stock_transfer.create')
                <a href="{{ route('stock-transfers.create') }}" class="btn btn-primary btn-sm" id="btn-new-transfer"><i class="fas fa-plus mr-1"></i>New Transfer</a>
            @endcan
        </div>
    </div>

    @include('admin.alert_message')

    <div class="crm-filter-panel crm-animate-in delay-1">
        <div class="crm-filter-body" style="display:block;">
            <form method="GET">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="f-from">From</label>
                        <select name="from" id="f-from" class="form-control">
                            <option value="">All</option>
                            @foreach ($locations as $k => $name)<option value="{{ $k }}" @selected((string) request('from') === (string) $k)>{{ $name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="f-to">To</label>
                        <select name="to" id="f-to" class="form-control">
                            <option value="">All</option>
                            @foreach ($locations as $k => $name)<option value="{{ $k }}" @selected((string) request('to') === (string) $k)>{{ $name }}</option>@endforeach
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
                    <div class="filter-group"><label for="f-start">From date</label><input type="date" name="start_date" id="f-start" class="form-control" value="{{ request('start_date') }}"></div>
                    <div class="filter-group"><label for="f-end">To date</label><input type="date" name="end_date" id="f-end" class="form-control" value="{{ request('end_date') }}"></div>
                    <div class="filter-group" style="align-self:flex-end;">
                        <button type="submit" class="btn btn-primary btn-sm" id="btn-filter-transfers"><i class="fas fa-search mr-1"></i>Filter</button>
                        <a href="{{ route('stock-transfers.index') }}" class="btn btn-secondary btn-sm">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="crm-table-card crm-animate-in delay-2">
        <div class="crm-table-header"><div class="table-title"><i class="fas fa-list"></i> {{ $transfers->total() }} transfer(s)</div></div>
        <div style="overflow-x:auto;">
            <table class="crm-table">
                <thead><tr><th>No.</th><th>Date</th><th>From</th><th>To</th><th>Products</th><th>Qty</th><th>Status</th><th>By</th><th></th></tr></thead>
                <tbody>
                    @forelse ($transfers as $t)
                        <tr>
                            <td><b>{{ $t->transfer_no }}</b></td>
                            <td>{{ $t->date->format('d M Y') }}</td>
                            <td>{{ $t->from_name }}</td>
                            <td>{{ $t->to_name }}</td>
                            <td>{{ $t->details->map(fn ($d) => $d->product->model ?? '#' . $d->product_id)->unique()->take(3)->implode(', ') }}{{ $t->details->pluck('product_id')->unique()->count() > 3 ? ' …' : '' }}</td>
                            <td>{{ rtrim(rtrim(number_format($t->details->sum('qty'), 2), '0'), '.') ?: '0' }}</td>
                            <td>@if ($t->status === 'cancelled')<span class="badge badge-secondary">Cancelled</span>@else<span class="badge badge-success">Completed</span>@endif</td>
                            <td>{{ $t->creator->name ?? '—' }}</td>
                            <td>@can('permission', 'stock_transfer.view')<a href="{{ route('stock-transfers.show', $t->id) }}" class="btn btn-secondary btn-sm" id="btn-view-{{ $t->id }}">View</a>@endcan</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" style="text-align:center; padding:24px; color:var(--page-text-muted);">No transfers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="padding:12px 16px;">{{ $transfers->links() }}</div>
    </div>
</div>
@endsection
