<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\SupportSetting;
use Illuminate\Http\Request;

/** Support rules: hours allowed per ticket priority, days of silence before a reminder, days after Solved before closing. Head office only. */
class SupportSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware(fn ($request, $next) => Helper::headOfficeOnly($request, $next));
        $this->middleware('permission:support_settings.index')->only(['index']);
        $this->middleware('permission:support_settings.edit')->only(['update']);
    }

    public function index()
    {
        return view('admin.support.settings', ['setting' => SupportSetting::current()]);
    }

    public function update(Request $request)
    {
        $rule = ['required', 'integer', 'between:1,2000'];
        $data = $request->validate([
            'sla_critical_hours' => $rule, 'sla_high_hours' => $rule, 'sla_medium_hours' => $rule, 'sla_low_hours' => $rule,
            'stale_days'      => ['required', 'integer', 'between:1,60'],
            'auto_close_days' => ['required', 'integer', 'between:1,60'],
        ], [
            'between' => 'Use a number between :min and :max.',
        ]);

        SupportSetting::current()->update($data);
        SupportSetting::forget();

        return redirect()->route('support.settings')->with('success', 'Support settings saved. New deadlines apply to every open ticket straight away.');
    }
}
