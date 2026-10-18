<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Doctor;
use App\Models\DoctorLedger;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\SaleDetail;
use App\Models\SaleMaster;
use App\Models\SaleReturn;
use App\Models\SaleReturnDetail;
use App\Models\Setting;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SaleReturnController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:sale_return.index')->only(['index']);
        $this->middleware('permission:sale_return.create')->only(['create', 'store', 'getSaleItems', 'getCustomerInvoices']);
        $this->middleware('permission:sale_return.view')->only(['show']);
        $this->middleware('permission:sale_return.delete')->only(['destroy']);
    }

    /**
     * Display a listing of sales returns (credit notes).
     */
    public function index(Request $request): View
    {
        $query = SaleReturn::with(['customer.lead', 'vendor', 'doctor'])
            ->latest('date')
            ->latest('id');

        // Text Search
        if ($request->filled('search')) {
            $s = trim($request->search);
            $cleanS = ltrim($s, '#');
            $query->where(function ($q) use ($s, $cleanS) {
                $q->where('credit_note_no', 'like', "%{$s}%")
                  ->orWhere('credit_note_no', 'like', "%{$cleanS}%")
                  ->orWhere('sale_invoice_no', 'like', "%{$s}%")
                  ->orWhere('sale_invoice_no', 'like', "%{$cleanS}%")
                  ->orWhereHas('customer', function ($cq) use ($s) {
                      $cq->where('customer_uid', 'like', "%{$s}%")
                         ->orWhereHas('lead', function ($lq) use ($s) {
                             $lq->where('full_name', 'like', "%{$s}%")
                                ->orWhere('phone_number', 'like', "%{$s}%");
                         });
                  });
            });
        }

        // Customer Filter
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Settlement Type Filter
        if ($request->filled('settlement_type')) {
            $query->where('settlement_type', $request->settlement_type);
        }

        // Date range
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        // Branch filter for users who can view all branches
        if ($request->filled('branch')) {
            Helper::applyBranchFilter($query, $request->branch);
        }

        // Summary Statistics (KPI Cards)
        $statQuery = clone $query;
        $totalReturnsCount = $statQuery->count();
        $totalReturnGross = (float) $statQuery->sum('return_subtotal');
        $totalDeductions = (float) $statQuery->sum('deduction_amount');
        $totalCreditIssued = (float) $statQuery->sum('credit_note_amount');
        $cashRefunded = (float) (clone $query)->where('settlement_type', 'cash_refund')->sum('credit_note_amount');

        $saleReturns = $query->paginate(20)->withQueryString();

        $customers = Customer::with('lead:id,full_name,phone_number')
            ->get(['id', 'lead_id', 'customer_uid'])
            ->sortBy('name')
            ->values();

        $branches = User::where('type', 'vendor')->orderBy('name')->get(['id', 'name']);

        return view('admin.sale_return.index', compact(
            'saleReturns',
            'customers',
            'branches',
            'totalReturnsCount',
            'totalReturnGross',
            'totalDeductions',
            'totalCreditIssued',
            'cashRefunded'
        ));
    }

    /**
     * Show the form for creating a new sales return / credit note.
     */
    public function create(Request $request): View
    {
        $relations = [
            'customer' => function ($q) {
                $q->withoutGlobalScopes()->with([
                    'lead' => function ($lq) {
                        $lq->withoutGlobalScopes();
                    }
                ]);
            },
            'doctor' => function ($q) {
                $q->withoutGlobalScopes();
            },
            'saleDetails' => function ($q) {
                $q->withoutGlobalScopes()->with([
                    'product' => function ($pq) {
                        $pq->withoutGlobalScopes()->with('category');
                    },
                    'batch'
                ]);
            },
            'serviceDetails' => function ($q) {
                $q->withoutGlobalScopes()->with('serviceType');
            }
        ];

        $prefilledSale = null;
        if ($request->filled('sale_invoice_no')) {
            $clean = ltrim(trim($request->sale_invoice_no), '#');
            $prefilledSale = SaleMaster::withoutGlobalScopes()
                ->with($relations)
                ->where('sale_invoice_no', $clean)
                ->orWhere('sale_invoice_no', trim($request->sale_invoice_no))
                ->orWhereRaw('LOWER(TRIM(BOTH "#" FROM sale_invoice_no)) = ?', [strtolower($clean)])
                ->first();
        }

        $customers = Customer::withoutGlobalScopes()
            ->with(['lead' => function ($q) {
                $q->withoutGlobalScopes()->select('id', 'full_name', 'phone_number');
            }])
            ->get(['id', 'lead_id', 'customer_uid'])
            ->sortBy('name')
            ->values();

        $recentSales = SaleMaster::withoutGlobalScopes()
            ->with(['customer' => function ($q) {
                $q->withoutGlobalScopes()->with(['lead' => function ($lq) { $lq->withoutGlobalScopes(); }]);
            }])
            ->latest('date')
            ->limit(30)
            ->get(['id', 'sale_invoice_no', 'customer_id', 'date', 'total_amount', 'paid']);

        return view('admin.sale_return.create', compact('prefilledSale', 'customers', 'recentSales'));
    }

    /**
     * AJAX: Fetch invoice details and returnable items (products + services) with unsold serial numbers.
     */
    public function getSaleItems(Request $request): JsonResponse
    {
        $relations = [
            'customer' => function ($q) {
                $q->withoutGlobalScopes()->with([
                    'lead' => function ($lq) {
                        $lq->withoutGlobalScopes();
                    }
                ]);
            },
            'doctor' => function ($q) {
                $q->withoutGlobalScopes();
            },
            'saleDetails' => function ($q) {
                $q->withoutGlobalScopes()->with([
                    'product' => function ($pq) {
                        $pq->withoutGlobalScopes()->with('category');
                    },
                    'batch'
                ]);
            },
            'serviceDetails' => function ($q) {
                $q->withoutGlobalScopes()->with('serviceType');
            }
        ];

        $sale = null;
        if ($request->filled('sale_id')) {
            $sale = SaleMaster::withoutGlobalScopes()
                ->with($relations)
                ->find($request->sale_id);
        } elseif ($request->filled('invoice_no')) {
            $raw = trim($request->invoice_no);
            $clean = ltrim($raw, '#');
            
            // 1. Try exact match without global scope
            $sale = SaleMaster::withoutGlobalScopes()
                ->with($relations)
                ->where('sale_invoice_no', $clean)
                ->orWhere('sale_invoice_no', $raw)
                ->first();

            // 2. Try case-insensitive or trimmed match
            if (!$sale) {
                $sale = SaleMaster::withoutGlobalScopes()
                    ->with($relations)
                    ->whereRaw('LOWER(TRIM(BOTH "#" FROM sale_invoice_no)) = ?', [strtolower($clean)])
                    ->first();
            }

            // 3. Try partial LIKE match if user entered prefix
            if (!$sale && strlen($clean) >= 6) {
                $sale = SaleMaster::withoutGlobalScopes()
                    ->with($relations)
                    ->where('sale_invoice_no', 'like', "%{$clean}%")
                    ->first();
            }
        }

        if (!$sale) {
            return response()->json([
                'success' => false, 
                'message' => 'Sale invoice not found. Please verify the invoice number or select a customer below to pick from their invoices.'
            ], 404);
        }

        // Calculate already returned quantities and serials for this sale
        $existingReturns = SaleReturn::where('sale_master_id', $sale->id)
            ->with('details')
            ->get();

        $alreadyReturnedSerials = [];
        $alreadyReturnedProductQty = [];
        $alreadyReturnedServiceQty = [];

        foreach ($existingReturns as $ret) {
            foreach ($ret->details as $d) {
                if ($d->item_type === 'service' || !empty($d->service_type_id)) {
                    $sKey = $d->service_detail_id ?: ('type_' . $d->service_type_id);
                    $alreadyReturnedServiceQty[$sKey] = ($alreadyReturnedServiceQty[$sKey] ?? 0) + (float) $d->return_qty;
                } else {
                    $alreadyReturnedProductQty[$d->product_id] = ($alreadyReturnedProductQty[$d->product_id] ?? 0) + (float) $d->return_qty;
                    if (!empty($d->serial_no)) {
                        $serials = is_array($d->serial_no) ? $d->serial_no : (json_decode($d->serial_no, true) ?: []);
                        foreach ($serials as $s) {
                            $alreadyReturnedSerials[] = $s;
                        }
                    }
                }
            }
        }

        // 1. Process Physical Products
        $products = [];
        foreach ($sale->saleDetails as $detail) {
            if (!$detail->product) {
                continue;
            }

            $soldQty = (float) $detail->qty;
            $previouslyReturned = (float) ($alreadyReturnedProductQty[$detail->product_id] ?? 0);
            $availableReturnQty = max(0, $soldQty - $previouslyReturned);

            // Available unreturned serial numbers for this specific sale
            $unreturnedSerials = [];
            if (!empty($detail->serial_no)) {
                $soldSerials = is_array($detail->serial_no) ? $detail->serial_no : (json_decode($detail->serial_no, true) ?: []);
                foreach ($soldSerials as $s) {
                    if (!in_array($s, $alreadyReturnedSerials)) {
                        $unreturnedSerials[] = $s;
                    }
                }
            }

            // Net unit selling price (MRP minus discount per unit)
            $unitDiscount = $soldQty > 0 ? ((float) $detail->discount / $soldQty) : 0;
            $netUnitPrice = max(0, (float) $detail->mrp_price - $unitDiscount);

            $products[] = [
                'item_type'           => 'product',
                'sale_detail_id'      => $detail->id,
                'product_id'          => $detail->product_id,
                'product_name'        => $detail->product->product_name ?? ($detail->product->model ?? 'Product #' . $detail->product_id),
                'category_name'       => $detail->product->category?->category_name ?? '',
                'batch_id'            => $detail->batch_id,
                'batch_no'            => $detail->batch?->batch_no ?? null,
                'sold_qty'            => $soldQty,
                'previously_returned' => $previouslyReturned,
                'available_return_qty'=> $availableReturnQty,
                'mrp_price'           => (float) $detail->mrp_price,
                'unit_price'          => round($netUnitPrice, 2),
                'net_unit_price'      => round($netUnitPrice, 2),
                'has_serials'         => !empty($detail->serial_no),
                'available_serials'   => $unreturnedSerials,
                'is_service'          => false,
            ];
        }

        // 2. Process Clinical Services
        $services = [];
        if ($sale->serviceDetails) {
            foreach ($sale->serviceDetails as $svc) {
                $sKey = $svc->id ?: ('type_' . $svc->service_type_id);
                $soldQty = (float) $svc->qty;
                $previouslyReturned = (float) ($alreadyReturnedServiceQty[$sKey] ?? 0);
                $availableReturnQty = max(0, $soldQty - $previouslyReturned);

                $services[] = [
                    'item_type'           => 'service',
                    'service_detail_id'   => $svc->id,
                    'service_type_id'     => $svc->service_type_id,
                    'service_name'        => $svc->serviceType?->service_name ?? 'Clinical Service #' . $svc->service_type_id,
                    'sold_qty'            => $soldQty,
                    'previously_returned' => $previouslyReturned,
                    'available_return_qty'=> $availableReturnQty,
                    'unit_price'          => (float) $svc->service_price,
                    'net_unit_price'      => (float) $svc->service_price,
                    'has_serials'         => false,
                    'available_serials'   => [],
                    'is_service'          => true,
                ];
            }
        }

        $customerName = $sale->customer?->lead?->full_name ?? ($sale->customer?->name ?? 'N/A');
        $customerPhone = $sale->customer?->lead?->phone_number ?? ($sale->customer?->phone ?? 'N/A');
        $due = max(0, (float) $sale->total_amount - (float) $sale->paid);

        return response()->json([
            'success' => true,
            'sale' => [
                'id'               => $sale->id,
                'invoice_no'       => $sale->sale_invoice_no,
                'date'             => $sale->date ? $sale->date->format('Y-m-d') : '',
                'date_formatted'   => $sale->date ? $sale->date->format('d M Y') : '',
                'customer_id'      => $sale->customer_id,
                'customer_name'    => $customerName,
                'customer_phone'   => $customerPhone,
                'total_amount'     => (float) $sale->total_amount,
                'paid'             => (float) $sale->paid,
                'due'              => round($due, 2),
                'doctor_id'        => $sale->reference_by,
                'doctor_name'      => $sale->doctor?->name ?? null,
                'doctor_commission'=> (float) $sale->reference_amount,
            ],
            'products' => $products,
            'services' => $services,
            'items'    => array_merge($products, $services),
        ]);
    }

    /**
     * AJAX: Fetch list of recent sales for a given customer_id
     */
    public function getCustomerInvoices(Request $request): JsonResponse
    {
        $customerId = $request->customer_id;
        if (!$customerId) {
            return response()->json(['success' => false, 'invoices' => []]);
        }

        $sales = SaleMaster::withoutGlobalScopes()
            ->where('customer_id', $customerId)
            ->latest('date')
            ->latest('id')
            ->limit(50)
            ->get(['id', 'sale_invoice_no', 'date', 'total_amount', 'paid']);

        $invoices = $sales->map(function ($s) {
            $due = max(0, (float) $s->total_amount - (float) $s->paid);
            $formattedDate = $s->date ? $s->date->format('d M Y') : 'N/A';
            return [
                'id'         => $s->id,
                'invoice_no' => $s->sale_invoice_no,
                'date'       => $formattedDate,
                'total'      => (float) $s->total_amount,
                'paid'       => (float) $s->paid,
                'due'        => round($due, 2),
                'label'      => "#{$s->sale_invoice_no} ({$formattedDate}) — ৳" . number_format($s->total_amount, 2) . ($due > 0 ? " [Due: ৳" . number_format($due, 2) . "]" : " [Paid]"),
            ];
        });

        return response()->json([
            'success'  => true,
            'invoices' => $invoices,
        ]);
    }

    /**
     * Store a newly created sales return / credit note in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'customer_id'       => ['required', 'exists:customers,id'],
            'date'              => ['required', 'date'],
            'sale_invoice_no'   => ['nullable', 'string', 'max:100'],
            'settlement_type'   => ['required', 'in:due_reduction,store_credit,cash_refund'],
            'items'             => ['required', 'array', 'min:1'],
            'items.*.item_type' => ['nullable', 'in:product,service'],
            'items.*.return_qty'=> ['nullable', 'numeric', 'min:0'],
            'items.*.unit_price'=> ['nullable', 'numeric', 'min:0'],
            'reason'            => ['nullable', 'string', 'max:1000'],
        ]);

        // Filter items that have check = 1 or return_qty > 0
        $selectedItems = array_filter($request->items, function ($item) {
            return !empty($item['selected']) && (float) ($item['return_qty'] ?? 0) > 0;
        });

        if (empty($selectedItems)) {
            return redirect()->back()
                ->withInput()
                ->with('warning', 'Please select at least one product or clinical service to return with quantity greater than zero.');
        }

        DB::beginTransaction();
        try {
            // Find linked SaleMaster if invoice provided (ignore branch scope & clean #)
            $saleMaster = null;
            if ($request->filled('sale_invoice_no')) {
                $clean = ltrim(trim($request->sale_invoice_no), '#');
                $saleMaster = SaleMaster::withoutGlobalScopes()
                    ->where('sale_invoice_no', $clean)
                    ->orWhere('sale_invoice_no', trim($request->sale_invoice_no))
                    ->orWhereRaw('LOWER(TRIM(BOTH "#" FROM sale_invoice_no)) = ?', [strtolower($clean)])
                    ->first();
            }

            // Generate unique Credit Note Number: CN-YYYYMMDD-XXXX (include withTrashed to prevent collisions)
            $datePrefix = date('Ymd', strtotime($request->date));
            $lastToday = SaleReturn::withTrashed()
                ->where('credit_note_no', 'like', "CN-{$datePrefix}-%")
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();

            $seq = 1;
            if ($lastToday && preg_match('/CN-\d{8}-(\d+)/', $lastToday->credit_note_no, $m)) {
                $seq = (int) $m[1] + 1;
            }
            $creditNoteNo = sprintf("CN-%s-%04d", $datePrefix, $seq);

            // Compute return totals
            $returnSubtotal = 0;
            $totalDeductions = 0;

            foreach ($selectedItems as $item) {
                $qty = (float) $item['return_qty'];
                $price = (float) $item['unit_price'];
                $lineGross = $qty * $price;

                $deductionRate = (float) ($item['deduction_rate'] ?? 0);
                $deductionAmount = (float) ($item['deduction_amount'] ?? 0);

                // If user entered deduction rate % and no direct deduction amount
                if ($deductionRate > 0 && $deductionAmount <= 0) {
                    $deductionAmount = round(($lineGross * $deductionRate) / 100, 2);
                }

                $returnSubtotal += $lineGross;
                $totalDeductions += $deductionAmount;
            }

            $creditNoteAmount = max(0, $returnSubtotal - $totalDeductions);

            // -------------------------------------------------------------
            // Smart Financial Allocation Correlation Engine:
            // Bucket A: Clears unpaid Due on this invoice first
            // Bucket B: Remaining balance after clearing Due (Refundable or Store Credit)
            // Golden Rule: A patient can NEVER be refunded more cash than they actually paid!
            // -------------------------------------------------------------
            $dueAtReturn = 0;
            $paidAtReturn = 0;
            if ($saleMaster) {
                $dueAtReturn = max(0, round((float) $saleMaster->total_amount - (float) $saleMaster->paid, 2));
                $paidAtReturn = max(0, (float) $saleMaster->paid);
            }

            $dueReducedAmount = min($creditNoteAmount, $dueAtReturn);
            $excessCredit = max(0, $creditNoteAmount - $dueReducedAmount);
            $maxRefundableCash = min($excessCredit, $paidAtReturn);

            $patientRefundAmount = 0;
            if ($request->settlement_type === 'cash_refund') {
                $patientRefundAmount = $maxRefundableCash;
            }

            // Branch scoping
            $vendorId = auth()->user()->branchId() ?: ($saleMaster?->vendor_id ?: null);

            // Create SaleReturn Master
            $saleReturn = new SaleReturn();
            $saleReturn->credit_note_no = $creditNoteNo;
            $saleReturn->sale_master_id = $saleMaster?->id;
            $saleReturn->sale_invoice_no = $saleMaster?->sale_invoice_no ?: $request->sale_invoice_no;
            $saleReturn->customer_id = $request->customer_id;
            $saleReturn->vendor_id = $vendorId;
            $saleReturn->date = $request->date;
            $saleReturn->return_subtotal = $returnSubtotal;
            $saleReturn->deduction_amount = $totalDeductions;
            $saleReturn->credit_note_amount = $creditNoteAmount;
            $saleReturn->due_amount_at_return = $dueAtReturn;
            $saleReturn->due_reduced_amount = $dueReducedAmount;
            $saleReturn->patient_refund_amount = $patientRefundAmount;
            $saleReturn->settlement_type = $request->settlement_type;
            $saleReturn->payment_type_id = ($request->settlement_type === 'cash_refund' && $patientRefundAmount > 0) ? ($request->payment_type_id ?: 1) : null;
            $saleReturn->doctor_id = $saleMaster?->reference_by;
            $saleReturn->doctor_commission_adjusted = (float) ($request->doctor_commission_adjusted ?? 0);
            $saleReturn->reason = $request->reason;
            $saleReturn->status = 'completed';
            $saleReturn->created_by = auth()->id();
            $saleReturn->save();

            // Save Return Details (Differentiating Physical Products vs Clinical Services)
            foreach ($selectedItems as $item) {
                $itemType = ($item['item_type'] ?? 'product') === 'service' ? 'service' : 'product';
                $qty = (float) $item['return_qty'];
                $price = (float) $item['unit_price'];
                $lineGross = $qty * $price;
                $deductionRate = (float) ($item['deduction_rate'] ?? 0);
                $deductionAmount = (float) ($item['deduction_amount'] ?? 0);
                if ($deductionRate > 0 && $deductionAmount <= 0) {
                    $deductionAmount = round(($lineGross * $deductionRate) / 100, 2);
                }
                $netLine = max(0, $lineGross - $deductionAmount);

                $detail = new SaleReturnDetail();
                $detail->sale_return_id = $saleReturn->id;
                $detail->item_type = $itemType;
                $detail->sold_qty = (float) ($item['sold_qty'] ?? $qty);
                $detail->return_qty = $qty;
                $detail->unit_price = $price;
                $detail->deduction_rate = $deductionRate;
                $detail->deduction_amount = $deductionAmount;
                $detail->net_return_amount = $netLine;
                $detail->created_by = auth()->id();

                if ($itemType === 'product') {
                    // Physical Product Detail
                    $detail->sale_detail_id = $item['sale_detail_id'] ?? null;
                    $detail->product_id = $item['product_id'] ?? null;
                    $detail->batch_id = $item['batch_id'] ?? null;

                    // Serial numbers
                    $serials = [];
                    if (!empty($item['serial_no'])) {
                        if (is_array($item['serial_no'])) {
                            $serials = array_values(array_filter($item['serial_no']));
                        } else {
                            $serials = array_values(array_filter(array_map('trim', explode(',', $item['serial_no']))));
                        }
                    }
                    $detail->serial_no = count($serials) > 0 ? $serials : null;
                    $detail->save();

                    // 1. Restock Product in Inventory (Stock In)
                    if (!empty($item['product_id'])) {
                        Helper::stockIn(
                            $item['product_id'],
                            $request->date,
                            $qty,
                            "Sales Return Credit Note {$creditNoteNo}" . ($saleReturn->sale_invoice_no ? " (Sale #{$saleReturn->sale_invoice_no})" : ''),
                            $creditNoteNo,
                            Helper::$returnToCustomer
                        );
                    }

                    // 2. Update Batch Return Qty if applicable
                    if (!empty($item['batch_id'])) {
                        $batch = Batch::find($item['batch_id']);
                        if ($batch) {
                            $batch->sale_return_qty = (float) ($batch->sale_return_qty ?? 0) + $qty;
                            $batch->save();
                        }
                    }
                } else {
                    // Clinical Service Detail (Intangible - ZERO Stock Movement)
                    $detail->service_detail_id = $item['service_detail_id'] ?? null;
                    $detail->service_type_id = $item['service_type_id'] ?? null;
                    $detail->product_id = null;
                    $detail->batch_id = null;
                    $detail->serial_no = null;
                    $detail->save();
                }
            }

            // -------------------------------------------------------------
            // Accounting & Settlement Integration:
            // -------------------------------------------------------------
            // 1. Always post Credit Note into Customer Ledger (Accounts Receivable credit / Store Credit)
            $cLedger = new CustomerLedger();
            $cLedger->customer_id = $saleReturn->customer_id;
            $cLedger->date = $saleReturn->date;
            $cLedger->details = "Credit Note #{$creditNoteNo}" . ($saleReturn->sale_invoice_no ? " for Sale #{$saleReturn->sale_invoice_no}" : '');
            $cLedger->debit = 0;
            $cLedger->credit = $creditNoteAmount;
            $cLedger->type = 'Credit Note';
            $cLedger->ref_invoice_no = $creditNoteNo;
            $cLedger->save();

            // 2. Bucket A: If there was unpaid Due on this invoice, reduce the invoice total amount
            if ($dueReducedAmount > 0 && $saleMaster) {
                $newTotal = max(0, round($saleMaster->total_amount - $dueReducedAmount, 2));
                $saleMaster->total_amount = $newTotal;
                if (round($saleMaster->total_amount - $saleMaster->paid, 2) <= 0.009) {
                    $saleMaster->due_date = null;
                }
                $saleMaster->save();
            }

            // 3. Bucket B: Cash/Bank Refund Payout (if requested and patient actually paid cash)
            if ($patientRefundAmount > 0) {
                // Post refund debit in CustomerLedger to balance the credit
                $rLedger = new CustomerLedger();
                $rLedger->customer_id = $saleReturn->customer_id;
                $rLedger->date = $saleReturn->date;
                $rLedger->details = "Cash Refund Payout for Credit Note #{$creditNoteNo}";
                $rLedger->debit = $patientRefundAmount;
                $rLedger->credit = 0;
                $rLedger->type = 'Refund Payout';
                $rLedger->ref_invoice_no = $creditNoteNo;
                $rLedger->save();

                // Log cash payout expense in general accounting Ledger
                $exp = new Ledger();
                $exp->date = $saleReturn->date;
                $exp->details = "Customer Refund Payout for Credit Note #{$creditNoteNo}";
                $exp->expense_amount = $patientRefundAmount;
                $exp->income_amount = 0;
                $exp->amount_type = Helper::$expense;
                $exp->type = 'Customer Refund';
                $exp->ref_invoice_no = $creditNoteNo;
                $exp->vendor_id = $vendorId;
                $exp->save();
            }

            // 4. Handle Doctor Commission Clawback / Adjustment
            if (!empty($saleReturn->doctor_id) && $saleReturn->doctor_commission_adjusted > 0) {
                $dLedger = new DoctorLedger();
                $dLedger->doctor_id = $saleReturn->doctor_id;
                $dLedger->date = $saleReturn->date;
                $dLedger->details = "Commission Adjustment for Return #{$creditNoteNo}" . ($saleReturn->sale_invoice_no ? " (Sale #{$saleReturn->sale_invoice_no})" : '');
                $dLedger->debit = $saleReturn->doctor_commission_adjusted;
                $dLedger->credit = 0;
                $dLedger->type = 'Commission Adjustment';
                $dLedger->ref_invoice_no = $creditNoteNo;
                $dLedger->save();
            }

            DB::commit();

            return redirect()->route('sale-returns.show', $saleReturn->id)
                ->with('success', "Credit Note #{$creditNoteNo} generated successfully.");
        } catch (\Exception $ex) {
            DB::rollBack();
            return redirect()->back()
                ->withInput()
                ->with('warning', 'Failed to process sales return: ' . \App\Classes\Helper::userMessage($ex));
        }
    }

    /**
     * Display the specified credit note voucher.
     */
    public function show(int $id): View
    {
        $saleReturn = SaleReturn::withoutGlobalScopes()->with([
            'customer' => function ($q) {
                $q->withoutGlobalScopes()->with(['lead' => function ($lq) { $lq->withoutGlobalScopes(); }]);
            },
            'saleMaster' => function ($q) {
                $q->withoutGlobalScopes();
            },
            'vendor',
            'doctor' => function ($q) {
                $q->withoutGlobalScopes();
            },
            'creator',
            'details' => function ($q) {
                $q->withoutGlobalScopes()->with([
                    'product' => function ($pq) {
                        $pq->withoutGlobalScopes()->with('brand');
                    },
                    'batch',
                    'serviceType',
                    'serviceDetail'
                ]);
            }
        ])->findOrFail($id);

        if (!$saleReturn->isAccessibleByAuthUser()) {
            abort(403, 'Unauthorized access to this credit note.');
        }

        $setting = Setting::first();

        return view('admin.sale_return.show', compact('saleReturn', 'setting'));
    }

    /**
     * Remove / void the specified sales return.
     */
    public function destroy(int $id): RedirectResponse
    {
        $saleReturn = SaleReturn::with('details')->findOrFail($id);

        if (!$saleReturn->isAccessibleByAuthUser()) {
            abort(403, 'Unauthorized to delete this credit note.');
        }

        DB::beginTransaction();
        try {
            $creditNoteNo = $saleReturn->credit_note_no;

            // 1. Revert Stock: Only restocked physical products are deducted back out
            foreach ($saleReturn->details as $d) {
                if ($d->isProduct() && !empty($d->product_id)) {
                    Helper::stockOut(
                        $d->product_id,
                        date('Y-m-d'),
                        $d->return_qty,
                        "Reversal of Voided Credit Note {$creditNoteNo}",
                        $creditNoteNo,
                        Helper::$stockout
                    );

                    if (!empty($d->batch_id)) {
                        $batch = Batch::find($d->batch_id);
                        if ($batch) {
                            $batch->sale_return_qty = max(0, (float) ($batch->sale_return_qty ?? 0) - (float) $d->return_qty);
                            $batch->save();
                        }
                    }
                }
            }

            // 2. Remove Customer Ledger entries
            CustomerLedger::where('ref_invoice_no', $creditNoteNo)->delete();

            // 3. Remove General Ledger entries (if cash refund was logged)
            Ledger::where('ref_invoice_no', $creditNoteNo)->delete();

            // 4. Remove Doctor Ledger adjustment (if any)
            DoctorLedger::where('ref_invoice_no', $creditNoteNo)->delete();

            // 5. Restore SaleMaster invoice amount if due was reduced
            if ($saleReturn->due_reduced_amount > 0 && $saleReturn->saleMaster) {
                $sale = $saleReturn->saleMaster;
                $sale->total_amount = round($sale->total_amount + $saleReturn->due_reduced_amount, 2);
                $sale->save();
            }

            // 6. Delete return details and master
            $saleReturn->details()->delete();
            $saleReturn->delete();

            DB::commit();

            return redirect()->route('sale-returns.index')
                ->with('success', "Credit Note #{$creditNoteNo} voided and stock/ledger reversed successfully.");
        } catch (\Exception $ex) {
            DB::rollBack();
            return redirect()->back()->with('warning', 'Failed to void credit note: ' . \App\Classes\Helper::userMessage($ex));
        }
    }
}
