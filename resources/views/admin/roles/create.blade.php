@extends('admin.layouts.master')
@section('page_title', 'Add Role')
@section('breadcrumb_parent', 'Roles')
@section('breadcrumb_parent_url', url('admin/roles'))
@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<style>
    .tp-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:10px; }
    .tp-card { display:block; background:var(--card-bg); border:1px solid var(--card-border); border-radius:12px; padding:12px; cursor:pointer; margin:0; }
    .tp-card input { margin-right:6px; }
    .tp-card:has(input:checked) { border-color:var(--accent-500); background:var(--accent-50); }
    .tp-card b { font-size:14px; }
    .tp-card small { display:block; color:var(--page-text-muted); margin-top:4px; line-height:1.4; }
</style>
@endpush
@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-user-plus" style="color: var(--accent-500); margin-right: 8px;"></i>Add Role</h1>
            <p>Start from a template, then fine-tune it with Change access</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/roles') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>Roles</a>
        </div>
    </div>
    @include('admin.alert_message')
    @if ($errors->any())
        <div class="alert alert-danger m-2">@foreach ($errors->all() as $msg)<div>{{ $msg }}</div>@endforeach</div>
    @endif
    <form action="{{url('admin/roles')}}" method="post">
        @csrf
        <div class="crm-form-section crm-animate-in delay-1">
            <div class="crm-form-section-header">
                <div class="section-icon" style="background: var(--accent-500);"><i class="fas fa-user-shield"></i></div>
                <h6>Role Information</h6>
            </div>
            <div class="crm-form-section-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name">Role Name <span style="color: var(--danger-500);">*</span></label>
                            <input type="text" class="form-control" id="name" placeholder="e.g. Mymensingh Front Desk" name="name" value="{{ old('name', $picked) }}" autocomplete="off" required>
                        </div>
                    </div>
                </div>
                <label class="mb-2">Start from</label>
                <div class="tp-grid" id="templates">
                    <label class="tp-card">
                        <input type="radio" name="template" value="" @checked(!old('template', $picked))>
                        <b>Blank role</b>
                        <small>No access yet. You choose everything on the next screen.</small>
                    </label>
                    @foreach ($templates as $tname => $t)
                        <label class="tp-card">
                            <input type="radio" name="template" value="{{ $tname }}" @checked(old('template', $picked) === $tname)>
                            <b>{{ $tname }}</b>
                            <small>{{ $t['description'] }}</small>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="crm-form-submit-bar">
            <a href="{{url('admin/roles')}}" class="btn btn-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i>Create role</button>
        </div>
    </form>
</div>
@endsection
