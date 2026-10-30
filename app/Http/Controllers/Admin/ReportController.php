<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Brand;
use App\Support\CsvDownload;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\Category;
use App\Models\Doctor;
use App\Models\DoctorLedger;
use App\Models\Lead;
use App\Models\Ledger;
use App\Models\MembershipTier;
use App\Models\CustomerAdvance;
use App\Models\Product;
use App\Models\PurchaseDetail;
use App\Models\Referral;
use App\Models\RewardLedger;
use App\Models\SaleDetail;
use App\Models\SaleMaster;
use App\Models\ServiceDetail;
use App\Models\ServiceType;
use App\Models\Setting;
use App\Models\User;
use App\Enums\RewardLedgerType;
use App\Enums\SourceOfEntry;
use App\Enums\LeadStatus;
use App\Exports\PurchaseStockReportExport;
use App\Exports\SalesPerformanceReportExport;
use App\Exports\MembershipLoyaltyReportExport;
use App\Exports\LeadFunnelReportExport;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    /**
     * Configure middleware to handle authorization for different report operations
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:ledger.index')->only(['ledgerCreate', 'ledgerReport']);
        $this->middleware('permission:warranty_expiry.index')->only(['warrantyExpiryCreate', 'warrantyExpiryReport']);
        $this->middleware('permission:battery_usage_expiry.index')->only(['batteryUsageExpiryReport']);
        $this->middleware('permission:stock.index')->only(['stockReportByProduct']);
        $this->middleware('permission:purchase_&_stock.index')->only(['purchaseStockReport']);
        $this->middleware('permission:doctor_&_referral.index')->only(['doctorReferralReport']);
        $this->middleware('permission:sales_performance.index')->only(['salesPerformanceReport']);
        $this->middleware('permission:membership_&_loyalty.index')->only(['membershipLoyaltyReport']);
        $this->middleware('permission:lead_funnel.index')->only(['leadFunnelReport', 'exportLeadFunnelReport']);
        $this->middleware('permission:sales_performance.index')->only(['exportSalesPerformanceReport']);
        $this->middleware('permission:membership_&_loyalty.index')->only(['exportMembershipLoyaltyReport']);
        $this->middleware('permission:purchase_&_stock.index')->only(['exportPurchaseStockReport']);
        $this->middleware('permission:advances_report.index')->only(['advancesReport']);
        $this->middleware('permission:services_status_report.index')->only(['servicesStatusReport']);
        // Branch logins and staff with a home branch only ever see their own branch (reports and exports)
        $this->middleware(fn ($request, $next) => Helper::forceReportBranch($request, $next));
    }

    /**
     * Display the form for creating a ledger report.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function ledgerCreate(Request $request = null): View
    {
        return $this->ledgerReport($request ?: request());
    }

    /**
     * Generate and display the ledger report based on the given dates and type.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function ledgerReport(Request $request): View
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);
        // Default: this month so far
        $startDate = $request->start_date ?: now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?: now()->toDateString();
        $type = $request->type ?: 'Both';
        $branch = $request->input('branch');
        $ledgerFilterTypes = Helper::ledgerFilterTypes();
        $branches = auth()->user()->canViewAllBranches() ? Helper::branchOptions() : [];

        // Head office sees every branch (narrowed by the branch filter); branch users keep their own branch
        $rows = fn (string $amountType) => Helper::readAllBranches(fn () => Helper::applyBranchFilter(
            Ledger::with(['head', 'vendor'])->where('amount_type', $amountType)->whereBetween('date', [$startDate, $endDate]),
            $branch
        )->orderBy('date', 'desc')->orderBy('id', 'desc')->get());

        $data = compact('startDate', 'endDate', 'type', 'ledgerFilterTypes', 'branch', 'branches');
        if (Helper::$expense == $type) {
            $data['expenses'] = $rows(Helper::$expense);
        } elseif (Helper::$income == $type) {
            $data['incomes'] = $rows(Helper::$income);
        } else {
            $data['expenses'] = $rows(Helper::$expense);
            $data['incomes'] = $rows(Helper::$income);
        }

        return view('admin.report.ledger', $data);
    }

    /**
     * Generate warranty expiry report
     * Finds unsold products where inventory warranty is approaching (calculated from product settings)
     *
     * @param Request $request
     * @return View
     */
    public function warrantyExpiryReport(Request $request): View|StreamedResponse
    {
        $alertDaysBefore = 10; // Fixed 10 days before expiry
        $alertProducts = [];
        $today = now()->startOfDay();

        // Get all products with warranty settings
        $products = Product::with(['category', 'brand', 'type'])->get();

        foreach ($products as $product) {
            // Skip if warranty settings are not configured
            if (!$product->warranty_inventory_months || !$product->warranty_sales_months) {
                continue;
            }

            $inventoryWarrantyMonths = $product->warranty_inventory_months;
            $salesWarrantyMonths = $product->warranty_sales_months;

            // Calculate the difference (when inventory warranty meets sales warranty)
           $monthDifference = $inventoryWarrantyMonths - $salesWarrantyMonths;

            // 1. Check batch-wise unsold products for this product
            $unsoldBatches = Batch::where('product_id', $product->id)
                ->get();

            foreach ($unsoldBatches as $batch) {
                // A batch created by a stock transfer keeps the purchase date of the batch it came from
                $originId = $batch->origin_batch_id ?: $batch->id;
                $purchaseDetail = PurchaseDetail::withoutGlobalScopes()->with(['purchaseMaster' => fn ($q) => $q->withoutGlobalScopes()])->where('batch_id', $originId)->first();
                if (!($batch->stock > 0)) {
                    continue;
                }

                if ($purchaseDetail && $purchaseDetail->purchaseMaster) {
                    $purchaseDate = $purchaseDetail->purchaseMaster->date;
                } else {
                    // Batch created by a stock adjustment: use its optional warranty start date, otherwise leave it out
                    $adjustedLine = \App\Models\StockAdjustmentDetail::where('batch_id', $originId)->where('direction', 'in')->whereNotNull('warranty_start_date')->first();
                    if (!$adjustedLine) {
                        continue;
                    }
                    $purchaseDate = $adjustedLine->warranty_start_date;
                }

                $warrantyExpiryDate = $purchaseDate->copy()->addMonths($inventoryWarrantyMonths);

                // Calculate alert date: month difference before warranty expiry - 10 days
                $alertDate = $warrantyExpiryDate->copy()->subMonths($monthDifference)->subDays($alertDaysBefore);

                $daysUntilAlert = $today->diffInDays($alertDate, false);

                if ($daysUntilAlert <= 30 && $daysUntilAlert >= -30) {
                    $alertProducts[] = [
                        'type' => 'batch',
                        'identifier' => $batch->batch_no,
                        'product' => $product,
                        'purchase_date' => $purchaseDate,
                        'inventory_warranty_months' => $inventoryWarrantyMonths,
                        'sales_warranty_months' => $salesWarrantyMonths,
                        'month_difference' => $monthDifference,
                        'warranty_expiry_date' => $warrantyExpiryDate,
                        'alert_date' => $alertDate,
                        'days_until_alert' => $daysUntilAlert,
                        'status' => $daysUntilAlert < 0 ? 'Alert Expired' : 'Approaching',
                        'remaining_stock' => $batch->stock,
                        'purchase_price' => $batch->purchase_price,
                    ];
                }
            }

            // 2. Check serial number unsold products for this product
            $availableSerials = Helper::getAvailableSerialNumbers($product->id);

            // Group available serials by purchase_detail_id to avoid loading purchase master multiple times
            $groupedSerials = [];
            foreach ($availableSerials as $serial) {
                // Unit added by a stock adjustment: it has no purchase date, so the optional warranty start date is used
                if (empty($serial['purchase_detail_id'])) {
                    if (!empty($serial['warranty_start_date'])) {
                        $startDate = \Illuminate\Support\Carbon::parse($serial['warranty_start_date']);
                        $expiry = $startDate->copy()->addMonths($inventoryWarrantyMonths);
                        $alertAt = $expiry->copy()->subMonths($monthDifference)->subDays($alertDaysBefore);
                        $daysTo = $today->diffInDays($alertAt, false);
                        if ($daysTo <= 30 && $daysTo >= -30) {
                            $alertProducts[] = [
                                'type' => 'serial', 'identifier' => $serial['serial_number'], 'serial_number' => $serial['serial_number'],
                                'product' => $product, 'purchase_date' => $startDate,
                                'inventory_warranty_months' => $inventoryWarrantyMonths, 'sales_warranty_months' => $salesWarrantyMonths,
                                'month_difference' => $monthDifference, 'warranty_expiry_date' => $expiry, 'alert_date' => $alertAt,
                                'days_until_alert' => $daysTo, 'status' => $daysTo < 0 ? 'Alert Expired' : 'Approaching',
                                'remaining_stock' => 1, 'purchase_price' => $serial['purchase_price'],
                            ];
                        }
                    }
                    continue;
                }
                $purchaseDetailId = $serial['purchase_detail_id'];
                if (!isset($groupedSerials[$purchaseDetailId])) {
                    $groupedSerials[$purchaseDetailId] = [
                        'serials' => [],
                        'purchase_price' => $serial['purchase_price'],
                        'purchase_master_id' => $serial['purchase_master_id'],
                    ];
                }
                $groupedSerials[$purchaseDetailId]['serials'][] = $serial['serial_number'];
            }

            // Process each purchase detail group
            foreach ($groupedSerials as $purchaseDetailId => $groupData) {
                // The unit may have been bought at another location and moved here by a stock transfer
                $purchaseDetail = PurchaseDetail::withoutGlobalScopes()->with(['purchaseMaster' => fn ($q) => $q->withoutGlobalScopes()])
                    ->where('id', $purchaseDetailId)
                    ->whereNull('batch_id') // Exclude batch products
                    ->first();

                if (!$purchaseDetail || !$purchaseDetail->purchaseMaster) {
                    continue;
                }

                $purchaseDate = $purchaseDetail->purchaseMaster->date;
                $warrantyExpiryDate = $purchaseDate->copy()->addMonths($inventoryWarrantyMonths);

                // Calculate alert date
                $alertDate = $warrantyExpiryDate->copy()->subMonths($monthDifference)->subDays($alertDaysBefore);

                $daysUntilAlert = $today->diffInDays($alertDate, false);

                if ($daysUntilAlert <= 30 && $daysUntilAlert >= -30) {
                    // Create individual rows for each unsold serial number
                    foreach ($groupData['serials'] as $serialNumber) {
                        $alertProducts[] = [
                            'type' => 'serial',
                            'identifier' => $serialNumber,
                            'serial_number' => $serialNumber,
                            'product' => $product,
                            'purchase_date' => $purchaseDate,
                            'inventory_warranty_months' => $inventoryWarrantyMonths,
                            'sales_warranty_months' => $salesWarrantyMonths,
                            'month_difference' => $monthDifference,
                            'warranty_expiry_date' => $warrantyExpiryDate,
                            'alert_date' => $alertDate,
                            'days_until_alert' => $daysUntilAlert,
                            'status' => $daysUntilAlert < 0 ? 'Alert Expired' : 'Approaching',
                            'remaining_stock' => 1, // Each serial represents 1 unit
                            'purchase_price' => $groupData['purchase_price'],
                        ];
                    }
                }
            }
        }

        // Sort by days until alert
        usort($alertProducts, function($a, $b) {
            return $a['days_until_alert'] <=> $b['days_until_alert'];
        });

        if ($request->input('export') === 'csv') {
            return CsvDownload::stream('warranty-expiry-' . now()->format('Y-m-d') . '.csv',
                ['Type', 'Serial / batch', 'Product', 'Category', 'Brand', 'Purchased', 'Inventory warranty (months)', 'Sales warranty (months)', 'Alert date', 'Days to alert', 'Status'],
                collect($alertProducts)->map(fn ($a) => [$a['type'], $a['identifier'], $a['product']->model ?? '', $a['product']->category->category_name ?? '', $a['product']->brand->brand_name ?? '',
                    $a['purchase_date']->format('Y-m-d'), $a['inventory_warranty_months'], $a['sales_warranty_months'], $a['alert_date']->format('Y-m-d'), $a['days_until_alert'], $a['status']]));
        }

        return view('admin.report.warranty-expiry', compact('alertProducts', 'alertDaysBefore'));
    }

    /**
     * Generate battery usage expiry report
     * Finds sold products where usage period (sale date + usage months) is approaching expiry
     *
     * @return View
     */
    public function batteryUsageExpiryReport(Request $request)
    {
        $alertDaysBefore = 15; // Alert 15 days before expiry
        $alertProducts = [];
        $today = now()->startOfDay();

        // Get all products with usage_months configured
        $products = Product::with(['category', 'brand', 'type'])
            ->whereNotNull('usage_months')
            ->where('usage_months', '>', 0)
            ->get();

        foreach ($products as $product) {
            $usageMonths = $product->usage_months;

            // Get all sold details for this product (both batch and serial)
            $saleDetails = SaleDetail::with(['salesMaster', 'product'])
                ->where('product_id', $product->id)
                ->whereHas('salesMaster')
                ->get();

            foreach ($saleDetails as $saleDetail) {
                $saleMaster = $saleDetail->salesMaster;
                if (!$saleMaster) {
                    continue;
                }

                $saleDate = $saleMaster->date->startOfDay();
                $usageExpiryDate = $saleDate->copy()->addMonths($usageMonths);
                $alertDate = $usageExpiryDate->copy()->subDays($alertDaysBefore);

                $daysUntilExpiry = $today->diffInDays($usageExpiryDate, false);
                $daysUntilAlert = $today->diffInDays($alertDate, false);

                // Show if within alert period (15 days before expiry, up to 30 days after)
                if ($daysUntilExpiry <= $alertDaysBefore && $daysUntilExpiry >= -30) {
                    // Determine identifier based on whether it's batch or serial
                    $identifier = null;
                    $type = null;

                    if (!empty($saleDetail->batch_id)) {
                        // Batch product
                        $batch = Batch::find($saleDetail->batch_id);
                        $identifier = $batch ? $batch->batch_no : 'N/A';
                        $type = 'batch';
                    } elseif (!empty($saleDetail->serial_no)) {
                        // Serial product
                        $serialNumbers = is_array($saleDetail->serial_no)
                            ? $saleDetail->serial_no
                            : json_decode($saleDetail->serial_no, true);

                        if (is_array($serialNumbers) && count($serialNumbers) > 0) {
                            if (count($serialNumbers) <= 2) {
                                $identifier = implode(', ', $serialNumbers);
                            } else {
                                $firstTwo = array_slice($serialNumbers, 0, 2);
                                $remaining = count($serialNumbers) - 2;
                                $identifier = implode(', ', $firstTwo) . ' ... (+' . $remaining . ' more)';
                            }
                        } else {
                            $identifier = 'N/A';
                        }
                        $type = 'serial';
                    } else {
                        // Regular product without batch or serial
                        $identifier = 'Qty: ' . $saleDetail->qty;
                        $type = 'regular';
                    }

                    $alertProducts[] = [
                        'type' => $type,
                        'identifier' => $identifier,
                        'product' => $product,
                        'sale_date' => $saleDate,
                        'sale_invoice' => $saleMaster->sale_invoice_no,
                        'usage_months' => $usageMonths,
                        'usage_expiry_date' => $usageExpiryDate,
                        'alert_date' => $alertDate,
                        'days_until_expiry' => $daysUntilExpiry,
                        'days_until_alert' => $daysUntilAlert,
                        'status' => $daysUntilExpiry < 0 ? 'Expired' : 'Approaching',
                        'qty' => $saleDetail->qty,
                        'unit_price' => $saleDetail->unit_price,
                        'customer' => $saleMaster->customer->lead->full_name ?? null,
                    ];
                }
            }
        }

        // Sort by days until expiry
        usort($alertProducts, function($a, $b) {
            return $a['days_until_expiry'] <=> $b['days_until_expiry'];
        });

        if ($request->input('export') === 'csv') {
            return CsvDownload::stream('battery-expiry-' . now()->format('Y-m-d') . '.csv',
                ['Type', 'Serial / batch', 'Product', 'Patient', 'Invoice', 'Sold', 'Usage (months)', 'Expires', 'Days to expiry', 'Status'],
                collect($alertProducts)->map(fn ($a) => [$a['type'], $a['identifier'], $a['product']->model ?? '', $a['customer'] ?? '', $a['sale_invoice'],
                    $a['sale_date']->format('Y-m-d'), $a['usage_months'], $a['usage_expiry_date']->format('Y-m-d'), $a['days_until_expiry'], $a['status']]));
        }

        return view('admin.report.battery-usage-expiry', compact('alertProducts', 'alertDaysBefore'));
    }

    /**
     * Display and generate purchase & stock report
     * Shows vendor-wise product purchases, sales, and stock balance
     * Shows default data when no filters applied
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function purchaseStockReport(Request $request): View
    {
        // Get filter parameters
        $filters = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'vendor_id' => $request->vendor_id,
            'product_id' => $request->product_id,
            'stock_status' => $request->stock_status ?? 'all',
        ];

        // Load filter options
        if ($branchId = auth()->user()->branchId()) {
            $filters['vendor_id'] = $branchId; // branch users only see their own branch
            $data['vendors'] = User::where('id', $branchId)->get(['id', 'name']);
        } else {
            $data['vendors'] = User::where('type', 'vendor')->orderBy('name')->get(['id', 'name']);
        }
        $data['products'] = Product::with(['category', 'brand', 'type'])->get();

        // Get report data
        $reportData = $this->getPurchaseStockReportData($filters);

        // Pass filters and data to view
        $data = array_merge($data, $reportData, $filters);

        return view('admin.report.purchase-stock', $data);
    }

    /**
     * start_date / end_date as a whole-day range for timestamp columns
     * (a bare end date would mean midnight and leave out the rest of that day).
     */
    private function dayRange(Request $request): array
    {
        return [
            date('Y-m-d 00:00:00', strtotime($request->start_date)),
            date('Y-m-d 23:59:59', strtotime($request->end_date)),
        ];
    }

    /**
     * Get purchase & stock report data based on filters
     *
     * @param array $filters
     * @return array
     */
    private function getPurchaseStockReportData(array $filters): array
    {
        // Get aggregated purchase data grouped by product and vendor
        $purchaseData = PurchaseDetail::query()
            ->select([
                'purchase_details.product_id',
                'purchase_masters.vendor_id',
                'purchase_masters.supplier_id',
                DB::raw('SUM(purchase_details.qty) as qty_purchased'),
                DB::raw('SUM(purchase_details.qty * purchase_details.purchase_price) as purchase_value'),
            ])
            ->join('purchase_masters', 'purchase_details.purchase_master_id', '=', 'purchase_masters.id')
            ->when($filters['start_date'] && $filters['end_date'], fn($q) =>
                $q->whereBetween('purchase_masters.date', [$filters['start_date'], $filters['end_date']])
            )
            ->when($filters['vendor_id'] === 'hq', fn($q) =>
                $q->whereNull('purchase_masters.vendor_id')
            )
            ->when($filters['vendor_id'] && $filters['vendor_id'] !== 'hq', fn($q) =>
                $q->where('purchase_masters.vendor_id', (int) $filters['vendor_id'])
            )
            ->when($filters['product_id'], fn($q) =>
                $q->where('purchase_details.product_id', $filters['product_id'])
            )
            ->groupBy('purchase_details.product_id', 'purchase_masters.vendor_id', 'purchase_masters.supplier_id')
            ->get();

        if ($purchaseData->isEmpty()) {
            return [
                'reportData' => [],
                'startDate' => $filters['start_date'],
                'endDate' => $filters['end_date'],
                'vendorId' => $filters['vendor_id'],
                'productId' => $filters['product_id'],
                'stockStatus' => $filters['stock_status'],
            ];
        }

        // Collect all unique IDs for batch loading
        $productIds = $purchaseData->pluck('product_id')->unique()->toArray();
        $vendorIds = $purchaseData->pluck('vendor_id')->filter()->unique()->toArray();
        $supplierIds = $purchaseData->pluck('supplier_id')->filter()->unique()->toArray();

        // Batch load products with relationships
        $products = Product::with(['category', 'brand', 'type'])
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        // Batch load vendors (users) and suppliers (suppliers table)
        $users = User::whereIn('id', $vendorIds)->pluck('name', 'id');
        $supplierNames = \App\Models\Supplier::withoutGlobalScopes()->whereIn('id', $supplierIds)->pluck('supplier_name', 'id');

        // Get stock data for all products using Helper method
        $stockFilters = [
            'product_id' => $filters['product_id'],
        ];
        $stockResult = Helper::getStockByProduct($stockFilters);
        $stockData = $stockResult['stockData']->keyBy('product_id');

        // Build report data
        $reportData = [];

        foreach ($purchaseData as $purchase) {
            $product = $products->get($purchase->product_id);

            if (!$product) {
                continue;
            }

            // Branch (vendor) name — purchases without vendor belong to Head Office
            $vendorName = $purchase->vendor_id ? ($users[$purchase->vendor_id] ?? 'N/A') : 'Head Office';
            $supplierName = $purchase->supplier_id ? ($supplierNames[$purchase->supplier_id] ?? 'N/A') : '—';

            // Get stock data from Helper result
            $stockInfo = $stockData->get($purchase->product_id);
            $qtySold = $stockInfo ? ($stockInfo['total_out'] ?? 0) : 0;
            $qtyPurchased = (int) $purchase->qty_purchased;
            $stockBalance = $stockInfo ? ($stockInfo['stock_balance'] ?? 0) : ($qtyPurchased - $qtySold);

            $reportData[] = [
                'vendor' => $vendorName,
                'supplier' => $supplierName,
                'product' => $product->model ?? 'N/A',
                'product_id' => $product->id,
                'category' => $product->category?->category_name ?? 'N/A',
                'brand' => $product->brand?->brand_name ?? 'N/A',
                'qty_purchased' => $qtyPurchased,
                'purchase_value' => number_format($purchase->purchase_value, 2),
                'qty_sold' => (int) $qtySold,
                'stock_balance' => $stockBalance,
            ];
        }

        // Sort by vendor name, then by product
        usort($reportData, fn($a, $b) => [
            strcmp($a['vendor'], $b['vendor']),
            strcmp($a['product'], $b['product']),
        ] <=> [0, 0]);

        return [
            'reportData' => $reportData,
            'startDate' => $filters['start_date'],
            'endDate' => $filters['end_date'],
            'vendorId' => $filters['vendor_id'],
            'productId' => $filters['product_id'],
        ];
    }

    /**
     * Export purchase & stock report to Excel
     *
     * @param \Illuminate\Http\Request $request
     * @param string $type (raw|summary)
     * @param string $format (xlsx|csv)
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportPurchaseStockReport(Request $request, string $type = 'raw', string $format = 'xlsx'): BinaryFileResponse
    {
        // Get filter parameters
        $filters = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'vendor_id' => $request->vendor_id,
            'product_id' => $request->product_id,
        ];

        // Vendors can only export their own branch
        if ($branchId = auth()->user()->branchId()) {
            $filters['vendor_id'] = $branchId;
        }

        // Get report data
        $reportData = $this->getPurchaseStockReportData($filters);
        $reportData = $reportData['reportData'];

        // Get company name
        $setting = Setting::where('id', 1)->first(['company_name']);
        $companyName = $setting ? $setting->company_name : 'CRM System';

        // Get current user name
        $userName = auth()->user()->name ?? 'System';

        // Get vendor name if filtered
        $vendorName = '';
        if ($filters['vendor_id'] === 'hq') {
            $vendorName = 'Head Office';
        } elseif (!empty($filters['vendor_id'])) {
            $vendorName = (string) User::where('id', $filters['vendor_id'])->value('name');
        }

        // Get product model name if filtered
        $productName = '';
        if (!empty($filters['product_id'])) {
            $product = Product::where('id', $filters['product_id'])->first(['model']);
            $productName = $product ? $product->model : '';
        }

        // Validate export type
        if (!in_array($type, ['raw', 'summary'])) {
            $type = 'raw';
        }

        // Validate format
        if (!in_array($format, ['xlsx', 'csv'])) {
            $format = 'xlsx';
        }

        // Generate filename
        $filename = 'purchase_stock_report_' . $type;
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $filename .= '_' . $filters['start_date'] . '_to_' . $filters['end_date'];
        }
        $filename .= '_' . now()->format('Y-m-d_His');

        // Create export instance
        $export = new PurchaseStockReportExport(
            $reportData,
            $filters,
            $type,
            $companyName,
            $userName,
            $vendorName,
            $productName
        );

        return Excel::download($export, $filename . '.xlsx');
    }

    /**
     * Display and generate stock report by product
     * Shows stock in/out and balance for all products
     * Shows default data when no filters applied
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function stockReportByProduct(Request $request): View|StreamedResponse
    {
        // Get filter parameters
        $filters = [
            'product_id' => $request->product_id,
            'category_id' => $request->category_id,
            'brand_id' => $request->brand_id,
            'stock_status' => $request->stock_status ?? 'all',
        ];

        // Get stock data using Helper method
        $stockResult = Helper::getStockByProduct($filters);

        // Load filter options
        $data['categories'] = Category::all();
        $data['brands'] = Brand::all();
        $data['setting'] = Setting::where('id', 1)->first(['company_name']);

        // Get stock data
        $data['stockReports'] = $stockResult['stockData'];
        $data['totalIn'] = $stockResult['totalIn'];
        $data['totalOut'] = $stockResult['totalOut'];
        $data['totalBalance'] = $stockResult['totalBalance'];

        // Filter by product if specified
        if ($request->filled('product_id')) {
            $data['selectedProduct'] = Product::with('category')->find($request->product_id);
        }

        if ($request->input('export') === 'csv') {
            return CsvDownload::stream('stock-report-' . now()->format('Y-m-d') . '.csv',
                ['Product', 'Category', 'Brand', 'Type', 'In', 'Out', 'Balance'],
                collect($data['stockReports'])->map(fn ($i) => [$i['product'] ?? '', $i['category'] ?? '', $i['brand'] ?? '', $i['type'] ?? '', $i['total_in'], $i['total_out'], $i['stock_balance']]));
        }

        return view('admin.report.stock', $data);
    }

    /**
     * Display and generate doctor & referral report
     * Shows doctor-wise sales, commission, and payment status
     * Shows default data when no filters applied
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function doctorReferralReport(Request $request): View
    {
        // Get filter parameters
        $filters = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'doctor_id' => $request->doctor_id,
            'vendor_id' => $request->vendor_id,
            'payment_status' => $request->payment_status ?? 'all',
        ];

        // Load filter options
        $data['doctors'] = Doctor::where('status', 1)->get();
        $data['products'] = Product::with(['category', 'brand', 'type'])->get();
        $data['vendors'] = User::where('type', 'vendor')->get();

        // Get report data
        $reportData = $this->getDoctorReferralReportData($filters);

        // Pass filters and data to view
        $data = array_merge($data, $reportData, $filters);

        return view('admin.report.doctor-referral', $data);
    }

    /**
     * Get doctor referral report data based on filters
     *
     * @param array $filters
     * @return array
     */
    private function getDoctorReferralReportData(array $filters): array
    {
        // Get aggregated sales data by doctor using a single optimized query
        $salesByDoctor = SaleMaster::query()
            ->select([
                'reference_by as doctor_id',
                DB::raw('COUNT(*) as sale_count'),
                DB::raw('SUM(total_amount) as sales_value'),
                DB::raw('SUM(reference_amount) as commission_amount'),
            ])
            ->whereNotNull('reference_by')
            ->whereNotNull('reference_amount')
            ->where('reference_amount', '>', 0)
            ->when($filters['start_date'] && $filters['end_date'], fn($q) =>
                $q->whereBetween('date', [$filters['start_date'], $filters['end_date']])
            )
            ->when($filters['doctor_id'], fn($q) =>
                $q->where('reference_by', $filters['doctor_id'])
            )
            ->when($filters['vendor_id'], fn($q) =>
                $q->where('vendor_id', $filters['vendor_id'])
            )
            ->groupBy('reference_by')
            ->get();

        if ($salesByDoctor->isEmpty()) {
            return [
                'reportData' => [],
                'startDate' => $filters['start_date'],
                'endDate' => $filters['end_date'],
                'doctorId' => $filters['doctor_id'],
                'vendorId' => $filters['vendor_id'],
                'paymentStatus' => $filters['payment_status'],
            ];
        }

        // Get doctor IDs for batch loading
        $doctorIds = $salesByDoctor->pluck('doctor_id')->unique()->toArray();

        // Batch load doctor information
        $doctors = Doctor::whereIn('id', $doctorIds)
            ->get()
            ->keyBy('id');

        // Batch load all ledger data for these doctors in a single query
        $ledgerData = DoctorLedger::query()
            ->select([
                'doctor_id',
                'type',
                DB::raw('SUM(credit) as total_credit'),
                DB::raw('SUM(debit) as total_debit'),
            ])
            ->whereIn('doctor_id', $doctorIds)
            ->groupBy('doctor_id', 'type')
            ->get()
            ->groupBy('doctor_id');

        // Build report data
        $reportData = [];

        foreach ($salesByDoctor as $sale) {
            $doctor = $doctors->get($sale->doctor_id);

            if (!$doctor) {
                continue;
            }

            // Calculate ledger totals
            $ledger = $ledgerData->get($sale->doctor_id);
            $totalCredit = $ledger?->firstWhere('type', Helper::$sales)?->total_credit ?? 0;
            $totalDebit = $ledger?->firstWhere('type', 'Payment')?->total_debit ?? 0;
            $pending = $totalCredit - $totalDebit;

            // Apply payment status filter
            if ($filters['payment_status'] !== 'all') {
                if ($filters['payment_status'] === 'paid' && $pending > 0) {
                    continue;
                }
                if ($filters['payment_status'] === 'pending' && $pending <= 0) {
                    continue;
                }
            }

            // Calculate commission percentage
            $commissionPercent = $sale->sales_value > 0
                ? round(($sale->commission_amount / $sale->sales_value) * 100, 2)
                : 0;

            $reportData[] = [
                'doctor_id' => $doctor->id,
                'doctor_name' => $doctor->name,
                'qualification' => $doctor->qualification ?? 'N/A',
                'sales_value' => (float) $sale->sales_value,
                'commission_percent' => $commissionPercent,
                'commission_amount' => (float) $sale->commission_amount,
                'sale_count' => (int) $sale->sale_count,
                'paid' => (float) $totalDebit,
                'pending' => (float) $pending,
            ];
        }

        // Sort by sales value descending
        usort($reportData, fn($a, $b) => $b['sales_value'] <=> $a['sales_value']);

        return [
            'reportData' => $reportData,
            'startDate' => $filters['start_date'],
            'endDate' => $filters['end_date'],
            'doctorId' => $filters['doctor_id'],
            'vendorId' => $filters['vendor_id'],
            'paymentStatus' => $filters['payment_status'],
        ];
    }

    /**
     * Display and generate sales performance report
     * Shows sales metrics grouped by various dimensions
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function salesPerformanceReport(Request $request): View
    {
        // Get filter parameters
        $filters = [
            'group_by' => $request->group_by ?? 'vendor',
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'product_id' => $request->product_id,
            'doctor_id' => $request->doctor_id,
            'vendor_id' => $request->vendor_id,
        ];

        // Get report data using Helper method
        $reportResult = Helper::getSalesPerformanceReport($filters);

        // Load filter options
        $data['products'] = Product::select('id', 'model')->get();
        $data['doctors'] = Doctor::where('status', 1)->select('id', 'name')->get();
        $data['vendors'] = User::where('type', 'vendor')->select('id', 'name')->get();
        $data['categories'] = Category::select('id', 'category_name')->get();
        $data['membershipTiers'] = MembershipTier::select('id', 'name')->get();

        // Pass report data and filters to view
        $data = array_merge($data, $reportResult, $filters);

        return view('admin.report.sales-performance', $data);
    }

    /**
     * Export sales performance report to Excel
     *
     * @param \Illuminate\Http\Request $request
     * @param string $type (full|summary)
     * @param string $format (xlsx|csv)
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportSalesPerformanceReport(Request $request, string $type = 'full', string $format = 'xlsx'): BinaryFileResponse
    {
        // Get filter parameters
        $filters = [
            'group_by' => $request->group_by ?? 'vendor',
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'product_id' => $request->product_id,
            'doctor_id' => $request->doctor_id,
            'vendor_id' => $request->vendor_id,
        ];

        // Get report data using Helper method
        $reportResult = Helper::getSalesPerformanceReport($filters);

        // Get company name
        $setting = Setting::where('id', 1)->first(['company_name']);
        $companyName = $setting ? $setting->company_name : 'CRM System';

        // Get current user name
        $userName = auth()->user()->name ?? 'System';

        // Get product name if filtered
        $productName = '';
        if (!empty($filters['product_id'])) {
            $product = Product::where('id', $filters['product_id'])->first(['model']);
            $productName = $product ? $product->model : '';
        }

        // Get doctor name if filtered
        $doctorName = '';
        if (!empty($filters['doctor_id'])) {
            $doctor = Doctor::where('id', $filters['doctor_id'])->first(['name']);
            $doctorName = $doctor ? $doctor->name : '';
        }

        // Get vendor name if filtered
        $vendorName = '';
        if (!empty($filters['vendor_id'])) {
            $vendor = User::where('id', $filters['vendor_id'])->first(['supplier_name', 'name']);
            $vendorName = $vendor ? ($vendor->supplier_name ?? $vendor->name) : '';
        }

        // Validate export type
        if (!in_array($type, ['full', 'summary'])) {
            $type = 'full';
        }

        // Validate format
        if (!in_array($format, ['xlsx', 'csv'])) {
            $format = 'xlsx';
        }

        // Generate filename
        $filename = 'sales_performance_report_' . $type;
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $filename .= '_' . $filters['start_date'] . '_to_' . $filters['end_date'];
        }
        $filename .= '_' . now()->format('Y-m-d_His');

        // Create export instance
        $export = new SalesPerformanceReportExport(
            $reportResult['reportData'],
            $filters,
            [
                'totalQty' => $reportResult['totalQty'],
                'totalRevenue' => $reportResult['totalRevenue'],
                'totalDiscount' => $reportResult['totalDiscount'],
                'totalCommission' => $reportResult['totalCommission'],
                'totalNetRevenue' => $reportResult['totalNetRevenue'],
                'avgInvoice' => $reportResult['avgInvoice'],
            ],
            $type,
            $companyName,
            $userName,
            $productName,
            $doctorName,
            $vendorName
        );

        return Excel::download($export, $filename . '.xlsx');
    }

    /**
     * Display Membership & Loyalty Report
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function membershipLoyaltyReport(Request $request): View
    {
        // Build query for tier-wise statistics
        $query = MembershipTier::query()
            ->select([
                'membership_tiers.id',
                'membership_tiers.name',
                'membership_tiers.min_purchase',
                'membership_tiers.max_purchase',
                'membership_tiers.earn_percentage',
                DB::raw('COUNT(DISTINCT customers.id) as members_count'),
            ])
            ->leftJoin('customers', 'membership_tiers.id', '=', 'customers.membership_tier_id')
            ->when($request->filled('tier_id'), function($q) use ($request) {
                $q->where('membership_tiers.id', $request->tier_id);
            })
            ->groupBy('membership_tiers.id', 'membership_tiers.name', 'membership_tiers.min_purchase',
                     'membership_tiers.max_purchase', 'membership_tiers.earn_percentage');

        $tiers = $query->get();
        $tierIds = $tiers->pluck('id')->toArray();

        // Initialize empty data if no tiers
        if (empty($tierIds)) {
            $data['reportData'] = collect();
            $data['totalMembers'] = 0;
            $data['totalRevenue'] = 0;
            $data['totalPointsIssued'] = 0;
            $data['totalPointsRedeemed'] = 0;
            $data['referralSummary'] = (object) [
                'totalReferrals' => 0,
                'successfulReferrals' => 0,
                'pendingReferrals' => 0,
                'rewardedReferrals' => 0,
                'totalReferralPointsIssued' => 0,
            ];
        } else {
            // Batch load revenue data
            $revenueQuery = SaleMaster::query()
                ->select([
                    'customers.membership_tier_id',
                    DB::raw('SUM(sale_masters.total_amount - sale_masters.total_discount) as revenue'),
                ])
                ->join('customers', 'sale_masters.customer_id', '=', 'customers.id')
                ->whereIn('customers.membership_tier_id', $tierIds)
                ->when($request->filled('start_date') && $request->filled('end_date'), function($q) use ($request) {
                    $q->whereBetween('sale_masters.date', [$request->start_date, $request->end_date]);
                })
                ->when($request->filled('vendor_id'), function($q) use ($request) {
                    $q->where('sale_masters.vendor_id', $request->vendor_id);
                })
                ->whereNull('sale_masters.deleted_at')
                ->groupBy('customers.membership_tier_id');

            $revenueData = $revenueQuery->pluck('revenue', 'membership_tier_id');

            // Points issued (earn + referral)
            $pointsIssuedQuery = RewardLedger::query()
                ->select([
                    'customers.membership_tier_id',
                    DB::raw('SUM(CASE WHEN reward_ledgers.points > 0 THEN reward_ledgers.points ELSE 0 END) as points_issued'),
                ])
                ->join('customers', 'reward_ledgers.customer_id', '=', 'customers.id')
                ->whereIn('customers.membership_tier_id', $tierIds)
                ->whereIn('reward_ledgers.type', [RewardLedgerType::EARN->value, RewardLedgerType::REFERRAL->value, RewardLedgerType::MILESTONE->value])
                ->when($request->filled('start_date') && $request->filled('end_date'), function($q) use ($request) {
                    $q->whereBetween('reward_ledgers.created_at', $this->dayRange($request));
                })
                ->when($request->filled('points_status'), function($q) use ($request) {
                    if ($request->points_status === 'expiring') {
                        $q->where('reward_ledgers.points', '>', 0)
                          ->whereBetween('reward_ledgers.expiry_date', [now(), now()->addDays(30)]);
                    } else {
                        $q->where('reward_ledgers.points', '>', 0);
                    }
                })
                ->whereNull('reward_ledgers.deleted_at')
                ->groupBy('customers.membership_tier_id');

            $pointsIssuedData = $pointsIssuedQuery->pluck('points_issued', 'membership_tier_id');

            // Points redeemed
            $pointsRedeemedQuery = RewardLedger::query()
                ->select([
                    'customers.membership_tier_id',
                    DB::raw('SUM(ABS(reward_ledgers.points)) as points_redeemed'),
                ])
                ->join('customers', 'reward_ledgers.customer_id', '=', 'customers.id')
                ->whereIn('customers.membership_tier_id', $tierIds)
                ->where('reward_ledgers.type', RewardLedgerType::REDEEM->value)
                ->where('reward_ledgers.points', '<', 0)
                ->when($request->filled('start_date') && $request->filled('end_date'), function($q) use ($request) {
                    $q->whereBetween('reward_ledgers.created_at', $this->dayRange($request));
                })
                ->whereNull('reward_ledgers.deleted_at')
                ->groupBy('customers.membership_tier_id');

            $pointsRedeemedData = $pointsRedeemedQuery->pluck('points_redeemed', 'membership_tier_id');

            // Build report data
            $reportData = [];
            $totalMembers = 0;
            $totalRevenue = 0;
            $totalPointsIssued = 0;
            $totalPointsRedeemed = 0;

            foreach ($tiers as $tier) {
                $membersCount = (int) ($tier->members_count ?? 0);
                $revenue = (float) ($revenueData[$tier->id] ?? 0);
                $pointsIssued = (float) ($pointsIssuedData[$tier->id] ?? 0);
                $pointsRedeemed = (float) ($pointsRedeemedData[$tier->id] ?? 0);

                $reportData[] = (object) [
                    'tier' => $tier->name,
                    'tier_id' => $tier->id,
                    'members' => $membersCount,
                    'revenue' => $revenue,
                    'points_issued' => $pointsIssued,
                    'points_redeemed' => $pointsRedeemed,
                    'points_balance' => $pointsIssued - $pointsRedeemed,
                ];

                $totalMembers += $membersCount;
                $totalRevenue += $revenue;
                $totalPointsIssued += $pointsIssued;
                $totalPointsRedeemed += $pointsRedeemed;
            }

            $data['reportData'] = collect($reportData);
            $data['totalMembers'] = $totalMembers;
            $data['totalRevenue'] = $totalRevenue;
            $data['totalPointsIssued'] = $totalPointsIssued;
            $data['totalPointsRedeemed'] = $totalPointsRedeemed;

            // Referral summary
            $referralQuery = Referral::query()
                ->select([
                    DB::raw('COUNT(*) as total_referrals'),
                    DB::raw('SUM(CASE WHEN status = "successful" THEN 1 ELSE 0 END) as successful_referrals'),
                    DB::raw('SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_referrals'),
                    DB::raw('SUM(CASE WHEN rewarded_at IS NOT NULL THEN 1 ELSE 0 END) as rewarded_referrals'),
                ])
                ->when($request->filled('start_date') && $request->filled('end_date'), function($q) use ($request) {
                    $q->whereBetween('created_at', $this->dayRange($request));
                })
                ->whereNull('deleted_at');

            $referralData = $referralQuery->first();

            $referralPointsQuery = RewardLedger::query()
                ->select(DB::raw('SUM(points) as total_referral_points'))
                ->where('type', RewardLedgerType::REFERRAL->value)
                ->where('points', '>', 0)
                ->when($request->filled('start_date') && $request->filled('end_date'), function($q) use ($request) {
                    $q->whereBetween('created_at', $this->dayRange($request));
                })
                ->whereNull('deleted_at');

            $referralPointsData = $referralPointsQuery->first();

            $data['referralSummary'] = (object) [
                'totalReferrals' => (int) ($referralData->total_referrals ?? 0),
                'successfulReferrals' => (int) ($referralData->successful_referrals ?? 0),
                'pendingReferrals' => (int) ($referralData->pending_referrals ?? 0),
                'rewardedReferrals' => (int) ($referralData->rewarded_referrals ?? 0),
                'totalReferralPointsIssued' => (float) ($referralPointsData->total_referral_points ?? 0),
            ];
        }

        // Load filter options
        $data['tiers'] = MembershipTier::orderBy('min_purchase')->get();
        $data['vendors'] = User::where('type', 'vendor')->select('id', 'name')->get();

        // Pass filters to view
        $data['start_date'] = $request->start_date ?? null;
        $data['end_date'] = $request->end_date ?? null;
        $data['tier_id'] = $request->tier_id ?? null;
        $data['vendor_id'] = $request->vendor_id ?? null;
        $data['points_status'] = $request->points_status ?? null;

        return view('admin.report.membership-loyalty', $data);
    }

    /**
     * Export membership loyalty report to Excel
     *
     * @param \Illuminate\Http\Request $request
     * @param string $type (full|summary)
     * @param string $format (xlsx|csv)
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportMembershipLoyaltyReport(Request $request, string $type = 'full', string $format = 'xlsx'): BinaryFileResponse
    {
        // Get filter parameters
        $filters = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'tier_id' => $request->tier_id,
            'vendor_id' => $request->vendor_id,
            'points_status' => $request->points_status,
        ];

        // Build query for tier-wise statistics (reused from main report method)
        $query = MembershipTier::query()
            ->select([
                'membership_tiers.id',
                'membership_tiers.name',
                'membership_tiers.min_purchase',
                'membership_tiers.max_purchase',
                'membership_tiers.earn_percentage',
                DB::raw('COUNT(DISTINCT customers.id) as members_count'),
            ])
            ->leftJoin('customers', 'membership_tiers.id', '=', 'customers.membership_tier_id')
            ->when($request->filled('tier_id'), function($q) use ($request) {
                $q->where('membership_tiers.id', $request->tier_id);
            })
            ->groupBy('membership_tiers.id', 'membership_tiers.name', 'membership_tiers.min_purchase',
                     'membership_tiers.max_purchase', 'membership_tiers.earn_percentage');

        $tiers = $query->get();
        $tierIds = $tiers->pluck('id')->toArray();

        // Initialize report data
        $reportData = collect();
        $totalMembers = 0;
        $totalRevenue = 0;
        $totalPointsIssued = 0;
        $totalPointsRedeemed = 0;

        if (!empty($tierIds)) {
            // Batch load revenue data
            $revenueQuery = SaleMaster::query()
                ->select([
                    'customers.membership_tier_id',
                    DB::raw('SUM(sale_masters.total_amount - sale_masters.total_discount) as revenue'),
                ])
                ->join('customers', 'sale_masters.customer_id', '=', 'customers.id')
                ->whereIn('customers.membership_tier_id', $tierIds)
                ->when($request->filled('start_date') && $request->filled('end_date'), function($q) use ($request) {
                    $q->whereBetween('sale_masters.date', [$request->start_date, $request->end_date]);
                })
                ->when($request->filled('vendor_id'), function($q) use ($request) {
                    $q->where('sale_masters.vendor_id', $request->vendor_id);
                })
                ->groupBy('customers.membership_tier_id')
                ->get()
                ->keyBy('membership_tier_id');

            // Points issued
            $pointsIssuedQuery = RewardLedger::query()
                ->select([
                    'customers.membership_tier_id',
                    DB::raw('SUM(CASE WHEN points > 0 THEN ABS(points) ELSE 0 END) as points_issued'),
                ])
                ->join('customers', 'reward_ledgers.customer_id', '=', 'customers.id')
                ->whereIn('customers.membership_tier_id', $tierIds)
                ->when($request->filled('start_date') && $request->filled('end_date'), function($q) use ($request) {
                    $q->whereBetween('reward_ledgers.created_at', $this->dayRange($request));
                })
                ->when($request->filled('points_status'), function($q) use ($request) {
                    if ($request->points_status === 'issued') {
                        // All issued points
                    } elseif ($request->points_status === 'expiring') {
                        $q->where('reward_ledgers.expiry_date', '<=', now()->addDays(30));
                    }
                })
                ->groupBy('customers.membership_tier_id')
                ->get()
                ->keyBy('membership_tier_id');

            // Points redeemed
            $pointsRedeemedQuery = RewardLedger::query()
                ->select([
                    'customers.membership_tier_id',
                    DB::raw('SUM(CASE WHEN points < 0 THEN ABS(points) ELSE 0 END) as points_redeemed'),
                ])
                ->join('customers', 'reward_ledgers.customer_id', '=', 'customers.id')
                ->whereIn('customers.membership_tier_id', $tierIds)
                ->when($request->filled('start_date') && $request->filled('end_date'), function($q) use ($request) {
                    $q->whereBetween('reward_ledgers.created_at', $this->dayRange($request));
                })
                ->groupBy('customers.membership_tier_id')
                ->get()
                ->keyBy('membership_tier_id');

            // Build report data
            foreach ($tiers as $tier) {
                $membersCount = (int) $tier->members_count;
                $revenue = (float) ($revenueQuery->get($tier->id)?->revenue ?? 0);
                $pointsIssued = (float) ($pointsIssuedQuery->get($tier->id)?->points_issued ?? 0);
                $pointsRedeemed = (float) ($pointsRedeemedQuery->get($tier->id)?->points_redeemed ?? 0);

                $reportData->push((object) [
                    'tier' => $tier->name,
                    'tier_id' => $tier->id,
                    'members' => $membersCount,
                    'revenue' => $revenue,
                    'points_issued' => $pointsIssued,
                    'points_redeemed' => $pointsRedeemed,
                    'points_balance' => $pointsIssued - $pointsRedeemed,
                ]);

                $totalMembers += $membersCount;
                $totalRevenue += $revenue;
                $totalPointsIssued += $pointsIssued;
                $totalPointsRedeemed += $pointsRedeemed;
            }
        }

        // Get company name
        $setting = Setting::where('id', 1)->first(['company_name']);
        $companyName = $setting ? $setting->company_name : 'CRM System';

        // Get current user name
        $userName = auth()->user()->name ?? 'System';

        // Get tier name if filtered
        $tierName = '';
        if (!empty($filters['tier_id'])) {
            $tier = MembershipTier::where('id', $filters['tier_id'])->first(['name']);
            $tierName = $tier ? $tier->name : '';
        }

        // Get vendor name if filtered
        $vendorName = '';
        if (!empty($filters['vendor_id'])) {
            $vendor = User::where('id', $filters['vendor_id'])->first(['name']);
            $vendorName = $vendor ? $vendor->name : '';
        }

        // Validate export type
        if (!in_array($type, ['full', 'summary'])) {
            $type = 'full';
        }

        // Validate format
        if (!in_array($format, ['xlsx', 'csv'])) {
            $format = 'xlsx';
        }

        // Generate filename
        $filename = 'membership_loyalty_report_' . $type;
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $filename .= '_' . $filters['start_date'] . '_to_' . $filters['end_date'];
        }
        $filename .= '_' . now()->format('Y-m-d_His');

        // Prepare totals array
        $totals = [
            'totalMembers' => $totalMembers,
            'totalRevenue' => $totalRevenue,
            'totalPointsIssued' => $totalPointsIssued,
            'totalPointsRedeemed' => $totalPointsRedeemed,
        ];

        // Create export instance
        $export = new MembershipLoyaltyReportExport(
            $reportData,
            $totals,
            $filters,
            $type,
            $companyName,
            $userName,
            $tierName,
            $vendorName
        );

        return Excel::download($export, $filename . '.xlsx');
    }

    /**
     * Display Lead Funnel & Conversion Report
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function leadFunnelReport(Request $request): View
    {
        // Build base query with filters
        $query = Lead::query()
            ->when($request->filled('start_date') && $request->filled('end_date'), function($q) use ($request) {
                $q->whereBetween('created_at', $this->dayRange($request));
            })
            ->when($request->filled('source_of_entry'), function($q) use ($request) {
                $q->where('source_of_entry', $request->source_of_entry);
            })
            ->when($request->filled('vendor_id'), function($q) use ($request) {
                $q->where('vendor_id', $request->vendor_id);
            })
            ->when($request->filled('staff_id'), function($q) use ($request) {
                $q->where('created_by', $request->staff_id);
            })
            ->when($request->filled('stage'), function($q) use ($request) {
                $q->where('lead_status', $request->stage);
            })
            ->whereNull('deleted_at');

        // Clone query for different aggregations
        $baseQuery = clone $query;

        // Get count by stage (funnel)
        $stageStats = $baseQuery->select([
                'lead_status',
                DB::raw('COUNT(*) as count'),
                DB::raw('MIN(created_at) as first_created_at'),
                DB::raw('MAX(created_at) as last_created_at'),
            ])
            ->groupBy('lead_status')
            ->get()
            ->keyBy('lead_status');

        // Get total leads for conversion calculation
        $totalLeads = $query->count();

        // Build report data with conversion metrics using LeadStatus enum
        $reportData = [];
        $previousCount = 0;
        $cumulativeCount = 0;

        foreach (LeadStatus::cases() as $case) {
            $stage = $case->value;
            $label = $case->label();
            $stageData = $stageStats->get($stage);
            $count = $stageData ? (int) $stageData->count : 0;

            // Conversion % from previous stage
            if ($previousCount > 0) {
                $conversionPercent = round(($count / $previousCount) * 100, 2);
            } else {
                $conversionPercent = $totalLeads > 0 ? round(($count / $totalLeads) * 100, 2) : 0;
            }

            // Overall conversion % from initial leads
            $overallConversion = $totalLeads > 0 ? round(($count / $totalLeads) * 100, 2) : 0;

            // Calculate avg time in stage (if we have follow-up data)
            $avgTimeInHours = null;
            $avgTimeInDays = null;

            if ($stageData && $stageData->first_created_at && $stageData->last_created_at) {
                $firstTime = Carbon::parse($stageData->first_created_at);
                $lastTime = Carbon::parse($stageData->last_created_at);
                $hoursDiff = $firstTime->diffInHours($lastTime);
                $daysDiff = $firstTime->diffInDays($lastTime);

                if ($hoursDiff > 0 && $count > 1) {
                    $avgTimeInHours = round($hoursDiff / $count, 2);
                    $avgTimeInDays = round($daysDiff / $count, 2);
                }
            }

            $reportData[] = (object) [
                'stage' => $label,
                'stage_value' => $stage,
                'count' => $count,
                'conversion_percent' => $conversionPercent,
                'overall_conversion' => $overallConversion,
                'avg_time_hours' => $avgTimeInHours,
                'avg_time_days' => $avgTimeInDays,
            ];

            $previousCount = $count;
            $cumulativeCount += $count;
        }

        // Calculate overall conversion rate (Won / Total)
        $wonCount = $stageStats->get('won') ? (int) $stageStats->get('won')->count : 0;
        $overallConversionRate = $totalLeads > 0 ? round(($wonCount / $totalLeads) * 100, 2) : 0;

        $data['reportData'] = collect($reportData);
        $data['totalLeads'] = $totalLeads;
        $data['wonLeads'] = $wonCount;
        $data['lostLeads'] = $stageStats->get('lost') ? (int) $stageStats->get('lost')->count : 0;
        $data['overallConversionRate'] = $overallConversionRate;

        // Load filter options
        $data['sources'] = SourceOfEntry::cases();
        $data['staffs'] = User::select('id', 'name')->get();
        $data['vendors'] = User::where('type', 'vendor')->select('id', 'name')->get();
        $data['stages'] = LeadStatus::cases();

        // Pass filters to view
        $data['start_date'] = $request->start_date ?? null;
        $data['end_date'] = $request->end_date ?? null;
        $data['source_of_entry'] = $request->source_of_entry ?? null;
        $data['vendor_id'] = $request->vendor_id ?? null;
        $data['staff_id'] = $request->staff_id ?? null;
        $data['stage'] = $request->stage ?? null;

        return view('admin.report.lead-funnel', $data);
    }

    /**
     * Export lead funnel report to Excel
     *
     * @param \Illuminate\Http\Request $request
     * @param string $type (full|summary)
     * @param string $format (xlsx|csv)
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportLeadFunnelReport(Request $request, string $type = 'full', string $format = 'xlsx'): BinaryFileResponse
    {
        // Get filter parameters
        $filters = [
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'source_of_entry' => $request->source_of_entry,
            'vendor_id' => $request->vendor_id,
            'staff_id' => $request->staff_id,
            'stage' => $request->stage,
        ];

        // Build base query with filters
        $query = Lead::query()
            ->when($request->filled('start_date') && $request->filled('end_date'), function($q) use ($request) {
                $q->whereBetween('created_at', $this->dayRange($request));
            })
            ->when($request->filled('source_of_entry'), function($q) use ($request) {
                $q->where('source_of_entry', $request->source_of_entry);
            })
            ->when($request->filled('vendor_id'), function($q) use ($request) {
                $q->where('vendor_id', $request->vendor_id);
            })
            ->when($request->filled('staff_id'), function($q) use ($request) {
                $q->where('created_by', $request->staff_id);
            })
            ->when($request->filled('stage'), function($q) use ($request) {
                $q->where('lead_status', $request->stage);
            })
            ->whereNull('deleted_at');

        // Clone query for different aggregations
        $baseQuery = clone $query;

        // Get count by stage (funnel)
        $stageStats = $baseQuery->select([
                'lead_status',
                DB::raw('COUNT(*) as count'),
                DB::raw('MIN(created_at) as first_created_at'),
                DB::raw('MAX(created_at) as last_created_at'),
            ])
            ->groupBy('lead_status')
            ->get()
            ->keyBy('lead_status');

        // Get total leads for conversion calculation
        $totalLeads = $query->count();

        // Build report data with conversion metrics using LeadStatus enum
        $reportData = [];
        $previousCount = 0;
        $cumulativeCount = 0;

        foreach (LeadStatus::cases() as $case) {
            $stage = $case->value;
            $label = $case->label();
            $stageData = $stageStats->get($stage);
            $count = $stageData ? (int) $stageData->count : 0;

            // Conversion % from previous stage
            if ($previousCount > 0) {
                $conversionPercent = round(($count / $previousCount) * 100, 2);
            } else {
                $conversionPercent = $totalLeads > 0 ? round(($count / $totalLeads) * 100, 2) : 0;
            }

            // Overall conversion % from initial leads
            $overallConversion = $totalLeads > 0 ? round(($count / $totalLeads) * 100, 2) : 0;

            // Calculate avg time in stage
            $avgTimeInHours = null;
            $avgTimeInDays = null;

            if ($stageData && $stageData->first_created_at && $stageData->last_created_at) {
                $firstTime = Carbon::parse($stageData->first_created_at);
                $lastTime = Carbon::parse($stageData->last_created_at);
                $hoursDiff = $firstTime->diffInHours($lastTime);
                $daysDiff = $firstTime->diffInDays($lastTime);

                if ($hoursDiff > 0 && $count > 1) {
                    $avgTimeInHours = round($hoursDiff / $count, 2);
                    $avgTimeInDays = round($daysDiff / $count, 2);
                }
            }

            $reportData[] = (object) [
                'stage' => $label,
                'stage_value' => $stage,
                'count' => $count,
                'conversion_percent' => $conversionPercent,
                'overall_conversion' => $overallConversion,
                'avg_time_hours' => $avgTimeInHours,
                'avg_time_days' => $avgTimeInDays,
            ];

            $previousCount = $count;
            $cumulativeCount += $count;
        }

        // Calculate totals
        $wonCount = $stageStats->get('won') ? (int) $stageStats->get('won')->count : 0;
        $lostCount = $stageStats->get('lost') ? (int) $stageStats->get('lost')->count : 0;
        $overallConversionRate = $totalLeads > 0 ? round(($wonCount / $totalLeads) * 100, 2) : 0;

        $totals = [
            'totalLeads' => $totalLeads,
            'wonLeads' => $wonCount,
            'lostLeads' => $lostCount,
            'overallConversionRate' => $overallConversionRate,
        ];

        // Get company name
        $setting = Setting::where('id', 1)->first(['company_name']);
        $companyName = $setting ? $setting->company_name : 'CRM System';

        // Get current user name
        $userName = auth()->user()->name ?? 'System';

        // Get source name if filtered
        $sourceName = '';
        if (!empty($filters['source_of_entry'])) {
            $source = collect(SourceOfEntry::cases())->firstWhere('value', $filters['source_of_entry']);
            $sourceName = $source ? $source->label() : '';
        }

        // Get staff name if filtered
        $staffName = '';
        if (!empty($filters['staff_id'])) {
            $staff = User::where('id', $filters['staff_id'])->first(['name']);
            $staffName = $staff ? $staff->name : '';
        }

        // Get vendor name if filtered
        $vendorName = '';
        if (!empty($filters['vendor_id'])) {
            $vendor = User::where('id', $filters['vendor_id'])->first(['name']);
            $vendorName = $vendor ? $vendor->name : '';
        }

        // Get stage name if filtered
        $stageName = '';
        if (!empty($filters['stage'])) {
            $stage = collect(LeadStatus::cases())->firstWhere('value', $filters['stage']);
            $stageName = $stage ? $stage->label() : '';
        }

        // Validate export type
        if (!in_array($type, ['full', 'summary'])) {
            $type = 'full';
        }

        // Validate format
        if (!in_array($format, ['xlsx', 'csv'])) {
            $format = 'xlsx';
        }

        // Generate filename
        $filename = 'lead_funnel_report_' . $type;
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $filename .= '_' . $filters['start_date'] . '_to_' . $filters['end_date'];
        }
        $filename .= '_' . now()->format('Y-m-d_His');

        // Create export instance
        $export = new LeadFunnelReportExport(
            collect($reportData),
            $filters,
            $totals,
            $type,
            $companyName,
            $userName,
            $sourceName,
            $staffName,
            $vendorName,
            $stageName
        );

        // Export based on format
        if ($format === 'csv') {
            return Excel::download($export, $filename . '.csv');
        }

        return Excel::download($export, $filename . '.xlsx');
    }

    /**
     * Customer Advances & Refund Report (Part E)
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function advancesReport(Request $request): View|StreamedResponse
    {
        $allBranches = Helper::isAllBranchesMode();
        $branchFilter = $allBranches ? (string) $request->input('branch', 'all') : null;
        $branches = Helper::branchOptions();

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate   = $request->input('end_date', now()->toDateString());
        $status    = $request->input('status', 'all');
        $search    = $request->input('search');

        $query = CustomerAdvance::with(['customer.lead', 'appointment.service', 'creator', 'vendorUser']);

        if (!auth()->user()->canViewAllBranches() && auth()->user()->branchId()) {
            $query->where('vendor_id', auth()->user()->branchId());
        } elseif ($allBranches && $branchFilter && $branchFilter !== 'all') {
            if ($branchFilter === 'hq') {
                $query->whereNull('vendor_id');
            } else {
                $query->where('vendor_id', (int) $branchFilter);
            }
        }

        if ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('receipt_no', 'like', "%{$search}%")
                  ->orWhere('refund_receipt_no', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhereHas('customer.lead', function ($lq) use ($search) {
                      $lq->where('full_name', 'like', "%{$search}%")
                         ->orWhere('phone_number', 'like', "%{$search}%");
                  });
            });
        }

        // Summary Aggregates (scoped to the branch and date filter)
        $summaryQuery = clone $query;
        $aggregates = $summaryQuery->selectRaw("
            COUNT(*) as total_count,
            COALESCE(SUM(amount), 0) as total_received,
            COALESCE(SUM(applied_amount), 0) as total_applied,
            COALESCE(SUM(refunded_amount), 0) as total_refunded,
            COALESCE(SUM(deduction_fee), 0) as total_deduction_fee,
            COALESCE(SUM(CASE WHEN status = 'credit_kept' THEN balance ELSE 0 END), 0) as total_credit_kept,
            COALESCE(SUM(CASE WHEN status = 'active' THEN balance ELSE 0 END), 0) as total_active_balance
        ")->first();

        if ($request->input('export') === 'csv') {
            return CsvDownload::stream('advances-' . now()->format('Y-m-d') . '.csv',
                ['Date', 'Receipt', 'Patient', 'Branch', 'Received', 'Applied', 'Refunded', 'Deduction fee', 'Balance', 'Status'],
                (clone $query)->orderBy('date', 'desc')->orderBy('id', 'desc')->limit(5000)->get()->map(fn ($a) => [
                    $a->date, $a->receipt_no, $a->customer->lead->full_name ?? '', $a->vendorUser->name ?? 'Head Office',
                    $a->amount, $a->applied_amount, $a->refunded_amount, $a->deduction_fee, $a->balance, $a->status]));
        }

        $advances = $query->orderBy('date', 'desc')->orderBy('id', 'desc')->paginate(25)->withQueryString();

        return view('admin.report.advances', compact(
            'advances',
            'aggregates',
            'startDate',
            'endDate',
            'status',
            'search',
            'branchFilter',
            'branches',
            'allBranches'
        ));
    }

    /**
     * Services Status Report: Sold vs Done vs Pending (Part E)
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function servicesStatusReport(Request $request): View|StreamedResponse
    {
        $allBranches = Helper::isAllBranchesMode();
        $branchFilter = $allBranches ? (string) $request->input('branch', 'all') : null;
        $branches = Helper::branchOptions();

        $startDate   = $request->input('start_date', now()->subDays(60)->toDateString());
        $endDate     = $request->input('end_date', now()->toDateString());
        $status      = $request->input('status', 'all');
        $bookingType = $request->input('booking_type', 'all');
        $serviceTypeId = $request->input('service_type_id');
        $search      = $request->input('search');

        $query = ServiceDetail::with([
            'serviceType',
            'saleMaster.customer.lead',
            'saleMaster.vendor',
            'appointment.user',
            'doneByUser'
        ])->whereHas('saleMaster', function ($sq) use ($startDate, $endDate) {
            if ($startDate && $endDate) {
                $sq->whereBetween('date', [$startDate, $endDate]);
            }
        });

        // Branch filtering
        if (!auth()->user()->canViewAllBranches() && auth()->user()->branchId()) {
            $query->whereHas('saleMaster', function ($sq) {
                $sq->where('vendor_id', auth()->user()->branchId());
            });
        } elseif ($allBranches && $branchFilter && $branchFilter !== 'all') {
            $query->whereHas('saleMaster', function ($sq) use ($branchFilter) {
                if ($branchFilter === 'hq') {
                    $sq->whereNull('vendor_id');
                } else {
                    $sq->where('vendor_id', (int) $branchFilter);
                }
            });
        }

        if ($status && $status !== 'all') {
            $query->where('fulfillment_status', $status);
        }

        if ($bookingType && $bookingType !== 'all') {
            $query->whereHas('serviceType', function ($tq) use ($bookingType) {
                $tq->where('booking_type', $bookingType);
            });
        }

        if ($serviceTypeId) {
            $query->where('service_type_id', $serviceTypeId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('saleMaster', function ($sq) use ($search) {
                    $sq->where('sale_invoice_no', 'like', "%{$search}%")
                       ->orWhereHas('customer.lead', function ($lq) use ($search) {
                           $lq->where('full_name', 'like', "%{$search}%")
                              ->orWhere('phone_number', 'like', "%{$search}%");
                       });
                })->orWhereHas('serviceType', function ($tq) use ($search) {
                    $tq->where('service_type_name', 'like', "%{$search}%");
                });
            });
        }

        // Summary Aggregates
        $summaryQuery = clone $query;
        $allDetails = $summaryQuery->get();
        $totalSold = $allDetails->count();
        $doneCount = $allDetails->where('fulfillment_status', ServiceDetail::STATUS_DONE)->count();
        $bookedCount = $allDetails->where('fulfillment_status', ServiceDetail::STATUS_BOOKED)->count();
        $notBookedCount = $allDetails->where('fulfillment_status', ServiceDetail::STATUS_NOT_BOOKED)->count();
        $totalRevenue = $allDetails->sum(function ($s) {
            return ($s->qty * $s->service_price) - (($s->qty * $s->service_price / 100) * ($s->discount ?? 0));
        });

        // Calculate average waiting days for pending services
        $pendingItems = $allDetails->whereIn('fulfillment_status', [ServiceDetail::STATUS_NOT_BOOKED, ServiceDetail::STATUS_BOOKED]);
        $avgWaitingDays = 0;
        if ($pendingItems->count() > 0) {
            $totalDays = $pendingItems->sum(function ($item) {
                return $item->daysWaiting();
            });
            $avgWaitingDays = round($totalDays / $pendingItems->count(), 1);
        }

        if ($request->input('export') === 'csv') {
            return CsvDownload::stream('services-status-' . now()->format('Y-m-d') . '.csv',
                ['Sold on', 'Invoice', 'Patient', 'Service', 'Qty', 'Price', 'Status', 'Days waiting'],
                $allDetails->take(5000)->map(fn ($s) => [$s->saleMaster->date ?? '', $s->saleMaster->sale_invoice_no ?? '', $s->saleMaster->customer->lead->full_name ?? '',
                    $s->serviceType->service_type_name ?? '', $s->qty, $s->service_price, $s->fulfillment_status, in_array($s->fulfillment_status, [ServiceDetail::STATUS_NOT_BOOKED, ServiceDetail::STATUS_BOOKED], true) ? $s->daysWaiting() : '']));
        }

        $services = $query->orderBy('created_at', 'desc')->paginate(25)->withQueryString();
        $serviceTypes = ServiceType::where('status', 1)->orderBy('service_type_name')->get();

        return view('admin.report.services_status', compact(
            'services',
            'totalSold',
            'doneCount',
            'bookedCount',
            'notBookedCount',
            'totalRevenue',
            'avgWaitingDays',
            'startDate',
            'endDate',
            'status',
            'bookingType',
            'serviceTypeId',
            'serviceTypes',
            'search',
            'branchFilter',
            'branches',
            'allBranches'
        ));
    }
}
