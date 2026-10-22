@extends('admin.layouts.master')
@section('page_title', $sequence->exists ? 'Edit Sequence' : 'New Sequence')
@section('breadcrumb_parent', 'Follow-up Sequences')
@section('breadcrumb_parent_url', route('sequences.index'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<style>
.sq-card { padding: 16px 20px; margin-bottom: 14px; }
.sq-card h2 { font-size: 14px; font-weight: 700; margin: 0 0 10px; }
.sq-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px 14px; }
.sq-card label { font-size: 12px; font-weight: 600; margin-bottom: 2px; }
.sq-checks label { font-weight: 400; margin-right: 12px; }
.sq-step-row { border: 1px solid var(--gray-200); border-radius: 10px; padding: 10px 12px; margin-bottom: 10px; position: relative; }
.sq-step-row .sq-step-top { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.sq-step-row .sq-num { font-weight: 700; width: 22px; }
.sq-step-row .sq-remove { margin-left: auto; }
.sq-vars { font-size: 11.5px; color: var(--page-text-muted); }
</style>
@endpush

@php
    $stepRows = old('steps') ?? $steps->map(function ($s) {
        $m = (int) $s->delay_minutes;
        [$v, $u] = $m > 0 && $m % 1440 === 0 ? [$m / 1440, 'days'] : ($m > 0 && $m % 60 === 0 ? [$m / 60, 'hours'] : [$m, 'minutes']);
        return ['channel' => $s->channel, 'delay_value' => $v, 'delay_unit' => $u, 'subject' => $s->subject, 'body' => $s->body, 'wa_template' => $s->wa_template, 'wa_language' => $s->wa_language];
    })->all();
    if (!$stepRows) {
        $stepRows = [['channel' => 'sms', 'delay_value' => 0, 'delay_unit' => 'minutes', 'body' => '']];
    }
    $trigger = old('trigger', $sequence->trigger);
@endphp

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-stream" style="color: var(--accent-500); margin-right: 8px;"></i>{{ $sequence->exists ? $sequence->name : 'New sequence' }}</h1>
            <p>Each step runs after the wait you set, counted from the step before (the first from the start). SMS, email and WhatsApp wait for the morning if their time falls at night.</p>
        </div>
        <div class="header-actions"><a href="{{ route('sequences.index') }}" class="btn btn-secondary btn-sm">Back</a></div>
    </div>

    @if ($errors->any())<div class="alert alert-danger" style="font-size:13px;">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ $sequence->exists ? route('sequences.update', $sequence->id) : route('sequences.store') }}" id="sq-form">
        @csrf @if ($sequence->exists) @method('PUT') @endif

        <div class="crm-table-card sq-card">
            <h2>About</h2>
            <div class="sq-grid">
                <div><label for="sq-name">Name</label><input type="text" name="name" id="sq-name" class="form-control form-control-sm" required maxlength="120" value="{{ old('name', $sequence->name) }}"></div>
                <div style="grid-column: span 2;"><label for="sq-desc">What it is for</label><input type="text" name="description" id="sq-desc" class="form-control form-control-sm" maxlength="1000" value="{{ old('description', $sequence->description) }}"></div>
            </div>
            <label class="mt-2" style="font-weight:400;"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $sequence->is_active))> Switched on</label>
        </div>

        <div class="crm-table-card sq-card">
            <h2>Starts when</h2>
            <select name="trigger" id="sq-trigger" class="form-control form-control-sm" style="max-width:340px;">
                @foreach (\App\Models\Sequence::TRIGGERS as $k => $v)<option value="{{ $k }}" @selected($trigger === $k)>{{ $v }}</option>@endforeach
            </select>
            @if ($pipelines->count() > 1)
                <div class="mt-2" data-for="deal_created deal_lost">
                    <label for="sq-pipeline" style="font-size:12px;" class="mb-1">For deals in</label>
                    @php $seqPipeline = old('trigger_pipeline_id', $sequence->exists ? $sequence->trigger_pipeline_id : $pipelines->firstWhere('is_default', true)?->id); @endphp
                    <select name="trigger_pipeline_id" id="sq-pipeline" class="form-control form-control-sm" style="max-width:340px;">
                        <option value="">Any pipeline</option>
                        @foreach ($pipelines as $pl)<option value="{{ $pl->id }}" @selected((int) $seqPipeline === $pl->id)>{{ $pl->name }}</option>@endforeach
                    </select>
                </div>
            @endif
            <div class="sq-checks mt-2" data-for="deal_created">
                <div style="font-size:12px;" class="mb-1">From these sources (none ticked = any):</div>
                @foreach ($sources as $src)<label><input type="checkbox" name="trigger_sources[]" value="{{ $src->value }}" @checked(in_array($src->value, old('trigger_sources', $sequence->trigger_sources ?? []), true))> {{ $src->label() }}</label>@endforeach
            </div>
            <div class="mt-2" data-for="stage_entered">
                <select name="trigger_stage_id" class="form-control form-control-sm" style="max-width:340px;" aria-label="Stage">
                    <option value="">Choose the stage…</option>
                    @foreach ($stages->groupBy('pipeline_id') as $pipelineId => $group)
                        <optgroup label="{{ $pipelines->firstWhere('id', $pipelineId)?->name }}">
                            @foreach ($group as $s)<option value="{{ $s->id }}" @selected((int) old('trigger_stage_id', $sequence->trigger_stage_id) === $s->id)>{{ $s->name }}</option>@endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div class="sq-checks mt-2" data-for="deal_lost">
                <div style="font-size:12px;" class="mb-1">For these lost reasons (none ticked = any):</div>
                @foreach ($lostReasons as $r)<label><input type="checkbox" name="trigger_lost_reasons[]" value="{{ $r->id }}" @checked(in_array($r->id, array_map('intval', old('trigger_lost_reasons', $sequence->trigger_lost_reasons ?? [])), true))> {{ $r->name }}</label>@endforeach
            </div>
            <div class="mt-2 text-muted" style="font-size:12px;" data-for="manual">Start it from a deal: open the deal → Activities → Follow-up sequence.</div>

            <h2 class="mt-3">Stops when</h2>
            <div class="sq-checks">
                <label><input type="checkbox" name="stop_on_reply" value="1" @checked(old('stop_on_reply', $sequence->stop_on_reply))> the patient replies (WhatsApp or SMS)</label>
                <label><input type="checkbox" name="stop_on_appointment" value="1" @checked(old('stop_on_appointment', $sequence->stop_on_appointment))> an appointment is booked</label>
                <label><input type="checkbox" name="stop_on_stage_change" value="1" @checked(old('stop_on_stage_change', $sequence->stop_on_stage_change))> the deal moves to another stage</label>
            </div>
            <div class="text-muted" style="font-size:12px;">It always stops when the deal is won or lost (a sequence started by “deal lost” stops if the deal is reopened or the person comes back).</div>
        </div>

        <div class="crm-table-card sq-card">
            <h2>Steps</h2>
            <div class="sq-vars mb-2">In texts you can use <code>@{{name}}</code>, <code>@{{company}}</code>, <code>@{{company_phone}}</code>, <code>@{{owner}}</code>.
                @unless ($mailOn) Email: the mail server is not set up yet (MAIL_MAILER), so email steps are only written to the log. @endunless
                @unless ($waOn) WhatsApp: set WHATSAPP_PHONE_NUMBER_ID and WHATSAPP_TOKEN to send templates; until then those steps are skipped. @endunless
            </div>
            <div id="sq-steps">
                @foreach ($stepRows as $i => $s)
                    @include('admin.sequences.partials.step', ['i' => $i, 's' => $s])
                @endforeach
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" id="sq-add-step"><i class="fas fa-plus mr-1"></i>Add step</button>
            <template id="sq-step-template">@include('admin.sequences.partials.step', ['i' => '__I__', 's' => ['channel' => 'call', 'delay_value' => 1, 'delay_unit' => 'days']])</template>
        </div>

        <button type="submit" class="btn btn-primary" id="sq-save">Save sequence</button>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    function showTrigger() {
        var t = document.getElementById('sq-trigger').value;
        document.querySelectorAll('[data-for]').forEach(function (el) { el.style.display = el.getAttribute('data-for').split(' ').indexOf(t) >= 0 ? '' : 'none'; });
    }
    function showChannel(row) {
        var ch = row.querySelector('.sq-channel').value;
        row.querySelectorAll('[data-ch]').forEach(function (el) { el.style.display = el.getAttribute('data-ch').split(' ').indexOf(ch) >= 0 ? '' : 'none'; });
    }
    function renumber() {
        document.querySelectorAll('#sq-steps .sq-step-row').forEach(function (row, i) {
            row.querySelector('.sq-num').textContent = (i + 1) + '.';
            row.querySelectorAll('[name]').forEach(function (el) { el.name = el.name.replace(/steps\[[^\]]+\]/, 'steps[' + i + ']'); });
        });
    }
    document.getElementById('sq-trigger').addEventListener('change', showTrigger);
    document.getElementById('sq-steps').addEventListener('change', function (e) { if (e.target.classList.contains('sq-channel')) { showChannel(e.target.closest('.sq-step-row')); } });
    document.getElementById('sq-steps').addEventListener('click', function (e) {
        var btn = e.target.closest('.sq-remove');
        if (btn && document.querySelectorAll('#sq-steps .sq-step-row').length > 1) { btn.closest('.sq-step-row').remove(); renumber(); }
    });
    document.getElementById('sq-add-step').addEventListener('click', function () {
        var html = document.getElementById('sq-step-template').innerHTML.replace(/__I__/g, String(Date.now()));
        var wrap = document.createElement('div'); wrap.innerHTML = html.trim();
        var row = wrap.firstElementChild; document.getElementById('sq-steps').appendChild(row); showChannel(row); renumber();
    });
    document.querySelectorAll('#sq-steps .sq-step-row').forEach(showChannel);
    showTrigger();
})();
</script>
@endpush
