@extends('admin.layouts.master')
@section('page_title', 'Add Expense')
@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush
@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-money-bill-wave" style="color: var(--accent-500); margin-right: 8px;"></i>Add Expense</h1>
            <p>Record a new expense entry</p>
        </div>
        <div class="header-actions">
            @can('permission', 'expense.index')
            <a href="{{ url('admin/expense') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>Expense List</a>
            @endcan
        </div>
    </div>
    @include('admin.alert_message')
    <form action="{{ url('admin/expense') }}" enctype="multipart/form-data" method="post">
        @csrf
        <div class="crm-form-section crm-animate-in delay-1">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--danger-500);"><i class="fas fa-money-bill-wave"></i></div>
                <h6>Expense Details</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="income_expense_head_id">Head Type <span style="color: var(--danger-500);">*</span></label>
                            <select class="form-control" name="income_expense_head_id" required>
                                <option value="">Select Head Type</option>
                                @foreach ($heads as $key => $val)
                                    <option value="{{ $val->id }}">{{ $val->head_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="date">Date <span style="color: var(--danger-500);">*</span></label>
                            <input type="date" class="form-control" name="date" value="{{ date('Y-m-d') }}" id="date" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="paid">Amount <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" class="form-control" name="paid" id="paid" required>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="details">Remarks</label>
                            <textarea class="form-control" name="details" id="details" required></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="crm-form-submit-bar">
            <a href="{{ url('admin/expense') }}" class="btn btn-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Save</button>
        </div>
    </form>
</div>
@endsection
