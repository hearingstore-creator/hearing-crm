<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeafSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeafSettingController extends Controller
{
    /**
     * Configure middleware to handle authorization for different leaf setting operations
     * 
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:leaf_settings.index')->only(['index']);
        $this->middleware('permission:leaf_settings.create')->only(['create', 'store']);
        $this->middleware('permission:leaf_settings.edit')->only(['edit', 'update']);
        $this->middleware('permission:leaf_settings.delete')->only(['destroy']);
    }

    /**
     * Display a listing of the leaf settings.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        $leafSettings = LeafSetting::orderBy('id', 'desc')->get();

        return view('admin.leaf_settings.index', compact('leafSettings'));
    }

    /**
     * Show the form for creating a new leaf setting.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function create(): View
    {
        return view('admin.leaf_settings.create');
    }

    /**
     * Store a newly created leaf setting in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validate($request, [
            'leaf_type' => 'required|string|unique:leaf_settings',
            'total_number_per_box' => 'required|integer',
        ]);

        $leafSetting = new LeafSetting($request->all());

        if ($leafSetting->save()) {
            return redirect('admin/leaf-settings')->with('success', 'Saved Successfully');
        }

        return redirect()->back()->with('warning', 'Not Saved');
    }

    /**
     * Show the form for editing the specified leaf setting.
     *
     * @param  int  $id
     * @return \Illuminate\Contracts\View\View
     */
    public function edit(int $id): View
    {
        $leafSettings = LeafSetting::findOrFail($id);

        return view('admin.leaf_settings.edit', compact('leafSettings'));
    }

    /**
     * Update the specified leaf setting in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->validate($request, [
            'leaf_type' => 'required|unique:leaf_settings,leaf_type,' . $id,
            'total_number_per_box' => 'required|integer',
        ]);

        $leafSetting = LeafSetting::findOrFail($id);
        $leafSetting->fill($request->all());

        if ($leafSetting->save()) {
            return redirect('admin/leaf-settings')->with('success', 'Updated Successfully');
        }

        return redirect()->back()->with('warning', 'Not Updated');
    }

    /**
     * Remove the specified leaf setting from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        $leafSetting = LeafSetting::findOrFail($id);
        $leafSetting->delete();

        return redirect('/admin/leaf-settings')->with('success', 'Deleted Successfully');
    }
}
