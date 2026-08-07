<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\ManufacturerLedger;
use App\Models\ManufacturerReturnDetail;
use App\Models\ManufacturerReturnMaster;
use App\Models\PurchaseDetail;
use App\Models\PurchaseMaster;
use App\Models\Setting;
use App\Models\Stock;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class ManufacturerReturnController extends Controller
{
    /**
     * Configure middleware to handle authorization for different manufacturer return operations
     * 
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:add_return.create')->only(['addReturn', 'manufacturerAddReturn', 'store']);
        $this->middleware('permission:manufacturer_return_list.index')->only(['index', 'manufacturerReturnList']);
        $this->middleware('permission:manufacturer_return_list.view')->only(['show']);
        $this->middleware('permission:manufacturer_return_list.delete|wastage_return_list.delete')->only(['destroy']);
        $this->middleware('permission:wastage_return_list.index')->only(['wastageReturn', 'wastageReturnList']);
    }

    /**
     * Display the form for adding a new manufacturer return.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function addReturn(): View
    {
        return view('admin.manufacturer_return.add_return');
    }

    /**
     * Find purchase details and display the return creation form.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function manufacturerAddReturn(Request $request)
    {
        $data['purchaseMaster'] = PurchaseMaster::where('purchase_invoice_no', $request->purchase_invoice_no)->first();
        $data['purchaseDetail'] = PurchaseDetail::with(['medicine.leaf:id,total_number_per_box', 'medicine.batch'])->where('purchase_invoice_no', $request->purchase_invoice_no)->get();
        $data['wastage'] = Helper::$wastage;
        $data['manufacturer'] = Helper::$manufacturer;

        if (!isset($data['purchaseMaster'])) {
            return redirect()->back()->with('warning', 'Not Found!!!');
        } else {
            return view('admin.manufacturer_return.create', $data);
        }
    }

    /**
     * Display a listing of the manufacturer returns.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        return view('admin.manufacturer_return.index');
    }

    /**
     * Get the list of manufacturer returns for DataTables.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function manufacturerReturnList(): JsonResponse
    {
        $type = Helper::$manufacturer;
        $data = ManufacturerReturnMaster::join('purchase_masters', 'purchase_masters.purchase_invoice_no', '=', 'manufacturer_return_masters.purchase_invoice_no')
            ->join('manufacturers', 'manufacturers.id', '=', 'purchase_masters.manufacturer_id')
            ->select('manufacturer_return_masters.*', 'manufacturers.manufacturer_name')
            ->where('manufacturer_return_masters.type', $type)
            ->orderBy('id', 'desc')
            ->get();

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('action', function ($row) {
                return '
                    <div class="d-flex flex-wrap align-items-center justify-content-center">
                        <a href="' . route('manufacturer-return.show', $row->id) . '" class="btn btn-success btn-sm mr-1" title="View Details">
                            <i class="fas fa-eye"></i>
                        </a>
                        <form action="' . route('manufacturer-return.destroy', $row->id) . '" method="POST" onsubmit="return confirmDelete(event)">
                            ' . csrf_field() . '
                            ' . method_field('DELETE') . '
                            <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                                <i class="far fa-trash-alt"></i>
                            </button>
                        </form>
                    </div>
                ';
            })
            ->rawColumns(['action'])
            ->toJson();
    }

    /**
     * Display the wastage return view.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function wastageReturn(): View
    {
        return view('admin.manufacturer_return.wastage');
    }

    /**
     * Get the list of wastage returns for DataTables.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function wastageReturnList(): JsonResponse
    {
        $type = Helper::$wastage;
        $data = ManufacturerReturnMaster::join('purchase_masters', 'purchase_masters.purchase_invoice_no', '=', 'manufacturer_return_masters.purchase_invoice_no')
            ->join('manufacturers', 'manufacturers.id', '=', 'purchase_masters.manufacturer_id')
            ->select('manufacturer_return_masters.*', 'manufacturers.manufacturer_name')
            ->where('manufacturer_return_masters.type', $type)
            ->orderBy('id', 'desc')
            ->get();

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('action', function ($row) {
                $actionBtn = '<div class="row">
                <div class="col-6 text-right">
                <a href="' . route('manufacturer-return.show', $row->id) . '" class="edit btn btn-success btn-sm"><i class="fas fa-eye"> </i></a>
                </div>
                <div class="col-6">
                <form action="' . route('manufacturer-return.destroy', $row->id) . '" method="POST">
                <input type="hidden" name="_token" value=" ' . csrf_token() . ' ">
                <input type="hidden" name="_method" value="delete" />
                <button type="submit" class="btn btn-danger btn-sm" onclick="confirmDelete(event)"><i class="far fa-trash-alt"> </i></button>
                </form>
                </div>
                </div>';
                return $actionBtn;
            })
            ->rawColumns(['action'])
            ->toJson();
    }

    /**
     * Store a newly created manufacturer return in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validate($request, [
            'purchase_invoice_no' => ['required'],
            'date' => ['required']
        ]);

        DB::beginTransaction();
        $type = Helper::$returnToManufacturer;

        try {
            $mrMaster = new ManufacturerReturnMaster();
            $mrMaster->date = $request->date;
            $mrMaster->details = $request->details;
            $mrMaster->purchase_invoice_no = $request->purchase_invoice_no;
            $mrMaster->type = $request->type;

            if ($mrMaster->save()) {
                $totalDeduction = 0;
                $totalAmount = 0;

                if (isset($request->medicine)) {
                    foreach ($request->medicine as $val) {
                        if (!empty($val['check'])) {
                            $totalDeduction += $val['deduction_taka'];
                            $totalAmount += $val['return_qty'] * $val['unit_price'] - $val['deduction_taka'];

                            $mrDetails = new ManufacturerReturnDetail();
                            $mrDetails->m_r_master_id = $mrMaster->id;
                            $mrDetails->medicine_id = $val['medicine_id'];
                            $mrDetails->batch_id = $val['batch_id'];
                            $mrDetails->return_qty = $val['return_qty'];
                            $mrDetails->unit_price = $val['unit_price'];
                            $mrDetails->deduction = $val['deduction'];
                            $mrDetails->deduction_taka = $val['deduction_taka'];
                            $mrDetails->save();

                            Helper::stockOut($val['medicine_id'], $request->date, $val['return_qty'], $request->details . ' (Purchase Invoice No-' . $request->purchase_invoice_no . ')', $mrMaster->id, $type);
                            Helper::updateBatch($val['medicine_id'], $val['batch_id'], null, null, $val['return_qty'], $type);
                        }
                    }

                    $request['paid_amount'] = $totalAmount;

                    ManufacturerReturnMaster::find($mrMaster->id)
                        ->update([
                            'total_amount' => $totalAmount,
                            'total_deduction' => $totalDeduction
                        ]);

                    Helper::manufacturerLedgerDebit($request, $mrMaster->id, $type);
                    DB::commit();

                    if ($request->type == Helper::$wastage) {
                        return redirect('admin/wastage-return')->with('success', 'Saved Successfully');
                    }

                    return redirect('admin/manufacturer-return')->with('success', 'Saved Successfully');
                }

                DB::commit();
                return redirect()->back()->with('warning', 'Please select medicine!');
            }

            DB::commit();
            return redirect()->back()->with('warning', 'Not Saved');
        } catch (\Exception $ex) {
            DB::rollback();
            return redirect()->back()->with('warning', $ex->getMessage());
        }
    }

    /**
     * Display the specified manufacturer return.
     *
     * @param int $id
     * @return \Illuminate\Contracts\View\View
     */
    public function show(int $id): View
    {
        $data['mRMaster'] = ManufacturerReturnMaster::with('purchase.manufacturer:id,manufacturer_name,phone')->where('id', $id)->first();
        $data['mRDetail'] = ManufacturerReturnDetail::with('medicine:id,medicine_name,strength')->where('m_r_master_id', $id)->get();
        $data['setting'] =  Setting::where('id', 1)->first();
        return view('admin.manufacturer_return.details', $data);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        DB::beginTransaction();
        $type = Helper::$returnToManufacturer;
        $details = ManufacturerReturnDetail::where('m_r_master_id', $id)->get();

        foreach ($details as $val) {
            $batch = Batch::where('id', $val->batch_id)->where('medicine_id', $val->medicine_id)->first();
            $batch->purchase_return_qty -= $val->return_qty;
            $batch->update();
        }

        ManufacturerReturnMaster::where('id', $id)->delete();
        ManufacturerReturnDetail::where('m_r_master_id', $id)->delete();
        ManufacturerLedger::where('ref_invoice_no', $id)->where('type', $type)->delete();
        Stock::where('ref_invoice_no', $id)->where('type', $type)->delete();

        DB::commit();
        return redirect()->back()->with('success', 'Deleted Successfully');
    }
}
