{{-- Alerts strip for the Today dashboard (loaded after the page). $alerts = DashboardTodayService::alerts() --}}
@if (!empty($alerts['error']))
    <div class="td-err">Alerts could not be loaded right now.</div>
@else
    <div class="an-alerts" id="smart-alerts">
        @forelse ($alerts['alerts'] ?? [] as $i => $al)
            <a href="{{ $al['url'] }}" class="an-alert t-{{ $al['tone'] }}" id="alert-{{ $i }}"><i class="{{ $al['icon'] }}"></i>{{ $al['text'] }}</a>
        @empty
            <span class="an-alert t-success"><i class="fas fa-check-circle"></i>All clear: no business alerts right now</span>
        @endforelse
    </div>
@endif
