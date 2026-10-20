<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Doctor;
use App\Models\DoctorCategoryCommission;
use App\Models\DoctorChamber;
use App\Models\DoctorProductCommission;
use App\Models\Product;
use App\Models\DoctorServiceCommission;
use App\Models\ServiceType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

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
        $this->middleware('permission:doctor_list.index|add_sale.create|sale_list.edit|proposals.create')->only(['commissionRules']);
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

        // Filter by referrer_type
        if (request()->filled('referrer_type')) {
            $query->where('referrer_type', request('referrer_type'));
        }

        // Filter by date range
        if (request()->filled('start_date')) {
            $query->whereDate('created_at', '>=', request('start_date'));
        }

        if (request()->filled('end_date')) {
            $query->whereDate('created_at', '<=', request('end_date'));
        }

        // Per-doctor balance (credit = commission earned, debit = paid)
        $balances = \App\Models\DoctorLedger::selectRaw('doctor_id, SUM(credit) as cr, SUM(debit) as dr')
            ->groupBy('doctor_id')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->doctor_id => (float) $r->cr - (float) $r->dr]);

        if (request()->boolean('has_due')) {
            $query->whereIn('id', $balances->filter(fn ($b) => $b > 0)->keys());
        }

        $doctors = $query->with(['ledgers', 'chambers', 'categoryCommissions.category', 'serviceCommissions.serviceType'])
            ->orderBy('id', 'desc')
            ->get();

        $monthStart = now()->startOfMonth();
        $referralSales = \App\Models\SaleMaster::whereNotNull('reference_by')
            ->where('reference_by', '!=', '')
            ->where('created_at', '>=', $monthStart);

        $doctorStats = [
            'total'             => Doctor::count(),
            'active'            => Doctor::where('status', 1)->count(),
            'due'               => $balances->filter(fn ($b) => $b > 0)->sum(),
            'due_count'         => $balances->filter(fn ($b) => $b > 0)->count(),
            'month_sales'       => (float) (clone $referralSales)->sum('total_amount'),
            'month_sales_count' => (clone $referralSales)->count(),
            'month_commission'  => (float) \App\Models\DoctorLedger::where('created_at', '>=', $monthStart)->sum('credit'),
            'month_paid'        => (float) \App\Models\DoctorLedger::where('created_at', '>=', $monthStart)->sum('debit'),
        ];

        return view('admin.doctor.index', compact('doctors', 'doctorStats'));
    }

    /**
     * Show the form for creating a new doctor.
     *
     * @return View
     */
    public function create(): View
    {
        $categories = Category::where('status', 1)->orderBy('category_name')->get();
        $serviceTypes = ServiceType::where('status', 1)->orderBy('service_type_name')->get();

        $products = Product::where('status', 1)->orderBy('model')->get(['id', 'model']);

        return view('admin.doctor.create', compact('categories', 'serviceTypes', 'products'));
    }

    /**
     * Store a newly created doctor in storage.
     *
     * @param Request $request HTTP request containing doctor data
     * @return RedirectResponse Redirects to doctor list or back with warning
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'                       => 'required|string|max:255',
            'contact_number'             => 'nullable|string|max:50',
            'email'                      => 'nullable|email|max:255',
            'qualification'              => 'nullable|string|max:255',
            'product_commission_percent' => 'nullable|numeric|min:0|max:100',
            'product_commission_amount'  => 'nullable|numeric|min:0',
            'service_commission_percent' => 'nullable|numeric|min:0|max:100',
            'service_commission_amount'  => 'nullable|numeric|min:0',
            'referrer_type'              => 'nullable|string|in:doctor,quack,agent',
            'status'                     => 'nullable|boolean',

            // Chambers validation
            'chambers'                   => 'nullable|array',
            'chambers.*.chamber_name'    => 'required|string|max:255',
            'chambers.*.address'         => 'required|string',
            'chambers.*.phone'           => 'nullable|string|max:50',
            'chambers.*.visiting_days'   => 'nullable|string|max:100',
            'chambers.*.visiting_time'   => 'nullable|string|max:100',

            // Category commissions validation
            'product_commissions'                     => 'nullable|array',
            'product_commissions.*.product_id'        => 'required|exists:products,id',
            'product_commissions.*.commission_type'   => 'required|in:percent,fixed',
            'product_commissions.*.commission_value'  => 'required|numeric|min:0',

            'category_commissions'                    => 'nullable|array',
            'category_commissions.*.category_id'      => 'required|exists:categories,id',
            'category_commissions.*.commission_type'  => 'required|in:percent,fixed',
            'category_commissions.*.commission_value' => 'required|numeric|min:0',

            // Service commissions validation
            'service_commissions'                     => 'nullable|array',
            'service_commissions.*.service_type_id'   => 'required|exists:service_types,id',
            'service_commissions.*.commission_type'   => 'required|in:percent,fixed',
            'service_commissions.*.commission_value'  => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $doctorData = $request->only([
                'name',
                'referrer_type',
                'contact_number',
                'email',
                'qualification',
                'product_commission_percent',
                'product_commission_amount',
                'service_commission_percent',
                'service_commission_amount',
            ]);
            $doctorData['referrer_type'] = $request->input('referrer_type') ?: Doctor::TYPE_DOCTOR;

            if (auth()->user()->branchId()) {
                $doctorData['vendor_id'] = auth()->user()->branchId();
            }

            $doctorData['status'] = $request->has('status') ? (int) $request->status : 1;

            $doctor = new Doctor($doctorData);
            $doctor->save();

            // 1. Process Chambers
            $primaryIndex = (int) $request->input('primary_chamber_index', 0);
            $primaryAddress = null;

            if ($request->filled('chambers') && is_array($request->chambers)) {
                foreach ($request->chambers as $idx => $chamberData) {
                    if (empty($chamberData['chamber_name']) || empty($chamberData['address'])) {
                        continue;
                    }

                    $isPrimary = ($idx === $primaryIndex);
                    if ($isPrimary) {
                        $primaryAddress = $chamberData['address'];
                    }

                    DoctorChamber::create([
                        'doctor_id'     => $doctor->id,
                        'chamber_name'  => $chamberData['chamber_name'],
                        'address'       => $chamberData['address'],
                        'phone'         => $chamberData['phone'] ?? null,
                        'visiting_days' => $chamberData['visiting_days'] ?? null,
                        'visiting_time' => $chamberData['visiting_time'] ?? null,
                        'is_primary'    => $isPrimary,
                    ]);
                }
            }

            // Sync legacy address field for backward compatibility
            if ($primaryAddress) {
                $doctor->address = $primaryAddress;
                $doctor->save();
            }

            // 2. Process Category Commissions
            if ($request->filled('category_commissions') && is_array($request->category_commissions)) {
                $seenCats = [];
                foreach ($request->category_commissions as $catComm) {
                    $catId = (int) ($catComm['category_id'] ?? 0);
                    if ($catId && !in_array($catId, $seenCats)) {
                        $seenCats[] = $catId;
                        DoctorCategoryCommission::create([
                            'doctor_id'        => $doctor->id,
                            'category_id'      => $catId,
                            'commission_type'  => $catComm['commission_type'] ?? 'percent',
                            'commission_value' => (float) ($catComm['commission_value'] ?? 0),
                        ]);
                    }
                }
            }

            // 2b. Process model-wise commissions
            $this->saveProductCommissions($doctor, $request);

            // 3. Process Service Commissions
            if ($request->filled('service_commissions') && is_array($request->service_commissions)) {
                $seenServices = [];
                foreach ($request->service_commissions as $srvComm) {
                    $srvId = (int) ($srvComm['service_type_id'] ?? 0);
                    if ($srvId && !in_array($srvId, $seenServices)) {
                        $seenServices[] = $srvId;
                        DoctorServiceCommission::create([
                            'doctor_id'        => $doctor->id,
                            'service_type_id'  => $srvId,
                            'commission_type'  => $srvComm['commission_type'] ?? 'percent',
                            'commission_value' => (float) ($srvComm['commission_value'] ?? 0),
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect('admin/doctor')->with('success', 'Doctor registered successfully with chambers and commission rules');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('warning', 'Error saving doctor: ' . \App\Classes\Helper::userMessage($e))->withInput();
        }
    }

    /**
     * Show the form for editing the specified doctor.
     *
     * @param int $id Doctor ID
     * @return View
     */
    public function edit(int $id): View
    {
        $doctor = Doctor::with(['chambers', 'categoryCommissions.category', 'serviceCommissions.serviceType', 'productCommissions'])->findOrFail($id);
        $categories = Category::where('status', 1)->orderBy('category_name')->get();
        $serviceTypes = ServiceType::where('status', 1)->orderBy('service_type_name')->get();
        // Products with a rule stay selectable even if later set inactive
        $products = Product::where('status', 1)->orWhereIn('id', $doctor->productCommissions->pluck('product_id'))->orderBy('model')->get(['id', 'model']);

        return view('admin.doctor.edit', compact('doctor', 'categories', 'serviceTypes', 'products'));
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
        $request->validate([
            'name'                       => 'required|string|max:255',
            'contact_number'             => 'nullable|string|max:50',
            'email'                      => 'nullable|email|max:255',
            'qualification'              => 'nullable|string|max:255',
            'product_commission_percent' => 'nullable|numeric|min:0|max:100',
            'product_commission_amount'  => 'nullable|numeric|min:0',
            'service_commission_percent' => 'nullable|numeric|min:0|max:100',
            'service_commission_amount'  => 'nullable|numeric|min:0',
            'referrer_type'              => 'nullable|string|in:doctor,quack,agent',
            'status'                     => 'nullable|boolean',

            // Chambers validation
            'chambers'                   => 'nullable|array',
            'chambers.*.chamber_name'    => 'required|string|max:255',
            'chambers.*.address'         => 'required|string',
            'chambers.*.phone'           => 'nullable|string|max:50',
            'chambers.*.visiting_days'   => 'nullable|string|max:100',
            'chambers.*.visiting_time'   => 'nullable|string|max:100',

            // Category commissions validation
            'product_commissions'                     => 'nullable|array',
            'product_commissions.*.product_id'        => 'required|exists:products,id',
            'product_commissions.*.commission_type'   => 'required|in:percent,fixed',
            'product_commissions.*.commission_value'  => 'required|numeric|min:0',

            'category_commissions'                    => 'nullable|array',
            'category_commissions.*.category_id'      => 'required|exists:categories,id',
            'category_commissions.*.commission_type'  => 'required|in:percent,fixed',
            'category_commissions.*.commission_value' => 'required|numeric|min:0',

            // Service commissions validation
            'service_commissions'                     => 'nullable|array',
            'service_commissions.*.service_type_id'   => 'required|exists:service_types,id',
            'service_commissions.*.commission_type'   => 'required|in:percent,fixed',
            'service_commissions.*.commission_value'  => 'required|numeric|min:0',
        ]);

        $doctor = Doctor::findOrFail($id);

        DB::beginTransaction();
        try {
            $doctorData = $request->only([
                'name',
                'referrer_type',
                'contact_number',
                'email',
                'qualification',
                'product_commission_percent',
                'product_commission_amount',
                'service_commission_percent',
                'service_commission_amount',
            ]);
            if ($request->filled('referrer_type')) {
                $doctorData['referrer_type'] = $request->input('referrer_type');
            }

            if (auth()->user()->branchId()) {
                $doctorData['vendor_id'] = auth()->user()->branchId();
            }

            $doctorData['status'] = $request->has('status') ? (int) $request->status : 1;

            $doctor->update($doctorData);

            // 1. Sync Chambers
            $primaryIndex = (int) $request->input('primary_chamber_index', 0);
            $primaryAddress = null;

            DoctorChamber::where('doctor_id', $doctor->id)->delete();

            if ($request->filled('chambers') && is_array($request->chambers)) {
                $idx = 0;
                foreach ($request->chambers as $chamberData) {
                    if (empty($chamberData['chamber_name']) || empty($chamberData['address'])) {
                        continue;
                    }

                    $isPrimary = ($idx === $primaryIndex);
                    if ($isPrimary) {
                        $primaryAddress = $chamberData['address'];
                    }

                    DoctorChamber::create([
                        'doctor_id'     => $doctor->id,
                        'chamber_name'  => $chamberData['chamber_name'],
                        'address'       => $chamberData['address'],
                        'phone'         => $chamberData['phone'] ?? null,
                        'visiting_days' => $chamberData['visiting_days'] ?? null,
                        'visiting_time' => $chamberData['visiting_time'] ?? null,
                        'is_primary'    => $isPrimary,
                    ]);
                    $idx++;
                }
            }

            // Sync legacy address field
            if ($primaryAddress) {
                $doctor->address = $primaryAddress;
                $doctor->save();
            }

            // 2. Sync Category Commissions
            DoctorCategoryCommission::where('doctor_id', $doctor->id)->delete();
            if ($request->filled('category_commissions') && is_array($request->category_commissions)) {
                $seenCats = [];
                foreach ($request->category_commissions as $catComm) {
                    $catId = (int) ($catComm['category_id'] ?? 0);
                    if ($catId && !in_array($catId, $seenCats)) {
                        $seenCats[] = $catId;
                        DoctorCategoryCommission::create([
                            'doctor_id'        => $doctor->id,
                            'category_id'      => $catId,
                            'commission_type'  => $catComm['commission_type'] ?? 'percent',
                            'commission_value' => (float) ($catComm['commission_value'] ?? 0),
                        ]);
                    }
                }
            }

            // 2b. Sync model-wise commissions
            DoctorProductCommission::where('doctor_id', $doctor->id)->delete();
            $this->saveProductCommissions($doctor, $request);

            // 3. Sync Service Commissions
            DoctorServiceCommission::where('doctor_id', $doctor->id)->delete();
            if ($request->filled('service_commissions') && is_array($request->service_commissions)) {
                $seenServices = [];
                foreach ($request->service_commissions as $srvComm) {
                    $srvId = (int) ($srvComm['service_type_id'] ?? 0);
                    if ($srvId && !in_array($srvId, $seenServices)) {
                        $seenServices[] = $srvId;
                        DoctorServiceCommission::create([
                            'doctor_id'        => $doctor->id,
                            'service_type_id'  => $srvId,
                            'commission_type'  => $srvComm['commission_type'] ?? 'percent',
                            'commission_value' => (float) ($srvComm['commission_value'] ?? 0),
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect('admin/doctor')->with('success', 'Doctor updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('warning', 'Error updating doctor: ' . \App\Classes\Helper::userMessage($e))->withInput();
        }
    }

    /**
     * Get commission rules and chambers for a specific doctor as JSON.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function commissionRules(int $id): JsonResponse
    {
        $doctor = Doctor::with(['chambers', 'categoryCommissions', 'serviceCommissions', 'productCommissions'])->findOrFail($id);

        return response()->json($doctor->getCommissionRulesArray());
    }

    /** One rule per product; the last one wins if a product is listed twice. */
    private function saveProductCommissions(Doctor $doctor, Request $request): void
    {
        $rules = [];
        foreach ((array) $request->input('product_commissions', []) as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId) {
                $rules[$productId] = [
                    'commission_type'  => ($row['commission_type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent',
                    'commission_value' => (float) ($row['commission_value'] ?? 0),
                ];
            }
        }
        foreach ($rules as $productId => $rule) {
            DoctorProductCommission::create(['doctor_id' => $doctor->id, 'product_id' => $productId] + $rule);
        }
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

        // A doctor with referred sales or commission history stays, so statements and reports keep their name
        $hasSales = DB::table('sale_masters')->where('reference_by', $doctor->id)->whereNull('deleted_at')->exists();
        $hasLedger = DB::table('doctor_ledgers')->where('doctor_id', $doctor->id)->whereNull('deleted_at')->exists();
        if ($hasSales || $hasLedger) {
            return redirect()->back()->with('warning', "Cannot delete {$doctor->name}: this doctor has referred sales or commission history. Set the doctor to Inactive instead.");
        }

        $doctor->delete();

        return redirect('/admin/doctor')->with('success', 'Deleted Successfully');
    }
}
