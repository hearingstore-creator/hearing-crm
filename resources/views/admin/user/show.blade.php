@extends('admin.layouts.master')
@section('page_title', $user->name)
@section('breadcrumb_parent', 'People')
@section('breadcrumb_parent_url', url('admin/user'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    @include('admin.partials.access-summary-style')
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-user-circle" style="color: var(--accent-500); margin-right: 8px;"></i>{{ $user->name }}</h1>
            <p>{{ $user->email }} · {{ $user->isVendor() ? 'Branch login' : ($user->branch->name ?? 'Head office') }} · {{ $user->status == 1 ? 'Active' : 'Deactivated' }}</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/user') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>People</a>
            @if ($canEdit)
                <a href="{{ route('user.edit', $user->id) }}" class="btn btn-primary btn-sm" id="btn-edit-person"><i class="fas fa-pen mr-1"></i>Edit</a>
            @endif
        </div>
    </div>

    @include('admin.alert_message')

    <div class="ra-card">
        <h3>Role</h3>
        @forelse ($user->roles as $role)
            <a href="{{ route('roles.show', $role->id) }}" class="mr-3">{{ $role->name }}</a>
        @empty
            <span style="color:var(--page-text-muted); font-size:13px;">{{ $fixed ? 'Branch logins and professionals use fixed access, not roles.' : 'No role yet, so this person sees nothing.' }}</span>
        @endforelse
        <div style="color:var(--page-text-muted); font-size:12.5px; margin-top:8px;">Last sign-in: {{ $user->last_login_at ? $user->last_login_at->format('d M Y, h:i A') : 'Never' }}</div>
    </div>

    @if ($user->isSuperAdmin())
        <div class="ra-card" id="everything"><b>Super Admin can do everything.</b></div>
    @elseif ($fixed)
        <div class="ra-card" id="fixed-access">Access for {{ $user->isVendor() ? 'branch logins' : 'professionals' }} is set in the system, not by roles.</div>
    @else
        <div class="ra-card">
            <h3>What {{ $user->name }} can do</h3>
            @include('admin.partials.access-summary')
        </div>
    @endif
</div>
@endsection
