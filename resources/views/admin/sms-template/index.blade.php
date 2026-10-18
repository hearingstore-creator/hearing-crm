@extends('admin.layouts.master')
@section('page_title', 'SMS Templates')
@section('breadcrumb_parent', 'Communication')
@section('breadcrumb_parent_url', url('admin/sms-templates'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-file-alt" style="color: var(--accent-500); margin-right: 8px;"></i>SMS Templates</h1>
            <p>Create and manage message templates</p>
        </div>
        <div class="header-actions">
            @can('permission', 'templates.create')
                <a href="{{ url('admin/sms-templates/create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i>Add Template</a>
            @endcan
            <a href="{{ url('admin/sms') }}" class="btn btn-secondary btn-sm" style="margin-left: 6px;">
                <i class="fas fa-comment-dots mr-1"></i>SMS Centre
            </a>
        </div>
    </div>

    @include('admin.alert_message')

    <div class="crm-table-card crm-animate-in delay-1">
        <div class="crm-table-header">
            <div class="table-title"><i class="fas fa-list" style="color: var(--page-text-muted);"></i> All Templates <span class="table-count">{{ count($templates) }}</span></div>
        </div>
        <div style="overflow-x: auto;">
            <table class="crm-table">
                <thead><tr><th style="width: 50px;">#</th><th>Name</th><th>Category</th><th>Body</th><th>Variables</th><th>Status</th><th style="width: 110px; text-align: center;">Actions</th></tr></thead>
                <tbody>
                    @forelse ($templates as $key => $template)
                    <tr>
                        <td style="color: var(--page-text-muted); font-size: 12px;">{{ ++$key }}</td>
                        <td><span style="font-weight: 500;">{{ $template->name }}</span></td>
                        <td>
                            <span class="crm-status-badge badge-status-verified"><span class="dot"></span>{{ $template->category_label }}</span>
                        </td>
                        <td style="font-size: 12px; color: var(--page-text-secondary); max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ Str::limit($template->body, 80) }}</td>
                        <td>
                            @php $vars = is_array($template->variables) ? $template->variables : json_decode($template->getRawOriginal('variables'), true) ?? []; @endphp
                            @if($vars)
                                @foreach($vars as $var)
                                    @php $tag = '{{' . $var . '}}'; @endphp
                                    <span style="display: inline-block; background: var(--accent-50); color: var(--accent-600); padding: 2px 8px; border-radius: 4px; font-size: 11px; font-family: monospace; margin: 2px;">{{ $tag }}</span>
                                @endforeach
                            @else
                                <span style="color: var(--page-text-muted);">—</span>
                            @endif
                        </td>
                        <td>@if($template->is_active)<span class="crm-status-badge badge-status-won"><span class="dot"></span>Active</span>@else<span class="crm-status-badge badge-status-lost"><span class="dot"></span>Inactive</span>@endif</td>
                        <td><div class="crm-actions">@can('permission', 'templates.edit')<a href="{{ route('sms-templates.edit', $template->id) }}" class="action-btn btn-edit" title="Edit"><i class="fas fa-pen"></i></a>@endcan @can('permission', 'templates.delete')<form action="{{ route('sms-templates.destroy', $template->id) }}" method="POST" style="display:inline;">@csrf @method('DELETE')<button type="submit" class="action-btn btn-delete" title="Delete" onclick="confirmDelete(event)"><i class="far fa-trash-alt"></i></button></form>@endcan</div></td>
                    </tr>
                    @empty
                    <tr><td colspan="7" style="text-align: center; padding: 40px !important; color: var(--page-text-muted);"><i class="fas fa-file-alt" style="font-size: 32px; display: block; margin-bottom: 12px; color: var(--gray-300);"></i>No templates found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
