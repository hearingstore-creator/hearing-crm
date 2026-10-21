@extends('admin.layouts.master')
@section('page_title', 'People')
@section('breadcrumb_parent', 'Administration')
@section('breadcrumb_parent_url', url('admin/user'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<style>
    .pp-chips { display:flex; flex-wrap:wrap; gap:8px; margin:0 0 12px; }
    .pp-chip { border:1px solid var(--card-border); background:var(--card-bg); color:var(--page-text); border-radius:20px; padding:4px 13px; font-size:13px; text-decoration:none; }
    .pp-chip b { margin-left:4px; }
    .pp-chip.on { background:var(--accent-50); border-color:var(--accent-500); color:var(--accent-700); }
    .pp-chip.warn b { color:#be123c; }
    .pp-filters { display:flex; flex-wrap:wrap; gap:8px; padding:10px 14px; border-bottom:1px solid var(--card-border); }
    .pp-filters .form-control { width:auto; min-width:150px; }
    .pp-filters .pp-q { flex:1 1 220px; }
    .pp-tag { font-size:11px; color:var(--page-text-muted); border:1px solid var(--card-border); border-radius:6px; padding:0 6px; margin-left:6px; }
    .pp-sub { font-size:12px; color:var(--page-text-muted); }
    .pp-inline { display:inline; }
    .pp-link { background:none; border:0; padding:0; color:var(--accent-600, #4f46e5); font-size:12.5px; cursor:pointer; }
    .pp-link.danger { color:#be123c; }
</style>
@endpush

@section('content')
@php $canSmsTeam = auth()->user()->hasAccess('compose_&_send.team') && ! auth()->user()->branchId(); @endphp
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-users-cog" style="color: var(--accent-500); margin-right: 8px;"></i>People</h1>
            <p>Everyone who can sign in, what they can do, and when they last did</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/roles') }}" class="btn btn-secondary btn-sm" id="btn-roles"><i class="fas fa-user-tag mr-1"></i>Roles</a>
            <a href="{{ url('admin/user/create') }}" class="btn btn-primary btn-sm" id="btn-add-person">
                <i class="fas fa-plus mr-1"></i>Add person
            </a>
        </div>
    </div>

    @include('admin.alert_message')

    <div class="pp-chips" id="people-chips">
        <a href="{{ url('admin/user') }}" class="pp-chip {{ $f['status'] === '' ? 'on' : '' }}">Everyone <b>{{ $counts['all'] }}</b></a>
        <a href="{{ url('admin/user?status=active') }}" class="pp-chip {{ $f['status'] === 'active' ? 'on' : '' }}">Active <b>{{ $counts['active'] }}</b></a>
        <a href="{{ url('admin/user?status=inactive') }}" class="pp-chip {{ $f['status'] === 'inactive' ? 'on' : '' }}">Deactivated <b>{{ $counts['inactive'] }}</b></a>
        <a href="{{ url('admin/user?status=norole') }}" id="chip-norole" class="pp-chip {{ $counts['norole'] ? 'warn' : '' }} {{ $f['status'] === 'norole' ? 'on' : '' }}">No role yet <b>{{ $counts['norole'] }}</b></a>
    </div>

    <div class="crm-table-card crm-animate-in delay-1">
        <form method="GET" action="{{ url('admin/user') }}" class="pp-filters" id="people-filters">
            <input type="search" name="q" value="{{ $f['q'] }}" class="form-control form-control-sm pp-q" placeholder="Search name or email">
            <select name="role" class="form-control form-control-sm" onchange="this.form.submit()">
                <option value="">All roles</option>
                @foreach ($roles as $r)<option value="{{ $r->id }}" @selected($f['role'] === $r->id)>{{ $r->name }}</option>@endforeach
            </select>
            <select name="branch" class="form-control form-control-sm" onchange="this.form.submit()">
                <option value="">All branches</option>
                <option value="head" @selected($f['branch'] === 'head')>Head office</option>
                @foreach ($branches as $bid => $bname)<option value="{{ $bid }}" @selected($f['branch'] === (string) $bid)>{{ $bname }}</option>@endforeach
            </select>
            @if ($f['status'] !== '')<input type="hidden" name="status" value="{{ $f['status'] }}">@endif
            <button class="btn btn-secondary btn-sm" type="submit">Search</button>
            @if ($f['q'] !== '' || $f['role'] || $f['branch'] !== '' || $f['status'] !== '')
                <a href="{{ url('admin/user') }}" class="btn btn-link btn-sm">Clear</a>
            @endif
        </form>

        <div style="overflow-x: auto;">
            <table class="crm-table" id="people-table">
                <thead>
                    <tr>
                        <th>Person</th>
                        <th>Role</th>
                        <th>Works at</th>
                        <th>Last sign-in</th>
                        <th>Status</th>
                        <th style="width: 240px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        @php
                            $kind = $user->isVendor() ? 'Branch login' : ($user->isProfessional() ? 'Professional' : null);
                            $mine = $user->id === auth()->id();
                        @endphp
                        <tr data-user="{{ $user->id }}">
                            <td>
                                <div class="crm-name-cell">
                                    <div class="crm-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                                    <div class="crm-name-text">
                                        <span class="name">{{ $user->name }}@if ($mine)<span class="pp-tag">You</span>@endif @if ($kind)<span class="pp-tag">{{ $kind }}</span>@endif</span>
                                        <div class="pp-sub">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if ($user->roles->isNotEmpty())
                                    @foreach ($user->roles as $role)
                                        <span class="crm-status-badge badge-status-verified"><span class="dot"></span>{{ $role->name }}</span>
                                    @endforeach
                                @elseif ($kind)
                                    <span class="pp-sub">Fixed {{ strtolower($kind) }} access</span>
                                @else
                                    <span class="pp-no-role" style="color:#be123c; font-size:12.5px; font-weight:600;">No role yet</span>
                                @endif
                            </td>
                            <td style="font-size:13px;">{{ $user->isVendor() ? $user->name : ($user->branch->name ?? 'Head office') }}</td>
                            <td style="font-size:13px;" title="{{ $user->last_login_at?->format('d M Y, h:i A') }}">
                                @if ($user->last_login_at){{ $user->last_login_at->diffForHumans() }}@else<span class="pp-sub">Never</span>@endif
                                @if ($user->must_change_password)<div class="pp-sub">Password change pending</div>@endif
                            </td>
                            <td>
                                @if ($user->status == 1)
                                    <span class="crm-status-badge badge-status-won"><span class="dot"></span>Active</span>
                                @else
                                    <span class="crm-status-badge badge-status-lost"><span class="dot"></span>Deactivated</span>
                                @endif
                            </td>
                            <td style="font-size:12.5px;">
                                <a href="{{ route('user.show', $user->id) }}" class="pp-link" title="What can they do">Access</a>
                                @if ($canSmsTeam ?? false)
                                    · <a href="#" class="pp-link btn-quick-sms" data-type="employee" data-id="{{ $user->id }}" data-name="{{ $user->name }}" data-phone="{{ $user->mobile_no }}" title="Send SMS">SMS</a>
                                @endif
                                @if (isset($manageable[$user->id]))
                                    · <a href="{{ route('user.edit', $user->id) }}" class="pp-link">Edit</a>
                                    @if (! $mine)
                                        <form action="{{ route('user.reset', $user->id) }}" method="POST" class="pp-inline" onsubmit="return confirm('Set a new temporary password for {{ addslashes($user->name) }}?');">
                                            @csrf · <button type="submit" class="pp-link" title="Reset password">Reset password</button>
                                        </form>
                                        <form action="{{ route('user.status', $user->id) }}" method="POST" class="pp-inline" @if ($user->status == 1) onsubmit="return confirm('Deactivate {{ addslashes($user->name) }}? They are signed out on their next click.');" @endif>
                                            @csrf <input type="hidden" name="status" value="{{ $user->status == 1 ? 0 : 1 }}">
                                            · <button type="submit" class="pp-link {{ $user->status == 1 ? 'danger' : '' }}" id="toggle-{{ $user->id }}">{{ $user->status == 1 ? 'Deactivate' : 'Activate' }}</button>
                                        </form>
                                        @can('permission', 'users.delete')
                                        <form action="{{ route('user.destroy', $user->id) }}" method="POST" class="pp-inline">
                                            @csrf @method('DELETE')
                                            · <button type="submit" class="pp-link danger" onclick="confirmDelete(event)">Delete</button>
                                        </form>
                                        @endcan
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px !important; color: var(--page-text-muted);">
                                <i class="fas fa-users" style="font-size: 32px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>
                                No one matches. <a href="{{ url('admin/user') }}">Clear the filters</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@if ($canSmsTeam ?? false)
    @include('admin.sms.partials.quick-send')
@endif
@endsection
