{{-- Follow-up sequences on this deal (drawer, Activities tab). --}}
@php $running = ($enrollments ?? collect())->where('status', 'active'); @endphp
<div class="dd-sequence" id="dd-sequence" style="border:1px dashed var(--gray-200); border-radius:10px; padding:8px 10px; margin:10px 0; font-size:12.5px;">
    <div style="font-weight:600; margin-bottom:4px;"><i class="fas fa-stream"></i> Follow-up sequence</div>
    @forelse ($running as $e)
        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            <span><b>{{ $e->sequence->name ?? 'Sequence' }}</b> · next step {{ $e->next_run_at ? $e->next_run_at->diffForHumans() : 'soon' }}</span>
            @if ($can['move'])
                <form method="POST" action="{{ route('deals.sequence.stop', $e->id) }}" style="margin-left:auto;">@csrf<button type="submit" class="btn btn-outline-secondary btn-sm py-0" id="dd-seq-stop-{{ $e->id }}">Stop</button></form>
            @endif
        </div>
    @empty
        @foreach (($enrollments ?? collect())->take(2) as $e)
            <div class="text-muted">{{ $e->sequence->name ?? 'Sequence' }}: {{ $e->status === 'completed' ? 'finished' : 'stopped – ' . $e->stop_reason }} ({{ $e->ended_at?->format('d M') }})</div>
        @endforeach
        @if ($can['move'] && !$closed && ($sequenceOptions ?? collect())->count())
            <form method="POST" action="{{ route('deals.sequence.start', $deal->id) }}" style="display:flex; gap:6px; margin-top:4px;">
                @csrf
                <select name="sequence_id" class="form-control form-control-sm" aria-label="Sequence" id="dd-seq-pick">
                    @foreach ($sequenceOptions as $s)<option value="{{ $s->id }}">{{ $s->name }}{{ $s->is_active ? '' : ' (off – runs for this deal only)' }}</option>@endforeach
                </select>
                <button type="submit" class="btn btn-outline-primary btn-sm" id="dd-seq-start">Start</button>
            </form>
        @elseif (!($enrollments ?? collect())->count())
            <div class="text-muted">None running.</div>
        @endif
    @endforelse
</div>
