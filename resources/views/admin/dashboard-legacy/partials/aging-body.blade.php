{{-- Shared body for customer-dues and supplier-payables aging cards.
     Expects: $w (buckets,total,count,top), $who ('customer'|'supplier'), $linkBase --}}
@php
    $tk = $tk ?? fn ($v) => '৳' . number_format((float) $v);
    $agingMeta = $agingMeta ?? [
        '0_30' => ['0–30 days', '#10b981', 'var(--success-50)', 'var(--success-700)'],
        '31_60' => ['31–60 days', '#f59e0b', 'var(--warning-50)', 'var(--warning-600)'],
        '61_90' => ['61–90 days', '#f97316', '#fff7ed', '#c2410c'],
        '90_plus' => ['90+ days', '#e11d48', 'var(--danger-50)', 'var(--danger-700)'],
    ];
    $total = max((float) ($w['total'] ?? 0), 0);
@endphp
<div class="an-body flush">
    <div style="padding:10px 8px 0;">
        <div style="display:flex;align-items:baseline;justify-content:space-between;gap:10px;">
            <div><span style="font-size:22px;font-weight:800;color:var(--page-text);">{{ $tk($total) }}</span>
                <span style="font-size:12px;color:var(--page-text-muted);margin-left:6px;">{{ $who === 'customer' ? 'owed by' : 'owed to' }} {{ $w['count'] ?? 0 }} {{ $who }}{{ ($w['count'] ?? 0) == 1 ? '' : 's' }}</span></div>
        </div>
        @if ($total > 0)
        <div class="an-split" style="height:12px;margin-top:10px;">
            @foreach ($agingMeta as $k => [$l, $c])
                @if (($w['buckets'][$k] ?? 0) > 0)<span style="width:{{ round($w['buckets'][$k] / $total * 100, 1) }}%;background:{{ $c }};" title="{{ $l }}: {{ $tk($w['buckets'][$k]) }}"></span>@endif
            @endforeach
        </div>
        @endif
        <div class="an-aging-grid">
            @foreach ($agingMeta as $k => [$l, $c, $bg, $fg])
                <div style="background:{{ $bg }};"><div class="v">{{ $tk($w['buckets'][$k] ?? 0) }}</div><div class="l" style="color:{{ $fg }};">{{ $l }}</div></div>
            @endforeach
        </div>
    </div>
    @forelse ($w['top'] ?? [] as $p)
        <div class="an-rank">
            <a class="main" href="{{ url($linkBase . $p['id']) }}"><div class="t">{{ $p['name'] }}</div><div class="s">oldest unpaid {{ $p['oldest_days'] }} days{{ $p['phone'] ? ' · ' . $p['phone'] : '' }}</div></a>
            <div class="val" style="color:{{ $p['oldest_days'] > 60 ? 'var(--danger-600)' : 'var(--page-text)' }};">{{ $tk($p['balance']) }}</div>
            @if ($p['phone'])<a href="tel:{{ $p['phone'] }}" class="lk-btn call" title="Call {{ $p['phone'] }}"><i class="fas fa-phone"></i></a>@endif
        </div>
    @empty
        <div class="lk-empty"><i class="fas fa-check-circle" style="color:var(--success-500);"></i>Nothing outstanding</div>
    @endforelse
    <div class="an-note" style="padding:0 8px;"><i class="fas fa-info-circle"></i>Payments are applied to the oldest {{ $who === 'customer' ? 'invoices' : 'purchases' }} first (from the {{ $who }} ledger).</div>
</div>
