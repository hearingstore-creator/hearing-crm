<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class DoctorController extends Controller
{
    /**
     * Configure middleware to handle authorization for different doctor operations
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:doctor_list.index')->only(['index']);
        $this->middleware('permission:add_doctor.create')->only(['create', 'store']);
        $this->middleware('permission:doctor_list.edit')->only(['edit', 'update']);
        $this->middleware('permission:doctor_list.delete')->only(['destroy']);
    }

    /**
     * Display a listing of all doctors.
     *
     * @return View
     */
    public function index(): View
    {
        $query = Doctor::query();

        // Filter by name
        if (request()->filled('name')) {
            $query->where('name', 'like', '%' . request('name') . '%');
        }

        // Filter by contact number
        if (request()->filled('contact_number')) {
            $query->where('contact_number', 'like', '%' . request('contact_number') . '%');
        }

        // Filter by email
        if (request()->filled('email')) {
            $query->where('email', 'like', '%' . request('email') . '%');
        }

        // Filter by qualification
        if (request()->filled('qualification')) {
            $query->where('qualification', 'like', '%' . request('qualification') . '%');
        }

        // Filter by status
        if (request()->filled('status')) {
            $query->where('status', request('status'));
        }

        // Filter by date range
        if (request()->filled('start_date')) {
            $query->whereDate('created_at', '>=', request('start_date'));
        }

        if (request()->filled('end_date')) {
            $query->whereDate('created_at', '<=', request('end_date'));
        }

        $doctors = $query->with('ledgers')->orderBy('id', 'desc')->get();

        return view('admin.doctor.index', compact('doctors'));
    }

    /**
     * Show the form for creating a new doctor.
     *
     * @return View
     */
    public function create(): View
    {
        return view('admin.doctor.create');
    }

    /**
     * Store a newly created doctor in storage.
     *
     * @param Request $request HTTP request containing doctor data
     * @return RedirectResponse Redirects to doctor list or back with warning
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validate($request, [
            'name' => 'required|string|max:255',
            'contact_number' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'qualification' => 'nullable|string|max:255',
            'product_commission_percent' => 'nullable|numeric|min:0|max:100',
            'product_commission_amount' => 'nullable|numeric|min:0',
            'service_commission_percent' => 'nullable|numeric|min:0|max:100',
            'service_commission_amount' => 'nullable|numeric|min:0',
            'status' => 'nullable|boolean',
        ]);
        
        $requestAll = $request->all();

        if (auth()->user()->isVendor()) {
            $requestAll['vendor_id'] = auth()->id();
        }

        $doctor = new Doctor($requestAll);
        // Set default status if not provided
        $doctor->status = $request->has('status') ? 1 : 0;

        if ($doctor->save()) {
            return redirect('admin/doctor')->with('success', 'Saved Successfully');
        }

        return redirect()->back()->with('warning', 'Not Saved');
    }

    /**
     * Show the form for editing the specified doctor.
     *
     * @param int $id Doctor ID
     * @return View
     */
    public function edit(int $id): View
    {
        $doctor = Doctor::findOrFail($id);
        return view('admin.doctor.edit', compact('doctor'));
    }

    /**
     * Update the specified doctor in storage.
     *
     * @param Request $request HTTP request containing updated doctor data
     * @param int $id Doctor ID
     * @return RedirectResponse Redirects to doctor list or back with warning
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->validate($request, [
            'name' => 'required|string|max:255',
            'contact_number' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'qualification' => 'nullable|string|max:255',
            'product_commission_percent' => 'nullable|numeric|min:0|max:100',
            'product_commission_amount' => 'nullable|numeric|min:0',
            'service_commission_percent' => 'nullable|numeric|min:0|max:100',
            'service_commission_amount' => 'nullable|numeric|min:0',
            'status' => 'nullable|boolean',
        ]);

        $requestAll = $request->all();

        if (auth()->user()->isVendor()) {
            $requestAll['vendor_id'] = auth()->id();
        }
        
        $doctor = Doctor::findOrFail($id);
        $doctor->fill($requestAll);
        // Set default status if not provided
        $doctor->status = $request->has('status') ? 1 : 0;

        if ($doctor->save()) {
            return redirect('admin/doctor')->with('success', 'Updated Successfully');
        }

        return redirect()->back()->with('warning', 'Not Updated');
    }

    /**
     * Remove the specified doctor from storage.
     *
     * @param int $id Doctor ID
     * @return RedirectResponse Redirects to doctor list with success message
     */
    public function destroy(int $id): RedirectResponse
    {
        $doctor = Doctor::findOrFail($id);
        $doctor->delete();

        return redirect('/admin/doctor')->with('success', 'Deleted Successfully');
    }
}
