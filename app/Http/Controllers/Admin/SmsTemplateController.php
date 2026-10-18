<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\SmsTemplateCategory;
use App\Models\SmsTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SmsTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:templates.index')->only(['index']);
        $this->middleware('permission:templates.create')->only(['create', 'store']);
        $this->middleware('permission:templates.edit')->only(['edit', 'update']);
        $this->middleware('permission:templates.delete')->only(['destroy']);
    }

    public function index(): View
    {
        $templates = SmsTemplate::orderBy('id', 'desc')->get();
        $categories = SmsTemplateCategory::cases();
        return view('admin.sms-template.index', compact('templates', 'categories'));
    }

    public function create(): View
    {
        $categories = SmsTemplateCategory::cases();
        return view('admin.sms-template.create', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->validate($request, [
            'name' => 'required|string|unique:sms_templates,name',
            'category' => 'required|in:' . implode(',', array_column(SmsTemplateCategory::cases(), 'value')),
            'body' => 'required|string|max:1000',
            'is_active' => 'nullable',
        ]);

        $template = new SmsTemplate($request->only('name', 'category', 'body', 'is_active'));

        // Extract variables from body
        preg_match_all('/\{\{(\w+)\}\}/', $request->body, $matches);
        $template->variables = array_unique($matches[1] ?? []);

        if ($template->save()) {
            return redirect('admin/sms-templates')->with('success', 'Template Saved Successfully');
        }

        return redirect()->back()->with('warning', 'Not Saved');
    }

    public function edit(int $id): View
    {
        $template = SmsTemplate::findOrFail($id);
        $categories = SmsTemplateCategory::cases();
        return view('admin.sms-template.edit', compact('template', 'categories'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->validate($request, [
            'name' => 'required|string|unique:sms_templates,name,' . $id,
            'category' => 'required|in:' . implode(',', array_column(SmsTemplateCategory::cases(), 'value')),
            'body' => 'required|string|max:1000',
            'is_active' => 'nullable',
        ]);

        $template = SmsTemplate::findOrFail($id);
        $template->fill($request->only('name', 'category', 'body', 'is_active'));

        // Extract variables from body
        preg_match_all('/\{\{(\w+)\}\}/', $request->body, $matches);
        $template->variables = array_unique($matches[1] ?? []);

        if ($template->save()) {
            return redirect('admin/sms-templates')->with('success', 'Template Updated Successfully');
        }

        return redirect()->back()->with('warning', 'Not Updated');
    }

    public function destroy(int $id): RedirectResponse
    {
        $template = SmsTemplate::findOrFail($id);

        if ($blocked = \App\Services\UsageGuard::blockMessage("template \"{$template->name}\"", \App\Services\UsageGuard::SMS_TEMPLATE, $template->id)) {
            return redirect()->back()->with('warning', $blocked);
        }

        $template->delete();

        return redirect('/admin/sms-templates')->with('success', 'Deleted Successfully');
    }
}
