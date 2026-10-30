{{-- Lead score badge. Params: $deal --}}
@if ($deal->score !== null)
    @php
        $band = \App\Services\LeadScorer::band((int) $deal->score);
        $why = collect($deal->score_reasons ?? [])->map(fn ($r) => ($r['points'] > 0 ? '+' : '') . $r['points'] . ' ' . $r['label'])->implode("\n");
    @endphp
    <span class="dc-score s-{{ $band }}" title="Lead score {{ $deal->score }}/100{{ $why ? "\n" . $why : '' }}" id="dc-score-{{ $deal->id }}">{{ $deal->score }}</span>
@endif
