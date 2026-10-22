<?php

namespace App\Http\Controllers;

use App\Models\NpsResponse;
use App\Models\Setting;
use App\Services\RetentionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The one-question satisfaction survey a patient opens from an SMS link (/s/{token}). */
class PublicSurveyController extends Controller
{
    public function show(string $token): View
    {
        return view('public.survey', ['survey' => $this->find($token), 'setting' => Setting::find(1)]);
    }

    public function answer(Request $request, string $token, RetentionService $retention): RedirectResponse
    {
        $survey = $this->find($token);
        if ($survey->answered_at) {
            return back()->with('done', 'Thank you – your answer was already saved.');
        }
        $data = $request->validate(['score' => ['required', 'integer', 'min:0', 'max:10'], 'comment' => ['nullable', 'string', 'max:1000']],
            ['score.required' => 'Please choose a number from 0 to 10.']);
        $retention->answer($survey, (int) $data['score'], $data['comment'] ?? null);

        return back()->with('done', 'Thank you for your answer!');
    }

    private function find(string $token): NpsResponse
    {
        abort_unless(strlen($token) >= 32, 404);

        return NpsResponse::where('token', $token)->firstOrFail();
    }
}
