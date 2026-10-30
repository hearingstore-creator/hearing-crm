<?php

namespace App\Services;

use App\Enums\InquiryType;
use App\Models\Deal;
use App\Models\PipelineStage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

/**
 * What a stage needs before a deal may sit in it (pipeline_stages.required_fields).
 * One rule set for the Deals board (drag / move) and the Add Lead form.
 */
class StageGate
{
    /** Validated gate answers; $deal supplies already-known values (interest, value) when present. */
    public static function validate(Request $request, PipelineStage $to, ?Deal $deal = null): array
    {
        $required = $to->required_fields ?? [];
        $rules = [];
        $messages = [];

        if (in_array('inquiry_for', $required, true)) {
            if (!$request->filled('inquiry_for') && !empty($deal?->inquiry_for)) {
                $request->merge(['inquiry_for' => $deal->inquiry_for]);
            }
            $rules['inquiry_for'] = ['required', 'array', 'min:1'];
            $rules['inquiry_for.*'] = [new Enum(InquiryType::class)];
            $messages['inquiry_for.required'] = 'What does the patient need? Choose at least one interest.';
        }
        if (in_array('note', $required, true)) {
            $rules['note'] = ['required', 'string', 'max:1000'];
            $messages['note.required'] = 'Please add the counselling note.';
        }
        if (in_array('appointment_or_walkin', $required, true)) {
            $rules['walk_in'] = ['nullable', 'boolean'];
            $rules['appointment_at'] = ['nullable', 'required_unless:walk_in,1', 'date', 'after_or_equal:today'];
            $messages['appointment_at.required_unless'] = 'Book an appointment date or tick "Walk-in".';
        }
        if (in_array('trial_product', $required, true)) {
            $rules['trial_product'] = ['required', 'string', 'max:150'];
        }
        // how the in-session trial went (optional; feeds the trial reports)
        $rules['trial_result'] = ['nullable', \Illuminate\Validation\Rule::in(array_keys(\App\Models\DealTrial::RESULTS))];
        $rules['trial_product_id'] = ['nullable', 'integer', 'exists:products,id'];
        if (in_array('trial_return_date', $required, true)) {
            $rules['trial_return_date'] = ['required', 'date', 'after_or_equal:today'];
        }
        if (in_array('value', $required, true)) {
            if (!$request->filled('value') && $deal?->value > 0) {
                $request->merge(['value' => $deal->value]);
            }
            $rules['value'] = ['required', 'numeric', 'min:1', 'max:99999999'];
            $messages['value.required'] = 'Please enter the quoted value.';
        }

        // Optional extras that may be sent even when not required
        $rules += ['note' => ['nullable', 'string', 'max:1000'], 'value' => ['nullable', 'numeric', 'min:0', 'max:99999999']];

        $data = $request->validate($rules, $messages);
        if (!empty($data['walk_in'])) {
            unset($data['appointment_at']);
        }

        return $data;
    }
}
