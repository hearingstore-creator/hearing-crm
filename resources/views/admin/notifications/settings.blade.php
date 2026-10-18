@extends('admin.layouts.master')
@section('page_title', 'Notification Settings')

@push('css')
<style>
    .ns-wrap { max-width: 720px; }
    .ns-card { background: var(--card-bg, #fff); border: 1px solid var(--border-color, rgba(0,0,0,.08)); border-radius: 16px; padding: 22px 24px; box-shadow: 0 6px 24px rgba(15,23,42,.05); }
    .ns-card h5 { margin: 0 0 4px; font-weight: 700; }
    .ns-card .ns-sub { color: var(--page-text-muted); font-size: 13px; margin-bottom: 14px; }
    .ns-row { display: flex; align-items: center; gap: 14px; padding: 14px 0; border-top: 1px solid var(--border-color, rgba(0,0,0,.06)); }
    .ns-ic { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; background: var(--accent-50, rgba(99,102,241,.1)); color: var(--accent-600, #4f46e5); flex: 0 0 40px; transition: transform .2s; }
    .ns-row:hover .ns-ic { transform: scale(1.06); }
    .ns-txt { flex: 1; }
    .ns-txt strong { display: block; font-size: 14px; }
    .ns-txt small { color: var(--page-text-muted); }
    .ns-sw { position: relative; width: 44px; height: 24px; flex: 0 0 44px; }
    .ns-sw input { opacity: 0; width: 0; height: 0; }
    .ns-sw span { position: absolute; inset: 0; border-radius: 99px; background: rgba(148,163,184,.45); cursor: pointer; transition: background .2s; }
    .ns-sw span::after { content: ''; position: absolute; top: 3px; left: 3px; width: 18px; height: 18px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.25); transition: transform .2s; }
    .ns-sw input:checked + span { background: var(--accent-500, #6366f1); }
    .ns-sw input:checked + span::after { transform: translateX(20px); }
    .ns-sw input:focus-visible + span { outline: 2px solid var(--accent-500, #6366f1); outline-offset: 2px; }
    .ns-foot { display: flex; justify-content: flex-end; padding-top: 16px; border-top: 1px solid var(--border-color, rgba(0,0,0,.06)); }
    [data-theme="dark"] .ns-card, .dark-mode .ns-card { background: var(--card-bg, #1e293b); border-color: rgba(255,255,255,.08); box-shadow: none; }
</style>
@endpush

@section('content')
<div class="ns-wrap">
    @if (session('success'))
        <div class="alert alert-success" id="ns-saved">{{ session('success') }}</div>
    @endif
    <form method="POST" action="{{ route('notifications.settings.save') }}" class="ns-card" id="ns-form">
        @csrf
        <h5>Bell notifications</h5>
        <div class="ns-sub">Choose which alerts appear in your bell. SMS to customers are not affected.</div>
        @foreach ($categories as $key => [$label, $hint, $icon])
            <label class="ns-row" for="ns-{{ $key }}">
                <div class="ns-ic"><i class="fa-solid {{ $icon }}"></i></div>
                <div class="ns-txt"><strong>{{ $label }}</strong><small>{{ $hint }}</small></div>
                <div class="ns-sw">
                    <input type="checkbox" name="on[]" value="{{ $key }}" id="ns-{{ $key }}" @checked(! in_array($key, $muted, true))>
                    <span></span>
                </div>
            </label>
        @endforeach
        <div class="ns-foot">
            <button type="submit" class="btn btn-primary" id="ns-save"><i class="fa-solid fa-check mr-1"></i> Save</button>
        </div>
    </form>
</div>
@endsection
