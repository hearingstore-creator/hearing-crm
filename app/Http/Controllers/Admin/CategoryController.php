<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class CategoryController extends Controller
{
    /**
     * Configure middleware to handle authorization for different category operations
     * 
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:category.index')->only(['index']);
        $this->middleware('permission:category.create')->only(['create', 'store']);
        $this->middleware('permission:category.edit')->only(['edit', 'update']);
        $this->middleware('permission:category.delete')->only(['destroy']);
    }

    /**
     * Display a listing of all categories.
     *
     * @return View
     */
    public function index()
    {
        $categories = Category::orderBy('id', 'desc')->get();
        return view('admin.category.index', compact('categories'));
    }

    /**
     * Show the form for creating a new category.
     *
     * @return View
     */
    public function create(): View
    {
        return view('admin.category.create');
    }

    /**
     * Store a newly created category in storage.
     *
     * @param Request $request HTTP request containing category data
     * @return RedirectResponse Redirects to category list or back with warning
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'category_name'  => 'required|string|unique:categories',
        ]);
        
        $category = new Category($request->all());

        if ($category->save()) {
            return redirect('admin/category')->with('success', 'Saved Successfully');
        }

        return redirect()->back()->with('warning', 'Not Saved');
    }



    /**
     * Show the form for editing the specified category.
     *
     * @param int $id Category ID
     * @return View
     */
    public function edit(int $id): View
    {
        $category = Category::findOrFail($id);
        return view('admin.category.edit', compact('category'));
    }

    /**
     * Update the specified category in storage.
     *
     * @param Request $request HTTP request containing updated category data
     * @param int $id Category ID
     * @return RedirectResponse Redirects to category list or back with warning
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->validate($request, [
            'category_name'  => 'required|unique:categories,category_name,'.$id,
        ]);
        
        $category = Category::findOrFail($id);
        $category->fill($request->all());

        if ($category->save()) {
            return redirect('admin/category')->with('success', 'Updated Successfully');
        }

        return redirect()->back()->with('warning', 'Not Updated');
    }

    /**
     * Remove the specified category from storage.
     *
     * @param int $id Category ID
     * @return RedirectResponse Redirects to category list with success message
     */
    public function destroy(int $id): RedirectResponse
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return redirect('/admin/category')->with('success', 'Deleted Successfully');
    }
}
