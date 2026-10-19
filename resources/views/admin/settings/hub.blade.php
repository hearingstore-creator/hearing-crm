@extends('admin.layouts.master')
@section('page_title', 'All Settings')
@section('breadcrumb_parent', 'Administration')
@section('breadcrumb_parent_url', url('admin/settings-hub'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <style>
        .sh-group { margin-bottom:22px; }
        .sh-group h3 { font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--page-text-muted); margin:0 0 10px; }
        .sh-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:10px; }
        .sh-tile { display:flex; gap:12px; align-items:flex-start; background:var(--card-bg); border:1px solid var(--card-border); border-radius:12px; padding:12px 14px; text-decoration:none; color:var(--page-text); }
        .sh-tile:hover { border-color:var(--accent-500); }
        .sh-tile i { width:34px; height:34px; display:flex; align-items:center; justify-content:center; border-radius:10px; background:var(--accent-50); color:var(--accent-600); flex:none; }
        .sh-tile b { display:block; font-size:14px; }
        .sh-tile small { display:block; color:var(--page-text-muted); line-height:1.4; margin-top:2px; }
    </style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-cog" style="color: var(--accent-500); margin-right: 8px;"></i>All Settings</h1>
            <p>Every setting you can change, in one place</p>
        </div>
    </div>

    @include('admin.alert_message')

    @forelse ($groups as $group => $tiles)
        <div class="sh-group">
            <h3>{{ $group }}</h3>
            <div class="sh-grid">
                @foreach ($tiles as [$label, $hint, $icon, $link])
                    <a href="{{ $link }}" class="sh-tile" data-tile="{{ \Illuminate\Support\Str::slug($label) }}">
                        <i class="fas {{ $icon }}"></i>
                        <span><b>{{ $label }}</b><small>{{ $hint }}</small></span>
                    </a>
                @endforeach
            </div>
        </div>
    @empty
        <div class="ra-card" style="padding:24px; color:var(--page-text-muted);">You do not have access to any settings screen yet.</div>
    @endforelse
</div>
@endsection
