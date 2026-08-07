<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeLedger;
use App\Models\Ledger;
use App\Models\Salary;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class SalaryController extends Controller
{
    /**
     * Configure middleware to handle authorization for different salary operations
     * 
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:salary_setup_list.index')->only(['index', 'salaryList']);
        $this->middleware('permission:add_salary_setup.create')->only(['create', 'store']);
        $this->middleware('permission:salary_payment.index')->only(['salaryPaymentList', 'salaryPaymentIndex']);
        $this->middleware('permission:salary_payment.create')->only(['salaryPayment', 'salaryPaymentCreate']);
        $this->middleware('permission:salary_setup_list.delete')->only(['destroy']);
        $this->middleware('permission:salary_payment.delete')->only(['salaryPaymentDestroy']);
    }
    
    /**
     * Display a listing of the salaries.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        return view('admin.salary.index');
    }

    /**
     * Get the list of salaries for DataTables.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function salaryList(): JsonResponse
    {
        $data = Salary::join('employees', 'employees.id', '=', 'salaries.employee_id')
            ->select('salaries.*', 'employees.name')
            ->orderBy('id', 'desc')
            ->get();

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('gross_salary', function ($row) {
                return $row->basic_salary + $row->earning - $row->deduction;
            })
            ->addColumn('month_year', function ($row) {
                return Carbon::parse($row->month_year)->format('F, Y');
            })
            ->addColumn('action', function ($row) {
                return '<div class="row">
                <div class="col-3">
                <form action="' . route('salary.destroy', $row->id) . '" method="POST">
                <input type="hidden" name="_token" value="' . csrf_token() . '">
                <input type="hidden" name="_method" value="delete" />
                <button type="submit" class="btn btn-danger btn-sm" title="Delete" onclick="confirmDelete(event)"><i class="far fa-trash-alt"> </i></button>
                </form>
                </div>
                </div>';
            })
            ->rawColumns(['gross_salary', 'action'])
            ->toJson();
    }

    /**
     * Display the form for creating a new salary payment.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function salaryPaymentCreate(): View
    {
        return view('admin.salary.payment_create');
    }

    /**
     * Display the list of salary payments.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function salaryPaymentIndex(): View
    {
        return view('admin.salary.payment_list');
    }

    /**
     * Get the list of salary payments for DataTables.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function salaryPaymentList(): JsonResponse
    {
        $data = EmployeeLedger::join('employees', 'employees.id', '=', 'employee_ledgers.employee_id')
            ->where('type', Helper::$salaryPayment)
            ->select('employee_ledgers.*', 'employees.name')
            ->orderBy('id', 'desc')
            ->get();

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('month_year', function ($row) {
                return Carbon::parse($row->month_year)->format('F, Y');
            })
            ->addColumn('action', function ($row) {
                return '<div class="row">
                <div class="col-3">
                <form action="' . route('salary.payment.destroy', $row->ref_invoice_no) . '" method="POST">
                <input type="hidden" name="_token" value="' . csrf_token() . '">
                <input type="hidden" name="_method" value="delete" />
                <button type="submit" class="btn btn-danger btn-sm" title="Delete" onclick="confirmDelete(event)"><i class="far fa-trash-alt"> </i></button>
                </form>
                </div>
                </div>';
            })
            ->rawColumns(['action'])
            ->toJson();
    }

    /**
     * Show the form for creating a new salary entry.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function create(): View
    {
        $data['employees'] = Employee::where('status', 1)->get();
        $data['salaryType'] = Helper::salaryType();

        return view('admin.salary.create', $data);
    }

    /**
     * Get employee salary details.
     *
     * @param \Illuminate\Http\Request $request
     * @return mixed
     */
    public function employeeSalary(Request $request): JsonResponse
    {
        $employee = Employee::where('id', $request->id)->first();
        $due = EmployeeLedger::where('employee_id', $request->id)->selectRaw('SUM(credit) - SUM(debit) as balance')->first();

        return response()->json([
            'employee' => $employee,
            'due' => $due->balance,
        ]);

    }

    /**
     * Store a newly created salary in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validate(
            $request,
            [
                'employee_id' => ['required'],
                'month_year' => ['required'],
                'salary_type' => ['required'],
                'basic_salary' => ['required'],
                'gross_salary' => ['required'],
            ],
            [
                'employee_id.required' => 'The employee field is required.'
            ]
        );

        $salaryCount = Salary::where('month_year', $request->month_year)->where('employee_id', $request->employee_id)->count();
        if ($salaryCount > 0) {
            return redirect()->back()->with('warning', 'Duplicate entry!');
        }

        DB::beginTransaction();
        $todaysDate = date('Y-m-d');
        $salary = new Salary();
        $salary->employee_id = $request->employee_id;
        $salary->salary_type = $request->salary_type;
        $salary->month_year = $request->month_year;
        $salary->date = $todaysDate;
        $salary->basic_salary = $request->basic_salary;
        $salary->earning = $request->earning;
        $salary->deduction = $request->deduction;

        if ($salary->save()) {
            $empLedger = new EmployeeLedger();
            $empLedger->employee_id = $request->employee_id;
            $empLedger->date = $todaysDate;
            $empLedger->month_year = $request->month_year;
            $empLedger->credit = $request->basic_salary + $request->earning - $request->deduction;
            $empLedger->debit = 0;
            $empLedger->details = 'Salary Setup';
            $empLedger->type = Helper::$salarySetup;
            $empLedger->ref_invoice_no = $salary->id;
            $empLedger->save();
            DB::commit();
            return redirect('admin/salary')->with('success', 'Saved Successfully');
        }

        DB::commit();
        return redirect()->back()->with('warning', 'Not Saved!');
    }

    /**
     * Store a new salary payment in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function salaryPayment(Request $request): RedirectResponse
    {
        $this->validate(
            $request,
            [
                'employee_id' => ['required'],
                'month_year' => ['required'],
                'paid_amount' => ['required'],
            ],
            [
                'employee_id.required' => 'The employee field is required.'
            ]
        );

        DB::beginTransaction();
        $todaysDate = date('Y-m-d');
        $type = Helper::$salaryPayment;
        $expenseInvoiceNo = date('ymdhis');

        $empLedger = new EmployeeLedger();
        $empLedger->employee_id = $request->employee_id;
        $empLedger->date = $todaysDate;
        $empLedger->month_year = $request->month_year;
        $empLedger->credit = 0;
        $empLedger->debit = $request->paid_amount;
        $empLedger->details = $request->details;
        $empLedger->type = $type;
        $empLedger->ref_invoice_no = $expenseInvoiceNo;

        if ($empLedger->save()) {
            $request['date'] = $todaysDate;
            Helper::expenseLedger($request, $expenseInvoiceNo, null, $type);
            DB::commit();
            return redirect('admin/salary-payment')->with('success', 'Saved Successfully');
        }

        DB::commit();
        return redirect()->back()->with('warning', 'Not Saved!');
    }

    /**
     * Remove the specified salary from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        DB::beginTransaction();
        Salary::where('id', $id)->delete();
        EmployeeLedger::where('type', Helper::$salarySetup)->where('ref_invoice_no', $id)->delete();
        DB::commit();
        return redirect()->back()->with('success', 'Deleted Successfully');
    }

    /**
     * Remove the specified salary payment from storage.
     *
     * @param string $invoiceNo
     * @return \Illuminate\Http\RedirectResponse
     */
    public function salaryPaymentDestroy(string $invoiceNo): RedirectResponse
    {
        DB::beginTransaction();
        EmployeeLedger::where('type', Helper::$salaryPayment)->where('ref_invoice_no', $invoiceNo)->delete();
        Ledger::where('invoice_no', $invoiceNo)->where('type', Helper::$salaryPayment)->delete();
        DB::commit();
        return redirect()->back()->with('success', 'Deleted Successfully');
    }
}
