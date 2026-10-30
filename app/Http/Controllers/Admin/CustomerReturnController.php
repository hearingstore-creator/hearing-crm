<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\CustomerLedger;
use App\Models\CustomerReturnDetail;
use App\Models\CustomerReturnMaster;
use App\Models\SaleDetail;
use App\Models\SaleMaster;
use App\Models\Setting;
use App\Models\Stock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomerReturnController extends Controller
{
    /**
     * Configure middleware to handle authorization for different customer return operations
     * 
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:add_return.create')->only(['customerAddReturn', 'store']);
        $this->middleware('permission:customer_return_list.index')->only(['index', 'customerReturnList']);
        $this->middleware('permission:customer_return_list.view')->only(['show']);
        $this->middleware('permission:customer_return_list.delete')->only(['destroy']);
    }

    /**
     * Show the form for adding a customer return.
     *
     * @param Request $request
     * @return RedirectResponse|View
     */
    public function customerAddReturn(Request $request): RedirectResponse|View
    {
        $data['saleMaster'] = SaleMaster::where('sale_invoice_no', $request->sale_invoice_no)->first();
        $data['saleDetail'] = SaleDetail::with(['medicine.leaf:id,total_number_per_box'])->where('sale_invoice_no', $request->sale_invoice_no)->get();

        if (!isset($data['saleMaster'])) {
            return redirect()->back()->with('warning', 'Not Found!!!');
        } else {
            return view('admin.customer_return.create', $data);
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index(): View
    {
        return view('admin.customer_return.index');
    }

    /**
     * Returns a list of customer returns for DataTables.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function customerReturnList(): JsonResponse
    {
        $data = CustomerReturnMaster::join('sale_masters', 'sale_masters.sale_invoice_no', '=', 'customer_return_masters.sale_invoice_no')
            ->join('customers', 'customers.id', '=', 'sale_masters.customer_id')
            ->whereNull('sale_masters.deleted_at')
            ->select('customer_return_masters.*', 'customers.name')
            ->orderBy('id', 'desc')
            ->get();

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('action', function ($row) {
                $actionBtn = '<div class="row">
                <div class="col-6 text-right">
                <a href="' . route('customer-return.show', $row->id) . '" class="edit btn btn-success btn-sm"><i class="fas fa-eye"> </i></a>
                </div>
                <div class="col-6">
                <form action="' . route('customer-return.destroy', $row->id) . '" method="POST">
                <input type="hidden" name="_token" value=" ' . csrf_token() . ' ">
                <input type="hidden" name="_method" value="delete" />
                
                <button type="submit" class="btn btn-danger btn-sm" onclick="confirmDelete(event)"><i class="far fa-trash-alt"> </i></button>               
                </form> 
                </div>
                </div>
                ';
                return $actionBtn;
            })
            ->rawColumns(['action'])
            ->toJson();
    }
    

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validate($request, [
            'sale_invoice_no' => ['required'],
            'date'            => ['required']
        ]);

        DB::beginTransaction();
        $type = Helper::$returnToCustomer;

        try {
            $crMaster = new CustomerReturnMaster();
            $crMaster->date = $request->date;
            $crMaster->details = $request->details;
            $crMaster->sale_invoice_no = $request->sale_invoice_no;
            if ($crMaster->save()) {
                $totalDeduction = 0;
                $totalAmount = 0;

                if (isset($request->medicine)) {
                    foreach ($request->medicine as $val) {
                        if (!empty($val['check'])) {
                            $totalDeduction += $val['deduction_taka'];
                            $totalAmount += $val['return_qty'] * $val['unit_price'] - $val['deduction_taka'];

                            $mrDetails = new CustomerReturnDetail();
                            $mrDetails->c_r_master_id = $crMaster['id'];
                            $mrDetails->medicine_id = $val['medicine_id'];
                            $mrDetails->batch_id = $val['batch_id'];
                            $mrDetails->return_qty = $val['return_qty'];
                            $mrDetails->unit_price = $val['unit_price'];
                            $mrDetails->deduction = $val['deduction'];
                            $mrDetails->deduction_taka = $val['deduction_taka'];
                            $mrDetails->save();
                            Helper::stockIn($val['medicine_id'], $request->date, $val['return_qty'], $request->details . ' (Sale Invoice No-' . $request->sale_invoice_no . ')', $crMaster['id'], $type);
                            Helper::updateBatch($val['medicine_id'], $val['batch_id'], null, null, $val['return_qty'], $type);
                        }
                    }
                    $request['paid_amount'] = $totalAmount;

                    CustomerReturnMaster::find($crMaster['id'])
                        ->update([
                            'total_amount'    => $totalAmount,
                            'total_deduction' => $totalDeduction
                        ]);

                    Helper::customerLedgerCredit($request, $crMaster['id'], $type);
                    DB::commit();
                    return redirect('admin/customer-return')->with('success', 'Saved Successfully');
                } else {
                    DB::commit();
                    return redirect()->back()->with('warning', 'Please select medicine!');
                }
            } else {
                DB::commit();
                return redirect()->back()->with('warning', 'Not Saved');
            }
        } catch (\Exception $ex) {
            DB::rollback();
            return redirect()->back()->with('warning', \App\Classes\Helper::userMessage($ex));
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function show(int $id): View
    {
        $data['cRMaster'] = CustomerReturnMaster::with('sale.customer:id,name,phone')->where('id', $id)->first();
        $data['cRDetail'] = CustomerReturnDetail::with('medicine:id,medicine_name,strength')->where('c_r_master_id', $id)->get();
        $data['setting'] =  Setting::where('id', 1)->first();
        return view('admin.customer_return.details', $data);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        DB::beginTransaction();
        $type = Helper::$returnToCustomer;

        $details = CustomerReturnDetail::where('c_r_master_id', $id)->get();

        foreach ($details as $val) {
            $batch = Batch::where('id', $val->batch_id)->where('medicine_id', $val->medicine_id)->first();
            if ($batch) {
                $batch->sale_return_qty -= $val->return_qty;
                $batch->update();
            }
        }
        
        CustomerReturnMaster::where('id', $id)->delete();
        CustomerReturnDetail::where('c_r_master_id', $id)->delete();
        CustomerLedger::where('ref_invoice_no', $id)->where('type', $type)->delete();
        Stock::where('ref_invoice_no', $id)->where('type', $type)->delete();

        DB::commit();
        return redirect()->back()->with('success', 'Deleted Successfully');
    }
}
