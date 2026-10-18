@extends('admin.layouts.master')
@section('page_title', 'Professionals')
@section('breadcrumb_parent', 'Appointments')
@section('breadcrumb_parent_url', url('admin/professionals'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-user-tie" style="color: var(--accent-500); margin-right: 8px;"></i>Professionals</h1>
            <p>Manage audiologists and specialists</p>
        </div>
        <div class="header-actions">
            @can('permission', 'add_professional.create')
                <a href="{{ url('admin/professionals/create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>Add Professional</a>
            @endcan
        </div>
    </div>

    @include('admin.alert_message')

    <div class="crm-table-card crm-animate-in delay-1">
        <div class="crm-table-header">
            <div class="table-title"><i class="fas fa-list" style="color: var(--page-text-muted);"></i> All Professionals <span class="table-count">{{ count($professionals) }}</span></div>
        </div>
        <div style="overflow-x: auto;">
            <table class="crm-table">
                <thead><tr><th style="width: 50px;">#</th><th>Name</th><th>Designation</th><th>Phone</th><th>Services</th><th>Working Days</th><th>Status</th><th style="width: 110px; text-align: center;">Actions</th></tr></thead>
                <tbody>
                    @forelse ($professionals as $key => $prof)
                    <tr>
                        <td style="color: var(--page-text-muted); font-size: 12px;">{{ ++$key }}</td>
                        <td>
                            <div class="crm-name-cell">
                                <div class="crm-avatar">{{ strtoupper(substr($prof->name, 0, 1)) }}</div>
                                <div class="crm-name-text"><span class="name">{{ $prof->name }}</span><span style="font-size: 11px; color: var(--page-text-muted);"><i class="fas fa-store mr-1"></i>{{ $prof->branch->name ?? 'Head Office' }}</span></div>
                            </div>
                        </td>
                        <td>@if($prof->designation)<span class="crm-status-badge badge-status-verified"><span class="dot"></span>{{ $prof->designation->label() }}</span>@else<span style="color: var(--page-text-muted);">—</span>@endif</td>
                        <td style="font-size: 13px;">{{ $prof->mobile_no ?? '—' }}</td>
                        <td>@foreach ($prof->appointmentServices as $svc)<span class="crm-status-badge badge-status-verified" style="margin: 2px;"><span class="dot"></span>{{ $svc->name }}</span>@endforeach</td>
                        <td>@foreach ($prof->workingDays->where('is_active', true) as $wd)<span style="display: inline-block; background: var(--gray-100); color: var(--page-text-secondary); padding: 2px 8px; border-radius: 12px; font-size: 11px; margin: 2px;">{{ $wd->day?->shortLabel() }}</span>@endforeach</td>
                        <td>@if($prof->status)<span class="crm-status-badge badge-status-won"><span class="dot"></span>Active</span>@else<span class="crm-status-badge badge-status-lost"><span class="dot"></span>Inactive</span>@endif</td>
                        <td><div class="crm-actions">@can('permission', 'professional_list.edit')<a href="{{ route('professionals.edit', $prof->id) }}" class="action-btn btn-edit" title="Edit"><i class="fas fa-pen"></i></a>@endcan @can('permission', 'professional_list.delete')<form action="{{ route('professionals.destroy', $prof->id) }}" method="POST" style="display:inline;">@csrf @method('DELETE')<button type="submit" class="action-btn btn-delete" title="Delete" onclick="confirmDelete(event)"><i class="far fa-trash-alt"></i></button></form>@endcan</div></td>
                    </tr>
                    @empty
                    <tr><td colspan="8" style="text-align: center; padding: 40px !important; color: var(--page-text-muted);"><i class="fas fa-user-tie" style="font-size: 32px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>No professionals found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
