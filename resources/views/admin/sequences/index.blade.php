@extends('admin.layouts.master')
@section('page_title', 'Follow-up Sequences')
@section('breadcrumb_parent', 'Leads')
@section('breadcrumb_parent_url', url('admin/deals'))

@push('css')
<link rel="stylesheet" href="{{ asset('assets/css/crm-module.css') }}">
<style>
.sq-card { padding: 14px 18px; margin-bottom: 12px; }
.sq-head { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.sq-head h2 { font-size: 15px; font-weight: 700; margin: 0; }
.sq-steps { display: flex; flex-wrap: wrap; gap: 6px; margin: 10px 0 6px; font-size: 12px; }
.sq-step { border: 1px solid var(--gray-200); border-radius: 16px; padding: 3px 10px; background: var(--gray-50); }
.sq-stats { font-size: 12px; color: var(--page-text-muted); display: flex; gap: 14px; flex-wrap: wrap; }
.sq-off { opacity: .7; }
</style>
@endpush

@section('content')
<div class="container-fluid" style="padding: 0 24px;">
    <div class="crm-page-header crm-animate-in">
        <div class="page-title-group">
            <h1><i class="fas fa-stream" style="color: var(--accent-500); margin-right: 8px;"></i>Follow-up Sequences</h1>
            <p>Planned follow-ups that run by themselves: SMS, email, WhatsApp and call tasks over days. They stop when the patient replies, books, moves on, buys or is lost.</p>
        </div>
        @can('permission', 'sequences.create')
        <div class="header-actions"><a href="{{ route('sequences.create') }}" class="btn btn-primary btn-sm" id="sq-new"><i class="fas fa-plus mr-1"></i>New sequence</a></div>
        @endcan
    </div>

    @forelse ($sequences as $sq)
        @php $st = $stats[$sq->id] ?? collect(); @endphp
        <div class="crm-table-card sq-card {{ $sq->is_active ? '' : 'sq-off' }}" id="sq-{{ $sq->id }}">
            <div class="sq-head">
                <h2>{{ $sq->name }}</h2>
                <span class="crm-status-badge {{ $sq->is_active ? 'badge-status-won' : 'badge-status-lost' }}"><span class="dot"></span>{{ $sq->is_active ? 'On' : 'Off' }}</span>
                <span class="text-muted" style="font-size:12.5px;">
                    {{ \App\Models\Sequence::TRIGGERS[$sq->trigger] ?? $sq->trigger }}
                    @if ($sq->trigger === 'deal_created' && $sq->trigger_sources) · {{ collect($sq->trigger_sources)->map(fn ($s) => \App\Enums\SourceOfEntry::tryFrom($s)?->label() ?? $s)->implode(', ') }}@endif
                    @if ($sq->trigger === 'stage_entered') · {{ \App\Models\PipelineStage::find($sq->trigger_stage_id)?->name }}@endif
                </span>
                <span style="margin-left:auto; display:flex; gap:6px;">
                    @can('permission', 'sequences.edit')
                        <form method="POST" action="{{ route('sequences.toggle', $sq->id) }}">@csrf<button class="btn btn-sm {{ $sq->is_active ? 'btn-outline-secondary' : 'btn-success' }}" type="submit">{{ $sq->is_active ? 'Switch off' : 'Switch on' }}</button></form>
                        <a href="{{ route('sequences.edit', $sq->id) }}" class="btn btn-outline-primary btn-sm">Edit</a>
                    @endcan
                    @can('permission', 'sequences.delete')
                        <form method="POST" action="{{ route('sequences.destroy', $sq->id) }}" onsubmit="return confirm('Delete this sequence? People in it stop.');">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm" type="submit" title="Delete"><i class="far fa-trash-alt"></i></button></form>
                    @endcan
                </span>
            </div>
            @if ($sq->description)<div class="text-muted" style="font-size:12.5px; margin-top:4px;">{{ $sq->description }}</div>@endif
            <div class="sq-steps">
                @foreach ($sq->steps as $step)
                    @php [$chLabel, $chIcon] = \App\Models\Sequence::CHANNELS[$step->channel] ?? [$step->channel, 'fas fa-circle']; @endphp
                    <span class="sq-step" title="{{ $step->body }}"><i class="{{ $chIcon }}"></i> {{ $step->position }}. {{ $chLabel }} <span class="text-muted">· {{ $step->delayLabel() }}</span></span>
                @endforeach
            </div>
            <div class="sq-stats">
                <span><b>{{ $st['active'] ?? 0 }}</b> in it now</span>
                <span><b>{{ $st['completed'] ?? 0 }}</b> finished</span>
                <span><b>{{ $st['stopped'] ?? 0 }}</b> stopped early (replied, booked, moved on…)</span>
                <span><b>{{ $sent[$sq->id] ?? 0 }}</b> steps done</span>
                <span><b>{{ $won[$sq->id] ?? 0 }}</b> went on to buy</span>
            </div>
        </div>
    @empty
        <div class="crm-table-card sq-card text-center text-muted">No sequences yet.</div>
    @endforelse
</div>
@endsection
