<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Classes\SMS;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\District;
use App\Models\Lead;
use App\Models\SaleMaster;
use App\Services\ExcelImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class CustomerController extends Controller
{
    /**
     * Configure middleware to handle authorization for different customer operations
     * 
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:add_customer.create')->only(['create', 'store']);
        $this->middleware('permission:customer_list.index')->only(['index', 'customerList']);
        $this->middleware('permission:customer_list.edit')->only(['edit', 'update']);
        $this->middleware('permission:customer_list.show')->only(['show']);
        $this->middleware('permission:customer_list.destroy')->only(['destroy']);
        $this->middleware('permission:customer_list.import')->only(['importCustomers']);
        $this->middleware('permission:customer_ledger.view')->only(['createCustomerLedger', 'customerLedger']);
        $this->middleware('permission:customer_payment.create')->only(['payment', 'paymentStore']);
    }

    /**
     * Display a listing of the customer.
     *
     * @return View
     */
    public function index(Request $request): View
    {
        $data['start_date'] = date('Y-m-d', strtotime('2021-11-01'));
        $data['end_date'] = date('Y-m-d');

        if ((isset($request->start_date) && $request->start_date != "") && (isset($request->end_date) && $request->end_date != "")) {
            $data['start_date'] = date('Y-m-d', strtotime($request->start_date));
            $data['end_date'] = date('Y-m-d', strtotime($request->end_date));
        }

        // Build query with lead relationship
        $query = Customer::with('lead', 'membershipTier')
            ->whereDate('created_at', '>=', $data['start_date'])
            ->whereDate('created_at', '<=', $data['end_date']);

        // Apply filters on lead relationship
        if (isset($request->phone_number)) {
            $query->whereHas('lead', function($q) use ($request) {
                $q->where('phone_number', $request->phone_number);
            });
        }

        if (isset($request->lead_uid)) {
            $query->whereHas('lead', function($q) use ($request) {
                $q->where('lead_uid', $request->lead_uid);
            });
        }

        if (isset($request->email)) {
            $query->whereHas('lead', function($q) use ($request) {
                $q->where('email', $request->email);
            });
        }

        $data['customers'] = $query->orderBy('id', 'desc')->get();

        return view('admin.customer.index', $data);
    }


    /**
     * Show the form for creating a new customer.
     *
     * @return View
     */
    public function create(): View
    {
        $data['districts'] = District::orderBy('name')->get();

        return view('admin.customer.create', $data);
    }

    /**
     * Store a newly created customer in storage.
     *
     * @param Request $request
     * @return RedirectResponse|JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'full_name' => ['required', 'string'],
            'phone_number' => ['required', 'string'],
            'age' => ['required', 'integer', 'min:0'],
            'email' => ['nullable', 'email'],
            'district_id' => ['nullable', 'exists:districts,id'],
            'address' => ['nullable', 'string'],
            'gender' => ['nullable', 'in:male,female,other'],
            'membership_id' => ['nullable', 'string', 'unique:customers,membership_id'],
        ]);

        DB::beginTransaction();
        try {
            // Create Lead first
            $lead = new Lead();
            $lead->full_name = $request->full_name;
            $lead->age = $request->age;
            $lead->gender = $request->gender;
            $lead->phone_number = $request->phone_number;
            $lead->email = $request->email;
            $lead->district_id = $request->district_id;
            $lead->address = $request->address;
            $lead->save();
            // Create Customer
            $customer = new Customer();
            $customer->lead_id = $lead->id;
            $customer->membership_id = $request->membership_id;
            $customer->referral_code = Helper::generateReferralCode($request->full_name);
            $customer->save();

            $sms = new SMS();
            $msisdn = $request->phone_number;
            $messageBody = "Dear {$request->full_name},\n\nWelcome to Our Hearing Care Family!\n\nThank you for choosing us. We are honored to be part of your journey to better hearing health.\n\nOur team of experts is committed to providing you with the best hearing solutions and personalized care.\n\nIf you have any questions or need assistance, please don't hesitate to reach out to us.\n\nBest regards,\nHearing Care Team";
            $csmsId = uniqid(); // csms id must be unique

            $sms->singleSms($msisdn, $messageBody, $csmsId);

            DB::commit();

            // Check if request is AJAX
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Customer Created Successfully',
                    'customer_id' => $customer->id,
                    'customer_name' => $lead->full_name
                ], 200);
            }

            return redirect('admin/customers')->with('success', 'Customer Created Successfully');
        } catch (\Exception $e) {
            DB::rollBack();

            // Check if request is AJAX
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not Saved: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()->with('warning', 'Not Saved: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified customer details with sales information.
     *
     * @param int $id
     * @return View
     */
    public function show(int $id): View
    {
        $customer = Customer::with('lead', 'membershipTier', 'referredBy')->findOrFail($id);

        // Get all sale masters for this customer
        $saleMasters = SaleMaster::with(['saleDetails.product', 'serviceDetails.serviceType'])
            ->where('customer_id', $id)
            ->orderBy('date', 'desc')
            ->get();

        return view('admin.customer.details', compact('customer', 'saleMasters'));
    }

    /**
     * @return View
     */
    public function createCustomerLedger(): View
    {
        $data['customers'] = Customer::where('status', 1)->orderBy('id', 'desc')->get();

        return view('admin.customer.ledger', $data);
    }

    /**
     * @param Request $request
     * @return View
     */
    public function customerLedger(Request $request): View
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $customerId = $request->customer_id;
        $customers = Customer::where('status', 1)->orderBy('id', 'desc')->get();
        $customer = Customer::find($customerId);
        $customerName = $customer ? $customer->lead->full_name : '';

        $ledgers = CustomerLedger::where('customer_id', $request->customer_id)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('id', 'desc')
            ->get();

        return view('admin.customer.ledger', compact('ledgers', 'startDate', 'endDate', 'customers', 'customerId', 'customerName'));
    }

    /**
     * Show the form for editing the specified customer.
     *
     * @param int $id
     * @return View
     */
    public function edit(int $id): View
    {
        $customer = Customer::with('lead')->findOrFail($id);
        $data['customer'] = $customer;
        $data['districts'] = District::orderBy('name')->get();

        return view('admin.customer.edit', $data);
    }

    /**
     * Update the specified customer in storage.
     *
     * @param Request $request
     * @param int $id
     * @return RedirectResponse
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'full_name' => ['required', 'string'],
            'phone_number' => ['required', 'string'],
            'age' => ['required', 'integer', 'min:0'],
            'email' => ['nullable', 'email'],
            'district_id' => ['nullable', 'exists:districts,id'],
            'address' => ['nullable', 'string'],
            'gender' => ['nullable', 'in:male,female,other'],
            'membership_id' => ['nullable', 'string', 'unique:customers,membership_id,' . $id],
        ]);

        DB::beginTransaction();
        try {
            $customer = Customer::with('lead')->findOrFail($id);

            // Update Lead
            if ($customer->lead) {
                $customer->lead->full_name = $request->full_name;
                $customer->lead->email = $request->email;
                $customer->lead->district_id = $request->district_id;
                $customer->lead->address = $request->address;
                $customer->lead->age = $request->age;
                $customer->lead->gender = $request->gender;
                $customer->lead->phone_number = $request->phone_number;
                $customer->lead->save();
            }

            // Update Customer
            $customer->membership_id = $request->membership_id;
            $customer->save();

            DB::commit();
            return redirect('admin/customers')->with('success', 'Customer Updated Successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('warning', 'Not Updated: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * @return View
     */
    public function payment(): View
    {
        $transactionType = Helper::transactionType();

        return view('admin.customer.payment', compact('transactionType'));
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function paymentStore(Request $request): RedirectResponse
    {
        $refInvoiceNo = strtoupper(uniqid());
        $invoiceNo = date('ymdhis');

        DB::beginTransaction();
        try {
            if ($request->type == Helper::$payment) {
                $type = Helper::$payment;
                $request['grand_total'] = $request->paid_amount;
                Helper::customerLedgerDebit($request, $refInvoiceNo, $type);
                Helper::expenseLedger($request, $invoiceNo, $refInvoiceNo, $type);
            } else {
                $type = Helper::$receive;
                Helper::customerLedgerCredit($request, $refInvoiceNo, $type);
                Helper::incomeLedger($request, $invoiceNo, $refInvoiceNo, $type);
            }
            DB::commit();

            return redirect()->back()->with('success', 'Saved Successfully');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('warning', 'An error occurred: ' . $e->getMessage());
        }
    }

     /**
     * Search for customers based on a search term.
     *
     * This method performs a simple LIKE query on the customers table to find matching names.
     * It is designed to be used in AJAX calls, for example, to populate a dropdown or autocomplete field.
     * The result is returned as a JSON object, ready for consumption by a select2 component.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function customerSearch(Request $request): JsonResponse
    {
        $data = Customer::whereHas('lead', function($q) use ($request) {
                  $q->where('full_name', 'LIKE', '%' . $request->search . '%');
                })->get();

        $response = [];

        foreach ($data as $val) {
            $response[] = [
                "id" => $val['id'],
                "text" => $val->lead['full_name'] ?? null ,
            ];
        }

        return response()->json($response);
    }

    /**
     * Remove the specified customer from storage.
     *
     * @param int $id
     * @return RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        $customer = Customer::findOrFail($id);
        $customer->delete();

        return redirect('/admin/customer')->with('success', 'Deleted Successfully');
    }

    /**
     * Import customers from Excel/CSV file.
     *
     * @param Request $request
     * @param ExcelImportService $importService
     * @return RedirectResponse
     */
    public function importCustomers(Request $request, ExcelImportService $importService): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240'
        ]);

        // Parse the file
        $excelData = $importService->getData($request->file('file'));

        // Check if file is empty
        if (empty($excelData)) {
            return back()->withErrors(['file' => 'The uploaded file is empty.']);
        }

        $errors = [];
        $validRows = [];
        $rowNumber = 2; // Starting from row 2 (row 1 is header)

        foreach ($excelData as $row) {
            // Validate each row based on customer creation form fields
            $validator = Validator::make($row, [
                'full_name' => ['required', 'string', 'max:255', 'min:2'],
                'phone_number' => ['required', 'string', 'max:20'],
                'age' => ['required', 'integer', 'min:0', 'max:150'],
                'email' => ['nullable', 'email', 'max:255'],
                'address' => ['nullable', 'string', 'max:1000'],
                'gender' => ['nullable', 'in:male,female,other'],
            ]);

            if ($validator->fails()) {
                // Collect errors with row number
                foreach ($validator->errors()->all() as $error) {
                    $errors[] = "Row {$rowNumber}: {$error}";
                }
            } else {
                $validRows[] = $row;
            }

            $rowNumber++;
        }

        // If there are validation errors, return them
        if (!empty($errors)) {
            return back()->withErrors([
                'file' => 'Validation failed for some rows:',
                'rows' => $errors
            ])->withInput();
        }

        // Check for duplicate phone numbers in the file
        $phoneNumbers = array_column($validRows, 'phone_number');
        $duplicates = array_filter(array_count_values($phoneNumbers), fn($count) => $count > 1);

        if (!empty($duplicates)) {
            return back()->withErrors([
                'file' => 'Duplicate phone numbers found in the file: ' . implode(', ', array_keys($duplicates))
            ]);
        }

        // Check for duplicate phone numbers in database
        $existingPhones = Lead::whereIn('phone_number', $phoneNumbers)->pluck('phone_number')->toArray();
        if (!empty($existingPhones)) {
            return back()->withErrors([
                'file' => 'Some phone numbers already exist in the database: ' . implode(', ', $existingPhones)
            ]);
        }

        // Import customers
        DB::beginTransaction();
        try {
            $importedCount = 0;

            foreach ($validRows as $row) {
                // Create Lead first
                $lead = new Lead();
                $lead->full_name = $row['full_name'];
                $lead->email = $row['email'] ?? null;
                $lead->district_id = $row['district_id'] ?? null;
                $lead->address = $row['address'] ?? null;
                $lead->age = $row['age'];
                $lead->gender = $row['gender'] ?? null;
                $lead->phone_number = $row['phone_number'];
                $lead->save();

                // Create Customer
                $customer = new Customer();
                $customer->lead_id = $lead->id;
                $customer->save();

                $importedCount++;
            }

            DB::commit();
            return back()->with('success', "File imported successfully. Total Customers Imported: {$importedCount}");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['file' => 'Error importing customers: ' . $e->getMessage()])->withInput();
        }
    }
}
