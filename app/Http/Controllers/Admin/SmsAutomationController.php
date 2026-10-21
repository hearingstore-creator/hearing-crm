<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\SmsAutomationTrigger;
use App\Models\SmsAutomation;
use App\Models\SmsTemplate;
use App\Services\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SmsAutomationController extends Controller
{
    public function __construct()
    {
        // SMS Centre is used by head office and Super Admin only
        $this->middleware(fn ($request, $next) => \App\Classes\Helper::headOfficeOnly($request, $next));
        $this->middleware('permission:sms_automation.index')->only(['index']);
        $this->middleware('permission:sms_automation.create')->only(['create', 'store']);
        $this->middleware('permission:sms_automation.edit')->only(['edit', 'update', 'toggle', 'mode']);
        $this->middleware('permission:sms_automation.delete')->only(['destroy']);
    }

    public function index()
    {
        return redirect('admin/sms#automation-tab');
    }

    public function create(): View
    {
        $triggers = SmsAutomationTrigger::selectable();
        $templates = SmsTemplate::where('is_active', true)->orderBy('name')->get();
        return view('admin.sms-automation.create', compact('triggers', 'templates'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->validate($request, [
            'name' => 'required|string|unique:sms_automations,name',
            'trigger_type' => 'required|in:' . implode(',', array_column(SmsAutomationTrigger::selectable(), 'value')),
            'message_body' => 'required|string|max:1000',
            'sms_template_id' => 'nullable|exists:sms_templates,id',
            'is_active' => 'nullable',
        ]);

        $automation = new SmsAutomation($request->only(
            'name', 'trigger_type', 'description', 'message_body', 'sms_template_id', 'is_active'
        ));

        if ($automation->save()) {
            return redirect('admin/sms#automation-tab')->with('success', 'Automation Saved Successfully');
        }

        return redirect()->back()->with('warning', 'Not Saved');
    }

    public function edit(int $id): View
    {
        $automation = SmsAutomation::findOrFail($id);
        $triggers = array_filter(SmsAutomationTrigger::cases(),
            fn ($t) => $t !== SmsAutomationTrigger::POINTS_MILESTONE || $automation->trigger_type === $t->value);
        $templates = SmsTemplate::where('is_active', true)->orderBy('name')->get();
        return view('admin.sms-automation.edit', compact('automation', 'triggers', 'templates'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->validate($request, [
            'name' => 'required|string|unique:sms_automations,name,' . $id,
            'trigger_type' => 'required|in:' . implode(',', array_column(SmsAutomationTrigger::cases(), 'value')),
            'message_body' => 'required|string|max:1000',
            'sms_template_id' => 'nullable|exists:sms_templates,id',
            'is_active' => 'nullable',
        ]);

        $automation = SmsAutomation::findOrFail($id);
        $automation->fill($request->only(
            'name', 'trigger_type', 'description', 'message_body', 'sms_template_id', 'is_active'
        ));

        if ($automation->save()) {
            return redirect('admin/sms#automation-tab')->with('success', 'Automation Updated Successfully');
        }

        return redirect()->back()->with('warning', 'Not Updated');
    }

    public function destroy(int $id): RedirectResponse
    {
        $automation = SmsAutomation::findOrFail($id);
        $automation->delete();

        return redirect('/admin/sms#automation-tab')->with('success', 'Deleted Successfully');
    }

    /**
     * The cost-control switch for every automation: all / essential only / off, and the price used for cost figures.
     */
    public function mode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mode' => 'required|in:all,essential,off',
            'cost_per_sms' => 'nullable|numeric|min:0|max:100',
        ]);

        $setting = \App\Models\Setting::findOrFail(1);
        $setting->sms_automation_mode = $data['mode'];
        if (isset($data['cost_per_sms'])) {
            $setting->sms_cost_per_sms = $data['cost_per_sms'];
        }
        $setting->save();

        $label = ['all' => 'All active automations will send.', 'essential' => 'Only essential messages (appointments, tickets, repair and pickup) will send.', 'off' => 'All automatic SMS are paused.'][$data['mode']];

        return redirect('admin/sms#automation-tab')->with('success', 'SMS mode saved. ' . $label);
    }

    /**
     * Toggle automation active status via AJAX
     */
    public function toggle(Request $request, int $id)
    {
        $automation = SmsAutomation::findOrFail($id);
        $automation->is_active = !$automation->is_active;
        $automation->save();

        return response()->json([
            'success' => true,
            'is_active' => $automation->is_active,
        ]);
    }
}
