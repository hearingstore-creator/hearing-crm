{{-- Compact My Day list for the Today dashboard. $myDay = HomeController::myDay() --}}
@php
    $rows = collect();

    foreach ($myDay['activities'] ?? [] as $a) {
        $at = \Illuminate\Support\Carbon::parse($a->follow_up_date);
        $over = $at->lt(today());
        $rows->push([
            'icon'  => $a->type?->icon() ?? 'fas fa-phone-alt',
            'title' => $a->lead->full_name ?? '—',
            'sub'   => ($a->type?->label() ?? 'Follow-up') . ($a->follow_up_notes ? ' · ' . \Illuminate\Support\Str::limit($a->follow_up_notes, 60) : ''),
            'badge' => $over ? $at->diffForHumans(today(), true) . ' late' : $at->format('h:i A'),
            'tone'  => $over ? 'danger' : '',
            'over'  => $over,
            'url'   => $a->deal_id && ($myDay['canBoard'] ?? false) ? url('admin/deals?deal=' . $a->deal_id) : url('admin/leads/' . $a->lead_id),
            'wa'    => null,
        ]);
    }

    foreach ($myDay['appointments'] ?? [] as $ap) {
        $rows->push([
            'icon'  => 'far fa-calendar-check',
            'title' => $ap->lead->full_name ?? 'Appointment',
            'sub'   => $ap->service->name ?? 'Appointment',
            'badge' => $ap->professionalTimeSlot?->timeSlot?->start_time ? \Illuminate\Support\Carbon::parse($ap->professionalTimeSlot->timeSlot->start_time)->format('h:i A') : 'Today',
            'tone'  => '',
            'over'  => false,
            'url'   => url('admin/appointments/' . $ap->id . '/edit'),
            'wa'    => null,
        ]);
    }

    $icons = ['service_due' => 'fas fa-tools', 'warranty' => 'fas fa-shield-alt', 'birthdays' => 'fas fa-birthday-cake', 'battery' => 'fas fa-battery-quarter'];
    foreach ($icons as $key => $icon) {
        foreach ($myDay[$key] ?? [] as $it) {
            $rows->push([
                'icon'  => $icon,
                'title' => $it['title'] ?? '',
                'sub'   => $it['subtitle'] ?? '',
                'badge' => $it['badge'] ?? '',
                'tone'  => $it['badge_tone'] ?? '',
                'over'  => (bool) ($it['is_overdue'] ?? false),
                'url'   => $it['url'] ?? '#',
                'wa'    => $it['whatsapp_url'] ?? null,
            ]);
        }
    }

    $rows = $rows->sortByDesc('over')->values();
    $shown = $rows->take(12);
@endphp

<div class="md-sum">
    <span><b>{{ $myDay['totalPersonal'] ?? $rows->count() }}</b> to do</span>
    <span><b>{{ $myDay['overdue'] ?? 0 }}</b> overdue</span>
    <span><b>{{ $myDay['tomorrow'] ?? 0 }}</b> due tomorrow</span>
</div>

@if ($shown->isEmpty())
    <div class="td-empty"><i class="fas fa-check-circle mr-1" style="color:#059669;"></i>Nothing waiting for you today.</div>
@else
    <div class="md-list">
        @foreach ($shown as $i => $r)
            <div class="md-item" id="td-md-{{ $i }}">
                <span class="md-i"><i class="{{ $r['icon'] }}"></i></span>
                <a class="md-t" href="{{ $r['url'] }}"><b>{{ $r['title'] }}</b><span>{{ $r['sub'] }}</span></a>
                @if ($r['wa'])<a class="md-wa" href="{{ $r['wa'] }}" target="_blank" rel="noopener" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>@endif
                @if ($r['badge'])<span class="md-badge {{ $r['tone'] }}">{{ $r['badge'] }}</span>@endif
            </div>
        @endforeach
    </div>
    @if ($rows->count() > $shown->count())
        <div class="td-note" style="margin:8px 0 0;">{{ $rows->count() - $shown->count() }} more on the full list</div>
    @endif
@endif
