<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class TemplateController extends Controller
{
    /**
     * Configure middleware to handle authorization for different template operations
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:template.index')->only(['index']);
        $this->middleware('permission:template.create')->only(['create', 'store']);
        $this->middleware('permission:template.edit')->only(['edit', 'update']);
        $this->middleware('permission:template.delete')->only(['destroy']);
    }

    /**
     * Display a listing of all templates.
     *
     * @return View
     */
    public function index(): View
    {
        $templates = Template::orderBy('id', 'desc')->get();
        return view('admin.template.index', compact('templates'));
    }

    /**
     * Show the form for creating a new template.
     *
     * @return View
     */
    public function create(): View
    {
        return view('admin.template.create');
    }

    /**
     * Store a newly created template in storage.
     *
     * @param Request $request HTTP request containing template data
     * @return RedirectResponse Redirects to template list or back with warning
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validate($request, [
            'title'  => 'required|string|unique:templates',
            'content' => 'nullable|string',
        ]);

        $template = new Template($request->all());

        if ($template->save()) {
            return redirect('admin/template')->with('success', 'Saved Successfully');
        }

        return redirect()->back()->with('warning', 'Not Saved');
    }

    /**
     * Show the form for editing the specified template.
     *
     * @param int $id Template ID
     * @return View
     */
    public function edit(int $id): View
    {
        $template = Template::findOrFail($id);
        return view('admin.template.edit', compact('template'));
    }

    /**
     * Update the specified template in storage.
     *
     * @param Request $request HTTP request containing updated template data
     * @param int $id Template ID
     * @return RedirectResponse Redirects to template list or back with warning
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->validate($request, [
            'title'  => 'required|unique:templates,title,'.$id,
            'content' => 'nullable|string',
        ]);

        $template = Template::findOrFail($id);
        $template->fill($request->all());

        if ($template->save()) {
            return redirect('admin/template')->with('success', 'Updated Successfully');
        }

        return redirect()->back()->with('warning', 'Not Updated');
    }

    /**
     * Remove the specified template from storage.
     *
     * @param int $id Template ID
     * @return RedirectResponse Redirects to template list with success message
     */
    public function destroy(int $id): RedirectResponse
    {
        $template = Template::findOrFail($id);
        $template->delete();

        return redirect('/admin/template')->with('success', 'Deleted Successfully');
    }
}
