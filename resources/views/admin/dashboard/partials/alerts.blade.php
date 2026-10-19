{{-- Alerts strip for the Today dashboard (loaded after the page). $alerts = DashboardTodayService::alerts() --}}
@if (!empty($alerts['error']))
    <div class="td-err">Alerts could not be loaded right now.</div>
@else
    <div class="an-alerts" id="smart-alerts">
        @forelse ($alerts['alerts'] ?? [] as $i => $al)
            @php $tone = $al['tone'] ?? 'info'; @endphp
            <a href="{{ $al['url'] }}" class="an-alert t-{{ $tone }}" id="alert-{{ $i }}">
                @if ($tone === 'danger')
                    <span class="an-dot danger"></span>
                @elseif ($tone === 'warning')
                    <span class="an-dot warning"></span>
                @endif
                <i class="{{ $al['icon'] }}"></i>
                <span class="an-txt">{{ $al['text'] }}</span>
                <i class="fas fa-chevron-right an-arrow"></i>
            </a>
        @empty
            <span class="an-alert t-success"><i class="fas fa-check-circle" style="color:#059669; margin-right:4px;"></i> All clear — no business alerts right now</span>
        @endforelse
    </div>
@endif
