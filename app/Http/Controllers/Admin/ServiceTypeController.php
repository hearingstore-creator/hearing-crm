<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceType;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class ServiceTypeController extends Controller
{
    /**
     * Configure middleware to handle authorization for different service type operations
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:service_type.index')->only(['index']);
        $this->middleware('permission:service_type.create')->only(['create', 'store']);
        $this->middleware('permission:service_type.edit')->only(['edit', 'update']);
        $this->middleware('permission:service_type.delete')->only(['destroy']);
    }

    /**
     * Display a listing of all service types.
     *
     * @return View
     */
    public function index()
    {
        $serviceTypes = ServiceType::orderBy('id', 'desc')->get();
        return view('admin.service_type.index', compact('serviceTypes'));
    }

    /**
     * Show the form for creating a new service type.
     *
     * @return View
     */
    public function create(): View
    {
        return view('admin.service_type.create');
    }

    /**
     * Store a newly created service type in storage.
     *
     * @param Request $request HTTP request containing service type data
     * @return RedirectResponse Redirects to service type list or back with warning
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'service_type_name'  => 'required|string|unique:service_types',
            'price' => 'nullable|numeric|min:0',
        ]);

        $serviceType = new ServiceType($request->all());

        if ($serviceType->save()) {
            return redirect('admin/service-type')->with('success', 'Saved Successfully');
        }

        return redirect()->back()->with('warning', 'Not Saved');
    }

    /**
     * Show the form for editing the specified service type.
     *
     * @param int $id Service Type ID
     * @return View
     */
    public function edit(int $id): View
    {
        $serviceType = ServiceType::findOrFail($id);
        return view('admin.service_type.edit', compact('serviceType'));
    }

    /**
     * Update the specified service type in storage.
     *
     * @param Request $request HTTP request containing updated service type data
     * @param int $id Service Type ID
     * @return RedirectResponse Redirects to service type list or back with warning
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->validate($request, [
            'service_type_name'  => 'required|unique:service_types,service_type_name,'.$id,
            'price' => 'nullable|numeric|min:0',
        ]);

        $serviceType = ServiceType::findOrFail($id);
        $serviceType->fill($request->all());

        if ($serviceType->save()) {
            return redirect('admin/service-type')->with('success', 'Updated Successfully');
        }

        return redirect()->back()->with('warning', 'Not Updated');
    }

    /**
     * Remove the specified service type from storage.
     *
     * @param int $id Service Type ID
     * @return RedirectResponse Redirects to service type list with success message
     */
    public function destroy(int $id): RedirectResponse
    {
        $serviceType = ServiceType::findOrFail($id);
        $serviceType->delete();

        return redirect('/admin/service-type')->with('success', 'Deleted Successfully');
    }
}
