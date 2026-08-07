<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DesignationController extends Controller
{
    /**
     * Configure middleware to handle authorization for different designation operations
     * 
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:designation.index')->only(['index']);
        $this->middleware('permission:designation.create')->only(['create', 'store']);
        $this->middleware('permission:designation.edit')->only(['edit', 'update']);
        $this->middleware('permission:designation.delete')->only(['destroy']);
    }

    /**
     * Display a listing of the designation.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        $designations = Designation::orderBy('id', 'desc')->get();
        return view('admin.designation.index', compact('designations'));
    }

    /**
     * Show the form for creating a new designation.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function create(): View
    {
        return view('admin.designation.create');
    }

    /**
     * Store a newly created designation in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validate($request, [
            'designation_name' => 'required|string|unique:designations',
        ]);

        $designation = new Designation($request->all());

        if ($designation->save()) {
            return redirect('admin/designation')->with('success', 'Saved Successfully');
        }

        return redirect()->back()->with('warning', 'Not Saved');
    }

    /**
     * Show the form for editing the specified designation.
     *
     * @param  int  $id
     * @return \Illuminate\Contracts\View\View
     */
    public function edit(int $id): View
    {
        $designation = Designation::findOrFail($id);
        return view('admin.designation.edit', compact('designation'));
    }

    /**
     * Update the specified designation in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->validate($request, [
            'designation_name' => 'required|unique:designations,designation_name,' . $id,
        ]);

        $designation = Designation::findOrFail($id);
        $designation->fill($request->all());

        if ($designation->save()) {
            return redirect('admin/designation')->with('success', 'Updated Successfully');
        }

        return redirect()->back()->with('warning', 'Not Updated');
    }

    /**
     * Remove the specified designation from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        $designation = Designation::findOrFail($id);
        $designation->delete();

        return redirect('/admin/designation')->with('success', 'Deleted Successfully');
    }
}
