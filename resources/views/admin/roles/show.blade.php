@extends('admin.layouts.master')
@section('page_title', $role->name)
@section('breadcrumb_parent', 'Roles')
@section('breadcrumb_parent_url', url('admin/roles'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    @include('admin.partials.access-summary-style')
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-user-shield" style="color: var(--accent-500); margin-right: 8px;"></i>{{ $role->name }}</h1>
            <p>What people with this role can do</p>
        </div>
        <div class="header-actions">
            <a href="{{ route('roles.index') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>All roles</a>
            @if ($canEdit && $role->name !== 'Super Admin')
                <a href="{{ route('roles.edit', $role->id) }}" class="btn btn-primary btn-sm" id="btn-edit-role"><i class="fas fa-pen mr-1"></i>Change access</a>
            @endif
        </div>
    </div>

    @include('admin.alert_message')

    <div class="ra-card">
        <h3>People with this role</h3>
        @forelse ($role->users as $u)
            <a href="{{ url('admin/user/' . $u->id . '/edit') }}" class="mr-3">{{ $u->name }}</a>
        @empty
            <span style="color:var(--page-text-muted); font-size:13px;">Nobody yet.</span>
        @endforelse
    </div>

    @if ($everything)
        <div class="ra-card" id="everything"><b>Super Admin can do everything.</b> This role cannot be limited or deleted.</div>
    @else
        <div class="ra-card">
            <h3>Access by area</h3>
            @include('admin.partials.access-summary')
        </div>
    @endif
</div>
@endsection
