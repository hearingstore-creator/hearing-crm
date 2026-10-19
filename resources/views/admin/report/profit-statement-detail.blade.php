@extends('admin.layouts.master')
@section('page_title', $label)
@section('breadcrumb_parent', 'Profit Statement')
@section('breadcrumb_parent_url', $back)

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@php
    $fmt = fn ($v) => number_format((float) $v, 2);
    $titles = ['product' => 'Product sales', 'service' => 'Service sales', 'expense' => 'Expense entries', 'income' => 'Other income entries'];
@endphp

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-list-ul" style="color: var(--accent-500); margin-right: 8px;"></i>{{ $label }}</h1>
            <p>{{ $titles[$kind] ?? 'Records' }} · {{ $start->format('d M Y') }} to {{ $end->format('d M Y') }}</p>
        </div>
        <div class="header-actions">
            <a href="{{ $back }}" class="btn btn-secondary btn-sm" id="btn-back"><i class="fas fa-arrow-left mr-1"></i>Profit Statement</a>
        </div>
    </div>

    <div class="crm-table-card crm-animate-in delay-1">
        <div style="overflow-x:auto;">
            <table class="crm-table" id="detail-table">
                <thead>
                    <tr>
                        @foreach ($detail['columns'] as $c)<th @if (in_array($c, ['Qty', 'Income', 'Cost', 'Amount'])) style="text-align:right;" @endif>{{ $c }}</th>@endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($detail['rows'] as $row)
                        <tr>
                            <td>{{ $row['date'] }}</td>
                            <td>@if ($row['link'])<a href="{{ url($row['link']) }}">{{ $row['ref'] }}</a>@else{{ $row['ref'] }}@endif</td>
                            @if (isset($row['qty']) || $kind === 'product' || $kind === 'service')
                                <td>{{ $row['what'] }}</td>
                                <td style="text-align:right;">{{ rtrim(rtrim(number_format($row['qty'], 2), '0'), '.') }}</td>
                                <td style="text-align:right;">{{ $fmt($row['amount']) }}</td>
                                @if ($kind === 'product')<td style="text-align:right;">{{ $fmt($row['cost']) }}</td>@endif
                            @else
                                <td>{{ $row['what'] }}</td>
                                <td style="text-align:right;">{{ $fmt($row['amount']) }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($detail['columns']) }}" style="text-align:center; padding:30px !important; color:var(--page-text-muted);">Nothing in this period.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr><td colspan="{{ count($detail['columns']) - ($kind === 'product' ? 2 : 1) }}"><b>Total shown</b></td>
                        <td style="text-align:right;"><b>{{ $fmt($detail['total']) }}</b></td>@if ($kind === 'product')<td></td>@endif</tr>
                </tfoot>
            </table>
        </div>
        @if ($detail['truncated'])
            <div class="ps-note" style="padding:12px 14px; font-size:12.5px; color:var(--page-text-muted);">Showing the first {{ \App\Services\ProfitStatement::DETAIL_LIMIT }} records. Narrow the dates to see the rest.</div>
        @endif
    </div>
</div>
@endsection
