<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\DoctorLedger;
use App\Models\IncomeExpenseHead;
use App\Models\Ledger;
use App\Models\SaleMaster;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DoctorPaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:doctor_payment.index')->only(['index', 'slip']);
        $this->middleware('permission:doctor_payment.create')->only(['create', 'store', 'getDoctorDue', 'getPeriodUnsettled']);
        $this->middleware('permission:doctor_payment.edit')->only(['edit', 'update']);
        $this->middleware('permission:doctor_payment.delete')->only(['destroy']);
    }

    /**
     * Display a listing of doctor payments with KPI summary and filters.
     */
    public function index(Request $request): View
    {
        $query = DoctorLedger::with(['doctor'])
            ->where('type', 'Payment');

        if ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->doctor_id);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        $payments = $query->latest('date')->latest('id')->paginate(20)->withQueryString();

        // Top KPI metrics
        $currentMonthStart = Carbon::now()->startOfMonth()->toDateString();
        $currentMonthEnd = Carbon::now()->endOfMonth()->toDateString();

        $monthPaid = (float) DoctorLedger::where('type', 'Payment')
            ->whereBetween('date', [$currentMonthStart, $currentMonthEnd])
            ->sum('debit');

        $totalPaid = (float) DoctorLedger::where('type', 'Payment')->sum('debit');

        $overallBalances = DoctorLedger::selectRaw('doctor_id, SUM(credit) as cr, SUM(debit) as dr')
            ->groupBy('doctor_id')
            ->get();

        $totalPayable = 0;
        $doctorsWithDue = 0;
        foreach ($overallBalances as $b) {
            $due = (float) $b->cr - (float) $b->dr;
            if ($due > 0.01) {
                $totalPayable += $due;
                $doctorsWithDue++;
            }
        }

        $doctors = Doctor::where('status', 1)->orderBy('name')->get(['id', 'name', 'qualification']);

        return view('admin.doctor_payment.index', compact(
            'payments',
            'doctors',
            'monthPaid',
            'totalPaid',
            'totalPayable',
            'doctorsWithDue'
        ));
    }

    /**
     * Show form for creating a new doctor payment with period settlement tools.
     */
    public function create(Request $request): View
    {
        $doctors = Doctor::where('status', 1)->orderBy('name')->get();
        $selectedDoctorId = $request->query('doctor_id');

        // Generate list of the last 12 calendar months for quick month selection
        $months = [];
        $cursor = Carbon::now()->startOfMonth();
        for ($i = 0; $i < 12; $i++) {
            $months[] = [
                'label' => $cursor->format('F Y'),
                'start' => $cursor->copy()->startOfMonth()->toDateString(),
                'end' => $cursor->copy()->endOfMonth()->toDateString(),
            ];
            $cursor->subMonth();
        }

        return view('admin.doctor_payment.create', compact('doctors', 'months', 'selectedDoctorId'));
    }

    /**
     * AJAX: Get doctor's overall due amount.
     */
    public function getDoctorDue(int $id): JsonResponse
    {
        $balances = DoctorLedger::where('doctor_id', $id)
            ->selectRaw('SUM(credit) as cr, SUM(debit) as dr')
            ->first();

        $cr = (float) ($balances->cr ?? 0);
        $dr = (float) ($balances->dr ?? 0);
        $due = max(0, $cr - $dr);

        return response()->json([
            'success' => true,
            'due_amount' => $due,
            'total_credit' => $cr,
            'total_debit' => $dr,
        ]);
    }

    /**
     * AJAX: Fetch referred sales and unsettled commission breakdown for a period.
     */
    public function getPeriodUnsettled(Request $request): JsonResponse
    {
        $doctorId = (int) $request->input('doctor_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if (!$doctorId || !$startDate || !$endDate) {
            return response()->json(['success' => false, 'message' => 'Missing parameters.'], 422);
        }

        // 1. Fetch referred sales in this period
        $sales = DB::table('sale_masters')
            ->leftJoin('customers', 'sale_masters.customer_id', '=', 'customers.id')
            ->leftJoin('leads', 'customers.lead_id', '=', 'leads.id')
            ->where('sale_masters.reference_by', $doctorId)
            ->whereBetween('sale_masters.date', [$startDate, $endDate])
            ->where('sale_masters.reference_amount', '>', 0)
            ->whereNull('sale_masters.deleted_at')
            ->select([
                'sale_masters.id',
                'sale_masters.date',
                'sale_masters.sale_invoice_no',
                'sale_masters.total_amount',
                'sale_masters.reference_amount as commission_amount',
                DB::raw("COALESCE(leads.full_name, 'Patient') as patient_name"),
                'leads.phone_number as patient_phone',
            ])
            ->orderBy('sale_masters.date', 'desc')
            ->get();

        // 2. Calculate doctor ledger balances for period and prior
        $periodCommission = (float) DoctorLedger::where('doctor_id', $doctorId)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('credit');

        $periodPaid = (float) DoctorLedger::where('doctor_id', $doctorId)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('type', 'Payment')
            ->sum('debit');

        $priorCr = (float) DoctorLedger::where('doctor_id', $doctorId)
            ->where('date', '<', $startDate)
            ->sum('credit');

        $priorDr = (float) DoctorLedger::where('doctor_id', $doctorId)
            ->where('date', '<', $startDate)
            ->sum('debit');

        $priorBalance = $priorCr - $priorDr;

        $overallCr = (float) DoctorLedger::where('doctor_id', $doctorId)->sum('credit');
        $overallDr = (float) DoctorLedger::where('doctor_id', $doctorId)->sum('debit');
        $overallDue = max(0, $overallCr - $overallDr);

        $periodNetPayable = max(0, $periodCommission - $periodPaid);

        return response()->json([
            'success' => true,
            'sales' => $sales,
            'sales_count' => $sales->count(),
            'period_commission' => $periodCommission,
            'period_paid' => $periodPaid,
            'period_net_payable' => $periodNetPayable,
            'prior_balance' => $priorBalance,
            'overall_due' => $overallDue,
            'suggested_amount' => $periodNetPayable > 0 ? $periodNetPayable : $overallDue,
        ]);
    }

    /**
     * Store doctor payment and automatically record to company general ledger.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'doctor_id' => ['required', 'exists:doctors,id'],
            'date' => ['required', 'date'],
            'debit' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'transaction_no' => ['nullable', 'string', 'max:100'],
            'settlement_type' => ['nullable', 'string', 'in:month,custom,general'],
            'settlement_period_start' => ['nullable', 'date'],
            'settlement_period_end' => ['nullable', 'date'],
            'details' => ['nullable', 'string', 'max:500'],
        ]);

        $doctor = Doctor::findOrFail($request->doctor_id);

        DB::beginTransaction();
        try {
            $voucherNo = 'DP-' . date('Ymd') . '-' . str_pad((string) (DoctorLedger::where('type', 'Payment')->count() + 1), 4, '0', STR_PAD_LEFT);

            // Construct meaningful details text
            $periodText = '';
            if ($request->settlement_period_start && $request->settlement_period_end) {
                $pStart = Carbon::parse($request->settlement_period_start);
                $pEnd = Carbon::parse($request->settlement_period_end);
                if ($pStart->format('Y-m') === $pEnd->format('Y-m') && $pStart->day === 1 && $pEnd->day === $pEnd->copy()->endOfMonth()->day) {
                    $periodText = $pStart->format('F Y');
                } else {
                    $periodText = $pStart->format('d M Y') . ' to ' . $pEnd->format('d M Y');
                }
            }

            $detailsParts = [];
            if ($request->filled('details')) {
                $detailsParts[] = trim($request->details);
            }
            if ($periodText) {
                $detailsParts[] = "Settlement for {$periodText}";
            }
            if ($request->payment_method) {
                $detailsParts[] = "Via {$request->payment_method}";
            }
            if ($request->transaction_no) {
                $detailsParts[] = "Ref: {$request->transaction_no}";
            }
            $detailsParts[] = "Voucher #{$voucherNo}";

            $finalDetails = implode(' · ', $detailsParts);

            // 1. Post to Doctor Ledger (Debit reduces Doctor Payable)
            $doctorLedger = new DoctorLedger();
            $doctorLedger->doctor_id = $doctor->id;
            $doctorLedger->vendor_id = auth()->user()->branchId() ?? $doctor->vendor_id;
            $doctorLedger->date = $request->date;
            $doctorLedger->details = $finalDetails;
            $doctorLedger->debit = $request->debit;
            $doctorLedger->credit = 0;
            $doctorLedger->type = 'Payment';
            $doctorLedger->ref_invoice_no = $voucherNo;
            $doctorLedger->payment_method = $request->payment_method;
            $doctorLedger->bank_name = $request->bank_name;
            $doctorLedger->transaction_no = $request->transaction_no;
            $doctorLedger->settlement_type = $request->settlement_type ?: 'general';
            $doctorLedger->settlement_period_start = $request->settlement_period_start;
            $doctorLedger->settlement_period_end = $request->settlement_period_end;
            $doctorLedger->save();

            // 2. Post to General Company Ledger (CRUCIAL FOR PROFIT ACCURACY)
            // Ensure Income/Expense Head exists for Doctor Payment
            $head = IncomeExpenseHead::where('head_type', Helper::$expense)
                ->where(function ($q) {
                    $q->where('head_name', 'like', '%Doctor%')
                      ->orWhere('id', 1);
                })
                ->first();

            $headId = $head ? $head->id : 1;

            $companyLedger = new Ledger();
            $companyLedger->income_expense_head_id = $headId;
            $companyLedger->vendor_id = auth()->user()->branchId() ?? $doctor->vendor_id;
            $companyLedger->invoice_no = date('ymdhis') . rand(10, 99);
            $companyLedger->date = $request->date;
            $companyLedger->details = "Doctor Commission Paid to {$doctor->name}" . ($periodText ? " ({$periodText})" : "") . " [{$request->payment_method}]";
            $companyLedger->expense_amount = $request->debit;
            $companyLedger->income_amount = 0;
            $companyLedger->amount_type = Helper::$expense; // 'Expense'
            $companyLedger->type = 'Doctor Payment';
            $companyLedger->ref_invoice_no = $voucherNo;
            $companyLedger->save();

            DB::commit();

            return redirect()->route('doctors.payment.slip', $doctorLedger->id)
                ->with('success', "Doctor payment of ৳" . number_format($request->debit, 2) . " saved successfully and posted to company accounts!");
        } catch (\Throwable $e) {
            DB::rollback();
            return redirect()->back()->withInput()->with('warning', 'Error saving payment: ' . $e->getMessage());
        }
    }

    /**
     * Show printable payment voucher slip.
     */
    public function slip(int $id): View
    {
        $payment = DoctorLedger::with(['doctor', 'doctor.vendor'])
            ->where('id', $id)
            ->where('type', 'Payment')
            ->firstOrFail();

        $setting = Setting::first();

        // Calculate doctor balance at the time of payment
        $totalCredit = (float) DoctorLedger::where('doctor_id', $payment->doctor_id)
            ->where('date', '<=', $payment->date)
            ->sum('credit');

        $totalDebit = (float) DoctorLedger::where('doctor_id', $payment->doctor_id)
            ->where('date', '<=', $payment->date)
            ->sum('debit');

        $remainingDue = max(0, $totalCredit - $totalDebit);

        // Fetch referred sales in the settlement period if defined
        $settledSales = collect();
        if ($payment->settlement_period_start && $payment->settlement_period_end) {
            $settledSales = DB::table('sale_masters')
                ->leftJoin('customers', 'sale_masters.customer_id', '=', 'customers.id')
                ->leftJoin('leads', 'customers.lead_id', '=', 'leads.id')
                ->where('sale_masters.reference_by', $payment->doctor_id)
                ->whereBetween('sale_masters.date', [$payment->settlement_period_start, $payment->settlement_period_end])
                ->where('sale_masters.reference_amount', '>', 0)
                ->whereNull('sale_masters.deleted_at')
                ->select([
                    'sale_masters.date',
                    'sale_masters.sale_invoice_no',
                    'sale_masters.total_amount',
                    'sale_masters.reference_amount',
                    DB::raw("COALESCE(leads.full_name, 'Patient') as patient_name"),
                ])
                ->orderBy('sale_masters.date')
                ->get();
        }

        return view('admin.doctor_payment.slip', compact('payment', 'setting', 'remainingDue', 'settledSales'));
    }

    /**
     * Remove the specified doctor payment and reverse general ledger expense.
     */
    public function destroy(int $id): RedirectResponse
    {
        $payment = DoctorLedger::where('id', $id)->where('type', 'Payment')->firstOrFail();

        DB::beginTransaction();
        try {
            // Delete corresponding entry in company ledgers
            if (!empty($payment->ref_invoice_no)) {
                Ledger::where('ref_invoice_no', $payment->ref_invoice_no)
                    ->where('type', 'Doctor Payment')
                    ->delete();
            }

            $payment->delete();

            DB::commit();
            return redirect()->route('doctors.payment.index')->with('success', 'Doctor payment deleted and company ledger updated successfully.');
        } catch (\Throwable $e) {
            DB::rollback();
            return redirect()->back()->with('warning', 'Error deleting payment: ' . $e->getMessage());
        }
    }
}
