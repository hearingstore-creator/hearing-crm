{{-- Trials on this deal (drawer, Activities tab): in-session and take-home. --}}
@php $trialList = $trials ?? collect(); @endphp
<div class="dd-trials" id="dd-trials" style="border:1px dashed var(--gray-200); border-radius:10px; padding:8px 10px; margin:10px 0; font-size:12.5px;">
    <div style="font-weight:600; margin-bottom:4px;"><i class="fas fa-assistive-listening-systems"></i> Trials</div>

    @foreach ($trialList as $t)
        <div style="padding:4px 0; border-top:1px solid var(--gray-100);" id="dd-trial-{{ $t->id }}">
            <b>{{ $t->product_label }}</b>
            <span class="text-muted">· {{ $t->kind === 'take_home' ? 'take-home' : 'in session' }} · {{ $t->created_at->format('d M') }}</span>
            @if ($t->result)<span class="badge badge-light">{{ \App\Models\DealTrial::RESULTS[$t->result] ?? $t->result }}</span>@endif
            @if ($t->kind === 'take_home')
                <div class="text-muted">
                    S/N {{ $t->serial_no }} · deposit ৳{{ number_format((float) $t->deposit, 2) }} ·
                    @if ($t->status === 'out')
                        <b style="color:{{ $t->return_due && $t->return_due->lt(today()) ? 'var(--danger-600,#dc2626)' : 'inherit' }};">due back {{ $t->return_due?->format('d M') }}</b>
                    @else
                        {{ $t->status === 'bought' ? 'bought' : 'returned' }} {{ $t->closed_at?->format('d M') }}
                    @endif
                </div>
                @if ($t->status === 'out' && $can['move'])
                    <div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:4px;">
                        <form method="POST" action="{{ route('deals.trials.close', $t->id) }}">@csrf<input type="hidden" name="outcome" value="bought">
                            <button type="submit" class="btn btn-outline-success btn-sm py-0" title="The device is on a sale to this patient">Bought</button></form>
                        <form method="POST" action="{{ route('deals.trials.close', $t->id) }}" style="display:flex; gap:4px;">@csrf<input type="hidden" name="outcome" value="returned">
                            <select name="deposit" class="form-control form-control-sm py-0" style="width:auto;" aria-label="Deposit">
                                <option value="refund">Refund deposit</option><option value="credit">Keep deposit as credit</option>
                            </select>
                            <input type="text" name="method" class="form-control form-control-sm py-0" style="width:90px;" value="cash" aria-label="Refund method">
                            <button type="submit" class="btn btn-outline-secondary btn-sm py-0" onclick="return confirm('Device returned to the centre?');">Returned</button></form>
                    </div>
                @endif
            @endif
        </div>
    @endforeach

    @if ($can['move'] && !$closed)
        <details class="mt-1">
            <summary style="cursor:pointer;">Record an in-session trial</summary>
            <form method="POST" action="{{ route('deals.trials.store', $deal->id) }}" style="display:grid; gap:4px; margin-top:4px;">
                @csrf
                <select name="product_id" class="form-control form-control-sm" aria-label="Model tried" required>
                    <option value="">Model tried…</option>
                    @foreach ($trialModels ?? [] as $pid => $plabel)<option value="{{ $pid }}">{{ $plabel }}</option>@endforeach
                </select>
                <select name="result" class="form-control form-control-sm" aria-label="How it went">
                    <option value="">How did it go?</option>
                    @foreach (\App\Models\DealTrial::RESULTS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                </select>
                <input type="text" name="note" class="form-control form-control-sm" maxlength="1000" placeholder="Note (settings, what they said)">
                <button type="submit" class="btn btn-outline-primary btn-sm">Save trial</button>
            </form>
        </details>
        <details class="mt-1">
            <summary style="cursor:pointer;">Take-home trial (with deposit)</summary>
            <form method="POST" action="{{ route('deals.trials.take-home', $deal->id) }}" style="display:grid; gap:4px; margin-top:4px;">
                @csrf
                <select name="product_id" class="form-control form-control-sm" aria-label="Model" required>
                    <option value="">Model…</option>
                    @foreach ($trialModels ?? [] as $pid => $plabel)<option value="{{ $pid }}">{{ $plabel }}</option>@endforeach
                </select>
                <input type="text" name="serial_no" class="form-control form-control-sm" maxlength="120" placeholder="Serial number of the unit" required>
                <div style="display:flex; gap:4px;">
                    <input type="number" name="deposit" class="form-control form-control-sm" min="0" step="1" placeholder="Deposit ৳">
                    <input type="text" name="method" class="form-control form-control-sm" value="cash" aria-label="Payment method">
                </div>
                <label class="mb-0" style="font-size:11.5px;">Back by <input type="date" name="return_due" class="form-control form-control-sm" min="{{ today()->toDateString() }}" value="{{ today()->addDays(7)->toDateString() }}" required></label>
                <button type="submit" class="btn btn-outline-primary btn-sm">Start take-home trial</button>
            </form>
        </details>
    @endif
</div>
