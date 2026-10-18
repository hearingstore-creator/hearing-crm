<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\ServiceCategory;
use App\Models\AppointmentService;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Enum;

class AppointmentServiceController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:service_list.index')->only(['index']);
        $this->middleware('permission:service_list.create')->only(['create', 'store']);
        $this->middleware('permission:service_list.edit')->only(['edit', 'update']);
        $this->middleware('permission:service_list.delete')->only(['destroy']);
    }

    public function index(): View
    {
        $data['services'] = AppointmentService::orderBy('id', 'desc')->get();
        $data['serviceCategories'] = ServiceCategory::cases();

        return view('admin.appointment_service.index', $data);
    }

    public function create(): View
    {
        $data['serviceCategories'] = ServiceCategory::cases();

        return view('admin.appointment_service.create', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->validate($request, [
            'name'                      => 'required|string|max:255|unique:appointment_services,name',
            'description'               => 'nullable|string',
            'category'                  => ['required', new Enum(ServiceCategory::class)],
            'duration_minutes'          => 'required|integer|min:5|max:480',
            'buffer_after_minutes'      => 'nullable|integer|min:0|max:120',
            'requires_resource'         => 'nullable|boolean',
            'resource_type'             => 'nullable|string|max:50',
            'is_online_booking_enabled' => 'nullable|boolean',
            'color_hex'                 => 'nullable|string|max:20',
            'max_per_day'               => 'nullable|integer|min:1|max:100',
            'status'                    => 'nullable|in:0,1',
        ]);

        $requiresResource = $request->boolean('requires_resource');

        AppointmentService::create([
            'name'                      => $request->name,
            'description'               => $request->description,
            'category'                  => $request->category,
            'duration_minutes'          => (int) ($request->duration_minutes ?: 45),
            'buffer_after_minutes'      => (int) ($request->buffer_after_minutes !== null ? $request->buffer_after_minutes : 10),
            'requires_resource'         => $requiresResource,
            'resource_type'             => $requiresResource ? ($request->resource_type ?: 'sound_booth') : null,
            'is_online_booking_enabled' => $request->boolean('is_online_booking_enabled'),
            'color_hex'                 => $request->color_hex ?: '#0284c7',
            'max_per_day'               => $request->max_per_day ?? 10,
            'status'                    => $request->status ?? 1,
        ]);

        return redirect('admin/appointment-services')->with('success', 'Saved Successfully');
    }

    public function edit(int $id): View
    {
        $data['service'] = AppointmentService::findOrFail($id);
        $data['serviceCategories'] = ServiceCategory::cases();

        return view('admin.appointment_service.edit', $data);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->validate($request, [
            'name'                      => 'required|string|max:255|unique:appointment_services,name,' . $id,
            'description'               => 'nullable|string',
            'category'                  => ['required', new Enum(ServiceCategory::class)],
            'duration_minutes'          => 'required|integer|min:5|max:480',
            'buffer_after_minutes'      => 'nullable|integer|min:0|max:120',
            'requires_resource'         => 'nullable|boolean',
            'resource_type'             => 'nullable|string|max:50',
            'is_online_booking_enabled' => 'nullable|boolean',
            'color_hex'                 => 'nullable|string|max:20',
            'max_per_day'               => 'nullable|integer|min:1|max:100',
            'status'                    => 'nullable|in:0,1',
        ]);

        $service = AppointmentService::findOrFail($id);
        $requiresResource = $request->boolean('requires_resource');

        $service->update([
            'name'                      => $request->name,
            'description'               => $request->description,
            'category'                  => $request->category,
            'duration_minutes'          => (int) ($request->duration_minutes ?: 45),
            'buffer_after_minutes'      => (int) ($request->buffer_after_minutes !== null ? $request->buffer_after_minutes : 10),
            'requires_resource'         => $requiresResource,
            'resource_type'             => $requiresResource ? ($request->resource_type ?: 'sound_booth') : null,
            'is_online_booking_enabled' => $request->boolean('is_online_booking_enabled'),
            'color_hex'                 => $request->color_hex ?: '#0284c7',
            'max_per_day'               => $request->max_per_day ?? 10,
            'status'                    => $request->status ?? 1,
        ]);

        return redirect('admin/appointment-services')->with('success', 'Updated Successfully');
    }

    public function destroy(int $id): RedirectResponse
    {
        AppointmentService::findOrFail($id)->delete();

        return redirect('admin/appointment-services')->with('success', 'Deleted Successfully');
    }
}
