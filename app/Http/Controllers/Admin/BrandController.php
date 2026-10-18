<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    /**
     * Configure middleware to handle authorization for different brand operations
     * 
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:brand.index')->only(['index']);
        $this->middleware('permission:brand.create')->only(['create', 'store']);
        $this->middleware('permission:brand.edit')->only(['edit', 'update']);
        $this->middleware('permission:brand.delete')->only(['destroy']);
    }

    /**
     * Display a listing of the brands.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        $brands = Brand::orderBy('id', 'desc')->get();
        return view('admin.brand.index', compact('brands'));
    }

    /**
     * Show the form for creating a new brand.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function create(): View
    {
        return view('admin.brand.create');
    }

    /**
     * Store a newly created brand in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validate($request, [
            'brand_name' => 'required|string|unique:brands',
        ]);

        $brand = new Brand($request->all());

        if ($brand->save()) {
            return redirect('admin/brand')->with('success', 'Saved Successfully');
        }

        return redirect()->back()->with('warning', 'Not Saved');
    }

    /**
     * Show the form for editing the specified brand.
     *
     * @param int $id
     * @return \Illuminate\Contracts\View\View
     */
    public function edit(int $id): View
    {
        $brand = Brand::findOrFail($id);
        return view('admin.brand.edit', compact('brand'));
    }

    /**
     * Update the specified brand in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->validate($request, [
            'brand_name' => 'required|unique:brands,brand_name,' . $id,
        ]);

        $brand = Brand::findOrFail($id);
        $brand->fill($request->all());

        if ($brand->save()) {
            return redirect('admin/brand')->with('success', 'Updated Successfully');
        }

        return redirect()->back()->with('warning', 'Not Updated');
    }

    /**
     * Remove the specified brand from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        $brand = Brand::findOrFail($id);

        if ($blocked = \App\Services\UsageGuard::blockMessage("brand \"{$brand->brand_name}\"", \App\Services\UsageGuard::BRAND, $brand->id)) {
            return redirect()->back()->with('warning', $blocked);
        }

        $brand->delete();
        return redirect('/admin/brand')->with('success', 'Deleted Successfully');
    }
}
