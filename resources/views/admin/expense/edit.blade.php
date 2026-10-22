@extends('admin.layouts.master')
@section('page_title', 'Edit Debit Voucher')
@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush
@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-edit" style="color: var(--accent-500); margin-right: 8px;"></i>Edit {{ $voucher->voucher_no }}</h1>
            <p>Vouchers can be changed until they are approved. Every change is logged.</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/expense/' . $voucher->id) }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>Back to voucher</a>
        </div>
    </div>
    @include('admin.alert_message')
    @include('admin.partials.voucher-form', [
        'kind' => 'debit', 'action' => url('admin/expense/' . $voucher->id), 'backUrl' => url('admin/expense/' . $voucher->id), 'amountField' => 'paid',
    ])
</div>
@endsection
