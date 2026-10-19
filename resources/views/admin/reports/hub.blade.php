@extends('admin.layouts.master')
@section('page_title', 'Reports')

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<style>
.rh-wrap { padding: 0 24px 24px; }
.rh-group { margin-bottom: 26px; }
.rh-group h2 { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--page-text-muted); margin: 0 0 10px; display:flex; align-items:center; gap:8px; }
.rh-group h2 i { color: var(--accent-500); }
.rh-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:14px; }
.rh-card { position:relative; display:block; padding:14px 44px 14px 16px; border-radius:14px; background:var(--card-bg); border:1px solid var(--card-border); color:var(--page-text); text-decoration:none !important; transition:transform .15s ease, box-shadow .15s ease; }
.rh-card:hover { transform:translateY(-2px); box-shadow:0 10px 22px -14px rgba(15,23,42,.45); color:var(--page-text); }
.rh-card b { display:block; font-size:14px; font-weight:700; }
.rh-card span { display:block; font-size:12px; color:var(--page-text-muted); margin-top:3px; }
.rh-star { position:absolute; right:8px; top:8px; width:30px; height:30px; border:0; background:transparent; border-radius:8px; color:var(--page-text-muted); cursor:pointer; font-size:15px; }
.rh-star:hover { background:var(--accent-50); color:#d97706; }
.rh-star.on { color:#f59e0b; }
.rh-msg { font-size:12.5px; color:#b91c1c; margin:0 0 10px; min-height:18px; }
.rh-empty { padding:30px; text-align:center; color:var(--page-text-muted); }
</style>
@endpush

@section('content')
<div class="container-fluid rh-wrap" id="reports-hub" data-star="{{ route('reports.star') }}" data-token="{{ csrf_token() }}">
    <div class="crm-page-header">
        <div class="page-title-group">
            <h1>Reports</h1>
            <p>Every report you can open, in one place. Star up to {{ $max }} to keep them at the top.</p>
        </div>
        <div class="header-actions">
            <a href="{{ route('dashboard.new') }}" class="btn btn-secondary btn-sm" id="rh-back-dashboard"><i class="fas fa-columns mr-1"></i>Dashboard</a>
        </div>
    </div>

    <div class="rh-msg" id="rh-msg" role="status"></div>

    @if (!$groups)
        <div class="rh-empty">No reports are switched on for your role.</div>
    @else
        <section class="rh-group" id="rh-starred" @if (!$starred) hidden @endif>
            <h2><i class="fas fa-star"></i>Starred</h2>
            <div class="rh-grid" id="rh-starred-grid">
                @foreach ($starred as $r)
                    <a class="rh-card" href="{{ url($r['url']) }}" data-url="{{ $r['url'] }}"><b>{{ $r['label'] }}</b><span>{{ $r['about'] }}</span></a>
                @endforeach
            </div>
        </section>

        @foreach ($groups as $name => $g)
            <section class="rh-group">
                <h2><i class="fas {{ $g['icon'] }}"></i>{{ $name }}</h2>
                <div class="rh-grid">
                    @foreach ($g['items'] as $r)
                        <div class="rh-card-wrap" style="position:relative">
                            <a class="rh-card" href="{{ url($r['url']) }}" id="rh-open-{{ \Illuminate\Support\Str::slug($r['label']) }}"><b>{{ $r['label'] }}</b><span>{{ $r['about'] }}</span></a>
                            <button type="button" class="rh-star {{ in_array($r['url'], $stars) ? 'on' : '' }}" data-url="{{ $r['url'] }}" data-label="{{ $r['label'] }}" data-about="{{ $r['about'] }}"
                                    aria-pressed="{{ in_array($r['url'], $stars) ? 'true' : 'false' }}" title="Star {{ $r['label'] }}" id="rh-star-{{ \Illuminate\Support\Str::slug($r['label']) }}"><i class="fas fa-star"></i></button>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    var root = document.getElementById('reports-hub');
    if (!root) return;
    var msg = document.getElementById('rh-msg'), section = document.getElementById('rh-starred'), grid = document.getElementById('rh-starred-grid');
    var base = @json(url('/'));

    function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]; }); }

    function redraw(stars) {
        var buttons = [].slice.call(document.querySelectorAll('.rh-star'));
        buttons.forEach(function (b) {
            var on = stars.indexOf(b.dataset.url) !== -1;
            b.classList.toggle('on', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        grid.innerHTML = stars.map(function (u) {
            var b = buttons.filter(function (x) { return x.dataset.url === u; })[0];
            return b ? '<a class="rh-card" href="' + esc(base.replace(/\/$/, '') + '/' + u) + '"><b>' + esc(b.dataset.label) + '</b><span>' + esc(b.dataset.about) + '</span></a>' : '';
        }).join('');
        section.hidden = !stars.length;
    }

    document.querySelectorAll('.rh-star').forEach(function (b) {
        b.addEventListener('click', function () {
            msg.textContent = '';
            fetch(root.dataset.star, {
                method: 'POST', credentials: 'same-origin',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': root.dataset.token, 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify({url: b.dataset.url})
            }).then(function (r) { return r.json().then(function (d) { return {ok: r.ok, d: d}; }); })
              .then(function (x) { if (x.ok) redraw(x.d.stars); else msg.textContent = x.d.message || 'Could not save the star.'; })
              .catch(function () { msg.textContent = 'Could not save the star.'; });
        });
    });
})();
</script>
@endpush
