<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Category;
use App\Models\LeafSetting;
use App\Models\Manufacturer;
use App\Models\Medicine;
use App\Models\PurchaseDetail;
use App\Models\Setting;
use App\Models\Stock;
use App\Models\Type;
use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Yajra\DataTables\Facades\DataTables;

class MedicineController extends Controller
{
    /**
     * Configure middleware to handle authorization for different medicine operations
     * 
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:medicine.index')->only(['index', 'medicineList', 'medicineInfo', 'batchWiseMedicineInfo']);
        $this->middleware('permission:medicine.create')->only(['create', 'store']);
        $this->middleware('permission:medicine.edit')->only(['edit', 'update']);
        $this->middleware('permission:medicine.delete')->only(['destroy']);
        $this->middleware('permission:medicine.barcode')->only(['barcodeGenerate']);
        $this->middleware('permission:batch_wise_stock_report.index')->only(['batchWiseStockReport']);
        $this->middleware('permission:stock_report.index')->only(['stockReportByProduct', 'stockReportByProductList']);
    }

    /**
     * Display a listing of the medicines.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        return view('admin.medicine.index');
    }

    /**
     * Get the list of medicines for DataTables.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function medicineList(): JsonResponse
    {
        $data = Medicine::join('categories', 'categories.id', '=', 'medicines.category_id')
            ->join('manufacturers', 'manufacturers.id', '=', 'medicines.manufacturer_id')
            ->select('medicines.*', 'categories.category_name', 'manufacturers.manufacturer_name')
            ->orderBy('id', 'desc')
            ->get();

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('image', function ($row) {
                if (!empty($row->image)) {
                    $url = asset($row->image);
                    return '<img src="' . $url . '" border="0" width="40" class="img-rounded" align="center" />';
                }
                return null;
            })
            ->addColumn('status', function ($row) {
                if ($row->status == 1) {
                    return '<span class="badge badge-success">Active</span>';
                }
                return '<span class="badge badge-danger">Inactive</span>';
            })
            ->addColumn('action', function ($row) {
                $buttons = '<div class="d-flex flex-wrap align-items-center">';
                if (Helper::hasPermission('medicine.edit')) {
                    $buttons .= '
                        <a href="' . route('medicine.edit', $row->id) . '" class="btn btn-success btn-sm mr-1" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                    ';
                }
                if (Helper::hasPermission('medicine.barcode')) {
                    $buttons .= '
                        <a href="' . route('medicine.barcode', $row->id) . '" class="btn btn-info btn-sm mr-1" title="Barcode">
                            <i class="fas fa-barcode"></i>
                        </a>
                    ';
                }
                if (Helper::hasPermission('medicine.delete')) {
                    $buttons .= '
                        <form action="' . route('medicine.destroy', $row->id) . '" method="POST" onsubmit="return confirmDelete(event)" style="display:inline-block;">
                            ' . csrf_field() . '
                            ' . method_field('DELETE') . '
                            <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                                <i class="far fa-trash-alt"></i>
                            </button>
                        </form>
                    ';
                }

                $buttons .= '</div>';

                return $buttons;
            })

            ->rawColumns(['image', 'status', 'action'])
            ->toJson();
    }

    /**
     * Show the form for creating a new medicine.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function create(): View
    {
        $data['leafSettings'] = LeafSetting::where('status', 1)->orderBy('id', 'desc')->get();
        $data['manufacturers'] = Manufacturer::where('status', 1)->orderBy('id', 'desc')->get();
        $data['units'] = Unit::where('status', 1)->orderBy('id', 'desc')->get();
        $data['categories'] = Category::where('status', 1)->orderBy('id', 'desc')->get();
        $data['types'] = Type::where('status', 1)->orderBy('id', 'desc')->get();

        return view('admin.medicine.create', $data);
    }

    /**
     * Store a newly created medicine in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validate(
            $request,
            [
                'medicine_name' => ['required', 'string', 'unique:medicines,medicine_name'],
                'unit_id' => ['required'],
                'manufacturer_id' => ['required'],
                'leaf_setting_id' => ['required'],
                'price' => ['required'],
                'cost' => ['required'],
                'status' => ['required'],
            ],
            [
                'unit_id.required' => 'The unit field is required.',
                'manufacturer_id.required' => 'The manufacturer field is required.',
                'leaf_setting_id.required' => 'The box size field is required.',
            ]
        );

        $requestAll = $request->all();
        $uploadPath = 'file/medicine/';
        $requestAll['image'] = Helper::fileUpload($request->file('image'), $uploadPath);
        $medicine = new Medicine($requestAll);

        if ($medicine->save()) {
            return redirect('admin/medicine/create')->with('success', 'Saved Successfully');
        }

        return redirect()->back()->with('warning', 'Not Saved');
    }

    /**
     * Show the form for editing the specified medicine.
     *
     * @param int $id
     * @return \Illuminate\Contracts\View\View
     */
    public function edit(int $id): View
    {
        $data['medicine'] = Medicine::findOrFail($id);
        $data['leafSettings'] = LeafSetting::where('status', 1)->orderBy('id', 'desc')->get();
        $data['manufacturers'] = Manufacturer::where('status', 1)->orderBy('id', 'desc')->get();
        $data['units'] = Unit::where('status', 1)->orderBy('id', 'desc')->get();
        $data['categories'] = Category::where('status', 1)->orderBy('id', 'desc')->get();
        $data['types'] = Type::where('status', 1)->orderBy('id', 'desc')->get();

        return view('admin.medicine.edit', $data);
    }

    /**
     * Update the specified medicine in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->validate(
            $request,
            [
                'medicine_name' => ['required', 'string', 'unique:medicines,medicine_name,' . $id],
                'unit_id' => ['required'],
                'manufacturer_id' => ['required'],
                'leaf_setting_id' => ['required'],
                'price' => ['required'],
                'cost' => ['required'],
                'status' => ['required'],
            ],
            [
                'unit_id.required' => 'The unit field is required.',
                'manufacturer_id.required' => 'The manufacturer field is required.',
                'leaf_setting_id.required' => 'The box size field is required.',
            ]
        );

        $requestAll = $request->all();
        $uploadPath = 'file/medicine/';
        if ($request->hasFile('image')) {
            $requestAll['image'] = Helper::fileUpload($request->file('image'), $uploadPath);
        }

        $medicine = Medicine::findOrFail($id);
        $medicine->fill($requestAll);

        if ($medicine->save()) {
            return redirect('admin/medicine')->with('success', 'Updated Successfully');
        }

        return redirect()->back()->with('warning', 'Not Updated');
    }

    /**
     * Get batch-wise medicine information.
     *
     * @param int $id
     * @return mixed
     */
    public function batchWiseMedicineInfo(int $id)
    {
        $batch = Batch::where('id', $id)
            ->select('id', 'ref_invoice_no', DB::raw("coalesce(SUM(purchase_qty),0) + coalesce(SUM(sale_return_qty),0) - coalesce(SUM(sale_qty),0) - coalesce(SUM(purchase_return_qty),0) as stock"))
            ->first();

        $purchaseDetails = PurchaseDetail::select('id', 'purchase_price', 'mrp_price')->where('purchase_invoice_no', $batch->ref_invoice_no)
            ->whereBatchId($id)->first();

        $batch->purchase_price = $purchaseDetails->purchase_price;
        $batch->mrp_price = $purchaseDetails->mrp_price;

        return $batch;
    }

    /**
     * Get medicine information including stock and batch details.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function medicineInfo(int $id): JsonResponse
    {
        $data['medicine'] = Medicine::with('unit:id,unit_name', 'batch')->where('id', $id)->first();
        $totalNumberPerBox = LeafSetting::find($data['medicine']->leaf_setting_id)->total_number_per_box;
        $data['medicine']->unit_price = number_format($data['medicine']->price, 2);
        $data['medicine']->purchase_price = number_format($data['medicine']->cost, 2);
        $data['medicine']->number_per_box = $totalNumberPerBox;
        $data['stock'] = Stock::where('medicine_id', $id)
            ->value(DB::raw("SUM(in_qty - out_qty)"));

        $batchId = Batch::whereMedicineId($id)->orderBy('id')->value('id');
        $data['batch'] = [];

        if (!empty($batchId)) {
            $data['batch'] = $this->batchWiseMedicineInfo($batchId);
        }

        return response()->json($data);
    }

    /**
     * Display the stock report by product.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function stockReportByProduct(): View
    {
        $data['setting'] = Setting::where('id', 1)->first(['company_name']);
        return view('admin.medicine.stock_report', $data);
    }

    /**
     * Get the stock report by product list for DataTables.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function stockReportByProductList(Request $request): JsonResponse
    {
        $stockType = $request->input('stockType');

        if ($stockType == Helper::$stockout) {
            $data = Stock::leftJoin('medicines', 'stocks.medicine_id', '=', 'medicines.id')
                ->leftJoin('manufacturers', 'manufacturers.id', '=', 'medicines.manufacturer_id')
                ->whereNull('stocks.deleted_at')
                ->select('medicines.id', 'medicines.medicine_name', 'manufacturers.manufacturer_name', DB::raw("SUM(stocks.in_qty) as in_qty"), DB::raw("SUM(stocks.out_qty) as out_qty"), DB::raw("SUM(stocks.in_qty) - SUM(stocks.out_qty) as stock"))
                ->groupBy('medicines.id')
                ->havingRaw('stock = 0')
                ->get();
        } else {
            $data = Stock::join('medicines', 'stocks.medicine_id', '=', 'medicines.id')
                ->leftJoin('manufacturers', 'manufacturers.id', '=', 'medicines.manufacturer_id')
                ->whereNull('stocks.deleted_at')
                ->whereNull('medicines.deleted_at')
                ->select('medicines.id', 'medicines.medicine_name', 'manufacturers.manufacturer_name', DB::raw("SUM(stocks.in_qty) as in_qty"), DB::raw('SUM(stocks.out_qty) as out_qty'), DB::raw("SUM(stocks.in_qty) - SUM(stocks.out_qty) as stock"))
                ->groupBy('medicines.id')
                ->get();
        }

        return DataTables::of($data)
            ->addIndexColumn()
            ->toJson();
    }

    /**
     * Display the batch-wise stock report.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function batchWiseStockReport(): View
    {
        $data['batchWiseStock'] = Batch::with(['product.brand'])
            ->groupBy('id')
            ->orderBy('id', 'desc')
            ->paginate(30);
        $data['setting'] = Setting::where('id', 1)->first(['company_name']);

        return view('admin.medicine.batch_wise_stock_report', $data);
    }

    /**
     * Generate a barcode for the specified medicine.
     *
     * @param int $id
     * @return \Illuminate\Contracts\View\View
     */
    public function barcodeGenerate(int $id): View
    {
        $data['medicine'] = Medicine::find($id);
        $data['itemCode'] = Helper::$itemCode;
        return view('admin.medicine.barcode', $data);
    }

    /**
     * Remove the specified medicine from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        $medicine = Medicine::find($id);
        $path = $medicine->image;

        if (File::exists($path)) {
            unlink($path);
        }

        $medicine->delete();
        return redirect('/admin/medicine')->with('success', 'Deleted Successfully');
    }
}
