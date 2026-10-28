<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\IncomeExpenseHead;
use App\Models\Ledger;
use App\Models\Product;
use App\Models\PurchaseDetail;
use App\Models\PurchaseMaster;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnDetail;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchaseReturnController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:purchase_return.index')->only(['index']);
        $this->middleware('permission:purchase_return.create')->only(['create', 'store', 'getPurchaseItems', 'getPurchasesBySupplier', 'lookupSerial']);
        $this->middleware('permission:purchase_return.view')->only(['show']);
        // Confirming posts the debit note to the supplier ledger, so it needs the same right as undoing it
        $this->middleware('permission:purchase_return.edit')->only(['edit', 'update', 'confirm', 'unconfirm']);
        $this->middleware('permission:purchase_return.delete')->only(['destroy']);
    }

    /**
     * Display a listing of purchase returns (debit notes).
     */
    public function index(Request $request): View
    {
        $query = PurchaseReturn::with(['supplier', 'purchaseMaster', 'vendor', 'creator', 'confirmedBy'])
            ->latest('date')
            ->latest('id');

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('return_no')) {
            $query->where('return_no', 'like', '%' . trim($request->return_no) . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        $allQuery = PurchaseReturn::query();
        if ($request->filled('supplier_id')) {
            $allQuery->where('supplier_id', $request->supplier_id);
        }

        $summary = [
            'total_count' => (clone $allQuery)->count(),
            'total_amount' => (clone $allQuery)->sum('total_amount'),
            'pending_count' => (clone $allQuery)->where('status', 'pending')->count(),
            'pending_amount' => (clone $allQuery)->where('status', 'pending')->sum('total_amount'),
            'confirmed_count' => (clone $allQuery)->where('status', 'confirmed')->count(),
            'accepted_amount' => (clone $allQuery)->where('status', 'confirmed')->sum('accepted_amount'),
            'loss_amount' => (clone $allQuery)->where('status', 'confirmed')->sum('loss_amount'),
            'suppliers_count' => (clone $allQuery)->distinct('supplier_id')->count('supplier_id'),
        ];

        $returns = $query->paginate(20)->withQueryString();
        $suppliers = Supplier::where('status', 1)->orderBy('supplier_name')->get(['id', 'supplier_name']);

        return view('admin.purchase_return.index', compact('returns', 'summary', 'suppliers'));
    }

    /**
     * Show form for creating a new return / debit note.
     */
    public function create(Request $request): View
    {
        $suppliers = Supplier::where('status', 1)->orderBy('supplier_name')->get();
        $selectedPurchase = null;
        $purchases = collect();

        // Check both GET query parameters and session old() inputs (e.g. redirected back after validation failure)
        $purchaseId = $request->input('purchase_id') ?: ($request->hasSession() ? $request->old('purchase_id') : null) ?: old('purchase_id');
        $supplierId = $request->input('supplier_id') ?: ($request->hasSession() ? $request->old('supplier_id') : null) ?: old('supplier_id');

        if ($purchaseId) {
            $selectedPurchase = PurchaseMaster::withoutGlobalScopes()->with(['supplier'])->find($purchaseId);
            if ($selectedPurchase && !$supplierId) {
                $supplierId = $selectedPurchase->supplier_id;
            }
        }

        if ($supplierId) {
            $purchases = PurchaseMaster::withoutGlobalScopes()
                ->where('supplier_id', $supplierId)
                ->latest('date')
                ->take(100)
                ->get(['id', 'purchase_invoice_no', 'supplier_memo_no', 'date', 'total_amount']);
        }

        $products = Product::with('brand')->orderBy('id', 'desc')->get()->map(function ($p) {
            return [
                'id' => $p->id,
                'product_name' => $p->product_name,
                'mrp_price' => (float) ($p->mrp_price ?? 0),
            ];
        });

        // Reconstruct old items if redirected back with validation errors
        $oldItems = [];
        $rawOldItems = ($request->hasSession() ? $request->old('items') : null) ?: old('items');
        if ($rawOldItems && is_array($rawOldItems)) {
            foreach ($rawOldItems as $idx => $oi) {
                $prodId = $oi['product_id'] ?? null;
                $prod = $prodId ? Product::withoutGlobalScopes()->find($prodId) : null;
                $billId = !empty($oi['purchase_master_id']) ? $oi['purchase_master_id'] : null;
                $bill = $billId ? PurchaseMaster::withoutGlobalScopes()->find($billId) : null;

                $serials = [];
                if (!empty($oi['serial_no'])) {
                    if (is_array($oi['serial_no'])) {
                        $serials = array_values(array_filter($oi['serial_no']));
                    } else {
                        $serials = array_values(array_filter(array_map('trim', explode(',', $oi['serial_no']))));
                    }
                }

                $oldItems[] = [
                    'product_id' => $prodId,
                    'product_name' => $prod ? $prod->product_name : ('Product #' . $prodId),
                    'purchase_master_id' => $billId,
                    'purchase_invoice_no' => $bill ? $bill->purchase_invoice_no : '',
                    'serial_no' => $serials,
                    'qty' => (float) ($oi['qty'] ?? (count($serials) > 0 ? count($serials) : 1)),
                    'purchase_price' => (float) ($oi['purchase_price'] ?? ($prod->mrp_price ?? 0)),
                ];
            }
        }

        return view('admin.purchase_return.create', compact('suppliers', 'selectedPurchase', 'purchases', 'products', 'supplierId', 'oldItems'));
    }

    /**
     * AJAX: Get historical purchase bills for a supplier.
     */
    public function getPurchasesBySupplier(Request $request): JsonResponse
    {
        $supplierId = $request->supplier_id;
        if (!$supplierId) {
            return response()->json(['success' => false, 'purchases' => []]);
        }

        $purchases = PurchaseMaster::withoutGlobalScopes()
            ->where('supplier_id', $supplierId)
            ->withCount('purchaseDetails')
            ->latest('date')
            ->take(50)
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'purchase_invoice_no' => $p->purchase_invoice_no,
                    'supplier_memo_no' => $p->supplier_memo_no ?? '',
                    'date' => $p->date ? $p->date->format('d M Y') : '',
                    'total_amount' => (float) $p->total_amount,
                    'items_count' => $p->purchase_details_count,
                ];
            });

        return response()->json([
            'success' => true,
            'purchases' => $purchases,
        ]);
    }

    /**
     * AJAX: Lookup a serial number to find which purchase bill and rate it had.
     */
    public function lookupSerial(Request $request): JsonResponse
    {
        $serial = trim($request->serial_no ?? '');
        $supplierId = $request->supplier_id;

        if (!$serial) {
            return response()->json(['success' => false, 'message' => 'Please enter a serial number.'], 400);
        }

        // Query purchase details where serial_no matches or contains the serial
        $query = PurchaseDetail::withoutGlobalScopes()
            ->with(['purchaseMaster.supplier', 'product'])
            ->where(function ($q) use ($serial) {
                $q->whereJsonContains('serial_no', $serial)
                  ->orWhere('serial_no', 'like', '%' . $serial . '%');
            });

        if ($supplierId) {
            $query->whereHas('purchaseMaster', function ($pq) use ($supplierId) {
                $pq->where('supplier_id', $supplierId);
            });
        }

        $detail = $query->latest('id')->first();

        if (!$detail) {
            return response()->json([
                'success' => false,
                'message' => "Serial '{$serial}' was not found in any purchase record" . ($supplierId ? " for this supplier." : ".")
            ], 404);
        }

        // Check if sold
        $isSold = DB::table('sale_details')
            ->where('product_id', $detail->product_id)
            ->where(function ($sq) use ($serial) {
                $sq->whereJsonContains('serial_no', $serial)
                   ->orWhere('serial_no', 'like', '%' . $serial . '%');
            })
            ->exists();

        if ($isSold) {
            return response()->json([
                'success' => false,
                'message' => "Serial '{$serial}' has already been sold to a customer."
            ], 422);
        }

        // Check if already returned
        $isReturned = DB::table('purchase_return_details')
            ->where('product_id', $detail->product_id)
            ->where(function ($rq) use ($serial) {
                $rq->whereJsonContains('serial_no', $serial)
                   ->orWhere('serial_no', 'like', '%' . $serial . '%');
            })
            ->exists();

        if ($isReturned) {
            return response()->json([
                'success' => false,
                'message' => "Serial '{$serial}' has already been returned in another debit note."
            ], 422);
        }

        // Written off by a stock adjustment (damaged / lost): it is no longer in stock to return
        if (isset(app(\App\Services\StockGuard::class)->adjustedSerials((int) $detail->product_id, null, 'out', true)[\App\Services\StockGuard::key($serial)])) {
            return response()->json([
                'success' => false,
                'message' => "Serial '{$serial}' was written off by a stock adjustment and is not in stock."
            ], 422);
        }

        // Moved to another location by a stock transfer: it can only go back to the supplier from the location that bought it
        $boughtAt = $detail->purchaseMaster->vendor_id ?? null;
        if (isset(app(\App\Services\StockGuard::class)->movedAway((int) $detail->product_id, $boughtAt)[\App\Services\StockGuard::key($serial)])) {
            $place = $boughtAt ? (\App\Models\User::find($boughtAt)?->name ?? 'the buying branch') : 'Head office';
            return response()->json([
                'success' => false,
                'message' => "Serial '{$serial}' is not at {$place} now. Move it back to {$place} first, then return it to the supplier."
            ], 422);
        }

        return response()->json([
            'success' => true,
            'item' => [
                'purchase_master_id' => $detail->purchase_master_id,
                'purchase_invoice_no' => $detail->purchaseMaster->purchase_invoice_no ?? 'Direct',
                'supplier_memo_no' => $detail->purchaseMaster->supplier_memo_no ?? '',
                'supplier_id' => $detail->purchaseMaster->supplier_id ?? $supplierId,
                'supplier_name' => $detail->purchaseMaster->supplier->supplier_name ?? '',
                'purchase_detail_id' => $detail->id,
                'product_id' => $detail->product_id,
                'product_name' => $detail->product->product_name ?? 'Product',
                'purchase_price' => (float) $detail->purchase_price,
                'serial_no' => $serial,
            ]
        ]);
    }

    /**
     * AJAX: Get purchase items with available serial numbers for return.
     */
    public function getPurchaseItems(Request $request): JsonResponse
    {
        $purchaseId = $request->purchase_id;
        $purchase = PurchaseMaster::withoutGlobalScopes()->with(['supplier'])->find($purchaseId);

        if (!$purchase) {
            return response()->json(['success' => false, 'message' => 'Purchase not found'], 404);
        }

        $purchaseDetails = PurchaseDetail::withoutGlobalScopes()
            ->where('purchase_master_id', $purchaseId)
            ->with([
                'product' => function ($pq) {
                    $pq->withoutGlobalScopes();
                }
            ])
            ->get();

        $items = [];
        foreach ($purchaseDetails as $d) {
            $serials = [];
            if (is_array($d->serial_no)) {
                $serials = $d->serial_no;
            } elseif (!empty($d->serial_no)) {
                $decoded = json_decode($d->serial_no, true);
                if (is_array($decoded)) {
                    $serials = $decoded;
                } else {
                    $serials = array_map('trim', explode(',', (string) $d->serial_no));
                }
            }
            $serials = array_values(array_filter(array_map('trim', (array) $serials)));

            $soldSerials = DB::table('sale_details')
                ->where('product_id', $d->product_id)
                ->whereNotNull('serial_no')
                ->pluck('serial_no')
                ->map(function ($s) {
                    if (is_array($s)) return $s;
                    $decoded = json_decode($s, true);
                    if (is_array($decoded)) return $decoded;
                    return array_map('trim', explode(',', (string) $s));
                })
                ->flatten()
                ->filter()
                ->map(fn ($s) => trim((string) $s))
                ->toArray();

            $alreadyReturnedSerials = DB::table('purchase_return_details')
                ->where('product_id', $d->product_id)
                ->whereNotNull('serial_no')
                ->pluck('serial_no')
                ->map(function ($s) {
                    if (is_array($s)) return $s;
                    $decoded = json_decode($s, true);
                    if (is_array($decoded)) return $decoded;
                    return array_map('trim', explode(',', (string) $s));
                })
                ->flatten()
                ->filter()
                ->map(fn ($s) => trim((string) $s))
                ->toArray();

            $writtenOff = app(\App\Services\StockGuard::class)->adjustedSerials((int) $d->product_id, null, 'out', true);
            $movedAway = app(\App\Services\StockGuard::class)->movedAway((int) $d->product_id, $purchase->vendor_id);
            $availableSerials = array_values(array_filter(
                array_diff($serials, $soldSerials, $alreadyReturnedSerials),
                fn ($s) => !isset($writtenOff[\App\Services\StockGuard::key((string) $s)]) && !isset($movedAway[\App\Services\StockGuard::key((string) $s)])
            ));

            $items[] = [
                'purchase_master_id' => $purchase->id,
                'purchase_invoice_no' => $purchase->purchase_invoice_no,
                'supplier_memo_no' => $purchase->supplier_memo_no,
                'purchase_detail_id' => $d->id,
                'product_id' => $d->product_id,
                'product_name' => $d->product->product_name ?? 'Product',
                'purchase_price' => (float) $d->purchase_price,
                'mrp_price' => (float) $d->mrp_price,
                'purchased_qty' => (float) $d->qty,
                'serials' => $availableSerials,
                'has_serials' => count($serials) > 0,
            ];
        }

        return response()->json([
            'success' => true,
            'purchase_id' => $purchase->id,
            'purchase_invoice_no' => $purchase->purchase_invoice_no,
            'supplier_memo_no' => $purchase->supplier_memo_no,
            'supplier_id' => $purchase->supplier_id,
            'supplier_name' => $purchase->supplier->supplier_name ?? '',
            'items' => $items,
        ]);
    }

    /**
     * Store newly created purchase return in PENDING status.
     * Physical stock is deducted because goods have left.
     * NO accounting entry is posted yet.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.purchase_price' => ['required', 'numeric', 'min:0'],
        ]);

        DB::beginTransaction();
        try {
            // Generate unique return number: DN-YYYYMMDD-XXXX
            $datePrefix = date('Ymd', strtotime($request->date));
            $seq = PurchaseReturn::whereDate('date', $request->date)->count() + 1;
            $returnNo = 'DN-' . $datePrefix . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

            // Double check uniqueness (including soft-deleted)
            while (PurchaseReturn::withTrashed()->where('return_no', $returnNo)->exists()) {
                $seq++;
                $returnNo = 'DN-' . $datePrefix . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
            }

            $totalAmount = 0;
            foreach ($request->items as $item) {
                $qty = (float) $item['qty'];
                $price = (float) $item['purchase_price'];
                $totalAmount += ($qty * $price);
            }

            // Check unique purchase bills across all items
            $itemBills = collect($request->items)->pluck('purchase_master_id')->filter()->unique();
            $headerPurchaseId = ($itemBills->count() === 1) ? $itemBills->first() : null;

            $purchaseReturn = new PurchaseReturn();
            $purchaseReturn->return_no = $returnNo;
            $purchaseReturn->purchase_master_id = $headerPurchaseId;
            $purchaseReturn->supplier_id = $request->supplier_id;
            $purchaseReturn->vendor_id = auth()->user()->branchId() ?: null;
            $purchaseReturn->date = $request->date;
            $purchaseReturn->total_amount = $totalAmount;
            $purchaseReturn->accepted_amount = null;
            $purchaseReturn->loss_amount = 0.00;
            $purchaseReturn->supplier_memo_no = $request->supplier_memo_no ? trim($request->supplier_memo_no) : null;
            $purchaseReturn->reason = $request->reason ?: null;
            $purchaseReturn->status = 'pending';
            $purchaseReturn->created_by = auth()->id();
            $purchaseReturn->save();

            // Save details & deduct physical stock
            foreach ($request->items as $item) {
                $qty = (float) $item['qty'];
                $price = (float) $item['purchase_price'];
                $lineTotal = $qty * $price;

                $serials = [];
                if (!empty($item['serial_no'])) {
                    if (is_array($item['serial_no'])) {
                        $serials = array_values(array_filter($item['serial_no']));
                    } else {
                        $serials = array_values(array_filter(array_map('trim', explode(',', $item['serial_no']))));
                    }
                    if (count($serials) > 0) {
                        $qty = count($serials);
                        $lineTotal = $qty * $price;
                    }
                }

                $detail = new PurchaseReturnDetail();
                $detail->purchase_return_id = $purchaseReturn->id;
                $detail->purchase_master_id = !empty($item['purchase_master_id']) ? $item['purchase_master_id'] : null;
                $detail->product_id = $item['product_id'];
                $detail->batch_id = $item['batch_id'] ?? null;
                $detail->serial_no = count($serials) > 0 ? $serials : null;
                $detail->qty = $qty;
                $detail->purchase_price = $price;
                $detail->total_price = $lineTotal;
                $detail->created_by = auth()->id();
                $detail->save();

                // Stock Out (Goods physically dispatched to supplier)
                Helper::stockOut(
                    (int) $item['product_id'],
                    $request->date,
                    $qty,
                    "Purchase Return (Pending Confirmation: {$returnNo})" . ($request->reason ? " — {$request->reason}" : ""),
                    $returnNo,
                    'Purchase Return'
                );
            }

            // Audit log
            ActivityLog::create([
                'user_id' => auth()->id(),
                'subject_type' => PurchaseReturn::class,
                'subject_id' => $purchaseReturn->id,
                'action' => 'created',
                'old_values' => [],
                'new_values' => [
                    'return_no' => $returnNo,
                    'supplier_id' => $request->supplier_id,
                    'total_amount' => $totalAmount,
                    'status' => 'pending',
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return redirect()->route('purchase-returns.show', $purchaseReturn->id)
                ->with('success', "Purchase Return #{$returnNo} created in Pending status. Stock has been deducted. Please confirm with the supplier document when received.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('warning', 'Error saving return: ' . \App\Classes\Helper::userMessage($e));
        }
    }

    /**
     * Show official printable Debit Note document and confirmation modal.
     */
    public function show(int $id): View
    {
        $return = PurchaseReturn::with([
            'supplier',
            'purchaseMaster',
            'vendor',
            'creator',
            'confirmedBy',
            'unconfirmedBy',
            'details.product',
            'details.purchaseMaster',
        ])->findOrFail($id);

        if (!$return->isAccessibleByAuthUser()) {
            abort(403, 'You do not have access to view this purchase return.');
        }

        $setting = Helper::invoiceBranding($return->vendor_id);
        $isSuperAdmin = auth()->user() && auth()->user()->isSuperAdmin();

        return view('admin.purchase_return.show', compact('return', 'setting', 'isSuperAdmin'));
    }

    /**
     * Confirm purchase return with supplier reconciliation document.
     * Posts to accounts (SupplierLedger and Ledger if there is a loss).
     * Fully idempotent with DB transaction and lock.
     */
    public function confirm(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'supplier_doc_no' => ['required', 'string', 'max:500'],
            'supplier_doc_date' => ['required', 'date'],
            'accepted_amount' => ['required', 'numeric', 'min:0'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::beginTransaction();
        try {
            $return = PurchaseReturn::lockForUpdate()->findOrFail($id);

            if (!$return->isAccessibleByAuthUser()) {
                DB::rollBack();
                abort(403, 'Unauthorized.');
            }

            // Idempotency check: Already confirmed?
            if ($return->status === 'confirmed') {
                DB::rollBack();
                return redirect()->route('purchase-returns.show', $return->id)
                    ->with('warning', 'This purchase return has already been confirmed.');
            }

            if ($return->status !== 'pending') {
                DB::rollBack();
                return redirect()->route('purchase-returns.show', $return->id)
                    ->with('warning', 'Only pending purchase returns can be confirmed.');
            }

            $acceptedAmount = round((float) $request->accepted_amount, 2);
            $returnAmount = round((float) $return->total_amount, 2);

            // Validation: Accepted amount cannot be higher than return amount
            if ($acceptedAmount > $returnAmount) {
                DB::rollBack();
                return redirect()->back()
                    ->withInput()
                    ->with('warning', "Accepted amount (৳" . number_format($acceptedAmount, 2) . ") cannot exceed the total return amount (৳" . number_format($returnAmount, 2) . "). This requires review.");
            }

            $lossAmount = max(0, round($returnAmount - $acceptedAmount, 2));
            $supplierDocNo = trim($request->supplier_doc_no);

            // 1. Post to SupplierLedger (reduce supplier payable by accepted amount)
            if ($acceptedAmount > 0) {
                $ledger = new SupplierLedger();
                $ledger->supplier_id = $return->supplier_id;
                $ledger->date = $request->supplier_doc_date;
                $ledger->details = "Debit Note (Return #{$return->return_no}) [Supplier Ref: {$supplierDocNo}]" . ($request->remarks ? " — {$request->remarks}" : "");
                $ledger->debit = $acceptedAmount;
                $ledger->credit = 0;
                $ledger->type = 'Debit Note';
                $ledger->ref_invoice_no = $return->return_no;
                $ledger->supplier_memo_no = $supplierDocNo;
                $ledger->save();
            }

            // 2. Post loss to Ledger (if accepted amount < return amount)
            if ($lossAmount > 0) {
                $head = IncomeExpenseHead::firstOrCreate(
                    ['head_name' => 'Purchase Return Loss', 'head_type' => 'Expense'],
                    ['status' => 1]
                );

                $expenseInvoiceNo = 'PRL-' . date('Ymd', strtotime($request->supplier_doc_date)) . '-' . str_pad((string) $return->id, 4, '0', STR_PAD_LEFT);

                $expenseReq = (object) [
                    'income_expense_head_id' => $head->id,
                    'date' => $request->supplier_doc_date,
                    'paid' => $lossAmount,
                    'details' => "Purchase Return Loss for #{$return->return_no} (Accepted ৳" . number_format($acceptedAmount, 2) . " of ৳" . number_format($returnAmount, 2) . ") [Supplier Ref: {$supplierDocNo}]",
                ];

                Helper::expenseLedger($expenseReq, $expenseInvoiceNo, $return->return_no, 'Purchase Return Loss');
            }

            // File attachment upload
            $attachmentPath = $return->attachment;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $filename = 'pr_' . $return->id . '_' . time() . '.' . Helper::safeUploadExtension($file);
                $file->move(public_path('uploads/purchase_returns'), $filename);
                $attachmentPath = 'uploads/purchase_returns/' . $filename;
            }

            $oldValues = [
                'status' => $return->status,
                'accepted_amount' => $return->accepted_amount,
                'loss_amount' => $return->loss_amount,
            ];

            $return->status = 'confirmed';
            $return->supplier_doc_no = $supplierDocNo;
            $return->supplier_doc_date = $request->supplier_doc_date;
            $return->accepted_amount = $acceptedAmount;
            $return->loss_amount = $lossAmount;
            $return->attachment = $attachmentPath;
            $return->remarks = $request->remarks ?: null;
            $return->confirmed_by = auth()->id();
            $return->confirmed_at = now();
            $return->save();

            // Audit Log
            ActivityLog::create([
                'user_id' => auth()->id(),
                'subject_type' => PurchaseReturn::class,
                'subject_id' => $return->id,
                'action' => 'confirmed',
                'old_values' => $oldValues,
                'new_values' => [
                    'status' => 'confirmed',
                    'supplier_doc_no' => $supplierDocNo,
                    'supplier_doc_date' => $request->supplier_doc_date,
                    'accepted_amount' => $acceptedAmount,
                    'loss_amount' => $lossAmount,
                    'remarks' => $request->remarks,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            $msg = "Purchase Return #{$return->return_no} confirmed successfully! Supplier payable reduced by ৳" . number_format($acceptedAmount, 2);
            if ($lossAmount > 0) {
                $msg .= " and ৳" . number_format($lossAmount, 2) . " posted as Purchase Return Loss.";
            }

            return redirect()->route('purchase-returns.show', $return->id)->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('warning', 'Error confirming return: ' . \App\Classes\Helper::userMessage($e));
        }
    }

    /**
     * Unconfirm a confirmed purchase return (Super Admin only).
     * Reverses all accounting entries (Supplier credit & Loss) and sets status back to Pending.
     */
    public function unconfirm(Request $request, int $id): RedirectResponse
    {
        if (!auth()->user() || !auth()->user()->isSuperAdmin()) {
            abort(403, 'Only Super Admin can unconfirm a purchase return.');
        }

        $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        DB::beginTransaction();
        try {
            $return = PurchaseReturn::lockForUpdate()->findOrFail($id);

            if ($return->status !== 'confirmed') {
                DB::rollBack();
                return redirect()->route('purchase-returns.show', $return->id)
                    ->with('warning', 'Only confirmed purchase returns can be unconfirmed.');
            }

            // 1. Reverse SupplierLedger debit note
            SupplierLedger::where('ref_invoice_no', $return->return_no)
                ->where('type', 'Debit Note')
                ->delete();

            // 2. Reverse General Ledger Purchase Return Loss
            Ledger::where('ref_invoice_no', $return->return_no)
                ->where('type', 'Purchase Return Loss')
                ->delete();

            $oldValues = [
                'status' => 'confirmed',
                'accepted_amount' => $return->accepted_amount,
                'loss_amount' => $return->loss_amount,
                'supplier_doc_no' => $return->supplier_doc_no,
                'supplier_doc_date' => $return->supplier_doc_date ? $return->supplier_doc_date->format('Y-m-d') : null,
            ];

            $return->status = 'pending';
            $return->unconfirm_reason = $request->reason;
            $return->unconfirmed_by = auth()->id();
            $return->unconfirmed_at = now();
            $return->save();

            // Audit log
            ActivityLog::create([
                'user_id' => auth()->id(),
                'subject_type' => PurchaseReturn::class,
                'subject_id' => $return->id,
                'action' => 'unconfirmed',
                'old_values' => $oldValues,
                'new_values' => [
                    'status' => 'pending',
                    'reason' => $request->reason,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return redirect()->route('purchase-returns.show', $return->id)
                ->with('success', "Purchase Return #{$return->return_no} unconfirmed successfully. Accounting entries reversed and status returned to Pending.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('warning', 'Could not unconfirm return: ' . \App\Classes\Helper::userMessage($e));
        }
    }

    /**
     * Show form to edit a pending return (Super Admin only).
     */
    public function edit(int $id): View|RedirectResponse
    {
        if (!auth()->user() || !auth()->user()->isSuperAdmin()) {
            abort(403, 'Only Super Admin can edit purchase returns.');
        }

        $return = PurchaseReturn::with(['details.product', 'details.purchaseMaster', 'supplier'])->findOrFail($id);

        if ($return->status === 'confirmed') {
            return redirect()->route('purchase-returns.show', $return->id)
                ->with('warning', "This return is Confirmed. You must first 'Unconfirm' it with a reason before editing.");
        }

        if ($return->status === 'cancelled') {
            return redirect()->route('purchase-returns.show', $return->id)
                ->with('warning', "Cancelled returns cannot be edited.");
        }

        $suppliers = Supplier::where('status', 1)->orderBy('supplier_name')->get();
        $products = Product::with('brand')->orderBy('id', 'desc')->get()->map(function ($p) {
            return [
                'id' => $p->id,
                'product_name' => $p->product_name,
            ];
        });

        return view('admin.purchase_return.edit', compact('return', 'suppliers', 'products'));
    }

    /**
     * Update a pending return (Super Admin only).
     * Adjusts inventory stock differences between old and new items.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        if (!auth()->user() || !auth()->user()->isSuperAdmin()) {
            abort(403, 'Only Super Admin can update purchase returns.');
        }

        $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'date' => ['required', 'date'],
            'edit_reason' => ['required', 'string', 'min:3', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.purchase_price' => ['required', 'numeric', 'min:0'],
        ]);

        DB::beginTransaction();
        try {
            $return = PurchaseReturn::lockForUpdate()->with('details')->findOrFail($id);

            if ($return->status !== 'pending') {
                DB::rollBack();
                return redirect()->route('purchase-returns.show', $return->id)
                    ->with('warning', "Only Pending purchase returns can be edited.");
            }

            // Old quantities grouped by product
            $oldProductQtys = [];
            foreach ($return->details as $d) {
                $oldProductQtys[$d->product_id] = ($oldProductQtys[$d->product_id] ?? 0) + (float) $d->qty;
            }

            // Calculate new items and new quantities grouped by product
            $newProductQtys = [];
            $totalAmount = 0;
            $newDetailsData = [];

            foreach ($request->items as $item) {
                $qty = (float) $item['qty'];
                $price = (float) $item['purchase_price'];
                $lineTotal = $qty * $price;

                $serials = [];
                if (!empty($item['serial_no'])) {
                    if (is_array($item['serial_no'])) {
                        $serials = array_values(array_filter($item['serial_no']));
                    } else {
                        $serials = array_values(array_filter(array_map('trim', explode(',', $item['serial_no']))));
                    }
                    if (count($serials) > 0) {
                        $qty = count($serials);
                        $lineTotal = $qty * $price;
                    }
                }

                $totalAmount += $lineTotal;
                $newProductQtys[$item['product_id']] = ($newProductQtys[$item['product_id']] ?? 0) + $qty;

                $newDetailsData[] = [
                    'purchase_master_id' => !empty($item['purchase_master_id']) ? $item['purchase_master_id'] : null,
                    'product_id' => $item['product_id'],
                    'batch_id' => $item['batch_id'] ?? null,
                    'serial_no' => count($serials) > 0 ? $serials : null,
                    'qty' => $qty,
                    'purchase_price' => $price,
                    'total_price' => $lineTotal,
                ];
            }

            // Adjust inventory stock difference per product
            $allProductIds = array_unique(array_merge(array_keys($oldProductQtys), array_keys($newProductQtys)));
            foreach ($allProductIds as $pId) {
                $oldQ = $oldProductQtys[$pId] ?? 0;
                $newQ = $newProductQtys[$pId] ?? 0;
                $diff = $newQ - $oldQ;

                if ($diff > 0) {
                    // More items returning to supplier -> stockOut diff
                    Helper::stockOut(
                        (int) $pId,
                        $request->date,
                        $diff,
                        "Purchase Return Edit Adjustment (#{$return->return_no}) — Reason: {$request->edit_reason}",
                        $return->return_no,
                        'Purchase Return Edit'
                    );
                } elseif ($diff < 0) {
                    // Less items returning to supplier -> stockIn abs(diff) back to inventory
                    Helper::stockIn(
                        (int) $pId,
                        $request->date,
                        abs($diff),
                        "Purchase Return Edit Adjustment (#{$return->return_no}) — Reason: {$request->edit_reason}",
                        $return->return_no,
                        'Purchase Return Edit'
                    );
                }
            }

            // Replace details
            $return->details()->delete();
            foreach ($newDetailsData as $row) {
                $detail = new PurchaseReturnDetail($row);
                $detail->purchase_return_id = $return->id;
                $detail->created_by = auth()->id();
                $detail->save();
            }

            $oldValues = [
                'total_amount' => $return->total_amount,
                'supplier_memo_no' => $return->supplier_memo_no,
                'reason' => $return->reason,
            ];

            $return->supplier_id = $request->supplier_id;
            $return->date = $request->date;
            $return->total_amount = $totalAmount;
            $return->supplier_memo_no = $request->supplier_memo_no ? trim($request->supplier_memo_no) : null;
            $return->reason = $request->reason ?: null;
            $return->updated_by = auth()->id();
            $return->save();

            // Audit log
            ActivityLog::create([
                'user_id' => auth()->id(),
                'subject_type' => PurchaseReturn::class,
                'subject_id' => $return->id,
                'action' => 'updated',
                'old_values' => $oldValues,
                'new_values' => [
                    'total_amount' => $totalAmount,
                    'supplier_memo_no' => $return->supplier_memo_no,
                    'reason' => $return->reason,
                    'edit_reason' => $request->edit_reason,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return redirect()->route('purchase-returns.show', $return->id)
                ->with('success', "Purchase Return #{$return->return_no} updated successfully! Stock movements adjusted.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('warning', 'Error updating return: ' . \App\Classes\Helper::userMessage($e));
        }
    }

    /**
     * Delete / Void a pending purchase return (Super Admin only).
     * Reverses stock movements and soft-deletes the record with a reason.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        if (!auth()->user() || !auth()->user()->isSuperAdmin()) {
            abort(403, 'Only Super Admin can delete purchase returns.');
        }

        $request->validate([
            'delete_reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        DB::beginTransaction();
        try {
            $return = PurchaseReturn::lockForUpdate()->with('details')->findOrFail($id);

            if ($return->status === 'confirmed') {
                DB::rollBack();
                return redirect()->back()
                    ->with('warning', "Confirmed purchase returns cannot be deleted directly. You must first 'Unconfirm' the return to reverse accounting entries, then delete.");
            }

            // Revert stock for each returned item
            foreach ($return->details as $detail) {
                Helper::stockIn(
                    $detail->product_id,
                    date('Y-m-d'),
                    $detail->qty,
                    "Revert Purchase Return #{$return->return_no} (Reason: {$request->delete_reason})",
                    $return->return_no,
                    'Purchase Return Void'
                );
            }

            // Safety net: ensure any lingering ledger entries are cleaned up
            SupplierLedger::where('ref_invoice_no', $return->return_no)
                ->where('type', 'Debit Note')
                ->delete();

            Ledger::where('ref_invoice_no', $return->return_no)
                ->where('type', 'Purchase Return Loss')
                ->delete();

            // Record cancellation reason and user before soft delete
            $return->status = 'cancelled';
            $return->delete_reason = $request->delete_reason;
            $return->deleted_by = auth()->id();
            $return->save();

            // Soft-delete details and return record
            $return->details()->delete();
            $return->delete();

            // Audit log
            ActivityLog::create([
                'user_id' => auth()->id(),
                'subject_type' => PurchaseReturn::class,
                'subject_id' => $return->id,
                'action' => 'deleted',
                'old_values' => [
                    'return_no' => $return->return_no,
                    'total_amount' => $return->total_amount,
                    'status' => 'pending',
                ],
                'new_values' => [
                    'status' => 'cancelled',
                    'delete_reason' => $request->delete_reason,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return redirect()->route('purchase-returns.index')
                ->with('success', "Purchase Return #{$return->return_no} voided and reversed successfully. Inventory restored.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('warning', 'Could not void return: ' . \App\Classes\Helper::userMessage($e));
        }
    }
}
