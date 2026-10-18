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

        $vendors = $query->get();
        $ids = $vendors->pluck('id');

        // Per-branch figures for the list (one grouped query per table, soft-deleted rows ignored)
        $base = function (string $table) use ($ids) {
            $q = \DB::table($table)->whereIn('vendor_id', $ids)->groupBy('vendor_id');
            return \Schema::hasColumn($table, 'deleted_at') ? $q->whereNull('deleted_at') : $q;
        };
        $count = fn (string $table, ?callable $scope = null) => $base($table)
            ->when($scope, $scope)
            ->selectRaw('vendor_id, COUNT(*) c')
            ->pluck('c', 'vendor_id');

        $monthStart = now()->startOfMonth()->toDateString();
        $data['stats'] = [
            'staff' => $count('users', fn ($q) => $q->where('type', '!=', 'vendor')),
            'leads' => $count('leads'),
            'sales_month' => $base('sale_masters')->where('date', '>=', $monthStart)
                ->selectRaw('vendor_id, SUM(total_amount) s')->pluck('s', 'vendor_id'),
            'due' => $base('sale_masters')
                ->selectRaw('vendor_id, SUM(total_amount - paid) d')->pluck('d', 'vendor_id'),
        ];

        $data['summary'] = [
            'total' => $vendors->count(),
            'active' => $vendors->where('status', 1)->count(),
            'sales_month' => (float) collect($data['stats']['sales_month'])->sum(),
            'due' => (float) collect($data['stats']['due'])->filter(fn ($d) => $d > 0)->sum(),
        ];

        $data['vendors'] = $vendors;
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
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $vendor = new User();
        $vendor->name = $request->name;
        $vendor->mobile_no = $request->mobile_no;
        $vendor->email = $request->email;
        $vendor->address = $request->address;
        $vendor->type = 'vendor';
        $vendor->password = bcrypt($request->password);
        $vendor->status = $request->status ?? 1;

        if ($request->hasFile('logo')) {
            $vendor->logo = \App\Classes\Helper::fileUpload($request->file('logo'), 'file/vendor/');
        }

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
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
        ]);

        $vendor = User::where('type', 'vendor')->findOrFail($id);
        $vendor->name = $request->name;
        $vendor->mobile_no = $request->mobile_no;
        $vendor->email = $request->email;
        $vendor->address = $request->address;

        if ($request->hasFile('logo')) {
            $this->deleteLogoFile($vendor->logo);
            $vendor->logo = \App\Classes\Helper::fileUpload($request->file('logo'), 'file/vendor/');
        } elseif ($request->boolean('remove_logo')) {
            $this->deleteLogoFile($vendor->logo);
            $vendor->logo = null;
        }

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

        // A branch owns leads, sales, stock, staff... deleting it would orphan them.
        $linked = $this->linkedRecordCounts($vendor->id);
        if (!empty($linked)) {
            $summary = collect($linked)->map(fn ($c, $label) => "$c $label")->implode(', ');

            return redirect('/admin/vendors')->with('warning', "Cannot delete {$vendor->name}: it still has $summary. Set it to Inactive instead.");
        }

        $vendor->delete();

        return redirect('/admin/vendors')->with('success', 'Deleted Successfully');
    }

    /**
     * Count records that belong to a branch, keyed by a human label (only non-zero counts).
     *
     * @param int $vendorId
     * @return array<string,int>
     */
    private function linkedRecordCounts(int $vendorId): array
    {
        $tables = [
            'leads' => 'leads', 'deals' => 'deals', 'sale_masters' => 'sales',
            'purchase_masters' => 'purchases', 'stocks' => 'stock entries', 'batches' => 'batches',
            'suppliers' => 'suppliers', 'doctors' => 'doctors', 'ledgers' => 'ledger entries',
            'lead_capture_forms' => 'lead forms', 'users' => 'staff',
        ];

        $counts = [];
        foreach ($tables as $table => $label) {
            if (!\Schema::hasTable($table) || !\Schema::hasColumn($table, 'vendor_id')) {
                continue;
            }
            $q = \DB::table($table)->where('vendor_id', $vendorId);
            if (\Schema::hasColumn($table, 'deleted_at')) {
                $q->whereNull('deleted_at');
            }
            if ($c = $q->count()) {
                $counts[$label] = $c;
            }
        }

        return $counts;
    }

    /**
     * Delete a previously uploaded vendor logo file (only inside public/file/vendor/).
     *
     * @param string|null $path
     * @return void
     */
    private function deleteLogoFile(?string $path): void
    {
        if ($path && str_starts_with($path, 'file/vendor/') && file_exists(public_path($path))) {
            @unlink(public_path($path));
        }
    }
}
