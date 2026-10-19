<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Exceptions\StockGuardException;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockAdjustment as Adjustment;
use App\Models\User;
use App\Services\StockAdjustment;
use App\Services\StockGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Stock adjustments: damaged, lost, found, count corrections, opening stock.
 * Head office and Super Admin only, and only while config crm.stock_adjustment (CRM_STOCK_ADJUSTMENT) is on.
 * An adjustment is never edited or deleted; a mistake is cancelled (Super Admin, with a reason).
 */
class StockAdjustmentController extends Controller
{
    public function __construct(protected StockAdjustment $adjustments, protected StockGuard $guard)
    {
        $this->middleware(function ($request, $next) {
            abort_unless(config('crm.stock_adjustment'), 404);

            return $next($request);
        });
        $this->middleware(fn ($request, $next) => Helper::headOfficeOnly($request, $next));
        $this->middleware('permission:stock_adjustment.index')->only(['index']);
        $this->middleware('permission:stock_adjustment.create')->only(['create', 'store', 'productStock']);
        $this->middleware('permission:stock_adjustment.view')->only(['show']);
        $this->middleware('permission:stock_adjustment.cancel')->only(['cancel']);
    }

    public function index(Request $request)
    {
        $query = Adjustment::with(['details.product', 'branch', 'creator'])->orderByDesc('id');

        if ($request->filled('location')) {
            $request->location === 'hq' ? $query->whereNull('vendor_id') : $query->where('vendor_id', (int) $request->location);
        }
        if ($request->filled('reason') && isset(Adjustment::REASONS[$request->reason])) {
            $query->where('reason', $request->reason);
        }
        if ($request->filled('status') && in_array($request->status, ['completed', 'cancelled'], true)) {
            $query->where('status', $request->status);
        }
        if ($request->filled('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }
        if ($request->filled('product_id')) {
            $query->whereHas('details', fn ($q) => $q->where('product_id', (int) $request->product_id));
        }

        return view('admin.stock_adjustment.index', [
            'adjustments' => $query->paginate(25)->withQueryString(),
            'locations'   => $this->locations(),
            'reasons'     => Adjustment::REASONS,
        ]);
    }

    public function create()
    {
        return view('admin.stock_adjustment.create', [
            'locations' => $this->locations(),
            'reasons'   => Adjustment::REASONS,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'date'                  => ['required', 'date'],
            'reason'                => ['required', 'in:' . implode(',', array_keys(Adjustment::REASONS))],
            'location'              => ['nullable', 'string', 'max:20'],
            'note'                  => ['nullable', 'string', 'max:1000'],
            'lines'                 => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.product_id'    => ['nullable', 'integer'],
            'lines.*.direction'     => ['nullable', 'in:in,out'],
            'lines.*.qty'           => ['nullable', 'numeric', 'min:0'],
            'lines.*.serials'       => ['nullable', 'string', 'max:20000'],
            'lines.*.batch_id'      => ['nullable', 'integer'],
            'lines.*.batch_no'      => ['nullable', 'string', 'max:100'],
            'lines.*.unit_cost'     => ['nullable', 'numeric', 'min:0'],
            'lines.*.mrp_price'     => ['nullable', 'numeric', 'min:0'],
            'lines.*.warranty_start_date' => ['nullable', 'date'],
        ]);

        $vendorId = ($request->location && $request->location !== 'hq') ? (int) $request->location : null;

        try {
            $adjustment = $this->adjustments->post([
                'vendor_id' => $vendorId,
                'date'      => $request->date,
                'reason'    => $request->reason,
                'note'      => $request->note,
                'lines'     => $request->input('lines', []),
            ], $request->user());
        } catch (StockGuardException $e) {
            return back()->withInput()->withErrors(['stock' => $e->problems()]);
        } catch (\Throwable $e) {
            return back()->withInput()->with('warning', Helper::userMessage($e));
        }

        return redirect()->route('stock-adjustments.show', $adjustment->id)->with('success', "Adjustment {$adjustment->adjustment_no} saved.");
    }

    public function show(int $id)
    {
        $adjustment = Adjustment::with(['details.product', 'branch', 'creator'])->findOrFail($id);
        $canceller = $adjustment->cancelled_by ? User::find($adjustment->cancelled_by) : null;

        return view('admin.stock_adjustment.show', compact('adjustment', 'canceller'));
    }

    public function cancel(Request $request, int $id)
    {
        $request->validate(['cancel_reason' => ['required', 'string', 'max:500']]);
        $adjustment = Adjustment::findOrFail($id);

        try {
            $this->adjustments->cancel($adjustment, $request->user(), $request->cancel_reason);
        } catch (StockGuardException $e) {
            return back()->withErrors(['stock' => $e->problems()]);
        } catch (\Throwable $e) {
            return back()->with('warning', Helper::userMessage($e));
        }

        return redirect()->route('stock-adjustments.show', $id)->with('success', 'Adjustment cancelled and stock restored.');
    }

    /** What can be removed at a location: serials and batches in stock, for the product picked on the form. */
    public function productStock(Request $request): JsonResponse
    {
        $productId = (int) $request->product_id;
        abort_unless(Product::whereKey($productId)->exists(), 404);
        $vendorId = ($request->location && $request->location !== 'hq') ? (int) $request->location : null;

        $batches = DB::table('batches')->where('product_id', $productId)->whereNull('deleted_at')
            ->when($vendorId, fn ($q) => $q->where('vendor_id', $vendorId), fn ($q) => $q->whereNull('vendor_id'))
            ->get(['id', 'batch_no', 'purchase_qty', 'sale_return_qty', 'adjust_qty', 'sale_qty', 'purchase_return_qty'])
            ->map(fn ($b) => [
                'id'        => $b->id,
                'batch_no'  => $b->batch_no,
                'available' => round((float) $b->purchase_qty + (float) $b->sale_return_qty + (float) $b->adjust_qty - (float) $b->sale_qty - (float) $b->purchase_return_qty, 2),
            ])->filter(fn ($b) => $b['available'] > 0)->values();

        return response()->json([
            'serials' => array_values($this->guard->serialsInStock($productId, $vendorId)),
            'batches' => $batches,
        ]);
    }

    /** [id|'hq' => name] for the location pickers. */
    protected function locations(): array
    {
        return ['hq' => 'Head office'] + User::where('type', 'vendor')->orderBy('name')->pluck('name', 'id')->all();
    }
}
