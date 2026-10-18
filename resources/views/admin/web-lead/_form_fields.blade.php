{{-- Capture form fields (create + edit). $f = LeadCaptureForm|null, $prefix = unique id prefix --}}
<div class="form-row">
    <div class="form-group col-sm-6">
        <label for="{{ $prefix }}-name">Name <i class="req text-danger">*</i></label>
        <input type="text" name="name" id="{{ $prefix }}-name" class="form-control" maxlength="100" required value="{{ $f->name ?? '' }}" placeholder="e.g. Main website – contact form">
    </div>
    <div class="form-group col-sm-3">
        <label for="{{ $prefix }}-branch">Send leads to</label>
        <select name="vendor_id" id="{{ $prefix }}-branch" class="form-control">
            <option value="">Head Office</option>
            @foreach ($branches as $bid => $bname)
                <option value="{{ $bid }}" @selected(($f->vendor_id ?? null) == $bid)>{{ $bname }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group col-sm-3">
        <label for="{{ $prefix }}-sla">Call within (min) <i class="req text-danger">*</i></label>
        <input type="number" name="sla_minutes" id="{{ $prefix }}-sla" class="form-control" min="1" max="1440" required value="{{ $f->sla_minutes ?? 15 }}">
    </div>
</div>
<div class="form-group">
    <label for="{{ $prefix }}-slug">Short link name <span class="wl-muted">(optional – letters, numbers and dashes)</span></label>
    <div class="input-group">
        <div class="input-group-prepend"><span class="input-group-text">{{ url('f') }}/</span></div>
        <input type="text" name="slug" id="{{ $prefix }}-slug" class="form-control" maxlength="40" pattern="[A-Za-z0-9\- ]{3,40}" value="{{ $f->slug ?? '' }}" placeholder="e.g. hearing-test">
    </div>
</div>
<div class="form-group">
    <label>Default interest <span class="wl-muted">(used when the website doesn't send one)</span></label>
    <div class="deal-pills">
        @foreach ($inquiries as $iq)
            <label><input type="checkbox" name="default_inquiry[]" value="{{ $iq->value }}" @checked(in_array($iq->value, $f->default_inquiry ?? [], true))><span>{{ $iq->label() }}</span></label>
        @endforeach
    </div>
</div>
<div class="custom-control custom-switch mb-3">
    <input type="checkbox" class="custom-control-input" name="is_active" value="1" id="{{ $prefix }}-active" @checked($f->is_active ?? true)>
    <label class="custom-control-label" for="{{ $prefix }}-active">Accept leads from this form</label>
</div>
