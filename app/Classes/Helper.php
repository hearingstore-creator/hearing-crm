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
            '1' => 'Cash Payment',
            '2' => 'Bank Payment',
            '3' => 'MFS Payment',
        ];
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
        $mLedger->details = $request->details ? $request->details . ' (Purchase invoice no - ' . $refInvoiceNo . ')' : 'Purchase invoice no - ' . $refInvoiceNo;
        $mLedger->debit = 0;
        $mLedger->credit = $request->total_amount;
        $mLedger->type = $type;
        $mLedger->ref_invoice_no = $refInvoiceNo;
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
        $mLedger->details = $request->details ? $request->details . ' (Purchase invoice no - ' . $refInvoiceNo . ')' : 'Purchase invoice no - ' . $refInvoiceNo;
        $mLedger->debit = $request->paid;
        $mLedger->credit = 0;
        $mLedger->type = $type;
        $mLedger->ref_invoice_no = $refInvoiceNo;
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

        if (auth()->check() && auth()->user()->isVendor()) {
            $stock->vendor_id = auth()->id();
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

        if (auth()->check() && auth()->user()->isVendor()) {
            $stock->vendor_id = auth()->id();
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

        if (auth()->check() && auth()->user()->isVendor()) {
            $batch->vendor_id = auth()->id();
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

        if (auth()->check() && auth()->user()->isVendor()) {
            $batch->vendor_id = auth()->id();
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

        if ($request->has('income_expense_head_id')) {
            $ledger->income_expense_head_id = $request->income_expense_head_id;
        }

        if (auth()->check() && auth()->user()->isVendor()) {
            $ledger->vendor_id = auth()->id();
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

        if ($request->has('income_expense_head_id')) {
            $ledger->income_expense_head_id = $request->income_expense_head_id;
        }

        if (auth()->check() && auth()->user()->isVendor()) {
            $ledger->vendor_id = auth()->id();
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
    public static function calculateDoctorCommission(float $totalAmount, float $commissionPercent): float
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
        if (empty($request->reference_by) || empty($request->reference_amount)) {
            return false;
        }

        $doctorLedger = new DoctorLedger();
        $doctorLedger->doctor_id = $request->reference_by;
        $doctorLedger->date = $request->date;
        $doctorLedger->details = $request->details ? $request->details . ' (Sale invoice no - ' . $refInvoiceNo . ')' : 'Sale invoice no - ' . $refInvoiceNo;
        $doctorLedger->debit = 0;
        $doctorLedger->credit = $request->reference_amount;
        $doctorLedger->type = $type;
        $doctorLedger->ref_invoice_no = $refInvoiceNo;
        $doctorLedger->save();

        return true;
    }

    public static function vendorScope($builder): void
    {
        if (auth()->check() && auth()->user()->isVendor()) {
            $builder->where('vendor_id', auth()->id());
        } else {
            $builder->whereNull('vendor_id');
        }
    }

    public static function vendorRelationScope($builder, string $relation): void
    {
        if (auth()->check() && auth()->user()->isVendor()) {
            $builder->whereHas($relation, function ($query) {
                $query->where('vendor_id', auth()->id());
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

        if (auth()->check() && auth()->user()->isVendor()) {
            $salesQuery->whereHas('salesMaster', function ($q) {
                $q->where('vendor_id', auth()->id());
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

        // Filter available serials
        foreach ($purchases as $purchase) {
            $serialNumbers = is_array($purchase->serial_no)
                ? $purchase->serial_no
                : json_decode($purchase->serial_no, true);

            if (!is_array($serialNumbers)) {
                continue;
            }

            foreach ($serialNumbers as $serialNumber) {
                // Skip already sold serial
                if (in_array($serialNumber, $soldSerials)) {
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
