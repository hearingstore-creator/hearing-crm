<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Doctor;
use App\Models\Lead;
use App\Models\Product;
use App\Models\PurchaseMaster;
use App\Models\RepairJob;
use App\Models\SaleMaster;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Header search (Ctrl+K): GET /admin/global-search?q=
 *
 * Returns groups of at most 5 results. A group is searched only when the user has one of its
 * permissions, and the models' branch scopes keep branch users inside their branch (head office
 * users who may view all branches search everything). Exact matches (phone, invoice, serial,
 * job number) are listed first.
 */
class GlobalSearchController extends Controller
{
    private const LIMIT = 5;

    public function search(Request $request): JsonResponse
    {
        $q = trim(mb_substr((string) $request->query('q', ''), 0, 100));
        if (mb_strlen($q) < 2) {
            return response()->json(['query' => $q, 'groups' => []]);
        }

        $user = $request->user();
        $can = fn (string ...$permissions) => collect($permissions)->contains(fn ($p) => $user->hasAccess($p));
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $phone = $this->phoneOf($q);

        $groups = Helper::readAllBranches(function () use ($q, $like, $phone, $can) {
            $groups = [];
            if ($can('customer_list.index', 'customer_lookup.index')) {
                $groups[] = $this->patients($q, $like, $phone, $can('customer_list.index'));
            }
            if ($can('lead_list.index', 'deal_board.index')) {
                $groups[] = $this->leads($like, $phone);
            }
            if ($can('sale_list.index')) {
                $groups[] = $this->sales($q);
            }
            if ($can('purchase_list.index')) {
                $groups[] = $this->purchases($q);
            }
            if ($can('repair_jobs_board.view', 'repair_history.view')) {
                $groups[] = $this->repairs($q);
            }
            if ($can('warranty_&_serial_check.index', 'new_repair_intake.index')) {
                $groups[] = $this->serials($q);
            }
            if ($can('product.index')) {
                $groups[] = $this->products($like);
            }
            if ($can('doctor_list.index')) {
                $groups[] = $this->doctors($like);
            }
            if ($can('supplier_list.index')) {
                $groups[] = $this->suppliers($like, $phone);
            }

            return $groups;
        });

        $groups[] = $this->pages($q);
        $groups[] = $this->actions($q, $can);

        return response()->json([
            'query'  => $q,
            'groups' => array_values(array_filter($groups, fn ($g) => !empty($g['items']))),
        ]);
    }

    /** A Bangladesh mobile in any common form (017…, 88017…, +88017…) as stored in normalized_phone. */
    private function phoneOf(string $q): ?string
    {
        $digits = preg_replace('/\D+/', '', $q);
        if (strlen($digits) < 10) {
            return null;
        }

        return Helper::normalizePhone($digits);
    }

    /**
     * Phone match on a leads query: the indexed normalized_phone, plus the last 10 digits of the
     * stored number for older rows whose normalized_phone was never filled.
     */
    private function phoneMatch($query, string $phone)
    {
        return $query->where(fn ($p) => $p->where('normalized_phone', $phone)
            ->orWhere(fn ($old) => $old->whereNull('normalized_phone')
                ->whereRaw("REPLACE(REPLACE(REPLACE(phone_number, '-', ''), ' ', ''), '+', '') LIKE ?", ['%' . substr($phone, -10)])));
    }

    private function group(string $key, string $label, string $icon, iterable $items): array
    {
        return ['key' => $key, 'label' => $label, 'icon' => $icon, 'items' => collect($items)->take(self::LIMIT)->values()->all()];
    }

    private function patients(string $q, string $like, ?string $phone, bool $canOpenProfile): array
    {
        $rows = Customer::with('lead:id,full_name,phone_number')
            ->where(function ($w) use ($q, $like, $phone) {
                $w->where('customer_uid', $q)
                  ->orWhere('customer_uid', 'like', $like)
                  ->orWhereHas('lead', function ($l) use ($like, $phone) {
                      $phone ? $this->phoneMatch($l, $phone) : $l->where('full_name', 'like', $like);
                  });
            })
            ->orderByRaw('customer_uid = ? desc', [$q])
            ->limit(self::LIMIT)
            ->get(['id', 'customer_uid', 'lead_id']);

        return $this->group('patients', 'Patients', 'fa-user', $rows->map(fn ($c) => [
            'title'    => $c->lead?->full_name ?: 'Patient',
            'subtitle' => trim(($c->lead?->phone_number ?? '') . ' · ' . $c->customer_uid, ' ·'),
            'url'      => $canOpenProfile ? url('admin/customers/' . $c->id) : url('admin/customer-lookup?customer=' . $c->id),
        ]));
    }

    private function leads(string $like, ?string $phone): array
    {
        $rows = Lead::query()
            ->whereDoesntHave('customer')
            ->where(fn ($w) => $phone
                ? $this->phoneMatch($w, $phone)
                : $w->where('full_name', 'like', $like)->orWhere('lead_uid', 'like', $like))
            ->latest('id')
            ->limit(self::LIMIT)
            ->get(['id', 'lead_uid', 'full_name', 'phone_number', 'lead_status']);

        return $this->group('leads', 'Leads', 'fa-user-plus', $rows->map(fn ($l) => [
            'title'    => $l->full_name ?: 'Lead',
            'subtitle' => trim(($l->phone_number ?? '') . ' · ' . ($l->lead_status instanceof \BackedEnum ? $l->lead_status->value : $l->lead_status), ' ·'),
            'url'      => url('admin/leads/' . $l->id),
        ]));
    }

    private function sales(string $q): array
    {
        $rows = SaleMaster::with('customer.lead:id,full_name')
            ->where('sale_invoice_no', 'like', $q . '%')
            ->orderByRaw('sale_invoice_no = ? desc', [$q])
            ->latest('id')
            ->limit(self::LIMIT)
            ->get(['id', 'sale_invoice_no', 'customer_id', 'date', 'total_amount']);

        return $this->group('sales', 'Sales invoices', 'fa-file-invoice', $rows->map(fn ($s) => [
            'title'    => 'Invoice ' . $s->sale_invoice_no,
            'subtitle' => trim(($s->customer?->lead?->full_name ?? '') . ' · ' . $s->date . ' · ৳' . number_format((float) $s->total_amount), ' ·'),
            'url'      => url('admin/sale/' . $s->sale_invoice_no),
        ]));
    }

    private function purchases(string $q): array
    {
        $rows = PurchaseMaster::with('supplier:id,supplier_name')
            ->where(fn ($w) => $w->where('purchase_invoice_no', 'like', $q . '%')->orWhere('supplier_memo_no', 'like', $q . '%'))
            ->latest('id')
            ->limit(self::LIMIT)
            ->get(['id', 'purchase_invoice_no', 'supplier_memo_no', 'supplier_id', 'date']);

        return $this->group('purchases', 'Purchases', 'fa-truck', $rows->map(fn ($p) => [
            'title'    => 'Purchase ' . $p->purchase_invoice_no,
            'subtitle' => trim(($p->supplier?->supplier_name ?? '') . ($p->supplier_memo_no ? ' · memo ' . $p->supplier_memo_no : '') . ' · ' . $p->date, ' ·'),
            'url'      => url('admin/purchase/' . $p->id),
        ]));
    }

    private function repairs(string $q): array
    {
        $rows = RepairJob::branchScope()
            ->with('customer.lead:id,full_name')
            ->where(fn ($w) => $w->where('job_no', 'like', $q . '%')
                ->orWhere('original_serial_no', $q)
                ->orWhere('replaced_serial_no', $q))
            ->latest('id')
            ->limit(self::LIMIT)
            ->get(['id', 'job_no', 'customer_id', 'original_serial_no', 'current_status']);

        return $this->group('repairs', 'Repair jobs', 'fa-tools', $rows->map(fn ($r) => [
            'title'    => 'Job ' . $r->job_no,
            'subtitle' => trim(($r->customer?->lead?->full_name ?? '') . ' · S/N ' . $r->original_serial_no . ' · ' . str_replace('_', ' ', (string) $r->current_status), ' ·'),
            'url'      => url('admin/repairs/' . $r->id),
        ]));
    }

    /** Serial numbers are matched exactly (they live inside JSON lists on purchases and sales). */
    private function serials(string $q): array
    {
        if (mb_strlen($q) < 4 || str_contains($q, ' ')) {
            return $this->group('serials', 'Serial numbers', 'fa-barcode', []);
        }
        $needle = '%"' . str_replace(['\\', '%', '_', '"'], ['\\\\', '\\%', '\\_', '\\"'], $q) . '"%';
        $sold = DB::table('sale_details')->whereNull('deleted_at')->where('serial_no', 'like', $needle)->exists();
        $bought = DB::table('purchase_details')->whereNull('deleted_at')->where('serial_no', 'like', $needle)->exists();
        if (!$sold && !$bought) {
            return $this->group('serials', 'Serial numbers', 'fa-barcode', []);
        }

        return $this->group('serials', 'Serial numbers', 'fa-barcode', [[
            'title'    => 'Serial ' . $q,
            'subtitle' => $sold ? 'Sold unit: warranty and history' : 'In stock: purchase record',
            'url'      => url('admin/repairs/lookup?serial=' . rawurlencode($q)),
        ]]);
    }

    private function products(string $like): array
    {
        $rows = Product::with('brand:id,brand_name')
            ->where(fn ($w) => $w->where('model', 'like', $like)->orWhereHas('brand', fn ($b) => $b->where('brand_name', 'like', $like)))
            ->orderBy('model')
            ->limit(self::LIMIT)
            ->get(['id', 'model', 'brand_id', 'status']);

        return $this->group('products', 'Products', 'fa-box', $rows->map(fn ($p) => [
            'title'    => $p->model,
            'subtitle' => trim(($p->brand?->brand_name ?? '') . (isset($p->status) && !$p->status ? ' · Inactive' : ''), ' ·'),
            'url'      => url('admin/product/' . $p->id . '/edit'),
        ]));
    }

    private function doctors(string $like): array
    {
        $rows = Doctor::where('name', 'like', $like)->orderBy('name')->limit(self::LIMIT)->get(['id', 'name']);

        return $this->group('doctors', 'Doctors', 'fa-user-md', $rows->map(fn ($d) => [
            'title' => $d->name, 'subtitle' => 'Doctor', 'url' => url('admin/doctor/' . $d->id . '/edit'),
        ]));
    }

    private function suppliers(string $like, ?string $phone): array
    {
        $rows = Supplier::where(fn ($w) => $w->where('supplier_name', 'like', $like)
                ->when($phone, fn ($p) => $p->orWhere('mobile_no', 'like', '%' . substr($phone, -10) . '%')))
            ->orderBy('supplier_name')->limit(self::LIMIT)->get(['id', 'supplier_name', 'mobile_no']);

        return $this->group('suppliers', 'Suppliers', 'fa-building', $rows->map(fn ($s) => [
            'title' => $s->supplier_name, 'subtitle' => $s->mobile_no ?: 'Supplier', 'url' => url('admin/suppliers/' . $s->id . '/edit'),
        ]));
    }

    /** Menu pages the user can open whose name matches ("due list", "roster"). */
    private function pages(string $q): array
    {
        $needle = mb_strtolower($q);
        $items = [];
        foreach (auth()->user()->accessibleMenus() as $parent) {
            foreach ($parent->children as $child) {
                $label = $child->label ?: $child->name;
                if (str_contains(mb_strtolower($label), $needle) || str_contains(mb_strtolower($parent->label ?: $parent->name), $needle)) {
                    $items[] = ['title' => $label, 'subtitle' => $parent->label ?: $parent->name, 'url' => url($child->route)];
                }
            }
        }

        return $this->group('pages', 'Pages', 'fa-compass', $items);
    }

    /** Quick actions ("new sale", "booking") the user is allowed to start. */
    private function actions(string $q, callable $can): array
    {
        $catalogue = [
            ['New sale', 'new sale invoice sell', 'admin/sale/create', 'add_sale.create'],
            ['New appointment', 'new appointment booking book', 'admin/appointments/create', 'add_appointment.create'],
            ['New repair intake', 'new repair intake service', 'admin/repairs/create', 'new_repair_intake.create'],
            ['New lead', 'new lead enquiry', 'admin/leads/create', 'add_lead.create'],
            ['New patient', 'new patient customer', 'admin/customers/create', 'add_customer.create'],
            ['New purchase', 'new purchase buy stock', 'admin/purchase/create', 'add_purchase.create'],
        ];
        $needle = mb_strtolower($q);
        $items = [];
        foreach ($catalogue as [$title, $words, $route, $permission]) {
            if ($can($permission) && collect(explode(' ', $words))->contains(fn ($w) => str_starts_with($w, $needle) || str_contains($needle, $w))) {
                $items[] = ['title' => $title, 'subtitle' => 'Quick action', 'url' => url($route)];
            }
        }

        return $this->group('actions', 'Actions', 'fa-bolt', $items);
    }
}
