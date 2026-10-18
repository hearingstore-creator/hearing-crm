@extends('admin.layouts.master')
@section('page_title', 'Merge Duplicates')
@section('breadcrumb_parent', 'Leads')
@section('breadcrumb_parent_url', url('admin/leads'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/plugins/select2/css/select2.min.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<style>
    .mg-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
    @media (max-width: 900px) { .mg-grid { grid-template-columns: 1fr; } }
    .mg-pick { padding: 18px 20px; }
    .mg-pick label { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--page-text-muted); }
    .mg-swap { display: flex; justify-content: center; margin: -6px 0; }
    .mg-card { border: 1px solid var(--card-border); border-radius: 16px; background: var(--card-bg); overflow: hidden; }
    .mg-card-head { display: flex; align-items: center; gap: 12px; padding: 14px 18px; border-bottom: 1px solid var(--card-border); }
    .mg-card.is-keep { border-color: #10b981; box-shadow: 0 10px 30px -18px rgba(16, 185, 129, .8); }
    .mg-card.is-keep .mg-card-head { background: linear-gradient(135deg, rgba(16, 185, 129, .12), transparent); }
    .mg-card.is-merge { border-style: dashed; }
    .mg-card.is-merge .mg-card-head { background: linear-gradient(135deg, rgba(244, 63, 94, .08), transparent); }
    .mg-role { font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; padding: 3px 10px; border-radius: 999px; }
    .is-keep .mg-role { background: rgba(16, 185, 129, .15); color: #047857; }
    .is-merge .mg-role { background: rgba(244, 63, 94, .12); color: #be123c; }
    .mg-card-head h3 { margin: 0; font-size: 16px; font-weight: 800; color: var(--page-text); }
    .mg-rows { padding: 6px 18px 14px; }
    .mg-row { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px dashed var(--card-border); font-size: 13px; }
    .mg-row:last-child { border-bottom: 0; }
    .mg-row span { color: var(--page-text-muted); }
    .mg-row b { color: var(--page-text); font-weight: 600; text-align: right; word-break: break-word; }
    .mg-row b.is-fill { color: #059669; }
    .mg-moves { display: flex; flex-wrap: wrap; gap: 8px; padding: 0 18px 16px; }
    .mg-moves span { font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 999px; background: var(--accent-50); color: var(--accent-700); }
    .mg-alert { display: flex; gap: 10px; align-items: flex-start; padding: 12px 16px; border-radius: 12px; font-size: 13.5px; margin: 16px 0; }
    .mg-alert.err { background: var(--danger-50); color: var(--danger-700); border: 1px solid rgba(239, 68, 68, .25); }
    .mg-alert.info { background: var(--accent-50); color: var(--accent-700); border: 1px solid var(--accent-100); }
    .mg-confirm { display: flex; align-items: center; gap: 12px; justify-content: flex-end; flex-wrap: wrap; padding: 16px 0 4px; }
    .mg-sug { display: flex; align-items: center; gap: 12px; padding: 12px 18px; border-bottom: 1px solid var(--gray-100); flex-wrap: wrap; }
    .mg-sug:last-child { border-bottom: 0; }
    .mg-sug-tag { font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 999px; background: var(--warning-50); color: var(--warning-600); white-space: nowrap; }
    .mg-people { display: flex; flex-wrap: wrap; gap: 8px; flex: 1; }
    .mg-person { display: inline-flex; flex-direction: column; padding: 7px 12px; border-radius: 10px; border: 1px solid var(--card-border); font-size: 12.5px; }
    .mg-person b { color: var(--page-text); }
    .mg-person small { color: var(--page-text-muted); }
    .mg-person .fa-crown { color: #f59e0b; }
    [data-theme="dark"] .is-keep .mg-role { color: #6ee7b7; }
    [data-theme="dark"] .is-merge .mg-role { color: #fda4af; }
    [data-theme="dark"] .mg-sug { border-bottom-color: rgba(148, 163, 184, .12); }
</style>
@endpush

@section('content')
@php
    $fields = [
        'Phone' => 'phone_number', 'ID' => 'lead_uid', 'Email' => 'email', 'Age' => 'age', 'Address' => 'address',
        'Contact person' => 'contact_person_name', 'Budget' => 'approx_budget', 'Source' => 'source_of_entry', 'Created' => 'created_at',
    ];
    $moveLabels = ['deals' => 'deals', 'lead_follow_ups' => 'activities', 'deal_notes' => 'notes', 'appointments' => 'appointments',
        'lead_status_histories' => 'history rows', 'lead_submissions' => 'web forms', 'sms_logs' => 'SMS'];
    $show = fn ($lead, $f) => $f === 'created_at' ? $lead->created_at?->format('d M Y') : (is_scalar($lead->{$f}) ? $lead->{$f} : null);
@endphp
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-object-group" style="color: var(--accent-500); margin-right: 8px;"></i>Merge Duplicates</h1>
            <p>Combine two records of the same person. Deals, activities, notes, appointments and SMS history move to the person you keep.</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/leads') }}" class="btn btn-secondary btn-sm" id="btn-back-leads"><i class="fas fa-arrow-left mr-1"></i>Leads</a>
        </div>
    </div>

    {{-- Pick two people --}}
    <form method="GET" action="{{ route('leads.merge') }}" class="crm-table-card crm-animate-in" id="mg-pick-form">
        <div class="mg-grid mg-pick">
            <div>
                <label for="mg-keep">Keep this person</label>
                <select name="keep" id="mg-keep" class="form-control mg-select" data-placeholder="Search name, phone or ID…">
                    @if ($keep)<option value="{{ $keep->id }}" selected>{{ $keep->full_name }} · {{ $keep->phone_number }} · {{ $keep->lead_uid }}</option>@endif
                </select>
            </div>
            <div>
                <label for="mg-merge">Merge this duplicate into it</label>
                <select name="merge" id="mg-merge" class="form-control mg-select" data-placeholder="Search name, phone or ID…">
                    @if ($merge)<option value="{{ $merge->id }}" selected>{{ $merge->full_name }} · {{ $merge->phone_number }} · {{ $merge->lead_uid }}</option>@endif
                </select>
            </div>
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 8px; padding: 0 20px 18px;">
            @if ($keep && $merge)
                <a href="{{ route('leads.merge', ['keep' => $merge->id, 'merge' => $keep->id]) }}" class="btn btn-secondary btn-sm" id="mg-swap"><i class="fas fa-exchange-alt mr-1"></i>Swap</a>
            @endif
            <button type="submit" class="btn btn-primary btn-sm" id="mg-compare"><i class="fas fa-columns mr-1"></i>Compare</button>
        </div>
    </form>

    {{-- Compare + confirm --}}
    @if ($keep && $merge)
        @if ($blocker)
            <div class="mg-alert err" id="mg-blocker"><i class="fas fa-ban" style="margin-top: 2px;"></i><span>{{ $blocker }}</span></div>
        @endif
        <div class="mg-grid crm-animate-in">
            @foreach (['keep' => $keep, 'merge' => $merge] as $role => $p)
                <div class="mg-card is-{{ $role }}" id="mg-card-{{ $role }}">
                    <div class="mg-card-head">
                        <span class="mg-role">{{ $role === 'keep' ? 'Keep' : 'Merge & remove' }}</span>
                        <h3>{{ $p->full_name }}</h3>
                        @if ($p->customer)<span class="dd-meta" title="Customer"><i class="fas fa-crown" style="color: #f59e0b;"></i> Customer</span>@endif
                    </div>
                    <div class="mg-rows">
                        @foreach ($fields as $label => $f)
                            @php
                                $v = $show($p, $f);
                                $fills = $role === 'keep' && blank($v) && filled($show($merge, $f)) && !in_array($f, ['phone_number', 'lead_uid', 'created_at'], true);
                            @endphp
                            <div class="mg-row"><span>{{ $label }}</span>
                                <b class="{{ $fills ? 'is-fill' : '' }}">{{ $fills ? $show($merge, $f) . ' (filled from duplicate)' : ($v ?? '—') }}</b>
                            </div>
                        @endforeach
                    </div>
                    <div class="mg-moves">
                        @foreach ($counts[$role] as $table => $n)
                            @if ($n)<span>{{ $n }} {{ $moveLabels[$table] }}</span>@endif
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        @unless ($blocker)
            <form method="POST" action="{{ route('leads.merge.store') }}" class="mg-confirm" id="mg-merge-form"
                  onsubmit="return confirm('Merge {{ addslashes($merge->full_name) }} into {{ addslashes($keep->full_name) }}? The duplicate record will be removed.');">
                @csrf
                <input type="hidden" name="keep" value="{{ $keep->id }}">
                <input type="hidden" name="merge" value="{{ $merge->id }}">
                <span class="dd-meta"><i class="fas fa-info-circle"></i> The duplicate is archived (soft-deleted), not erased. Sales and membership are not touched.</span>
                <button type="submit" class="btn btn-danger" id="mg-confirm"><i class="fas fa-object-group mr-1"></i>Merge now</button>
            </form>
        @endunless
    @endif

    {{-- Suggestions --}}
    <div class="crm-table-card crm-animate-in" style="margin-top: 22px;">
        <div class="crm-table-header"><div class="table-title-group"><h2><i class="fas fa-magic" style="color: var(--warning-500);"></i> Possible duplicates <span class="ps-badge">{{ count($suggestions) }}</span></h2></div></div>
        @forelse ($suggestions as $i => $g)
            <div class="mg-sug" id="mg-sug-{{ $i }}">
                <span class="mg-sug-tag">{{ $g['reason'] }}</span>
                <div class="mg-people">
                    @foreach ($g['people'] as $p)
                        <span class="mg-person">
                            <b>@if ($p->customer)<i class="fas fa-crown"></i> @endif{{ $p->full_name }}</b>
                            <small>{{ $p->phone_number }} · {{ $p->lead_uid }} · {{ $p->deals_count }} deal(s) · {{ $p->created_at?->format('d M Y') }}</small>
                        </span>
                    @endforeach
                </div>
                @php
                    $sorted = $g['people']->sortByDesc(fn ($p) => [$p->customer ? 1 : 0, $p->deals_count])->values();
                @endphp
                <a href="{{ route('leads.merge', ['keep' => $sorted[0]->id, 'merge' => $sorted[1]->id]) }}" class="btn btn-secondary btn-sm" id="mg-review-{{ $i }}">Review <i class="fas fa-arrow-right ml-1"></i></a>
            </div>
        @empty
            <div class="lk-empty" style="padding: 26px; text-align: center; color: var(--page-text-muted);"><i class="fas fa-check-circle" style="color: var(--success-500);"></i> No obvious duplicates found. Use the search above to merge any two people.</div>
        @endforelse
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/plugins/select2/js/select2.full.min.js') }}"></script>
<script>
    $('.mg-select').each(function () {
        $(this).select2({
            width: '100%',
            placeholder: this.getAttribute('data-placeholder'),
            minimumInputLength: 2,
            ajax: { url: @json(route('leads.merge.search')), dataType: 'json', delay: 250, data: p => ({ q: p.term }), processResults: d => ({ results: d }) },
        });
    });
</script>
@endpush
