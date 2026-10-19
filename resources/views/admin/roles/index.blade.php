@extends('admin.layouts.master')
@section('page_title', 'Roles')
@section('breadcrumb_parent', 'Administration')
@section('breadcrumb_parent_url', url('admin/roles'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-user-tag" style="color: var(--accent-500); margin-right: 8px;"></i>Roles</h1>
            <p>Manage user roles and permissions</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/roles/create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus mr-1"></i>Add Role / Start from template
            </a>
        </div>
    </div>

    @include('admin.alert_message')
    @if ($withoutRole > 0)
        <div class="alert alert-danger m-2" id="no-role-warning"><i class="fas fa-exclamation-triangle mr-1"></i>{{ $withoutRole }} active {{ $withoutRole === 1 ? 'person has' : 'people have' }} no role yet, so they can sign in but see nothing. <a href="{{ url('admin/user') }}">Open People</a></div>
    @endif

    <div class="crm-table-card crm-animate-in delay-1">
        <div class="crm-table-header">
            <div class="table-title">
                <i class="fas fa-list" style="color: var(--page-text-muted);"></i>
                All Roles
                <span class="table-count">{{ count($roles) }}</span>
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table class="crm-table" data-datatable="role-list">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Name</th>
                        <th>People</th>
                        <th style="width: 120px;">Permissions</th>
                        <th style="width: 110px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($roles as $key => $val)
                        <tr>
                            <td style="color: var(--page-text-muted); font-size: 12px;">{{ ++$key }}</td>
                            <td>
                                <a href="{{ route('roles.show', $val->id) }}" style="font-weight: 500;">{{ $val->name }}</a>
                            </td>
                            <td style="font-size:13px;">
                                @if ($val->users_count === 0)
                                    <span style="color:var(--page-text-muted);">Nobody</span>
                                @else
                                    <b>{{ $val->users_count }}</b>
                                    <span style="color:var(--page-text-muted);">{{ $val->users->take(3)->pluck('name')->join(', ') }}{{ $val->users_count > 3 ? ' +' . ($val->users_count - 3) : '' }}</span>
                                @endif
                            </td>
                            <td style="font-size:13px;">{{ $val->name === 'Super Admin' ? 'Everything' : $val->permissions_count }}</td>
                            @if($val->name != 'Super Admin' && isset($editable[$val->id]))
                            <td>
                                <div class="crm-actions">
                                    <a href="{{ route('roles.show', $val->id) }}" class="action-btn" title="What this role can do"><i class="fas fa-eye"></i></a>
                                    <a href="{{ route('roles.edit', $val->id) }}" class="action-btn btn-edit" title="Edit">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                    @can('permission', 'roles.delete')
                                    <form method="POST" action="{{ route('roles.destroy', $val->id) }}" style="display:inline;" onsubmit="return confirm('Delete this role?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="action-btn" title="{{ $val->users_count ? 'In use: move people first' : 'Delete' }}" @disabled($val->users_count > 0) style="border:0;background:none;"><i class="fas fa-trash"></i></button>
                                    </form>
                                    @endcan
                                </div>
                            </td>
                            @else
                            <td><div class="crm-actions"><a href="{{ route('roles.show', $val->id) }}" class="action-btn" title="What this role can do"><i class="fas fa-eye"></i></a></div></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
