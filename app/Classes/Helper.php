<?php

namespace App\Classes;

use App\Models\Batch;
use App\Models\CustomerLedger;
use App\Models\DoctorLedger;
use App\Models\Ledger;
use App\Models\MembershipTier;
use App\Models\Product;
use App\Models\PurchaseDetail;
use App\Models\SaleDetail;
use App\Models\SaleMaster;
use App\Models\SupplierLedger;
use App\Models\Stock;
use Gate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Helper class provides utility functions for file uploads, ledger operations, stock management, batch handling, and more.
 *
 * @package App\Classes
 */
class Helper
{
    /** @var string */
    public static string $purchase = 'Purchase';
    /** @var string */
    public static string $sales = 'Sales';
    /** @var string */
    public static string $income = 'Income';
    /** @var string */
    public static string $expense = 'Expense';
    /** @var string */
    public static string $both = 'Both';
    /** @var string */
    public static string $supplier = 'Supplier';
    /** @var string */
    public static string $wastage = 'Wastage';
    /** @var string */
    public static string $returnToSupplier = 'ReturnToSupplier';
    /** @var string */
    public static string $returnToCustomer = 'ReturnToCustomer';
    /** @var string */
    public static string $salarySetup = 'SalarySetup';
    /** @var string */
    public static string $salaryPayment = 'SalaryPayment';
    /** @var string */
    public static string $stockout = 'stock-out';
    /** @var string */
    public static string $stockall = 'stock-all';
    /** @var string */
    public static string $receive = 'Receive';
    /** @var string */
    public static string $payment = 'Payment';
    /** @var string */
    public static string $itemCode = '111';

    /**
     * Uploads a file to the specified path.
     *
     * @param UploadedFile|null $file
     * @param string $uploadPath
     * @return string|null URL of the uploaded file or null if no file
     */
    public static function fileUpload(?UploadedFile $file, string $uploadPath): ?string
    {
        if ($file) {
            $extension = $file->getClientOriginalExtension();
            $name = Str::uuid() . '.' . $extension;
            $file->move(public_path($uploadPath), $name);
            $fileUrl = $uploadPath . $name;

            return $fileUrl;
        }
        
        return null;
    }

    /**
     * Normalise a phone number to a canonical digits-only form for duplicate detection.
     * Bangladesh mobiles become 8801XXXXXXXXX (13 digits): 017.., +88017.., 0088017.., 17.. all match.
     * Other numbers are returned as plain digits. Returns null when no digits are present.
     *
     * @param string|null $phone
     * @return string|null
     */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '' || $digits === null) {
            return null;
        }
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '01')) {
            return '88' . $digits;
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '1')) {
            return '880' . $digits;
        }

        return substr($digits, 0, 20);
    }

    /**
     * Build the branding (logo, name, address, contact) used on invoices/printouts.
     *
     * Returns an unsaved copy of the company Setting (id 1). When the document
     * belongs to a vendor branch, the branch's own logo, name, address, mobile and
     * email override the company values field-by-field (empty branch fields fall
     * back to the company Settings). Never persisted.
     *
     * @param int|null $vendorId
     * @return \App\Models\Setting
     */
    public static function invoiceBranding(?int $vendorId = null): \App\Models\Setting
    {
        $base = \App\Models\Setting::where('id', 1)->first() ?? new \App\Models\Setting();
        $branding = $base->replicate();
        $branding->id = $base->id;
        $branding->is_branch = false;

        if ($vendorId) {
            $vendor = \App\Models\User::where('type', 'vendor')->find($vendorId);
            if ($vendor) {
                $map = [
                    'logo'         => $vendor->logo,
                    'company_name' => $vendor->name,
                    'address'      => $vendor->address,
                    'mobile'       => $vendor->mobile_no,
                    'phone'        => $vendor->mobile_no,
                    'email'        => $vendor->email,
                ];
                foreach ($map as $field => $value) {
                    if (!empty($value)) {
                        $branding->{$field} = $value;
                    }
                }
                $branding->is_branch = true;
            }
        }

        return $branding;
    }

    /**
     * Returns available ledger filter types.
     *
     * @return array
     */
    public static function ledgerFilterTypes(): array
    {
        return [self::$income, self::$expense, self::$both];
    }

    /**
     * Returns available payment types.
     *
     * @return array
     */
    public static function paymentType(): array
    {
        return [
            '4' => 'Credit / Pay Later (On Account)',
            '1' => 'Cash Payment',
            '2' => 'Bank Deposit / Cheque',
            '3' => 'Online Transfer',
        ];
    }

    /**
     * Returns available supplier payment disbursement methods.
     *
     * @return array
     */
    public static function supplierPaymentMethods(): array
    {
        return [
            'Cash' => 'Cash in Hand',
            'Bank Deposit' => 'Bank Deposit / Cheque',
            'Online Transfer' => 'Online Transfer (EFT / RTGS / NPSB / MFS)',
        ];
    }

    /**
     * Convert currency number into English words (e.g. 50000 -> Fifty Thousand Taka Only).
     *
     * @param float|int $number
     * @return string
     */
    public static function numberToWords($number): string
    {
        $number = (float) $number;
        if ($number <= 0) {
            return 'Zero Taka Only';
        }

        $ones = [
            1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
            6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
            11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
            16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen'
        ];
        $tens = [
            2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
            6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety'
        ];

        $convertGroup = function ($n) use (&$convertGroup, $ones, $tens) {
            $str = '';
            if ($n >= 100) {
                $str .= $ones[(int)($n / 100)] . ' Hundred ';
                $n %= 100;
            }
            if ($n >= 20) {
                $str .= $tens[(int)($n / 10)] . ' ';
                $n %= 10;
            }
            if ($n > 0) {
                $str .= $ones[$n] . ' ';
            }
            return trim($str);
        };

        $taka = (int) $number;
        $poisha = (int) round(($number - $taka) * 100);

        // Indian / Bangladeshi numbering: Crores (10,000,000), Lakhs (100,000), Thousands (1,000)
        $crore = (int) ($taka / 10000000);
        $taka %= 10000000;
        $lakh = (int) ($taka / 100000);
        $taka %= 100000;
        $thousand = (int) ($taka / 1000);
        $taka %= 1000;
        $hundred = $taka;

        $words = [];
        if ($crore > 0) {
            $words[] = $convertGroup($crore) . ' Crore';
        }
        if ($lakh > 0) {
            $words[] = $convertGroup($lakh) . ' Lakh';
        }
        if ($thousand > 0) {
            $words[] = $convertGroup($thousand) . ' Thousand';
        }
        if ($hundred > 0) {
            $words[] = $convertGroup($hundred);
        }

        $result = implode(' ', $words) . ' Taka';
        if ($poisha > 0) {
            $result .= ' and ' . $convertGroup($poisha) . ' Poisha';
        }

        return trim($result) . ' Only';
    }

    /**
     * Returns available transaction types.
     *
     * @return array
     */
    public static function transactionType(): array
    {
        return [self::$receive, self::$payment];
    }

    /**
     * Returns available blood groups.
     *
     * @return array
     */
    public static function bloodGroup(): array
    {
        return ['A+', 'O+', 'B+', 'AB+', 'A-', 'O-', 'B-', 'AB-'];
    }

    /**
     * Returns available salary types.
     *
     * @return array
     */
    public static function salaryType(): array
    {
        return ['Full Time', 'Hourly'];
    }

    /**
     * Returns available user types.
     *
     * @return array
     */
    public static function UserType(): array
    {
        return ['Admin', 'User'];
    }

    /**
     * Returns available ledger types.
     *
     * @return array
     */
    public static function ledgerType(): array
    {
        return [self::$income, self::$expense];
    }

    /**
     * Adds a credit entry to the supplier ledger.
     *
     * @param object $request
     * @param string $refInvoiceNo
     * @param string $type
     * @return bool
     */
    public static function supplierLedgerCredit(object $request, string $refInvoiceNo, string $type): bool
    {
        $mLedger = new SupplierLedger();
        $mLedger->supplier_id = $request->supplier_id;
        $mLedger->date = $request->date;
        $memoSuffix = !empty($request->supplier_memo_no) ? ' [Cash Memo: ' . $request->supplier_memo_no . ']' : '';
        $mLedger->details = $request->details ? $request->details . " (Purchase invoice no - {$refInvoiceNo}){$memoSuffix}" : "Purchase invoice no - {$refInvoiceNo}{$memoSuffix}";
        $mLedger->debit = 0;
        $mLedger->credit = $request->total_amount;
        $mLedger->type = $type;
        $mLedger->ref_invoice_no = $refInvoiceNo;
        $mLedger->supplier_memo_no = $request->supplier_memo_no ?? null;
        $mLedger->save();

        return true;
    }

    /**
     * Adds a debit entry to the supplier ledger.
     *
     * @param object $request
     * @param string $refInvoiceNo
     * @param string $type
     * @return bool
     */
    public static function supplierLedgerDebit(object $request, string $refInvoiceNo, string $type): bool
    {
        $mLedger = new SupplierLedger();
        $mLedger->supplier_id = $request->supplier_id;
        $mLedger->date = $request->date;
        $memoSuffix = !empty($request->supplier_memo_no) ? ' [Memo/Slip: ' . $request->supplier_memo_no . ']' : '';
        $mLedger->details = $request->details ? $request->details . " (Payment ref no - {$refInvoiceNo}){$memoSuffix}" : "Payment ref no - {$refInvoiceNo}{$memoSuffix}";
        $mLedger->debit = $request->paid ?? $request->paid_amount ?? 0;
        $mLedger->credit = 0;
        $mLedger->type = $type;
        $mLedger->ref_invoice_no = $refInvoiceNo;
        $mLedger->supplier_memo_no = $request->supplier_memo_no ?? null;
        if (!empty($request->payment_method)) {
            $mLedger->payment_method = $request->payment_method;
        }
        if (!empty($request->transaction_no)) {
            $mLedger->transaction_no = $request->transaction_no;
        }
        $mLedger->save();

        return true;
    }

    /**
     * Adds a debit entry to the customer ledger.
     *
     * @param object $request
     * @param string $refInvoiceNo
     * @param string $type
     * @return bool
     */
    public static function customerLedgerDebit(object $request, string $refInvoiceNo, string $type): bool
    {
        $cLedger = new CustomerLedger();
        $cLedger->customer_id = $request->customer_id;
        $cLedger->date = $request->date;
        $cLedger->details = $request->details;
        $cLedger->debit = $request->total_amount;
        $cLedger->credit = 0;
        $cLedger->type = $type;
        $cLedger->ref_invoice_no = $refInvoiceNo;
        $cLedger->save();

        return true;
    }

    /**
     * Adds a credit entry to the customer ledger.
     *
     * @param object $request
     * @param string $refInvoiceNo
     * @param string $type
     * @return bool
     */
    public static function customerLedgerCredit(object $request, string $refInvoiceNo, string $type): bool
    {
        $cLedger = new CustomerLedger();
        $cLedger->customer_id = $request->customer_id;
        $cLedger->date = $request->date;
        $cLedger->details = $request->details;
        $cLedger->debit = 0;
        $cLedger->credit = $request->paid;
        $cLedger->type = $type;
        $cLedger->ref_invoice_no = $refInvoiceNo;
        $cLedger->save();

        return true;
    }

    /**
     * Adds stock in entry for a product.
     *
     * @param int $product_id
     * @param string $date
     * @param float $qty
     * @param string $details
     * @param string $refInvoiceNo
     * @param string $type
     * @return bool
     */
    public static function stockIn(int $product_id, string $date, float $qty, ?string $details, string $refInvoiceNo, string $type): bool
    {
        $stock = new Stock();
        $stock->product_id = $product_id;
        $stock->date = $date;
        $stock->in_qty = $qty;
        $stock->out_qty = 0;
        $stock->details = $details;
        $stock->type = $type;
        $stock->ref_invoice_no = $refInvoiceNo;

        if (auth()->check() && auth()->user()->branchId()) {
            $stock->vendor_id = auth()->user()->branchId();
        }
        
        $stock->save();

        return true;
    }

    /**
     * Adds stock out entry for a product.
     *
     * @param int $product_id
     * @param string $date
     * @param float $qty
     * @param string $details
     * @param string $refInvoiceNo
     * @param string $type
     * @return bool
     */
    public static function stockOut(int $product_id, string $date, float $qty, ?string $details, string $refInvoiceNo, string $type): bool
    {
        $stock = new Stock();
        $stock->product_id = $product_id;
        $stock->date = $date;
        $stock->in_qty = 0;
        $stock->out_qty = $qty;
        $stock->details = $details;
        $stock->type = $type;
        $stock->ref_invoice_no = $refInvoiceNo;

        if (auth()->check() && auth()->user()->branchId()) {
            $stock->vendor_id = auth()->user()->branchId();
        }

        $stock->save();

        return true;
    }

    /**
     * Stores a new batch for a product.
     *
     * @param int $product_id
     * @param string|null $batch_no
     * @param ?string $expiry_date
     * @param float $purchase_price
     * @param float $qty
     * @param string $ref_invoice_no
     * @param string $type
     * @return int Batch ID
     */
    public static function storeBatch(int $product_id, ?string $batch_no, float $purchase_price, float $qty, string $ref_invoice_no, string $type): int
    {
        $batch = new Batch();

        if ($type === self::$purchase) {
            $batch->product_id = $product_id;
            $batch->batch_no = !empty($batch_no) ? $batch_no : (string) time();
            $batch->type = $type;
            $batch->ref_invoice_no = $ref_invoice_no;
            $batch->purchase_qty = $qty;
            $batch->purchase_price = $purchase_price;
        }

        if (auth()->check() && auth()->user()->branchId()) {
            $batch->vendor_id = auth()->user()->branchId();
        }
        $batch->save();
        
        return $batch->id;
    }

    /**
     * Updates an existing batch for a product.
     *
     * @param int $product_id
     * @param int $batch_id
     * @param string|null $batch_no
     * @param ?string $expiry_date
     * @param ?float $qty
     * @param string $type
     * @return bool
     */
    public static function updateBatch(int $product_id, int $batch_id, ?string $batch_no, ?float $qty, string $type): bool
    {
        $batch = Batch::where('id', $batch_id)->where('product_id', $product_id)->first();

        if (!$batch) {
            return false;
        }

        if (auth()->check() && auth()->user()->branchId()) {
            $batch->vendor_id = auth()->user()->branchId();
        }

        if ($type === self::$purchase) {
            $batch->purchase_qty = $qty;
            $batch->product_id = $product_id;
            $batch->batch_no = !empty($batch_no) ? $batch_no : (string) time();
        } elseif ($type === self::$sales) {
            $batch->sale_qty += $qty;
        } elseif ($type === self::$returnToSupplier) {
            $batch->purchase_return_qty += $qty;
        } elseif ($type === self::$returnToCustomer) {
            $batch->sale_return_qty += $qty;
        }
        $batch->update();

        return true;
    }

    /**
     * Adds an expense entry to the ledger.
     *
     * @param object $request
     * @param string $invoiceNo
     * @param ?string $refInvoiceNo
     * @param ?string $type
     * @return bool
     */
    public static function expenseLedger(object $request, string $invoiceNo, ?string $refInvoiceNo, ?string $type): bool
    {
        $ledger = new Ledger();

        if (isset($request->income_expense_head_id)) {
            $ledger->income_expense_head_id = $request->income_expense_head_id;
        }

        if (auth()->check() && auth()->user()->branchId()) {
            $ledger->vendor_id = auth()->user()->branchId();
        } else {
            $ledger->vendor_id = null;
        }

        $ledger->invoice_no = $invoiceNo;
        $ledger->date = $request->date;
        $ledger->details = $request->details;
        $ledger->expense_amount = $request->paid;
        $ledger->income_amount = 0;
        $ledger->amount_type = self::$expense;
        $ledger->type = $type;
        $ledger->ref_invoice_no = $refInvoiceNo;
        $ledger->save();

        return true;
    }

    /**
     * Adds an income entry to the ledger.
     *
     * @param object $request
     * @param string $invoiceNo
     * @param ?string $refInvoiceNo
     * @param ?string $type
     * @return bool
     */
    public static function incomeLedger(object $request, string $invoiceNo, ?string $refInvoiceNo, ?string $type): bool
    {
        $ledger = new Ledger();

        if (isset($request->income_expense_head_id)) {
            $ledger->income_expense_head_id = $request->income_expense_head_id;
        }

        if (auth()->check() && auth()->user()->branchId()) {
            $ledger->vendor_id = auth()->user()->branchId();
        } else {
            $ledger->vendor_id = null;
        }

        $ledger->invoice_no = $invoiceNo;
        $ledger->date = $request->date;
        $ledger->details = $request->details;
        $ledger->expense_amount = 0;
        $ledger->income_amount = $request->paid;
        $ledger->amount_type = self::$income;
        $ledger->type = $type;
        $ledger->ref_invoice_no = $refInvoiceNo;
        $ledger->save();

        return true;
    }

    public static function hasPermission(string $permission): bool
    {
        return auth()->check() && Gate::allows('permission', $permission);
    }

    /**
     * Calculate doctor commission amount based on total amount and commission percentage.
     *
     * @param float $totalAmount
     * @param float $commissionPercent
     * @return float
     */
    public static function calculateDoctorCommissionSimple(float $totalAmount, float $commissionPercent): float
    {
        return ($totalAmount * $commissionPercent) / 100;
    }

    /**
     * Add credit entry to doctor ledger when sale is made with doctor reference.
     *
     * @param object $request
     * @param string $refInvoiceNo
     * @param string $type
     * @return bool
     */
    public static function doctorLedgerCredit(object $request, string $refInvoiceNo, string $type): bool
    {
        if (empty($request->reference_by) || (float) ($request->reference_amount ?? 0) <= 0.001) {
            return false;
        }

        $doctorLedger = new DoctorLedger();
        $doctorLedger->doctor_id = $request->reference_by;
        $doctorLedger->date = $request->date;
        $doctorLedger->details = $request->details ? $request->details . ' (Sale invoice no - ' . $refInvoiceNo . ')' : 'Sale invoice no - ' . $refInvoiceNo;
        $doctorLedger->debit = 0;
        $doctorLedger->credit = (float) $request->reference_amount;
        $doctorLedger->type = $type;
        $doctorLedger->ref_invoice_no = $refInvoiceNo;
        $doctorLedger->save();

        return true;
    }

    /**
     * Compute doctor commission from items and configured doctor rules (category/service overrides or defaults).
     */
    public static function calculateDoctorCommission(
        int $doctorId,
        array $products = [],
        array $services = [],
        bool $isPercentProduct = true,
        bool $isPercentService = true
    ): float {
        $doctor = \App\Models\Doctor::with(['categoryCommissions', 'serviceCommissions'])->find($doctorId);
        if (!$doctor) {
            return 0.0;
        }

        $totalComm = 0.0;
        $categoryRules = $doctor->categoryCommissions->keyBy('category_id');
        $serviceRules  = $doctor->serviceCommissions->keyBy('service_type_id');

        $defProdPercent = (float) ($doctor->product_commission_percent ?? 0);
        $defProdFixed   = (float) ($doctor->product_commission_amount ?? 0);
        $defServPercent = (float) ($doctor->service_commission_percent ?? 0);
        $defServFixed   = (float) ($doctor->service_commission_amount ?? 0);

        // 1. Hearing Aids / Products
        if (!empty($products)) {
            $pIds = array_filter(array_column($products, 'product_id'));
            $prodCategories = !empty($pIds) ? \App\Models\Product::whereIn('id', $pIds)->pluck('category_id', 'id') : collect();

            foreach ($products as $p) {
                $pId = $p['product_id'] ?? null;
                $catId = $pId ? ($prodCategories[$pId] ?? null) : null;
                $qty = (float) ($p['qty'] ?? 1);
                $lineTotal = (float) ($p['line_total'] ?? 0);

                if ($qty <= 0 && $lineTotal <= 0) {
                    continue;
                }

                if ($catId && $categoryRules->has($catId)) {
                    $rule = $categoryRules->get($catId);
                    $totalComm += ($rule->commission_type === 'percent') ? ($lineTotal * $rule->commission_value / 100) : ($qty * $rule->commission_value);
                } else {
                    if ($isPercentProduct && $defProdPercent > 0) {
                        $totalComm += ($lineTotal * $defProdPercent / 100);
                    } elseif ($defProdFixed > 0) {
                        $totalComm += ($qty * $defProdFixed);
                    } elseif ($defProdPercent > 0) {
                        $totalComm += ($lineTotal * $defProdPercent / 100);
                    }
                }
            }
        }

        // 2. Clinical / Diagnostic Services
        if (!empty($services)) {
            foreach ($services as $s) {
                $srvId = $s['service_type_id'] ?? null;
                $qty = (float) ($s['qty'] ?? 1);
                $lineTotal = (float) ($s['line_total'] ?? 0);

                if ($qty <= 0 && $lineTotal <= 0) {
                    continue;
                }

                if ($srvId && $serviceRules->has($srvId)) {
                    $rule = $serviceRules->get($srvId);
                    $totalComm += ($rule->commission_type === 'percent') ? ($lineTotal * $rule->commission_value / 100) : ($qty * $rule->commission_value);
                } else {
                    if ($isPercentService && $defServPercent > 0) {
                        $totalComm += ($lineTotal * $defServPercent / 100);
                    } elseif ($defServFixed > 0) {
                        $totalComm += ($qty * $defServFixed);
                    } elseif ($defServPercent > 0) {
                        $totalComm += ($lineTotal * $defServPercent / 100);
                    }
                }
            }
        }

        return round($totalComm, 2);
    }

    /** When true, branch scopes are skipped (read-only "all branches" views). Set only via readAllBranches(). */
    protected static bool $allBranchesMode = false;

    /**
     * Run a read-only query block across every branch (HQ + all branches).
     * Only takes effect for users allowed to view all branches; everyone else keeps their normal scope.
     * Never wrap create/update/stock/ledger logic in this.
     */
    public static function readAllBranches(callable $callback)
    {
        $user = auth()->user();
        if (!$user || !$user->canViewAllBranches()) {
            return $callback();
        }

        $previous = self::$allBranchesMode;
        self::$allBranchesMode = true;
        try {
            return $callback();
        } finally {
            self::$allBranchesMode = $previous;
        }
    }

    public static function isAllBranchesMode(): bool
    {
        return self::$allBranchesMode;
    }

    /**
     * Narrow an "all branches" query by the list-page branch filter.
     * $branch: null/'all' = every branch, 'hq' = Head Office only, numeric = that branch.
     * No effect outside readAllBranches() (normal users keep their own scope).
     */
    public static function applyBranchFilter($query, ?string $branch, string $column = 'vendor_id')
    {
        if (!self::$allBranchesMode || $branch === null || $branch === '' || $branch === 'all') {
            return $query;
        }

        return $branch === 'hq'
            ? $query->whereNull($column)
            : $query->where($column, (int) $branch);
    }

    /** [id => name] of active branches, for branch filter dropdowns. */
    public static function branchOptions(): array
    {
        return \App\Models\User::where('type', 'vendor')->orderBy('name')->pluck('name', 'id')->all();
    }

    /** Branch id the current user belongs to (branch login or branch staff), null = Head Office. */
    public static function currentBranchId(): ?int
    {
        return auth()->check() ? auth()->user()->branchId() : null;
    }

    /**
     * Branch to store on a staff account (users.vendor_id) from the form value.
     * Invalid ids fall back to Head Office (null).
     */
    public static function staffBranchFromRequest($value): ?int
    {
        if (empty($value)) {
            return null;
        }

        return \App\Models\User::where('type', 'vendor')->whereKey((int) $value)->exists() ? (int) $value : null;
    }

    public static function vendorScope($builder): void
    {
        if (self::$allBranchesMode) {
            return;
        }

        $branchId = self::currentBranchId();
        if ($branchId) {
            $builder->where('vendor_id', $branchId);
        } else {
            $builder->whereNull('vendor_id');
        }
    }

    public static function vendorRelationScope($builder, string $relation): void
    {
        if (self::$allBranchesMode) {
            return;
        }

        $branchId = self::currentBranchId();
        if ($branchId) {
            $builder->whereHas($relation, function ($query) use ($branchId) {
                $query->where('vendor_id', $branchId);
            });
        } else {
            $builder->whereHas($relation, function ($query) {
                $query->whereNull('vendor_id');
            });
        }
    }

    /**
     * Get available (unsold) serial numbers for a specific product.
     * Filters out sold serials and returns only available ones with their details.
     *
     * @param int $productId
     * @return array Array of available serial numbers with purchase details
     */
    public static function getAvailableSerialNumbers(int $productId): array
    {
        $result = [];

        // Get purchased serials - filter by vendor if user is vendor
        $query = PurchaseDetail::with('purchaseMaster')
            ->where('product_id', $productId)
            ->whereNotNull('serial_no');

        $purchases = $query->get(['id', 'serial_no', 'mrp_price', 'purchase_price', 'purchase_master_id']);

        if ($purchases->isEmpty()) {
            return $result;
        }

        // Get sold serials - filter by vendor if user is vendor
        $salesQuery = SaleDetail::where('product_id', $productId)
            ->whereNotNull('serial_no');

        if (auth()->check() && auth()->user()->branchId()) {
            $salesQuery->whereHas('salesMaster', function ($q) {
                $q->where('vendor_id', auth()->user()->branchId());
            });
        } else {
            // General users can only get serials from global purchases
            $salesQuery->whereHas('salesMaster', function ($q) {
                $q->whereNull('vendor_id');
            });
        }

        $soldSerials = $salesQuery
            ->pluck('serial_no')
            ->flatten()
            ->toArray();

        // Get serials returned by customers (credit notes) - these are back in stock
        $customerReturnedSerials = \App\Models\SaleReturnDetail::where('product_id', $productId)
            ->whereNotNull('serial_no')
            ->pluck('serial_no')
            ->flatten()
            ->toArray();

        // Active sold serials: sold more often than returned by patients (a returned unit can be sold again)
        $soldCounts = array_count_values(array_map('strval', $soldSerials));
        $returnedCounts = array_count_values(array_map('strval', $customerReturnedSerials));
        $activeSoldSerials = array_map('strval', array_keys(array_filter(
            $soldCounts,
            fn ($count, $serial) => $count > ($returnedCounts[$serial] ?? 0),
            ARRAY_FILTER_USE_BOTH
        )));

        // Get returned serials to supplier (debit notes)
        $returnedSerials = \App\Models\PurchaseReturnDetail::where('product_id', $productId)
            ->whereNotNull('serial_no')
            ->pluck('serial_no')
            ->flatten()
            ->toArray();

        // Filter available serials
        foreach ($purchases as $purchase) {
            $serialNumbers = is_array($purchase->serial_no)
                ? $purchase->serial_no
                : json_decode($purchase->serial_no, true);

            if (!is_array($serialNumbers)) {
                continue;
            }

            foreach ($serialNumbers as $serialNumber) {
                // Skip already active-sold or supplier-returned serial
                if (in_array($serialNumber, $activeSoldSerials) || in_array($serialNumber, $returnedSerials)) {
                    continue;
                }

                $result[] = [
                    'purchase_detail_id' => $purchase->id,
                    'serial_number' => $serialNumber,
                    'mrp_price' => $purchase->mrp_price,
                    'purchase_price' => $purchase->purchase_price,
                    'purchase_master_id' => $purchase->purchase_master_id,
                ];
            }
        }

        return $result;
    }

    /**
     * Generate a referral code for a customer.
     *
     * @param string $name
     * @return string
     */
    public static function generateReferralCode(string $name): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $name), 0, 3));
        $prefix = str_pad($prefix, 3, 'X'); // fallback if name is too short
        return $prefix . strtoupper(Str::random(5));
    }

    /**
     * Get stock report data by product(s) with optimized queries
     *
     * @param array $filters
     * @return array
     */
    public static function getStockByProduct(array $filters = []): array
    {
        $productId = $filters['product_id'] ?? null;
        $categoryId = $filters['category_id'] ?? null;
        $brandId = $filters['brand_id'] ?? null;

        // Build stock query with aggregations
        $query = Stock::query()
            ->select([
                'stocks.product_id',
                DB::raw('SUM(stocks.in_qty) as total_in'),
                DB::raw('SUM(stocks.out_qty) as total_out'),
                DB::raw('SUM(stocks.in_qty) - SUM(stocks.out_qty) as stock_balance'),
            ])
            ->whereNull('stocks.deleted_at')
            ->groupBy('stocks.product_id');

        // Apply product filter
        if ($productId) {
            $query->where('stocks.product_id', $productId);
        }

        // Get stock data
        $stockData = $query->get()->keyBy('product_id');

        if ($stockData->isEmpty()) {
            return [
                'stockData' => collect(),
                'totalIn' => 0,
                'totalOut' => 0,
                'totalBalance' => 0,
            ];
        }

        // Collect product IDs for batch loading
        $productIds = $stockData->keys()->toArray();

        // Batch load products with relationships
        $products = Product::with(['category', 'brand', 'type'])
            ->whereIn('id', $productIds)
            ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
            ->when($brandId, fn($q) => $q->where('brand_id', $brandId))
            ->get()
            ->keyBy('id');

        // Build report data
        $reportData = [];
        $totalIn = 0;
        $totalOut = 0;
        $totalBalance = 0;

        foreach ($stockData as $productId => $stock) {
            $product = $products->get($productId);

            if (!$product) {
                continue;
            }

            $stockBalance = (int) $stock->stock_balance;
            $totalInQty = (int) $stock->total_in;
            $totalOutQty = (int) $stock->total_out;

            $reportData[] = [
                'product_id' => $product->id,
                'product' => $product->model ?? 'N/A',
                'category' => $product->category?->category_name ?? 'N/A',
                'brand' => $product->brand?->brand_name ?? 'N/A',
                'type' => $product->type?->type_name ?? 'N/A',
                'total_in' => $totalInQty,
                'total_out' => $totalOutQty,
                'stock_balance' => $stockBalance,
            ];

            $totalIn += $totalInQty;
            $totalOut += $totalOutQty;
            $totalBalance += $stockBalance;
        }

        // Sort by product name
        usort($reportData, fn($a, $b) => strcmp($a['product'], $b['product']));

        return [
            'stockData' => collect($reportData),
            'totalIn' => $totalIn,
            'totalOut' => $totalOut,
            'totalBalance' => $totalBalance,
        ];
    }

    /**
     * Get sales performance report data with dynamic grouping
     *
     * @param array $filters
     * @return array
     */
    public static function getSalesPerformanceReport(array $filters = []): array
    {
        $groupBy = $filters['group_by'] ?? 'vendor';
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $productId = $filters['product_id'] ?? null;
        $doctorId = $filters['doctor_id'] ?? null;
        $vendorId = $filters['vendor_id'] ?? null;

        // Determine grouping field and relationship
        $groupFieldMap = [
            'vendor' => ['field' => 'sale_masters.vendor_id', 'relation' => 'vendor', 'name_field' => 'supplier_name'],
            'staff' => ['field' => 'sale_masters.created_by', 'relation' => 'createdBy', 'name_field' => 'name'],
            'product' => ['field' => 'sale_details.product_id', 'relation' => 'saleDetails.product', 'name_field' => 'model'],
            'doctor' => ['field' => 'sale_masters.reference_by', 'relation' => 'doctor', 'name_field' => 'name'],
            'category' => ['field' => 'products.category_id', 'relation' => 'saleDetails.product.category', 'name_field' => 'category_name'],
            'membership_tier' => ['field' => 'customers.membership_tier_id', 'relation' => 'customer.membershipTier', 'name_field' => 'name'],
        ];

        $groupConfig = $groupFieldMap[$groupBy] ?? $groupFieldMap['vendor'];

        // Build base query
        $query = SaleMaster::withoutGlobalScope('vendor_filter')
            ->select([
                $groupConfig['field'] . ' as group_id',
                DB::raw('COUNT(DISTINCT sale_masters.id) as invoice_count'),
                DB::raw('SUM(sale_masters.total_amount) as revenue'),
                DB::raw('SUM(sale_masters.total_discount) as discount'),
                DB::raw('SUM(sale_masters.reference_amount) as commission'),
                DB::raw('SUM(sale_masters.total_amount) - SUM(sale_masters.total_discount) as net_revenue'),
            ])
            ->whereNull('sale_masters.deleted_at')
            ->when($startDate && $endDate, function($q) use ($startDate, $endDate) {
                $q->whereBetween('sale_masters.date', [$startDate, $endDate]);
            });

        // Add joins based on group_by type and select name fields
        if ($groupBy === 'product') {
            $query->join('sale_details', 'sale_masters.id', '=', 'sale_details.sale_master_id')
                   ->join('products', 'sale_details.product_id', '=', 'products.id')
                   ->when($productId, function($q) use ($productId) {
                       $q->where('sale_details.product_id', $productId);
                   })
                   ->when($doctorId, function($q) use ($doctorId) {
                       $q->where('sale_masters.reference_by', $doctorId);
                   })
                   ->when($vendorId, function($q) use ($vendorId) {
                       $q->where('sale_masters.vendor_id', $vendorId);
                   })
                   ->addSelect([
                       'products.model as group_name',
                       DB::raw('SUM(sale_details.qty) as qty_sold')
                   ]);
        } elseif ($groupBy === 'category') {
            $query->join('sale_details', 'sale_masters.id', '=', 'sale_details.sale_master_id')
                   ->join('products', 'sale_details.product_id', '=', 'products.id')
                   ->join('categories', 'products.category_id', '=', 'categories.id')
                   ->when($productId, function($q) use ($productId) {
                       $q->where('sale_details.product_id', $productId);
                   })
                   ->when($doctorId, function($q) use ($doctorId) {
                       $q->where('sale_masters.reference_by', $doctorId);
                   })
                   ->when($vendorId, function($q) use ($vendorId) {
                       $q->where('sale_masters.vendor_id', $vendorId);
                   })
                   ->addSelect([
                       'categories.category_name as group_name',
                       DB::raw('SUM(sale_details.qty) as qty_sold')
                   ]);
        } elseif ($groupBy === 'membership_tier') {
            $query->join('customers', 'sale_masters.customer_id', '=', 'customers.id')
                   ->leftJoin('membership_tiers', 'customers.membership_tier_id', '=', 'membership_tiers.id')
                   ->when($productId, function($q) use ($productId) {
                       $q->whereHas('saleDetails', function($query) use ($productId) {
                           $query->where('product_id', $productId);
                       });
                   })
                   ->when($doctorId, function($q) use ($doctorId) {
                       $q->where('sale_masters.reference_by', $doctorId);
                   })
                   ->when($vendorId, function($q) use ($vendorId) {
                       $q->where('sale_masters.vendor_id', $vendorId);
                   })
                   ->addSelect([
                       'membership_tiers.name as group_name',
                       DB::raw('0 as qty_sold')
                   ]);
        } elseif ($groupBy === 'staff') {
            $query->join('sale_details', 'sale_masters.id', '=', 'sale_details.sale_master_id')
                   ->join('users', 'sale_masters.created_by', '=', 'users.id')
                   ->when($productId, function($q) use ($productId) {
                       $q->where('sale_details.product_id', $productId);
                   })
                   ->when($doctorId, function($q) use ($doctorId) {
                       $q->where('sale_masters.reference_by', $doctorId);
                   })
                   ->when($vendorId, function($q) use ($vendorId) {
                       $q->where('sale_masters.vendor_id', $vendorId);
                   })
                   ->addSelect([
                       'users.name as group_name',
                       DB::raw('SUM(sale_details.qty) as qty_sold')
                   ]);
        } elseif ($groupBy === 'doctor') {
            $query->join('sale_details', 'sale_masters.id', '=', 'sale_details.sale_master_id')
                   ->join('doctors', function($join) {
                       $join->on('sale_masters.reference_by', '=', 'doctors.id')
                            ->whereNull('doctors.deleted_at');
                   })
                   ->when($productId, function($q) use ($productId) {
                       $q->where('sale_details.product_id', $productId);
                   })
                   ->when($doctorId, function($q) use ($doctorId) {
                       $q->where('sale_masters.reference_by', $doctorId);
                   })
                   ->when($vendorId, function($q) use ($vendorId) {
                       $q->where('sale_masters.vendor_id', $vendorId);
                   })
                   ->addSelect([
                       'doctors.name as group_name',
                       DB::raw('SUM(sale_details.qty) as qty_sold')
                   ]);
        } else {
            // For vendor
            $query->join('sale_details', 'sale_masters.id', '=', 'sale_details.sale_master_id')
                   ->join('users as vendor_users', 'sale_masters.vendor_id', '=', 'vendor_users.id')
                   ->when($productId, function($q) use ($productId) {
                       $q->where('sale_details.product_id', $productId);
                   })
                   ->when($doctorId, function($q) use ($doctorId) {
                       $q->where('sale_masters.reference_by', $doctorId);
                   })
                   ->when($vendorId, function($q) use ($vendorId) {
                       $q->where('sale_masters.vendor_id', $vendorId);
                   })
                   ->addSelect([
                       'vendor_users.name as group_name',
                       DB::raw('SUM(sale_details.qty) as qty_sold')
                   ]);
        }

        // Apply grouping
        $query->groupBy($groupConfig['field']);

        // Add not null conditions for specific groups
        if ($groupBy === 'staff') {
            $query->whereNotNull('sale_masters.created_by');
        } elseif ($groupBy === 'doctor') {
            $query->whereNotNull('sale_masters.reference_by');
        }

        $results = $query->get();

        if ($results->isEmpty()) {
            return [
                'reportData' => collect(),
                'totalRevenue' => 0,
                'totalDiscount' => 0,
                'totalCommission' => 0,
                'totalNetRevenue' => 0,
                'totalQty' => 0,
                'avgInvoice' => 0,
            ];
        }

        // Build report data
        $reportData = [];
        $totalRevenue = 0;
        $totalDiscount = 0;
        $totalCommission = 0;
        $totalNetRevenue = 0;
        $totalQty = 0;
        $totalInvoices = 0;

        foreach ($results as $result) {
            // Get group name directly from selected field
            $groupName = $result->group_name ?? 'N/A';

            $invoiceCount = (int) $result->invoice_count;
            $revenue = (float) ($result->revenue ?? 0);
            $discount = (float) ($result->discount ?? 0);
            $commission = (float) ($result->commission ?? 0);
            $netRevenue = (float) ($result->net_revenue ?? ($revenue - $discount));
            $qty = (int) ($result->qty_sold ?? 0);
            $avgInvoice = $invoiceCount > 0 ? round($netRevenue / $invoiceCount, 2) : 0;

            $reportData[] = [
                'group_name' => $groupName,
                'group_id' => $result->group_id,
                'group_type' => $groupBy,
                'qty' => $qty,
                'revenue' => $revenue,
                'discount' => $discount,
                'net_revenue' => $netRevenue,
                'commission' => $commission,
                'invoice_count' => $invoiceCount,
                'avg_invoice' => $avgInvoice,
            ];

            $totalRevenue += $revenue;
            $totalDiscount += $discount;
            $totalCommission += $commission;
            $totalNetRevenue += $netRevenue;
            $totalQty += $qty;
            $totalInvoices += $invoiceCount;
        }

        // Calculate overall average
        $overallAvgInvoice = $totalInvoices > 0 ? round($totalNetRevenue / $totalInvoices, 2) : 0;

        // Sort by net revenue descending
        usort($reportData, function($a, $b) {
            return $b['net_revenue'] <=> $a['net_revenue'];
        });

        return [
            'reportData' => collect($reportData),
            'totalRevenue' => $totalRevenue,
            'totalDiscount' => $totalDiscount,
            'totalCommission' => $totalCommission,
            'totalNetRevenue' => $totalNetRevenue,
            'totalQty' => $totalQty,
            'avgInvoice' => $overallAvgInvoice,
        ];
    }
}
