@extends('admin.layouts.master')
@section('page_title', 'Profit Budget')
@section('breadcrumb_parent', 'Profit Statement')
@section('breadcrumb_parent_url', url('admin/report/profit-statement'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-bullseye" style="color: var(--accent-500); margin-right: 8px;"></i>Monthly Budget</h1>
            <p>Targets shown next to the actual figures on the Profit Statement</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/report/profit-statement') }}?period=custom&start_date={{ $month }}-01&end_date={{ \Carbon\Carbon::parse($month . '-01')->endOfMonth()->toDateString() }}{{ $key !== 'all' ? '&vendor_id=' . $key : '' }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>Profit Statement</a>
        </div>
    </div>

    @include('admin.alert_message')
    @if ($errors->any())
        <div class="alert alert-danger m-2">@foreach ($errors->all() as $msg)<div>{{ $msg }}</div>@endforeach</div>
    @endif

    <form method="GET" action="{{ route('report.profit-budget') }}" class="crm-table-card" style="display:flex; gap:8px; padding:12px 14px; margin-bottom:14px; flex-wrap:wrap;" id="budget-pick">
        <input type="month" name="month" value="{{ $month }}" class="form-control form-control-sm" style="width:auto;" onchange="this.form.submit()">
        @if ($branches)
            <select name="vendor_id" class="form-control form-control-sm" style="width:auto;" onchange="this.form.submit()">
                <option value="all">All branches together</option>
                <option value="hq" @selected($key === 'hq')>Head office</option>
                @foreach ($branches as $id => $name)<option value="{{ $id }}" @selected($key === (string) $id)>{{ $name }}</option>@endforeach
            </select>
        @endif
    </form>

    <form method="POST" action="{{ route('report.profit-budget.save') }}" class="crm-table-card" style="padding:14px;" id="budget-form">
        @csrf
        <input type="hidden" name="month" value="{{ $month }}">
        <input type="hidden" name="vendor_id" value="{{ $key }}">
        <p style="color:var(--page-text-muted); font-size:13px;">Leave a line empty to set no target for it. Operating expenses is a ceiling: spending less is good.</p>
        <div class="row">
            @foreach ($metrics as $metric => $label)
                <div class="col-md-3 form-group">
                    <label for="b-{{ $metric }}">{{ $label }}</label>
                    <input type="number" step="0.01" class="form-control" name="amounts[{{ $metric }}]" id="b-{{ $metric }}" value="{{ $values[$metric] ?? '' }}">
                </div>
            @endforeach
        </div>
        <button type="submit" class="btn btn-primary btn-sm" id="btn-save-budget"><i class="fas fa-save mr-1"></i>Save budget</button>
    </form>
</div>
@endsection
