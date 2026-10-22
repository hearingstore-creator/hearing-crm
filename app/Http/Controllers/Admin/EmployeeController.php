<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeLedger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Yajra\DataTables\Facades\DataTables;

class EmployeeController extends Controller
{
    /**
     * Configure middleware to handle authorization for different employee operations
     * 
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:employee.index')->only(['index', 'employeeList']);
        $this->middleware('permission:employee.create')->only(['create', 'store']);
        $this->middleware('permission:employee.edit')->only(['edit', 'update']);
        $this->middleware('permission:employee.delete')->only(['destroy']);
    }

    /**
     * Display a listing of the employee.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        return view('admin.employee.index');
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function employeeList(): JsonResponse
    {
        $data = Employee::join('designations', 'employees.designation_id', '=', 'designations.id')
            ->select('employees.*', 'designations.designation_name')
            ->orderBy('id', 'desc')
            ->take(5000)
            ->get();

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('status', function ($row) {
                if ($row->status == 1) {
                    return '<span class="badge badge-success">Active</span>';
                }

                return '<span class="badge badge-danger">Inactive</span>';
            })
            ->addColumn('action', function ($row) {
                return '<div class="row">
                <div class="col-6 text-right">
                <a href="' . route('employee.edit', $row->id) . '" class="edit btn btn-success btn-sm"><i class="fas fa-edit"> </i></a>
                </div>
                <div class="col-6">
                <form action="' . route('employee.destroy', $row->id) . '" method="POST">
                <input type="hidden" name="_token" value="' . csrf_token() . '" />
                <input type="hidden" name="_method" value="delete" />
                <button type="submit" class="btn btn-danger btn-sm" onclick="confirmDelete(event)"><i class="far fa-trash-alt"> </i></button>
                </form>
                </div>
                </div>';
            })
            ->rawColumns(['status', 'action'])
            ->toJson();
    }

    /**
     * Show the form for creating a new employee.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function create(): View
    {
        $data['designations'] = Designation::where('status', 1)->get();
        $data['bloodGroup'] = Helper::bloodGroup();
        $data['salaryType'] = Helper::salaryType();

        return view('admin.employee.create', $data);
    }

    /**
     * Store a newly created employee in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validate(
            $request,
            [
                'name' => ['required', 'string'],
                'mobile_no' => ['required', 'string'],
                'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            ],
            [
                'designation_id.required' => 'The designation field is required.',
            ]
        );

        $requestAll = $request->all();
        $uploadPath = 'file/employee/';

        if ($request->hasfile('image')) {
            $requestAll['image'] = Helper::fileUpload($request->file('image'), $uploadPath);
        }

        $employee = new Employee($requestAll);

        if ($employee->save()) {
            return redirect('admin/employee')->with('success', 'Saved Successfully');
        }

        return redirect()->back()->with('warning', 'Not Saved');
    }

    /**
     * Show the form for editing the specified employee.
     *
     * @param  int  $id
     * @return \Illuminate\Contracts\View\View
     */
    public function edit(int $id): View
    {
        $data['employee'] = Employee::findOrFail($id);
        $data['designations'] = Designation::where('status', 1)->get();
        $data['bloodGroup'] = Helper::bloodGroup();
        $data['salaryType'] = Helper::salaryType();

        return view('admin.employee.edit', $data);
    }

    /**
     * Update the specified employee in storage.
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
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $requestAll = $request->all();
        $uploadPath = 'file/employee/';

        if ($request->hasfile('image')) {
            $requestAll['image'] = Helper::fileUpload($request->file('image'), $uploadPath);
        }

        $employee = Employee::findOrFail($id);
        $employee->fill($requestAll);

        if ($employee->save()) {
            return redirect('admin/employee')->with('success', 'Updated Successfully');
        }

        return redirect()->back()->with('warning', 'Not Updated');
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function employeeSalaryReport(): View
    {
        return view('admin.employee.salary_report');
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function employeeSalaryReportList(): JsonResponse
    {
        $data = EmployeeLedger::leftJoin('employees', 'employees.id', '=', 'employee_ledgers.employee_id')
            ->whereNull('employee_ledgers.deleted_at')
            ->select(
                'employee_ledgers.id',
                'employees.name',
                'employees.salary',
                DB::raw("SUM(employee_ledgers.credit) - SUM(employee_ledgers.debit) as due")
            )
            ->groupBy('employees.id')
            ->get();

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('due', function ($row) {
                if ($row->due < 0) {
                    return 0;
                }

                return $row->due;
            })
            ->addColumn('advanced', function ($row) {
                if ($row->due <= 0) {
                    return (-1) * $row->due;
                }

                return 0;
            })
            ->rawColumns(['due', 'advanced'])
            ->toJson();
    }

    /**
     * Remove the specified employee from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        $employee = Employee::findOrFail($id);
        $path = $employee->image;

        if (File::exists($path)) {
            unlink($path);
        }

        $employee->delete();

        return redirect('/admin/employee')->with('success', 'Deleted Successfully');
    }
}
