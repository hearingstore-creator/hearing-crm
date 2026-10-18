@extends('admin.layouts.master')
@section('page_title', 'Edit Income/Expense Head')
@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush
@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-stream" style="color: var(--accent-500); margin-right: 8px;"></i>Edit Income/Expense Head</h1>
            <p>Update accounting head information</p>
        </div>
        <div class="header-actions">
            <a href="{{url('admin/income-expense-head')}}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>Head List</a>
        </div>
    </div>
    @include('admin.alert_message')
    <form action="{{route('income-expense-head.update',$incomeExpenseHead->id)}}" enctype="multipart/form-data" method="post">
        @csrf
        @method('put')
        <div class="crm-form-section crm-animate-in delay-1">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--accent-500);"><i class="fas fa-stream"></i></div>
                <h6>Head Information</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="head_type">Head Type <span style="color: var(--danger-500);">*</span></label>
                            <select class="form-control" name="head_type">
                                <option value="">Select Head Type</option>
                                @foreach ($headTypes as $key => $val)
                                    <option value="{{$val}}" {{$incomeExpenseHead->head_type == $val ? 'selected' : ''}}>{{$val}}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="head_name">Head Name <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" class="form-control" id="head_name" value="{{$incomeExpenseHead->head_name}}" placeholder="Head Name" name="head_name" autocomplete="off" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Status <span style="color: var(--danger-500);">*</span></label>
                            <div class="mt-1">
                                <label class="radio-inline my-2"><input type="radio" name="status" value="1" {{$incomeExpenseHead->status ==1 ? 'checked' : ''}}> Active</label>
                                <label class="radio-inline my-2 ml-3"><input type="radio" name="status" value="0" {{$incomeExpenseHead->status ==0 ? 'checked' : ''}}> Inactive</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="crm-form-submit-bar">
            <a href="{{url('admin/income-expense-head')}}" class="btn btn-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Update</button>
        </div>
    </form>
</div>
@endsection
