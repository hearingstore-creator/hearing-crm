<div class="sq-step-row">
    <div class="sq-step-top">
        <span class="sq-num">{{ is_numeric($i) ? $i + 1 : '' }}.</span>
        <select name="steps[{{ $i }}][channel]" class="form-control form-control-sm sq-channel" style="width:200px;" aria-label="Channel">
            @foreach (\App\Models\Sequence::CHANNELS as $k => [$label])<option value="{{ $k }}" @selected(($s['channel'] ?? '') === $k)>{{ $label }}</option>@endforeach
        </select>
        <span style="font-size:12px;">after</span>
        <input type="number" name="steps[{{ $i }}][delay_value]" class="form-control form-control-sm" style="width:80px;" min="0" max="1000" value="{{ $s['delay_value'] ?? 0 }}" aria-label="Wait">
        <select name="steps[{{ $i }}][delay_unit]" class="form-control form-control-sm" style="width:110px;" aria-label="Unit">
            @foreach (['minutes' => 'minutes', 'hours' => 'hours', 'days' => 'days'] as $k => $v)<option value="{{ $k }}" @selected(($s['delay_unit'] ?? 'days') === $k)>{{ $v }}</option>@endforeach
        </select>
        <button type="button" class="btn btn-link btn-sm text-danger sq-remove" title="Remove step"><i class="fas fa-times"></i></button>
    </div>
    <div class="mt-2" data-ch="email"><input type="text" name="steps[{{ $i }}][subject]" class="form-control form-control-sm" maxlength="200" value="{{ $s['subject'] ?? '' }}" placeholder="Email subject"></div>
    <div class="mt-2" data-ch="sms email call"><textarea name="steps[{{ $i }}][body]" class="form-control form-control-sm" rows="2" maxlength="2000" placeholder="Message text (for a call task: what to talk about)">{{ $s['body'] ?? '' }}</textarea></div>
    <div class="mt-2" data-ch="whatsapp" style="display:flex; gap:8px;">
        <input type="text" name="steps[{{ $i }}][wa_template]" class="form-control form-control-sm" maxlength="100" value="{{ $s['wa_template'] ?? '' }}" placeholder="Approved template name, e.g. hearing_test_offer">
        <input type="text" name="steps[{{ $i }}][wa_language]" class="form-control form-control-sm" style="width:100px;" maxlength="10" value="{{ $s['wa_language'] ?? 'en' }}" placeholder="en / bn">
    </div>
</div>
