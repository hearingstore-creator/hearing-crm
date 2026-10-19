<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Category;
use App\Models\PurchaseDetail;
use App\Models\PurchaseMaster;
use App\Models\Type;
use App\Models\Brand;
use App\Models\Stock;
use App\Services\UsageGuard;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    const BATTERY_SIZE = ['10', '13', '312', '675'];

    /**
     * Configure middleware to handle authorization for different product operations
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('permission:product.index')->only(['index']);
        $this->middleware('permission:product.create')->only(['create', 'store']);
        $this->middleware('permission:product.edit')->only(['edit', 'update']);
        $this->middleware('permission:product.delete')->only(['destroy']);
        // Product lookups used by the sale, purchase, proposal and stock screens
        $this->middleware('permission:product.index|add_sale.create|sale_list.edit|sale_list.index|add_purchase.create|purchase_list.edit|proposals.create|stock.index')
            ->only(['productSearch', 'productInfo']);
    }

    /**
     * Display a listing of all products.
     *
     * @return View
     */
    public function index(): View
    {
        $products = Product::with(['category', 'type', 'brand'])
            ->orderBy('id', 'desc')
            ->get();
        return view('admin.product.index', compact('products'));
    }

    /**
     * Show the form for creating a new product.
     *
     * @return View
     */
    public function create(): View
    {
        $categories = Category::where('status', 1)->pluck('category_name', 'id');
        $types = Type::where('status', 1)->pluck('type_name', 'id');
        $brands = Brand::where('status', 1)->pluck('brand_name', 'id');
        $batterySizes = self::BATTERY_SIZE;

        return view('admin.product.create', compact('categories', 'types', 'brands', 'batterySizes'));
    }

    /**
     * Store a newly created product in storage.
     *
     * @param Request $request HTTP request containing product data
     * @return RedirectResponse Redirects to product list or back with warning
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validate($request, [
            'category_id' => 'required|exists:categories,id',
            'type_id' => 'required|exists:types,id',
            'brand_id' => 'required|exists:brands,id',
            'model' => ['required', 'string', 'max:255', $this->uniqueModel($request)],
            'battery_size' => 'nullable|in:10,13,312,675',
            'warranty_inventory_months' => 'required|integer|min:0',
            'warranty_sales_months' => 'nullable|integer|min:1',
            'reorder_level' => 'nullable|integer|min:0|max:100000',
            'service_interval_months' => 'nullable|integer|min:1|max:60',
        ], $this->modelMessages());

        $data = $request->all();

        // Calculate warranty alert date (25 months from now for inventory)
        if ($request->warranty_inventory_months) {
            $alertDate = Carbon::now()->addMonths(25);
            $data['warranty_inventory_alert_date'] = $alertDate;
        }

        $product = new Product($data);

        if ($product->save()) {
            return redirect('admin/product')->with('success', 'Product Saved Successfully');
        }

        return redirect()->back()->with('warning', 'Product Not Saved');
    }

    /**
     * Show the form for editing the specified product.
     *
     * @param int $id Product ID
     * @return View
     */
    public function edit(int $id): View
    {
        $product = Product::findOrFail($id);
        $categories = Category::where('status', 1)->pluck('category_name', 'id');
        $types = Type::where('status', 1)->pluck('type_name', 'id');
        $brands = Brand::where('status', 1)->pluck('brand_name', 'id');
        $batterySizes = self::BATTERY_SIZE;

        return view('admin.product.edit', compact('product', 'categories', 'types', 'brands', 'batterySizes'));
    }

    /**
     * Update the specified product in storage.
     *
     * @param Request $request HTTP request containing updated product data
     * @param int $id Product ID
     * @return RedirectResponse Redirects to product list or back with warning
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $this->validate($request, [
            'category_id' => 'required|exists:categories,id',
            'type_id' => 'required|exists:types,id',
            'brand_id' => 'required|exists:brands,id',
            'model' => ['required', 'string', 'max:255', $this->uniqueModel($request, $id)],
            'battery_size' => 'nullable|in:10,13,312,675',
            'warranty_inventory_months' => 'required|integer|min:0',
            'warranty_sales_months' => 'nullable|integer|min:1',
            'reorder_level' => 'nullable|integer|min:0|max:100000',
            'service_interval_months' => 'nullable|integer|min:1|max:60',
            'status' => 'nullable|in:0,1',
        ], $this->modelMessages());

        $product = Product::findOrFail($id);
        $data = $request->all();

        // Calculate warranty alert date (25 months from now for inventory)
        if ($request->warranty_inventory_months) {
            $alertDate = Carbon::now()->addMonths(25);
            $data['warranty_inventory_alert_date'] = $alertDate;
        }

        $product->fill($data);

        if ($product->save()) {
            return redirect('admin/product')->with('success', 'Product Updated Successfully');
        }

        return redirect()->back()->with('warning', 'Product Not Updated');
    }

    /**
     * Remove the specified product from storage.
     *
     * @param int $id Product ID
     * @return RedirectResponse Redirects to product list with success message
     */
    public function destroy(int $id): RedirectResponse
    {
        $product = Product::findOrFail($id);

        // A product with stock or history stays, so reports and old invoices keep its name
        if ($blocked = UsageGuard::blockMessage("{$product->model}", UsageGuard::PRODUCT, $product->id)) {
            return redirect()->back()->with('warning', $blocked);
        }

        $product->delete();

        return redirect('/admin/product')->with('success', 'Product Deleted Successfully');
    }

    /** A brand cannot have two products with the same model name (deleted products do not count). */
    protected function uniqueModel(Request $request, ?int $ignoreId = null)
    {
        return Rule::unique('products', 'model')
            ->where(fn ($q) => $q->where('brand_id', $request->input('brand_id'))->whereNull('deleted_at'))
            ->ignore($ignoreId);
    }

    protected function modelMessages(): array
    {
        return ['model.unique' => 'This brand already has a product with that model name.'];
    }

    /**
     * Search for products based on request parameters.
     *
     * This method provides a dynamic search functionality for products, typically used for AJAX requests.
     * It filters by supplier and category, and performs a text-based search on the product name.
     * The response is a JSON array formatted for use with select2 dropdowns.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function productSearch(Request $request)
    {
        $query = Product::query();

        // Retired (Inactive) models cannot be bought again; their remaining stock can still be sold
        if ($request->type === 'purchase') {
            $query->active();
        }

        $query->when($request->category_id, function ($q) use ($request) {
            return $q->where('category_id', $request->input('category_id'));
        });

        $exclude_ids = $request->input('exclude_ids', []);

        // Sale mode lists only products with stock here: one grouped subquery instead of a stock query per product
        if ($request->type !== 'purchase') {
            $query->whereIn('id', Stock::select('product_id')->groupBy('product_id')->havingRaw('SUM(in_qty - out_qty) > 0'));
        }

        $data = $query->where(function($q) use ($request) {
                $q->where('model', 'LIKE', '%' . $request->search . '%')
                  ->orWhereHas('category', function($subQ) use ($request) {
                      $subQ->where('category_name', 'LIKE', '%' . $request->search . '%');
                  })
                  ->orWhereHas('brand', function($subQ) use ($request) {
                      $subQ->where('brand_name', 'LIKE', '%' . $request->search . '%');
                  });
            })
            ->with(['category:id,category_name'])
            ->whereNotIn('id', $exclude_ids)
            ->orderBy('model')
            ->limit(50)
            ->get(['id', 'model', 'category_id']);

        $response = [];

        foreach ($data as $val) {
            $response[] = [
                "id" => $val['id'],
                "text" => ($val['category']->category_name ?? 'Unknown') . ' - ' . ($val['model'] ?? 'N/A'),
                "category_id" => $val['category_id'],
            ];
        }
        return response()->json($response);
    }

    /**
     * Calculate the stock for a given product.
     *
     * This helper method computes the current stock level for a specific product ID
     * by summing the 'in_qty' and subtracting the 'out_qty' from the stocks table.
     *
     * @param  int  $id
     * @return int
     */
    public function productStock(int $id): int
    {
        $query = Stock::where('product_id', $id);

        return $query->value(DB::raw("SUM(in_qty - out_qty)")) ?? 0;
    }

    
    /**
     * Get batch-wise medicine information.
     *
     * @param int $id
     * @return mixed
     */
    public function batchWiseProductInfo(int $id)
    {
        $batch = Batch::where('id', $id)
            ->select('id', 'batch_no', 'ref_invoice_no', DB::raw("coalesce(SUM(purchase_qty),0) + coalesce(SUM(sale_return_qty),0) - coalesce(SUM(sale_qty),0) - coalesce(SUM(purchase_return_qty),0) as stock"))
            ->first();
        $purchaseMasterId = PurchaseMaster::where('purchase_invoice_no', $batch->ref_invoice_no)->value('id');    

        $purchaseDetails = PurchaseDetail::select('id', 'purchase_price', 'mrp_price')->where('purchase_master_id', $purchaseMasterId)
            ->whereBatchId($id)->first();

        $batch->purchase_price = $purchaseDetails->purchase_price;
        $batch->mrp_price = $purchaseDetails->mrp_price;

        return $batch;
    }

    /**
     * Get product information for purchase form.
     *
     * This method retrieves detailed product information including pricing and stock data
     * to be used in the purchase form when a product is selected.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function productInfo(int $id): JsonResponse
    {
        $product = Product::with(['category', 'type', 'brand'])
            ->where('id', $id)
            ->first();

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $stock = $this->productStock($id);

        $batchId = Batch::whereProductId($id)->orderBy('id')->value('id');
        $batch = [];

        if (!empty($batchId)) {
            $batch= $this->batchWiseProductInfo($batchId);
        }

        $data = [
            'product' => $product,
            'stock' => $stock,
            'batch' => $batch
        ];

        return response()->json($data);
    }
}
