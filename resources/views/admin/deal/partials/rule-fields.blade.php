@php $p = $r ? 'r' . $r->id . '_' : 'rn_'; @endphp
<div style="display:grid; grid-template-columns: 1fr 90px; gap:6px 10px; font-size:12.5px;">
    <div><label class="mb-0" for="{{ $p }}name">Name</label><input type="text" name="name" id="{{ $p }}name" class="form-control form-control-sm" required maxlength="100" value="{{ $r->name ?? '' }}" placeholder="e.g. Facebook leads – sales team"></div>
    <div><label class="mb-0" for="{{ $p }}order">Order</label><input type="number" name="sort_order" id="{{ $p }}order" class="form-control form-control-sm" min="0" value="{{ $r->sort_order ?? 10 }}"></div>
</div>

<div style="font-size:12.5px; margin-top:6px;">
    <div class="mb-1"><b>When the lead came from</b> <span class="text-muted">(none ticked = any)</span></div>
    @foreach ($sources as $src)
        <label class="mr-2 mb-0" style="font-weight:400;"><input type="checkbox" name="sources[]" value="{{ $src->value }}" @checked(in_array($src->value, $r->sources ?? [], true))> {{ $src->label() }}</label>
    @endforeach
</div>
<div style="font-size:12.5px; margin-top:6px;">
    <div class="mb-1"><b>and wants</b> <span class="text-muted">(none ticked = anything)</span></div>
    @foreach ($inquiries as $iq)
        <label class="mr-2 mb-0" style="font-weight:400;"><input type="checkbox" name="inquiries[]" value="{{ $iq->value }}" @checked(in_array($iq->value, $r->inquiries ?? [], true))> {{ $iq->label() }}</label>
    @endforeach
</div>
<div style="font-size:12.5px; margin-top:6px;">
    <div class="mb-1"><b>and belongs to</b> <span class="text-muted">(none ticked = any branch)</span></div>
    <label class="mr-2 mb-0" style="font-weight:400;"><input type="checkbox" name="vendor_ids[]" value="0" @checked(in_array(0, array_map('intval', $r->vendor_ids ?? []), true))> Head office</label>
    @foreach ($branchList as $bid => $bname)
        <label class="mr-2 mb-0" style="font-weight:400;"><input type="checkbox" name="vendor_ids[]" value="{{ $bid }}" @checked(in_array((int) $bid, array_map('intval', $r->vendor_ids ?? []), true))> {{ $bname }}</label>
    @endforeach
</div>
@php $rulePipelines = $r ? array_map('intval', $r->pipeline_ids ?? []) : [(int) $pipelines->firstWhere('is_default', true)?->id]; @endphp
<div style="font-size:12.5px; margin-top:6px;">
    <div class="mb-1"><b>and the deal is in</b> <span class="text-muted">(none ticked = any pipeline)</span></div>
    @foreach ($pipelines as $pl)
        @continue($pl->archived_at && !in_array($pl->id, $rulePipelines, true))
        <label class="mr-2 mb-0" style="font-weight:400;"><input type="checkbox" name="pipeline_ids[]" value="{{ $pl->id }}" @checked(in_array($pl->id, $rulePipelines, true))> {{ $pl->name }}</label>
    @endforeach
</div>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:6px 10px; font-size:12.5px; margin-top:8px;">
    <div>
        <label class="mb-0" for="{{ $p }}users"><b>Give it to</b></label>
        <select name="user_ids[]" id="{{ $p }}users" class="form-control form-control-sm" multiple size="5" required>
            @foreach ($ruleUsers as $u)
                <option value="{{ $u->id }}" @selected(in_array($u->id, array_map('intval', $r->user_ids ?? []), true))>{{ $u->name }}{{ $u->vendor_id ? ' (' . ($branchList[$u->vendor_id] ?? 'branch') . ')' : '' }}</option>
            @endforeach
        </select>
        <small class="text-muted">Ctrl / ⌘ + click to pick several</small>
    </div>
    <div>
        <label class="mb-0" for="{{ $p }}method">How</label>
        <select name="method" id="{{ $p }}method" class="form-control form-control-sm">
            @foreach (\App\Models\LeadAssignmentRule::METHODS as $k => $v)<option value="{{ $k }}" @selected(($r->method ?? 'round_robin') === $k)>{{ $v }}</option>@endforeach
        </select>
        <label class="mb-0 mt-1" for="{{ $p }}branch">Hand the deal to branch</label>
        <select name="set_vendor_id" id="{{ $p }}branch" class="form-control form-control-sm">
            <option value="">Keep as it is</option>
            @foreach ($branchList as $bid => $bname)<option value="{{ $bid }}" @selected(($r->set_vendor_id ?? null) == $bid)>{{ $bname }}</option>@endforeach
        </select>
    </div>
    <div>
        <label class="mb-0" for="{{ $p }}max">Skip people with this many open deals</label>
        <input type="number" name="max_open" id="{{ $p }}max" class="form-control form-control-sm" min="1" value="{{ $r->max_open ?? '' }}" placeholder="no limit">
        <label class="mb-0 mt-1" for="{{ $p }}re">Not contacted after (minutes) → next person</label>
        <input type="number" name="reassign_after_minutes" id="{{ $p }}re" class="form-control form-control-sm" min="5" value="{{ $r->reassign_after_minutes ?? '' }}" placeholder="never">
    </div>
</div>
<div style="font-size:12.5px; margin-top:6px;">
    <label class="mr-3 mb-0" style="font-weight:400;"><input type="checkbox" name="include_manual" value="1" @checked($r->include_manual ?? false)> Also leads typed in by head office</label>
    <label class="mb-0" style="font-weight:400;"><input type="checkbox" name="is_active" value="1" @checked($r->is_active ?? true)> Active</label>
</div>
