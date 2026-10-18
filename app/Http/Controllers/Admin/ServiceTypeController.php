<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceType;
use App\Models\AppointmentService;
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
    public function index(): View
    {
        $serviceTypes = ServiceType::with('appointmentService')->orderBy('id', 'desc')->get();
        return view('admin.service_type.index', compact('serviceTypes'));
    }

    /**
     * Show the form for creating a new service type.
     *
     * @return View
     */
    public function create(): View
    {
        $appointmentServices = AppointmentService::where('status', 1)->orderBy('name')->get();
        return view('admin.service_type.create', compact('appointmentServices'));
    }

    /**
     * Store a newly created service type in storage.
     *
     * @param Request $request HTTP request containing service type data
     * @return RedirectResponse Redirects to service type list or back with warning
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validate($request, [
            'service_type_name'      => 'required|string|unique:service_types',
            'booking_type'           => 'required|string|in:walk_in,appointment_required,either',
            'appointment_service_id' => 'nullable|integer|exists:appointment_services,id',
            'price'                  => 'nullable|numeric|min:0',
        ]);

        $serviceType = new ServiceType($request->all());

        if ($serviceType->save()) {
            return redirect('admin/service-type')->with('success', 'Service saved successfully.');
        }

        return redirect()->back()->with('warning', 'Could not save service type.')->withInput();
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
        $appointmentServices = AppointmentService::where('status', 1)->orderBy('name')->get();
        return view('admin.service_type.edit', compact('serviceType', 'appointmentServices'));
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
            'service_type_name'      => 'required|unique:service_types,service_type_name,'.$id,
            'booking_type'           => 'required|string|in:walk_in,appointment_required,either',
            'appointment_service_id' => 'nullable|integer|exists:appointment_services,id',
            'price'                  => 'nullable|numeric|min:0',
        ]);

        $serviceType = ServiceType::findOrFail($id);
        $serviceType->fill($request->all());

        if ($serviceType->save()) {
            return redirect('admin/service-type')->with('success', 'Service updated successfully.');
        }

        return redirect()->back()->with('warning', 'Could not update service type.')->withInput();
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

        return redirect('/admin/service-type')->with('success', 'Service deleted successfully.');
    }
}
