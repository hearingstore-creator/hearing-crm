@php
    /** @var \App\Models\SmsAutomation|null $automation */
    $automation = $automation ?? null;
    $isActive   = (string) old('is_active', $automation ? (int) $automation->is_active : 1);
    $selTrigger = old('trigger_type', $automation->trigger_type ?? '');
    $triggerMeta = collect($triggers)->mapWithKeys(fn ($t) => [$t->value => [
        'label'     => $t->label(),
        'essential' => $t->isEssential(),
        'promo'     => $t->isPromotional(),
    ]]);
@endphp

<style>
    .smf-wrap { display:grid; grid-template-columns: minmax(0, 1.6fr) minmax(300px, 1fr); gap:20px; align-items:start; }
    .smf-card { background: var(--page-surface, #fff); border:1px solid var(--gray-100, #eef0f4); border-radius:16px; box-shadow: 0 1px 2px rgba(16,24,40,.04); margin-bottom:16px; overflow:hidden; }
    .smf-card-h { display:flex; align-items:center; gap:10px; padding:14px 20px; border-bottom:1px solid var(--gray-100, #eef0f4); }
    .smf-card-h .ic { width:30px; height:30px; border-radius:9px; display:inline-flex; align-items:center; justify-content:center; font-size:13px; color:#fff; }
    .smf-card-h h6 { margin:0; font-size:14px; font-weight:700; color: var(--page-text, #1f2937); }
    .smf-card-h small { margin-left:auto; font-size:12px; color: var(--page-text-muted, #6b7280); }
    .smf-card-b { padding:20px; }
    .smf-row { display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
    .smf-field { margin-bottom:16px; }
    .smf-field:last-child { margin-bottom:0; }
    .smf-label { display:block; margin:0 0 6px; font-size:12.5px; font-weight:600; color: var(--page-text, #1f2937); }
    .smf-label .req { color: var(--danger-500, #ef4444); }
    .smf-help { margin-top:6px; font-size:11.5px; line-height:1.45; color: var(--page-text-muted, #6b7280); }
    .smf-card .form-control { border-radius:10px; font-size:13.5px; }
    .smf-card select.form-control { height:40px; }
    .smf-card input.form-control { height:40px; }
    .smf-card textarea.form-control { line-height:1.55; resize:vertical; min-height:130px; }
    .smf-hint-box { display:none; margin-top:8px; padding:9px 12px; border-radius:10px; font-size:12px; line-height:1.5; }
    .smf-hint-box.show { display:block; }
    .smf-hint-box.ess { background: var(--info-50, #eff6ff); color: var(--info-700, #1d4ed8); }
    .smf-hint-box.promo { background: var(--warning-50, #fffbeb); color: var(--warning-700, #b45309); }

    .smf-vars { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:10px; }
    .smf-var { border:1px dashed var(--accent-300, #a5b4fc); background: var(--accent-50, #eef2ff); color: var(--accent-700, #4338ca); font-size:12px; font-weight:600; padding:4px 10px; border-radius:999px; cursor:pointer; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; transition: background .15s, transform .1s; }
    .smf-var:hover { background: var(--accent-100, #e0e7ff); }
    .smf-var:active { transform: scale(.96); }
    .smf-var:focus-visible { outline:2px solid var(--accent-500, #6366f1); outline-offset:1px; }
    .smf-counter { display:flex; justify-content:space-between; gap:10px; margin-top:6px; font-size:11.5px; color: var(--page-text-muted, #6b7280); }
    .smf-counter b { color: var(--page-text, #1f2937); }
    .smf-counter .warn { color: var(--warning-700, #b45309); font-weight:600; }

    .smf-seg { display:inline-flex; padding:3px; gap:2px; background: var(--gray-100, #eef0f4); border-radius:11px; }
    .smf-seg input { position:absolute; opacity:0; pointer-events:none; }
    .smf-seg label { margin:0; padding:7px 16px; border-radius:8px; font-size:13px; font-weight:600; color: var(--page-text-muted, #6b7280); cursor:pointer; transition: background .15s, color .15s, box-shadow .15s; }
    .smf-seg input:checked + label { background:#fff; color: var(--page-text, #1f2937); box-shadow: 0 1px 3px rgba(16,24,40,.12); }
    .smf-seg input[value="1"]:checked + label { color: var(--success-700, #047857); }
    .smf-seg input:focus-visible + label { outline:2px solid var(--accent-500, #6366f1); outline-offset:1px; }

    .smf-side { position:sticky; top:84px; }
    .smf-phone { background: linear-gradient(180deg, var(--gray-50, #f6f7fb), var(--gray-100, #eef0f4)); padding:18px 16px 20px; }
    .smf-phone-top { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
    .smf-avatar { width:34px; height:34px; border-radius:50%; background: linear-gradient(135deg, var(--accent-500, #6366f1), var(--accent-700, #4338ca)); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:13px; }
    .smf-phone-name { font-size:13px; font-weight:700; color: var(--page-text, #1f2937); line-height:1.2; }
    .smf-phone-sub { font-size:11px; color: var(--page-text-muted, #6b7280); }
    .smf-bubble { max-width:92%; padding:11px 14px; background:#fff; border-radius:4px 16px 16px 16px; box-shadow: 0 2px 8px -3px rgba(16,24,40,.18); font-size:13px; line-height:1.55; color: var(--page-text, #1f2937); white-space:pre-line; word-break:break-word; min-height:44px; }
    .smf-bubble.empty { color: var(--page-text-muted, #9aa1ae); font-style:italic; }
    .smf-bubble mark { background: var(--warning-50, #fffbeb); color: var(--warning-700, #b45309); padding:0 3px; border-radius:4px; }
    .smf-time { margin:6px 4px 0; font-size:10.5px; color: var(--page-text-muted, #9aa1ae); }
    .smf-note { padding:12px 16px 14px; font-size:11.5px; line-height:1.5; color: var(--page-text-muted, #6b7280); border-top:1px solid var(--gray-100, #eef0f4); }

    .smf-actions { display:flex; justify-content:flex-end; gap:10px; margin: 4px 0 24px; }

    @media (max-width: 991px) { .smf-wrap { grid-template-columns: 1fr; } .smf-side { position:static; } }
    @media (max-width: 560px) { .smf-row { grid-template-columns: 1fr; } }
</style>

<form action="{{ $action }}" method="POST" id="smfForm">
    @csrf
    @if(($method ?? 'POST') !== 'POST') @method($method) @endif

    <div class="smf-wrap">
        {{-- ─────────── Left: the form ─────────── --}}
        <div>
            <section class="smf-card">
                <div class="smf-card-h">
                    <span class="ic" style="background: var(--accent-500, #6366f1);"><i class="fas fa-bolt"></i></span>
                    <h6>Trigger</h6>
                    <small>When should this message go out?</small>
                </div>
                <div class="smf-card-b">
                    <div class="smf-row">
                        <div class="smf-field">
                            <label class="smf-label" for="name">Rule name <span class="req">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" id="name" placeholder="e.g. Repair ready for pickup" value="{{ old('name', $automation->name ?? '') }}" required>
                            @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="smf-field">
                            <label class="smf-label" for="trigger_type">Trigger event <span class="req">*</span></label>
                            <select name="trigger_type" id="trigger_type" class="form-control @error('trigger_type') is-invalid @enderror" required>
                                @unless($automation)<option value="">Select a trigger…</option>@endunless
                                @foreach($triggers as $trigger)
                                    <option value="{{ $trigger->value }}" {{ $selTrigger === $trigger->value ? 'selected' : '' }}>{{ $trigger->label() }}</option>
                                @endforeach
                            </select>
                            @error('trigger_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="smf-hint-box" id="smfTrigHint"></div>
                        </div>
                    </div>
                    <div class="smf-field">
                        <label class="smf-label" for="description">Description <span style="font-weight:500; color: var(--page-text-muted);">(optional)</span></label>
                        <input type="text" class="form-control" name="description" id="description" placeholder="Briefly describe what this automation does" value="{{ old('description', $automation->description ?? '') }}">
                    </div>
                </div>
            </section>

            <section class="smf-card">
                <div class="smf-card-h">
                    <span class="ic" style="background: var(--success-500, #22c55e);"><i class="fas fa-comment-dots"></i></span>
                    <h6>Message</h6>
                    <small>Click a variable to insert it</small>
                </div>
                <div class="smf-card-b">
                    <div class="smf-field">
                        <label class="smf-label" for="message_body">Message body <span class="req">*</span></label>
                        <div class="smf-vars" id="smfVars" aria-label="Variables you can insert"></div>
                        <textarea name="message_body" id="message_body" class="form-control @error('message_body') is-invalid @enderror" rows="6" maxlength="1000" placeholder="Dear @{{name}}, your order is ready. Call us on @{{company_phone}}." required>{{ old('message_body', $automation->message_body ?? '') }}</textarea>
                        @error('message_body')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="smf-counter">
                            <span id="smfParts"></span>
                            <span><b id="smfChars">0</b> / 1000</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="smf-card">
                <div class="smf-card-h">
                    <span class="ic" style="background: var(--warning-500, #f59e0b);"><i class="fas fa-toggle-on"></i></span>
                    <h6>Status</h6>
                </div>
                <div class="smf-card-b">
                    <div class="smf-seg" role="radiogroup" aria-label="Status">
                        <input type="radio" name="is_active" id="stActive" value="1" {{ $isActive === '1' ? 'checked' : '' }}>
                        <label for="stActive"><i class="fas fa-circle" style="font-size:7px; margin-right:6px;"></i>Active</label>
                        <input type="radio" name="is_active" id="stInactive" value="0" {{ $isActive === '1' ? '' : 'checked' }}>
                        <label for="stInactive"><i class="fas fa-circle" style="font-size:7px; margin-right:6px;"></i>Inactive</label>
                    </div>
                    <div class="smf-help">An inactive rule never sends. The SMS Centre mode (All / Essential only / Paused) still applies on top of this.</div>
                </div>
            </section>

            <div class="smf-actions">
                <a href="{{ url('admin/sms#automation-tab') }}" class="btn btn-secondary btn-sm">Cancel</a>
                <button type="submit" class="btn btn-primary btn-sm" id="smfSubmit"><i class="fas fa-save mr-1"></i>{{ $submitLabel }}</button>
            </div>
        </div>

        {{-- ─────────── Right: live preview ─────────── --}}
        <aside class="smf-side">
            <section class="smf-card" style="margin-bottom:0;">
                <div class="smf-card-h">
                    <span class="ic" style="background: var(--info-500, #3b82f6);"><i class="fas fa-mobile-alt"></i></span>
                    <h6>Live preview</h6>
                </div>
                <div class="smf-phone">
                    <div class="smf-phone-top">
                        <span class="smf-avatar"><i class="fas fa-store"></i></span>
                        <div>
                            <div class="smf-phone-name">Hearing Store</div>
                            <div class="smf-phone-sub">Text message · now</div>
                        </div>
                    </div>
                    <div class="smf-bubble empty" id="smfPreview">Your message will appear here.</div>
                    <div class="smf-time" id="smfPreviewNote"></div>
                </div>
                <div class="smf-note">Sample values are used for the preview. <mark style="background: var(--warning-50, #fffbeb); color: var(--warning-700, #b45309); padding:0 3px; border-radius:4px;">Highlighted</mark> variables are not filled in for this trigger and would be sent as plain text.</div>
            </section>
        </aside>
    </div>
</form>

@push('scripts')
@verbatim
<script>
(function () {
    var TRIGGERS = JSON.parse(document.getElementById('smfTriggerData').textContent);

    // Variables the system really fills in, per trigger (taken from SmsAutomationService / SmsService)
    var COMMON = ['name', 'company_phone'];
    var BY_TRIGGER = {
        customer_created:    ['name', 'customer_id', 'company_phone'],
        customer_first_sale: ['name', 'customer_name', 'customer_id', 'company_phone'],
        membership_upgrade:  ['name', 'customer_name', 'customer_id', 'tier', 'company_phone'],
        warranty_expiring:   ['name', 'customer_id', 'warranty_expiry_date', 'company_phone'],
        appointment_reminder:['name', 'appointment_date', 'company_phone'],
        payment_received:    ['name', 'amount', 'invoice', 'date', 'company_phone'],
        referral_earned:     ['name', 'amount', 'invoice', 'company_phone'],
        referral_paid:       ['name', 'amount', 'voucher', 'date', 'company_phone']
    };
    var SAMPLE = {
        name: 'Rahim Uddin', customer_name: 'Rahim Uddin', customer_id: 'C-1024', company_phone: '01700-000000',
        tier: 'Gold', warranty_expiry_date: '30 Dec 2026', appointment_date: '25 Oct 2026',
        amount: '5,000.00', invoice: 'INV-1042', voucher: 'VCH-210', date: '22 Oct 2026'
    };

    var $trig = document.getElementById('trigger_type');
    var $ta = document.getElementById('message_body');
    var $vars = document.getElementById('smfVars');
    var $prev = document.getElementById('smfPreview');
    var $note = document.getElementById('smfPreviewNote');
    var $hint = document.getElementById('smfTrigHint');
    var $chars = document.getElementById('smfChars');
    var $parts = document.getElementById('smfParts');

    function esc(s) { return s.replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function allowed() { return BY_TRIGGER[$trig.value] || COMMON; }

    function renderVars() {
        $vars.innerHTML = '';
        allowed().forEach(function (v) {
            var b = document.createElement('button');
            b.type = 'button'; b.className = 'smf-var'; b.setAttribute('data-var', v);
            b.textContent = '{{' + v + '}}';
            $vars.appendChild(b);
        });
        if (!BY_TRIGGER[$trig.value] && $trig.value) {
            var s = document.createElement('span');
            s.className = 'smf-help'; s.style.alignSelf = 'center';
            s.textContent = 'Other details depend on the trigger.';
            $vars.appendChild(s);
        }
    }

    function renderHint() {
        var m = TRIGGERS[$trig.value];
        $hint.className = 'smf-hint-box'; $hint.textContent = '';
        if (!m) return;
        if (m.essential) {
            $hint.className += ' show ess';
            $hint.textContent = 'Essential: still sends when the SMS Centre mode is "Essential only".';
        } else if (m.promo) {
            $hint.className += ' show promo';
            $hint.textContent = 'Promotional: skipped for numbers that opted out, and stops in "Essential only" mode.';
        }
    }

    function renderPreview() {
        var text = $ta.value, ok = allowed(), unknown = 0;
        $chars.textContent = text.length;

        // SMS parts (approximate): non-ASCII text (e.g. Bangla) uses the 70/67 limit, plain text 160/153
        var uni = /[^\x00-\x7F]/.test(text), len = text.length, one = uni ? 70 : 160, multi = uni ? 67 : 153;
        var parts = len === 0 ? 0 : (len <= one ? 1 : Math.ceil(len / multi));
        $parts.innerHTML = parts === 0 ? 'No text yet' :
            '<b>' + parts + '</b> SMS ' + (parts === 1 ? 'part' : 'parts') + ' (approx.) · ' + (uni ? 'Unicode text' : 'Plain text') +
            (parts > 1 ? ' <span class="warn">· each part is billed</span>' : '');

        if (!text.trim()) {
            $prev.className = 'smf-bubble empty'; $prev.textContent = 'Your message will appear here.'; $note.textContent = '';
            return;
        }
        var html = esc(text).replace(/\{\{(\w+)\}\}/g, function (all, k) {
            if (ok.indexOf(k) !== -1 && SAMPLE[k] !== undefined) return esc(SAMPLE[k]);
            unknown++;
            return '<mark>' + all + '</mark>';
        });
        $prev.className = 'smf-bubble'; $prev.innerHTML = html;
        $note.textContent = unknown ? unknown + ' variable' + (unknown > 1 ? 's' : '') + ' not available for this trigger' : '';
    }

    $vars.addEventListener('click', function (e) {
        var b = e.target.closest('.smf-var'); if (!b) return;
        var token = '{{' + b.getAttribute('data-var') + '}}';
        var s = $ta.selectionStart == null ? $ta.value.length : $ta.selectionStart, en = $ta.selectionEnd == null ? s : $ta.selectionEnd;
        $ta.value = $ta.value.slice(0, s) + token + $ta.value.slice(en);
        $ta.focus(); var pos = s + token.length; $ta.setSelectionRange(pos, pos);
        renderPreview();
    });
    $ta.addEventListener('input', renderPreview);
    $trig.addEventListener('change', function () { renderVars(); renderHint(); renderPreview(); });

    // Stop double submits
    document.getElementById('smfForm').addEventListener('submit', function () {
        var b = document.getElementById('smfSubmit'); b.disabled = true;
        b.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-1"></i>Saving…';
    });

    renderVars(); renderHint(); renderPreview();
})();
</script>
@endverbatim
@endpush

<script type="application/json" id="smfTriggerData">@json($triggerMeta)</script>
