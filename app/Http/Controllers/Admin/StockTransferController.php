<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Exceptions\StockGuardException;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockTransfer as Transfer;
use App\Models\User;
use App\Services\StockGuard;
use App\Services\StockTransfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Stock transfers between head office and branches.
 * Head office and Super Admin only, and only while config crm.stock_transfer (CRM_STOCK_TRANSFER) is on.
 * A transfer is never edited or deleted; a mistake is cancelled (Super Admin, with a reason).
 */
class StockTransferController extends Controller
{
    public function __construct(protected StockTransfer $transfers, protected StockGuard $guard)
    {
        $this->middleware(function ($request, $next) {
            abort_unless(config('crm.stock_transfer'), 404);

            return $next($request);
        });
        $this->middleware(fn ($request, $next) => Helper::headOfficeOnly($request, $next));
        $this->middleware('permission:stock_transfer.index')->only(['index']);
        $this->middleware('permission:stock_transfer.create')->only(['create', 'store', 'productStock']);
        $this->middleware('permission:stock_transfer.view')->only(['show']);
        $this->middleware('permission:stock_transfer.cancel')->only(['cancel']);
    }

    public function index(Request $request)
    {
        $query = Transfer::with(['details.product', 'fromBranch', 'toBranch', 'creator'])->orderByDesc('id');

        foreach (['from' => 'from_vendor_id', 'to' => 'to_vendor_id'] as $param => $column) {
            if ($request->filled($param)) {
                $request->{$param} === 'hq' ? $query->whereNull($column) : $query->where($column, (int) $request->{$param});
            }
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

        return view('admin.stock_transfer.index', [
            'transfers' => $query->paginate(25)->withQueryString(),
            'locations' => $this->locations(),
        ]);
    }

    public function create()
    {
        return view('admin.stock_transfer.create', ['locations' => $this->locations()]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'from'               => ['required', 'string', 'max:20'],
            'to'                 => ['required', 'string', 'max:20'],
            'date'               => ['required', 'date'],
            'note'               => ['nullable', 'string', 'max:1000'],
            'lines'              => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.product_id' => ['nullable', 'integer'],
            'lines.*.qty'        => ['nullable', 'numeric', 'min:0'],
            'lines.*.serials'    => ['nullable', 'string', 'max:20000'],
            'lines.*.batch_id'   => ['nullable', 'integer'],
        ]);

        try {
            $transfer = $this->transfers->post([
                'from_vendor_id' => $request->from === 'hq' ? null : (int) $request->from,
                'to_vendor_id'   => $request->to === 'hq' ? null : (int) $request->to,
                'date'           => $request->date,
                'note'           => $request->note,
                'lines'          => $request->input('lines', []),
            ], $request->user());
        } catch (StockGuardException $e) {
            return back()->withInput()->withErrors(['stock' => $e->problems()]);
        } catch (\Throwable $e) {
            return back()->withInput()->with('warning', Helper::userMessage($e));
        }

        return redirect()->route('stock-transfers.show', $transfer->id)->with('success', "Transfer {$transfer->transfer_no} saved.");
    }

    public function show(int $id)
    {
        $transfer = Transfer::with(['details.product', 'details.batch', 'fromBranch', 'toBranch', 'creator'])->findOrFail($id);
        $canceller = $transfer->cancelled_by ? User::find($transfer->cancelled_by) : null;

        return view('admin.stock_transfer.show', compact('transfer', 'canceller'));
    }

    public function cancel(Request $request, int $id)
    {
        $request->validate(['cancel_reason' => ['required', 'string', 'max:500']]);
        $transfer = Transfer::findOrFail($id);

        try {
            $this->transfers->cancel($transfer, $request->user(), $request->cancel_reason);
        } catch (StockGuardException $e) {
            return back()->withErrors(['stock' => $e->problems()]);
        } catch (\Throwable $e) {
            return back()->with('warning', Helper::userMessage($e));
        }

        return redirect()->route('stock-transfers.show', $id)->with('success', 'Transfer cancelled and stock moved back.');
    }

    /** What can be moved from a location: serials and batches in stock, for the product picked on the form. */
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
