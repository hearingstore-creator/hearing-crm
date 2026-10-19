{{-- Smart Alerts strip (Phase 1) — shown under the KPI cards --}}
@php $sa = $analytics['smart_alerts'] ?? null; @endphp
@if ($sa && empty($sa['error']))
    <div class="an-alerts dash-fade" id="smart-alerts" style="animation-delay:.05s;">
        @forelse ($sa['alerts'] ?? [] as $i => $al)
            <a href="{{ $al['url'] }}" class="an-alert t-{{ $al['tone'] }}" id="alert-{{ $i }}">
                <i class="{{ $al['icon'] }}"></i>{{ $al['text'] }}
            </a>
        @empty
            <span class="an-alert t-success"><i class="fas fa-check-circle"></i>All clear — no business alerts right now</span>
        @endforelse
    </div>
@endif
