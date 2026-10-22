@extends('admin.layouts.master')
@section('page_title', 'New Credit Voucher')
@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush
@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-hand-holding-usd" style="color: var(--accent-500); margin-right: 8px;"></i>New Credit Voucher</h1>
            <p>Record money received, with payer, receipt mode and document</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/income') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>Credit Vouchers</a>
        </div>
    </div>
    @include('admin.alert_message')
    @include('admin.partials.voucher-form', [
        'kind' => 'credit', 'action' => url('admin/income'), 'backUrl' => url('admin/income'), 'amountField' => 'paid_amount',
    ])
</div>
@endsection
