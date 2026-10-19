@extends('admin.layouts.master')
@section('page_title', $role->name)
@section('breadcrumb_parent', 'Roles')
@section('breadcrumb_parent_url', url('admin/roles'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <style>
        .ra-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(230px, 1fr)); gap:10px; }
        .ra-item { background:var(--card-bg); border:1px solid var(--card-border); border-radius:12px; padding:10px 12px; display:flex; justify-content:space-between; align-items:center; gap:8px; font-size:13px; }
        .ra-pill { font-size:11.5px; font-weight:600; padding:2px 9px; border-radius:20px; white-space:nowrap; }
        .ra-full { background:#dcfce7; color:#166534; }
        .ra-work { background:#dbeafe; color:#1e40af; }
        .ra-view { background:#f3f4f6; color:#374151; }
        .ra-none { color:var(--page-text-muted); font-weight:400; }
        .ra-card { background:var(--card-bg); border:1px solid var(--card-border); border-radius:14px; padding:16px; margin-bottom:16px; }
        .ra-card h3 { font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--page-text-muted); margin:0 0 10px; }
    </style>
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
            <div class="ra-grid" id="access-summary">
                @foreach ($summary as $area => $level)
                    <div class="ra-item">
                        <span>{{ $area }}</span>
                        <span class="ra-pill ra-{{ $level }}" data-level="{{ $level }}">{{ \App\Services\RoleAccess::LABELS[$level] }}</span>
                    </div>
                @endforeach
                <div class="ra-item">
                    <span>Dashboard</span>
                    <span class="ra-pill {{ $widgets ? 'ra-work' : 'ra-none' }}">{{ $widgets ? $widgets . ' widgets' : 'Basic' }}</span>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
