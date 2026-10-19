@extends('admin.layouts.master')
@section('page_title', 'Stock Transfer ' . $transfer->transfer_no)
@section('breadcrumb_parent', 'Stock Transfers')
@section('breadcrumb_parent_url', route('stock-transfers.index'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1>{{ $transfer->transfer_no }}
                @if ($transfer->status === 'cancelled')<span class="badge badge-secondary" style="font-size:12px;">Cancelled</span>@else<span class="badge badge-success" style="font-size:12px;">Completed</span>@endif
            </h1>
            <p>{{ $transfer->from_name }} → {{ $transfer->to_name }} · {{ $transfer->date->format('d M Y') }} · by {{ $transfer->creator->name ?? '—' }}</p>
        </div>
        <div class="header-actions">
            <a href="{{ route('stock-transfers.index') }}" class="btn btn-secondary btn-sm" id="btn-back-transfers"><i class="fas fa-arrow-left mr-1"></i>All transfers</a>
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()" id="btn-print-transfer"><i class="fas fa-print mr-1"></i>Print</button>
        </div>
    </div>

    @include('admin.alert_message')
    @if ($errors->has('stock'))
        <div class="alert alert-danger m-2">@foreach ($errors->get('stock') as $msg)<div>{{ $msg }}</div>@endforeach</div>
    @endif
    @error('cancel_reason')<div class="alert alert-danger m-2">{{ $message }}</div>@enderror

    @if ($transfer->note)
        <div class="crm-table-card" style="padding:14px 16px; margin-bottom:16px;"><b>Note:</b> {{ $transfer->note }}</div>
    @endif

    <div class="crm-table-card crm-animate-in delay-1">
        <div style="overflow-x:auto;">
            <table class="crm-table">
                <thead><tr><th>Product</th><th>Qty</th><th>Serial numbers / batch</th></tr></thead>
                <tbody>
                    @foreach ($transfer->details as $d)
                        <tr>
                            <td>{{ $d->product->model ?? '#' . $d->product_id }}</td>
                            <td>{{ rtrim(rtrim(number_format($d->qty, 2), '0'), '.') }}</td>
                            <td>@if ($d->serial_no){{ implode(', ', $d->serial_no) }}@elseif ($d->batch)Batch {{ $d->batch->batch_no }}@else—@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($transfer->status === 'cancelled')
        <div class="crm-table-card" style="padding:14px 16px; margin-top:16px;">
            <b>Cancelled</b> on {{ $transfer->cancelled_at?->format('d M Y H:i') }}{{ $canceller ? ' by ' . $canceller->name : '' }}. Reason: {{ $transfer->cancel_reason }}
        </div>
    @elseif (auth()->user()->isSuperAdmin())
        <div class="crm-table-card" style="padding:14px 16px; margin-top:16px;">
            <form method="POST" action="{{ route('stock-transfers.cancel', $transfer->id) }}" onsubmit="return confirm('Cancel this transfer and move the stock back?');">
                @csrf
                <label for="cancel_reason"><b>Cancel this transfer</b> (Super Admin). The stock moves back, if it is still at {{ $transfer->to_name }}.</label>
                <div style="display:flex; gap:8px; margin-top:6px;">
                    <input type="text" name="cancel_reason" id="cancel_reason" class="form-control" placeholder="Reason (required)" maxlength="500" required>
                    <button type="submit" class="btn btn-danger btn-sm" id="btn-cancel-transfer">Cancel transfer</button>
                </div>
            </form>
        </div>
    @endif
</div>
@endsection
