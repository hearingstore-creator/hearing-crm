{{-- Plain-language access grid. Needs $summary (area => level) and $widgets (dashboard widget count). --}}
<div class="ra-grid" id="access-summary">
    @foreach ($summary as $area => $level)
        <div class="ra-item">
            <span>{{ $area }}</span>
            <span class="ra-pill ra-{{ $level }}" data-level="{{ $level }}">{{ \App\Services\RoleAccess::LABELS[$level] }}</span>
        </div>
    @endforeach
    <div class="ra-item">
        <span>Dashboard</span>
        <span class="ra-pill {{ $widgets ? 'ra-work' : 'ra-none' }}">{{ $widgets ? $widgets . ' widgets' : 'Basic' }}</span>
    </div>
</div>
