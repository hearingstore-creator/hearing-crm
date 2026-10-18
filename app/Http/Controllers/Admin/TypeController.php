<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Type;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TypeController extends Controller
{
    /**
     * Configure middleware to handle authorization for different type operations
     * 
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:type.index')->only(['index']);
        $this->middleware('permission:type.create')->only(['create', 'store']);
        $this->middleware('permission:type.edit')->only(['edit', 'update']);
        $this->middleware('permission:type.delete')->only(['destroy']);
    }

    /**
     * Display a listing of the types.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        $types = Type::orderBy('id', 'desc')->get();
        return view('admin.type.index', compact('types'));
    }

    /**
     * Show the form for creating a new type.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function create(): View
    {
        return view('admin.type.create');
    }

    /**
     * Store a newly created type in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validate($request, [
            'type_name' => 'required|string|unique:types',
        ]);

        $type = new Type($request->all());

        if ($type->save()) {
            return redirect('admin/type')->with('success', 'Saved Successfully');
        }

        return redirect()->back()->with('warning', 'Not Saved');
    }

    /**
     * Show the form for editing the specified type.
     *
     * @param int $id
     * @return \Illuminate\Contracts\View\View
     */
    public function edit(int $id): View
    {
        $type = Type::findOrFail($id);
        return view('admin.type.edit', compact('type'));
    }

    /**
     * Update the specified type in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->validate($request, [
            'type_name' => 'required|unique:types,type_name,' . $id,
        ]);

        $type = Type::findOrFail($id);
        $type->fill($request->all());

        if ($type->save()) {
            return redirect('admin/type')->with('success', 'Updated Successfully');
        }

        return redirect()->back()->with('warning', 'Not Updated');
    }

    /**
     * Remove the specified type from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        $type = Type::findOrFail($id);

        if ($blocked = \App\Services\UsageGuard::blockMessage("type \"{$type->type_name}\"", \App\Services\UsageGuard::TYPE, $type->id)) {
            return redirect()->back()->with('warning', $blocked);
        }

        $type->delete();
        return redirect('/admin/type')->with('success', 'Deleted Successfully');
    }
}
