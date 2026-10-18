@extends('admin.layouts.master')
@section('page_title', 'Users')
@section('breadcrumb_parent', 'Administration')
@section('breadcrumb_parent_url', url('admin/user'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    {{-- Page Header --}}
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-user-shield" style="color: var(--accent-500); margin-right: 8px;"></i>Users</h1>
            <p>Manage system users and access roles</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/user/create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus mr-1"></i>Add User
            </a>
        </div>
    </div>

    @include('admin.alert_message')

    {{-- Data Table --}}
    <div class="crm-table-card crm-animate-in delay-1">
        <div class="crm-table-header">
            <div class="table-title">
                <i class="fas fa-list" style="color: var(--page-text-muted);"></i>
                All Users
                <span class="table-count">{{ count($users) }}</span>
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table class="crm-table" data-datatable="user-list">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Roles</th>
                        <th>Details</th>
                        <th>Status</th>
                        <th style="width: 110px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $key => $user)
                        <tr>
                            <td style="color: var(--page-text-muted); font-size: 12px;">{{ ++$key }}</td>
                            <td>
                                <div class="crm-name-cell">
                                    <div class="crm-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                                    <div class="crm-name-text">
                                        <span class="name">{{ $user->name }}</span>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size: 12px; color: var(--page-text-secondary);">{{ $user->email }}</td>
                            <td>
                                @if($user->roles->isNotEmpty())
                                    @foreach($user->roles as $role)
                                        <span class="crm-status-badge badge-status-verified"><span class="dot"></span>{{ $role->name }}</span>
                                    @endforeach
                                @else
                                    <span style="color: var(--page-text-muted); font-size: 12px;">No Role</span>
                                @endif
                            </td>
                            <td style="font-size: 12px; color: var(--page-text-secondary);">{{ $user->details ?? '—' }}</td>
                            <td>
                                @if($user->status == 1)
                                    <span class="crm-status-badge badge-status-won"><span class="dot"></span>Active</span>
                                @else
                                    <span class="crm-status-badge badge-status-lost"><span class="dot"></span>Inactive</span>
                                @endif
                            </td>
                            <td>
                                <div class="crm-actions">
                                    <a href="{{ route('user.edit', $user->id) }}" class="action-btn btn-edit" title="Edit">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                    <form action="{{ route('user.destroy', $user->id) }}" method="POST" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="action-btn btn-delete" title="Delete" onclick="confirmDelete(event)">
                                            <i class="far fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px !important; color: var(--page-text-muted);">
                                <i class="fas fa-users" style="font-size: 32px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>
                                No users found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
