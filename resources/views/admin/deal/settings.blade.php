@extends('admin.layouts.master')
@section('page_title', 'Pipeline Settings')
@section('breadcrumb_parent', 'Leads')
@section('breadcrumb_parent_url', url('admin/deals'))

@push('css')
    <link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/deal-board.css') }}?v={{ filemtime(public_path('assets/css/deal-board.css')) }}">
@endpush

@php
    $logLabels = [
        'stage_added' => 'added stage', 'stage_updated' => 'edited stage', 'stage_archived' => 'archived stage',
        'stage_restored' => 'restored stage', 'stages_reordered' => 'reordered the stages',
        'lost_reason_added' => 'added a lost reason', 'lost_reason_updated' => 'edited a lost reason',
        'default_owner_changed' => 'changed the default owner', 'office_hours_changed' => 'changed office hours', 'holiday_added' => 'added a public holiday', 'holiday_removed' => 'removed a public holiday',
    ];
    $fmt = fn ($v) => is_array($v) ? (implode(', ', array_map(fn ($x) => \App\Models\PipelineStage::GATE_FIELDS[$x] ?? $x, $v)) ?: 'none') : (is_bool($v) ? ($v ? 'yes' : 'no') : ($v ?? '—'));
@endphp

@section('content')
<div class="container-fluid" style="padding: 0 24px;">

    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-sliders-h" style="color: var(--accent-500); margin-right: 8px;"></i>Pipeline Settings</h1>
            <p>{{ $pipeline->name }} · stages, stage gates, lost reasons and who owns new deals</p>
        </div>
        <div class="header-actions">
            <a href="{{ url('admin/deals') }}" class="btn btn-primary btn-sm" id="btn-back-board"><i class="fas fa-columns mr-1"></i>Open Deals Board</a>
        </div>
    </div>

    @include('admin.alert_message')

    <div class="ps-grid crm-animate-in delay-1">
        <div>
            {{-- Stages --}}
            <div class="ps-card">
                <div class="ps-card-head">
                    <i class="fas fa-stream" style="color: var(--accent-500);"></i>
                    <div>
                        <h2>Stages</h2>
                        <p>Drag <i class="fas fa-grip-vertical"></i> to reorder. "Required when entering" is asked when a deal moves into the stage.</p>
                    </div>
                </div>
                <div class="ps-card-body" id="ps-stages">
                    @foreach ($stages as $stage)
                        <form method="POST" action="{{ url('admin/pipeline-settings/stages/' . $stage->id) }}" class="ps-stage" data-id="{{ $stage->id }}" id="stage-form-{{ $stage->id }}" style="--stage-color: {{ $stage->color }};">
                            @csrf @method('PUT')
                            <div class="ps-stage-row">
                                <div class="{{ $stage->is_system ? '' : 'ps-handle' }}" title="{{ $stage->is_system ? 'Always first' : 'Drag to reorder' }}">
                                    <i class="fas {{ $stage->is_system ? 'fa-lock' : 'fa-grip-vertical' }}"></i>
                                </div>
                                <div>
                                    <span class="ps-mini-lbl">Name</span>
                                    <input type="text" name="name" class="form-control" value="{{ $stage->name }}" maxlength="60" required id="stage-name-{{ $stage->id }}">
                                </div>
                                <div>
                                    <span class="ps-mini-lbl">Colour</span>
                                    <input type="color" name="color" class="form-control" value="{{ $stage->color }}" id="stage-color-{{ $stage->id }}">
                                </div>
                                <div>
                                    <span class="ps-mini-lbl">Win %</span>
                                    <input type="number" name="probability" class="form-control" value="{{ $stage->probability }}" min="0" max="100" required id="stage-prob-{{ $stage->id }}">
                                </div>
                                <div>
                                    <span class="ps-mini-lbl">Red after (days)</span>
                                    <input type="number" name="rotting_days" class="form-control" value="{{ $stage->rotting_days }}" min="1" max="365" placeholder="Never" id="stage-rot-{{ $stage->id }}">
                                </div>
                                <div>
                                    <span class="ps-mini-lbl">Old lead status</span>
                                    <select name="status_key" class="form-control" @disabled($stage->is_system) id="stage-status-{{ $stage->id }}">
                                        <option value="">— none —</option>
                                        @foreach ($statusKeys as $key => $label)
                                            <option value="{{ $key }}" @selected($stage->status_key === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div style="white-space: nowrap; align-self: end;">
                                    <span class="ps-badge" title="Open deals in this stage">{{ $openCounts[$stage->id] ?? 0 }} open</span>
                                    <button type="submit" class="btn btn-primary btn-sm" id="stage-save-{{ $stage->id }}"><i class="fas fa-save"></i></button>
                                    @unless ($stage->is_system)
                                        <button type="button" class="btn btn-secondary btn-sm js-archive" id="stage-archive-{{ $stage->id }}"
                                                data-id="{{ $stage->id }}" data-name="{{ $stage->name }}" data-open="{{ $openCounts[$stage->id] ?? 0 }}" title="Archive stage">
                                            <i class="fas fa-archive"></i>
                                        </button>
                                    @endunless
                                </div>
                            </div>
                            @unless ($stage->is_system)
                                <div class="ps-gates deal-pills">
                                    <span class="lbl">Required when entering</span>
                                    @foreach ($gateFields as $key => $label)
                                        <label><input type="checkbox" name="required_fields[]" value="{{ $key }}" @checked(in_array($key, $stage->required_fields ?? [], true))><span>{{ $label }}</span></label>
                                    @endforeach
                                </div>
                            @endunless
                        </form>
                    @endforeach

                    {{-- Add stage --}}
                    <form method="POST" action="{{ url('admin/pipeline-settings/stages') }}" class="ps-stage" id="stage-add-form" style="--stage-color: var(--page-text-muted); border-style: dashed;">
                        @csrf
                        <div class="ps-stage-row">
                            <div><i class="fas fa-plus" style="color: var(--page-text-muted);"></i></div>
                            <div><span class="ps-mini-lbl">New stage name</span><input type="text" name="name" class="form-control" maxlength="60" required placeholder="e.g. Fitting" id="new-stage-name"></div>
                            <div><span class="ps-mini-lbl">Colour</span><input type="color" name="color" class="form-control" value="#6366f1" id="new-stage-color"></div>
                            <div><span class="ps-mini-lbl">Win %</span><input type="number" name="probability" class="form-control" value="50" min="0" max="100" required id="new-stage-prob"></div>
                            <div><span class="ps-mini-lbl">Red after (days)</span><input type="number" name="rotting_days" class="form-control" value="7" min="1" max="365" id="new-stage-rot"></div>
                            <div>
                                <span class="ps-mini-lbl">Old lead status</span>
                                <select name="status_key" class="form-control" id="new-stage-status">
                                    <option value="">— none —</option>
                                    @foreach ($statusKeys as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div style="align-self: end;"><button type="submit" class="btn btn-primary btn-sm" id="new-stage-submit"><i class="fas fa-plus mr-1"></i>Add</button></div>
                        </div>
                        <div class="deal-hint mt-2">New stages are added at the end of the board. "Old lead status" keeps the existing lead list, dashboard and SMS filters in step.</div>
                    </form>
                </div>
            </div>

            @if ($archived->isNotEmpty())
                <div class="ps-card">
                    <div class="ps-card-head"><i class="fas fa-archive" style="color: var(--page-text-muted);"></i><div><h2>Archived stages</h2><p>Kept for history and reports. Restore to put a stage back on the board.</p></div></div>
                    <div class="ps-card-body">
                        @foreach ($archived as $stage)
                            <div class="ps-reason">
                                <span class="m-dot" style="width: 10px; height: 10px; border-radius: 50%; background: {{ $stage->color }};"></span>
                                <b style="flex: 1;">{{ $stage->name }}</b>
                                <span class="deal-hint">archived {{ $stage->archived_at->format('d M Y') }}</span>
                                <form method="POST" action="{{ url('admin/pipeline-settings/stages/' . $stage->id . '/restore') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary btn-sm" id="stage-restore-{{ $stage->id }}"><i class="fas fa-undo mr-1"></i>Restore</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div>
            {{-- Default owner --}}
            <div class="ps-card">
                <div class="ps-card-head"><i class="fas fa-user-check" style="color: var(--success-600);"></i><div><h2>Default owner</h2><p>Owns new Head Office deals (lead form, import, website).</p></div></div>
                <div class="ps-card-body">
                    <form method="POST" action="{{ url('admin/pipeline-settings/default-owner') }}" class="ps-reason">
                        @csrf @method('PUT')
                        <select name="default_owner_id" class="form-control" id="default-owner">
                            @foreach ($owners as $u)
                                <option value="{{ $u->id }}" @selected($pipeline->default_owner_id == $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm" id="default-owner-save">Save</button>
                    </form>
                    <div class="deal-hint">Branch staff always own the deals they create.</div>
                </div>
            </div>

            {{-- Office hours --}}
            <div class="ps-card" id="office-hours-card">
                <div class="ps-card-head"><i class="fas fa-business-time" style="color: var(--warning-600, #d97706);"></i><div><h2>Office hours</h2><p>New-lead call deadlines count only inside these hours on working days.</p></div></div>
                <div class="ps-card-body">
                    <form method="POST" action="{{ url('admin/pipeline-settings/office-hours') }}" id="office-hours-form">
                        @csrf @method('PUT')
                        <div class="oh-times">
                            <label for="office-open">Opens<input type="time" name="office_open" id="office-open" class="form-control" value="{{ old('office_open', $pipeline->office_open) }}" required></label>
                            <label for="office-close">Closes<input type="time" name="office_close" id="office-close" class="form-control" value="{{ old('office_close', $pipeline->office_close) }}" required></label>
                        </div>
                        <div class="oh-label">Weekly holiday</div>
                        <div class="oh-days">
                            @foreach ([6 => 'Sat', 7 => 'Sun', 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri'] as $iso => $day)
                                <label class="oh-day" for="holiday-{{ $iso }}">
                                    <input type="checkbox" name="weekly_holidays[]" value="{{ $iso }}" id="holiday-{{ $iso }}" @checked(in_array($iso, old('weekly_holidays', $pipeline->weekly_holidays ?? [])))>
                                    <span>{{ $day }}</span>
                                </label>
                            @endforeach
                        </div>
                        @if ($errors->hasAny(['office_open', 'office_close', 'weekly_holidays']))
                            <div class="text-danger mt-2" style="font-size: 12.5px;">{{ $errors->first('office_open') ?: $errors->first('office_close') ?: $errors->first('weekly_holidays') }}</div>
                        @endif
                        <button type="submit" class="btn btn-primary btn-sm mt-3" id="office-hours-save">Save</button>
                    </form>
                    <div class="deal-hint">Example: a website enquiry on Friday night is due Saturday at opening + 15 min.</div>

                    <div class="oh-label mt-3" id="public-holidays">Public holidays</div>
                    <ul class="oh-holidays">
                        @forelse ($holidays as $h)
                            <li id="holiday-row-{{ $h->id }}">
                                <span class="oh-date"><b>{{ $h->date->format('d') }}</b>{{ $h->date->format('M') }}</span>
                                <span class="oh-name">{{ $h->name }}<small>{{ $h->date->format('l, Y') }}</small></span>
                                <form method="POST" action="{{ url('admin/pipeline-settings/holidays/' . $h->id) }}" onsubmit="return confirm('Remove this holiday?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="oh-remove" id="holiday-remove-{{ $h->id }}" title="Remove" aria-label="Remove {{ $h->name }}"><i class="fas fa-times"></i></button>
                                </form>
                            </li>
                        @empty
                            <li class="oh-empty">No upcoming holidays. Add Eid, Victory Day, etc.</li>
                        @endforelse
                    </ul>
                    <form method="POST" action="{{ url('admin/pipeline-settings/holidays') }}" class="oh-add" id="holiday-add-form">
                        @csrf
                        <input type="date" name="holiday_date" id="holiday-date" class="form-control" min="{{ today()->toDateString() }}" value="{{ old('holiday_date') }}" required aria-label="Holiday date">
                        <input type="text" name="holiday_name" id="holiday-name" class="form-control" maxlength="80" placeholder="e.g. Eid-ul-Fitr" value="{{ old('holiday_name') }}" required aria-label="Holiday name">
                        <button type="submit" class="btn btn-outline-primary btn-sm" id="holiday-add"><i class="fas fa-plus"></i></button>
                    </form>
                    @if ($errors->hasAny(['holiday_date', 'holiday_name']))
                        <div class="text-danger mt-1" style="font-size: 12.5px;">{{ $errors->first('holiday_date') ?: $errors->first('holiday_name') }}</div>
                    @endif
                </div>
            </div>
            {{-- Lost reasons --}}
            <div class="ps-card">
                <div class="ps-card-head"><i class="fas fa-times-circle" style="color: var(--danger-600);"></i><div><h2>Lost reasons</h2><p>Untick to hide a reason from the Lost form (history keeps it).</p></div></div>
                <div class="ps-card-body">
                    @foreach ($lostReasons as $reason)
                        <form method="POST" action="{{ url('admin/pipeline-settings/lost-reasons/' . $reason->id) }}" class="ps-reason" id="reason-form-{{ $reason->id }}">
                            @csrf @method('PUT')
                            <input type="checkbox" name="is_active" value="1" @checked($reason->is_active) title="Active" id="reason-active-{{ $reason->id }}">
                            <input type="text" name="name" class="form-control" value="{{ $reason->name }}" maxlength="80" required id="reason-name-{{ $reason->id }}">
                            <button type="submit" class="btn btn-secondary btn-sm" id="reason-save-{{ $reason->id }}"><i class="fas fa-save"></i></button>
                        </form>
                    @endforeach
                    <form method="POST" action="{{ url('admin/pipeline-settings/lost-reasons') }}" class="ps-reason mt-2">
                        @csrf
                        <input type="text" name="name" class="form-control" maxlength="80" required placeholder="New reason" id="new-reason-name">
                        <button type="submit" class="btn btn-primary btn-sm" id="new-reason-submit"><i class="fas fa-plus"></i></button>
                    </form>
                </div>
            </div>

            {{-- Change log --}}
            <div class="ps-card">
                <div class="ps-card-head"><i class="fas fa-history" style="color: var(--accent-500);"></i><div><h2>Change log</h2><p>Last 40 changes</p></div></div>
                <div class="ps-card-body" style="max-height: 420px; overflow-y: auto;">
                    @forelse ($logs as $log)
                        <div class="ps-log">
                            <b>{{ $log->user->name ?? 'System' }}</b> {{ $logLabels[$log->action] ?? $log->action }}
                            @if ($log->stage)<b>"{{ $log->stage->name }}"</b>@endif
                            @foreach (($log->new_values ?? []) as $field => $value)
                                <div style="font-size: 12px; color: var(--page-text-secondary);">
                                    {{ str_replace('_', ' ', $field) }}:
                                    @if (array_key_exists($field, $log->old_values ?? []))<s>{{ $fmt($log->old_values[$field]) }}</s> → @endif
                                    {{ $fmt($value) }}
                                </div>
                            @endforeach
                            <div class="when">{{ $log->created_at?->format('d M Y, h:i A') }}</div>
                        </div>
                    @empty
                        <div class="deal-hint">No changes yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Archive stage --}}
<div class="modal fade deal-modal" id="archiveModal" tabindex="-1" role="dialog" aria-labelledby="archiveModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form method="POST" class="modal-content" id="archiveForm">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="archiveModalTitle"><span class="m-icon" style="background: var(--warning-500);"><i class="fas fa-archive"></i></span><span>Archive stage<span class="m-sub" id="archiveName"></span></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="deal-hint">Stages are never deleted: history and reports keep the name.</p>
                <div class="form-group mb-0" id="archiveMoveGroup">
                    <label for="archive-move-to">Move <b id="archiveCount"></b> open deal(s) to <i class="req text-danger">*</i></label>
                    <select name="move_to" id="archive-move-to" class="form-control">
                        @foreach ($stages as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning" id="archiveSubmit"><i class="fas fa-archive mr-1"></i>Archive</button>
            </div>
        </form>
    </div>
</div>
<div class="deal-toast-wrap" id="deal-toasts" aria-live="polite"></div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/plugins/sortablejs/Sortable.min.js') }}"></script>
    <script>
        $(function () {
            // Archive: ask where open deals go
            $('.js-archive').on('click', function () {
                var id = $(this).data('id'), open = +$(this).data('open');
                $('#archiveForm').attr('action', @json(url('admin/pipeline-settings/stages')) + '/' + id + '/archive');
                $('#archiveName').text($(this).data('name'));
                $('#archiveCount').text(open);
                $('#archiveMoveGroup').toggle(open > 0);
                $('#archive-move-to').prop('disabled', open === 0).find('option').prop('disabled', false).filter('[value="' + id + '"]').prop('disabled', true);
                $('#archive-move-to').val($('#archive-move-to option:not(:disabled)').first().val());
                $('#archiveModal').modal('show');
            });

            // Reorder stages (the locked "New" stage stays first on the server)
            var list = document.getElementById('ps-stages');
            if (window.Sortable && list) {
                Sortable.create(list, {
                    handle: '.ps-handle', draggable: 'form.ps-stage[data-id]', animation: 160, ghostClass: 'deal-ghost',
                    onEnd: function () {
                        var ids = $('#ps-stages form.ps-stage[data-id]').map(function () { return $(this).data('id'); }).get();
                        $.ajax({ url: @json(url('admin/pipeline-settings/stages/reorder')), method: 'POST', data: { ids: ids, _token: @json(csrf_token()) }, headers: { Accept: 'application/json' } })
                            .done(function (res) { showToast(res.message, 'ok'); })
                            .fail(function () { showToast('Could not save the order. Please reload.', 'err'); });
                    }
                });
            }

            function showToast(msg, type) {
                var $t = $('<div class="deal-toast ' + type + '"><i class="fas ' + (type === 'ok' ? 'fa-check-circle' : 'fa-exclamation-circle') + '"></i><span></span></div>');
                $t.find('span').text(msg);
                $('#deal-toasts').append($t);
                setTimeout(function () { $t.fadeOut(250, function () { $t.remove(); }); }, 3000);
            }
        });
    </script>
@endpush
