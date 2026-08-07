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
        $this->middleware('permission:add_service.create')->only(['create', 'store']);
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
            'name'        => 'required|string|max:255|unique:appointment_services,name',
            'description' => 'nullable|string',
            'category'    => ['required', new Enum(ServiceCategory::class)],
            'max_per_day' => 'nullable|integer|min:1|max:100',
            'status'      => 'nullable|in:0,1',
        ]);

        AppointmentService::create([
            'name'        => $request->name,
            'description' => $request->description,
            'category'    => $request->category,
            'max_per_day' => $request->max_per_day ?? 10,
            'status'     => $request->status ?? 1,
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
            'name'        => 'required|string|max:255|unique:appointment_services,name,' . $id,
            'description' => 'nullable|string',
            'category'    => ['required', new Enum(ServiceCategory::class)],
            'max_per_day' => 'nullable|integer|min:1|max:100',
            'status'      => 'nullable|in:0,1',
        ]);

        $service = AppointmentService::findOrFail($id);
        $service->fill($request->all());
        $service->save();

        return redirect('admin/appointment-services')->with('success', 'Updated Successfully');
    }

    public function destroy(int $id): RedirectResponse
    {
        AppointmentService::findOrFail($id)->delete();

        return redirect('admin/appointment-services')->with('success', 'Deleted Successfully');
    }
}
