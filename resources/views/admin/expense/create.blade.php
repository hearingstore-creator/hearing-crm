@extends('admin.layouts.master')
@section('page_title', 'New Debit Voucher')
@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush
@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-money-bill-wave" style="color: var(--accent-500); margin-right: 8px;"></i>New Debit Voucher</h1>
            <p>Record money paid out, with payee, payment mode and bill</p>
        </div>
        <div class="header-actions">
            @can('permission', 'expense.index')
            <a href="{{ url('admin/expense') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>Debit Vouchers</a>
            @endcan
        </div>
    </div>
    @include('admin.alert_message')
    @include('admin.partials.voucher-form', [
        'kind' => 'debit', 'action' => url('admin/expense'), 'backUrl' => url('admin/expense'), 'amountField' => 'paid',
    ])
</div>
@endsection
