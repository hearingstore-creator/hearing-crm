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
                <i class="fas fa-plus mr-1"></i>Add Role
            </a>
        </div>
    </div>

    @include('admin.alert_message')

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
                        <th style="width: 110px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($roles as $key => $val)
                        <tr>
                            <td style="color: var(--page-text-muted); font-size: 12px;">{{ ++$key }}</td>
                            <td>
                                <span style="font-weight: 500;">{{ $val->name }}</span>
                            </td>
                            @if($val->name != 'Super Admin' && isset($editable[$val->id]))
                            <td>
                                <div class="crm-actions">
                                    <a href="{{ route('roles.edit', $val->id) }}" class="action-btn btn-edit" title="Edit">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                </div>
                            </td>
                            @else
                            <td></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
