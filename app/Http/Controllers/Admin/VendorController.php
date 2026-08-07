<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    /**
     * Configure middleware to handle authorization for different vendor operations
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:add_vendor.create')->only(['create', 'store']);
        $this->middleware('permission:vendor_list.index')->only(['index']);
        $this->middleware('permission:vendor_list.edit')->only(['edit', 'update']);
        $this->middleware('permission:vendor_list.delete')->only(['destroy']);
    }

    /**
     * Display a listing of the vendors.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        $query = User::where('type', 'vendor')->orderBy('id', 'desc');

        // Apply filters
        if (request()->has('name') && request()->name != '') {
            $query->where('name', 'like', '%' . request()->name . '%');
        }

        if (request()->has('email') && request()->email != '') {
            $query->where('email', 'like', '%' . request()->email . '%');
        }

        if (request()->has('mobile_no') && request()->mobile_no != '') {
            $query->where('mobile_no', 'like', '%' . request()->mobile_no . '%');
        }

        $data['vendors'] = $query->get();
        return view('admin.vendor.index', $data);
    }

    /**
     * Show the form for creating a new vendor.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function create(): View
    {
        return view('admin.vendor.create');
    }

    /**
     * Store a newly created vendor in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validate($request, [
            'name' => ['required', 'string'],
            'mobile_no' => ['required', 'string'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $vendor = new User();
        $vendor->name = $request->name;
        $vendor->mobile_no = $request->mobile_no;
        $vendor->email = $request->email;
        $vendor->address = $request->address;
        $vendor->type = 'vendor';
        $vendor->password = bcrypt($request->password);
        $vendor->status = $request->status ?? 1;

        if ($vendor->save()) {
            return redirect('admin/vendors')->with('success', 'Saved Successfully');
        }

        return redirect()->back()->with('warning', 'Not Saved');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function show(int $id): void
    {
        // Not implemented
    }

    /**
     * Show the form for editing the specified vendor.
     *
     * @param  int  $id
     * @return \Illuminate\Contracts\View\View
     */
    public function edit(int $id): View
    {
        $vendor = User::where('type', 'vendor')->findOrFail($id);

        return view('admin.vendor.edit', compact('vendor'));
    }

    /**
     * Update the specified vendor in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->validate($request, [
            'name' => ['required', 'string'],
            'mobile_no' => ['required', 'string'],
            'email' => ['required', 'email', 'unique:users,email,' . $id],
        ]);

        $vendor = User::where('type', 'vendor')->findOrFail($id);
        $vendor->name = $request->name;
        $vendor->mobile_no = $request->mobile_no;
        $vendor->email = $request->email;
        $vendor->address = $request->address;

        if ($request->filled('password')) {
            $vendor->password = bcrypt($request->password);
        }

        $vendor->status = $request->status ?? 1;

        if ($vendor->save()) {
            return redirect('admin/vendors')->with('success', 'Updated Successfully');
        }

        return redirect()->back()->with('warning', 'Not Updated');
    }

    /**
     * Remove the specified vendor from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        $vendor = User::where('type', 'vendor')->findOrFail($id);
        $vendor->delete();

        return redirect('/admin/vendors')->with('success', 'Deleted Successfully');
    }
}
