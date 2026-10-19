@extends('admin.layouts.master')
@section('page_title', 'Stock Adjustment ' . $adjustment->adjustment_no)
@section('breadcrumb_parent', 'Stock Adjustments')
@section('breadcrumb_parent_url', route('stock-adjustments.index'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1>{{ $adjustment->adjustment_no }}
                @if ($adjustment->status === 'cancelled')<span class="badge badge-secondary" style="font-size:12px;">Cancelled</span>@else<span class="badge badge-success" style="font-size:12px;">Completed</span>@endif
            </h1>
            <p>{{ $adjustment->reason_label }} · {{ $adjustment->location }} · {{ $adjustment->date->format('d M Y') }} · by {{ $adjustment->creator->name ?? '—' }}</p>
        </div>
        <div class="header-actions">
            <a href="{{ route('stock-adjustments.index') }}" class="btn btn-secondary btn-sm" id="btn-back-adjustments"><i class="fas fa-arrow-left mr-1"></i>All adjustments</a>
        </div>
    </div>

    @include('admin.alert_message')
    @if ($errors->has('stock'))
        <div class="alert alert-danger m-2">
            @foreach ($errors->get('stock') as $msg)<div>{{ $msg }}</div>@endforeach
        </div>
    @endif
    @error('cancel_reason')<div class="alert alert-danger m-2">{{ $message }}</div>@enderror

    @if ($adjustment->note)
        <div class="crm-table-card" style="padding:14px 16px; margin-bottom:16px;"><b>Note:</b> {{ $adjustment->note }}</div>
    @endif

    <div class="crm-table-card crm-animate-in delay-1">
        <div style="overflow-x:auto;">
            <table class="crm-table">
                <thead><tr><th>Product</th><th>Change</th><th>Qty</th><th>Serial numbers / batch</th><th>Unit cost</th><th>Selling price</th><th>Supplier warranty from</th></tr></thead>
                <tbody>
                    @foreach ($adjustment->details as $d)
                        <tr>
                            <td>{{ $d->product->model ?? '#' . $d->product_id }}</td>
                            <td>@if ($d->direction === 'in')<span class="badge badge-success">Added</span>@else<span class="badge badge-warning">Removed</span>@endif</td>
                            <td>{{ rtrim(rtrim(number_format($d->qty, 2), '0'), '.') }}</td>
                            <td>
                                @if ($d->serial_no){{ implode(', ', $d->serial_no) }}
                                @elseif ($d->batch)Batch {{ $d->batch->batch_no }}
                                @else—@endif
                            </td>
                            <td>{{ $d->unit_cost !== null ? '৳' . number_format($d->unit_cost, 2) : '—' }}</td>
                            <td>{{ $d->mrp_price !== null ? '৳' . number_format($d->mrp_price, 2) : '—' }}</td>
                            <td>{{ $d->warranty_start_date ? $d->warranty_start_date->format('d M Y') : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($adjustment->status === 'cancelled')
        <div class="crm-table-card" style="padding:14px 16px; margin-top:16px;">
            <b>Cancelled</b> on {{ $adjustment->cancelled_at?->format('d M Y H:i') }}{{ $canceller ? ' by ' . $canceller->name : '' }}. Reason: {{ $adjustment->cancel_reason }}
        </div>
    @elseif (auth()->user()->isSuperAdmin())
        <div class="crm-table-card" style="padding:14px 16px; margin-top:16px;">
            <form method="POST" action="{{ route('stock-adjustments.cancel', $adjustment->id) }}" onsubmit="return confirm('Cancel this adjustment and put the stock back as it was?');">
                @csrf
                <label for="cancel_reason"><b>Cancel this adjustment</b> (Super Admin). The stock goes back as it was, if the units are untouched.</label>
                <div style="display:flex; gap:8px; margin-top:6px;">
                    <input type="text" name="cancel_reason" id="cancel_reason" class="form-control" placeholder="Reason (required)" maxlength="500" required>
                    <button type="submit" class="btn btn-danger btn-sm" id="btn-cancel-adjustment">Cancel adjustment</button>
                </div>
            </form>
        </div>
    @endif
</div>
@endsection
