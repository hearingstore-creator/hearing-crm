{{-- Lead assignment rules and lead score weights (Pipeline Settings). --}}
@php
    $sources = \App\Enums\SourceOfEntry::cases();
    $inquiries = \App\Enums\InquiryType::cases();
    $userName = $ruleUsers->pluck('name', 'id');
@endphp

<div class="ps-card" id="lead-assignment">
    <div class="ps-card-head"><i class="fas fa-random" style="color: var(--accent-600);"></i><div><h2>Lead assignment</h2><p>Who gets a new website, ad, WhatsApp or webhook lead. Rules are tried top to bottom; the first that matches and has someone free wins.</p></div></div>
    <div class="ps-card-body">
        @forelse ($rules as $rule)
            <details class="mb-2" style="border:1px solid var(--gray-100); border-radius:8px; padding:8px 10px;">
                <summary style="cursor:pointer; font-size:13px;">
                    <b>{{ $rule->sort_order }}. {{ $rule->name }}</b>
                    @unless ($rule->is_active)<span class="badge badge-light">off</span>@endunless
                    <span class="text-muted" style="font-size:12px;">
                        · {{ \App\Models\LeadAssignmentRule::METHODS[$rule->method] ?? $rule->method }} between
                        {{ collect($rule->user_ids)->map(fn ($id) => $userName[$id] ?? '#' . $id)->implode(', ') }}
                        · {{ $rule->assigned_count }} assigned
                    </span>
                </summary>
                <form method="POST" action="{{ url('admin/pipeline-settings/assignment-rules/' . $rule->id) }}" class="mt-2">
                    @csrf @method('PUT')
                    @include('admin.deal.partials.rule-fields', ['r' => $rule])
                    <div style="display:flex; gap:6px; margin-top:8px;">
                        <button type="submit" class="btn btn-primary btn-sm">Save rule</button>
                    </div>
                </form>
                <form method="POST" action="{{ url('admin/pipeline-settings/assignment-rules/' . $rule->id) }}" onsubmit="return confirm('Delete this rule? Deals keep their owners.');" class="mt-1">
                    @csrf @method('DELETE')<button type="submit" class="btn btn-link btn-sm text-danger p-0">Delete rule</button>
                </form>
            </details>
        @empty
            <p class="deal-hint">No rules yet: new inbound leads go to the default owner above.</p>
        @endforelse

        <details class="mt-2" {{ $rules->isEmpty() ? 'open' : '' }}>
            <summary style="cursor:pointer; font-weight:600; font-size:13px;"><i class="fas fa-plus mr-1"></i>Add a rule</summary>
            <form method="POST" action="{{ url('admin/pipeline-settings/assignment-rules') }}" class="mt-2" id="rule-add-form">
                @csrf
                @include('admin.deal.partials.rule-fields', ['r' => null])
                <button type="submit" class="btn btn-primary btn-sm mt-2" id="rule-add-save">Add rule</button>
            </form>
        </details>
        <div class="deal-hint mt-2">People on approved leave, deactivated, or at their open-deal limit are skipped. Branch staff still own the leads they type in themselves.</div>
    </div>
</div>

<div class="ps-card" id="lead-score">
    <div class="ps-card-head"><i class="fas fa-tachometer-alt" style="color: var(--danger-600);"></i><div><h2>Lead score</h2><p>Points for signs that a person will buy soon. The score (0–100) shows on every deal; hot from {{ config('lead_scoring.hot_from') }}, warm from {{ config('lead_scoring.warm_from') }}. Start: {{ config('lead_scoring.base') }}.</p></div></div>
    <div class="ps-card-body">
        <form method="POST" action="{{ url('admin/pipeline-settings/lead-score') }}" id="lead-score-form">
            @csrf @method('PUT')
            <table class="table table-sm mb-2" style="font-size:12.5px;">
                @foreach ($scoreWeights as $key => $w)
                    <tr>
                        <td>{{ $w['label'] }}</td>
                        <td style="width:90px;"><input type="number" name="weights[{{ $key }}]" value="{{ $w['points'] }}" min="-50" max="50" class="form-control form-control-sm" aria-label="{{ $w['label'] }}"></td>
                    </tr>
                @endforeach
            </table>
            <button type="submit" class="btn btn-primary btn-sm" id="lead-score-save">Save and re-score</button>
        </form>
    </div>
</div>
