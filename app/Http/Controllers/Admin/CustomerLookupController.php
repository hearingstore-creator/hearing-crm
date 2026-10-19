<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Enums\RewardLedgerType;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\RewardLedger;
use App\Models\SaleDetail;
use App\Models\SaleMaster;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Hotline "Customer Lookup": read-only customer 360 for support calls.
 * Search by phone, membership card no., customer UID, invoice no. or device serial no.
 * Users with "view all branches" (Super Admin / Call Center / Manager) see every branch.
 * Nothing here writes data.
 */
class CustomerLookupController extends Controller
{
    private const MAX_RESULTS = 20;

    public function __construct()
    {
        $this->middleware('permission:customer_lookup.index');
    }

    public function index(Request $request): View
    {
        $data = Helper::readAllBranches(function () use ($request) {
            $q = trim((string) $request->input('q', ''));
            $customerId = $request->integer('customer');

            $data = ['q' => $q, 'customers' => collect(), 'leads' => collect(), 'profile' => null];

            if ($customerId) {
                $data['profile'] = $this->profile($customerId);
            } elseif ($q !== '') {
                [$data['customers'], $data['leads']] = $this->search($q);
                // Exactly one customer and no other matches: open it directly
                if ($data['customers']->count() === 1 && $data['leads']->isEmpty()) {
                    $data['profile'] = $this->profile($data['customers']->first()->id);
                }
            }

            return $data;
        });

        return view('admin.customer_lookup.index', $data);
    }

    /** @return array{0: Collection, 1: Collection} customers, leads that are not customers yet */
    private function search(string $q): array
    {
        $digits = preg_replace('/\D+/', '', $q);
        $phoneTail = strlen($digits) >= 6 ? substr($digits, -10) : null;

        $customerIds = Customer::query()
            ->where('membership_id', $q)
            ->orWhere('customer_uid', $q)
            ->pluck('id');

        $customerIds = $customerIds
            ->merge(SaleMaster::where('sale_invoice_no', $q)->pluck('customer_id'))
            ->merge(
                SaleDetail::whereJsonContains('serial_no', $q)
                    ->with('salesMaster:id,customer_id')
                    ->get()
                    ->pluck('salesMaster.customer_id')
            );

        $leadQuery = Lead::query();
        if ($phoneTail) {
            $leadQuery->where(fn ($w) => $w->where('phone_number', 'like', "%{$phoneTail}%")
                ->orWhere('contact_person_phone', 'like', "%{$phoneTail}%"));
        } elseif (mb_strlen($q) >= 3) {
            $leadQuery->where('full_name', 'like', "%{$q}%");
        } else {
            $leadQuery->whereRaw('1 = 0');
        }
        $leads = $leadQuery->limit(self::MAX_RESULTS)->get(['id', 'lead_uid', 'full_name', 'phone_number', 'vendor_id', 'lead_status']);

        $leadCustomers = Customer::whereIn('lead_id', $leads->pluck('id'))->pluck('id', 'lead_id');
        $customerIds = $customerIds->merge($leadCustomers->values())->filter()->unique();

        $customers = Customer::with(['lead:id,full_name,phone_number,vendor_id', 'lead.vendor:id,name', 'membershipTier:id,name'])
            ->whereIn('id', $customerIds)
            ->limit(self::MAX_RESULTS)
            ->get();

        return [$customers, $leads->reject(fn ($l) => $leadCustomers->has($l->id))->values()];
    }

    private function profile(int $customerId): ?array
    {
        $customer = Customer::with(['lead', 'lead.vendor:id,name', 'membershipTier:id,name'])->find($customerId);
        if (!$customer) {
            return null;
        }

        $sales = SaleMaster::with('vendor:id,name')
            ->where('customer_id', $customer->id)
            ->orderByDesc('date')
            ->get();

        $devices = SaleDetail::with(['product:id,brand_id,model,warranty_sales_months,usage_months', 'product.brand:id,brand_name'])
            ->whereIn('sale_master_id', $sales->pluck('id'))
            ->get()
            ->map(function ($detail) use ($sales) {
                $sale = $sales->firstWhere('id', $detail->sale_master_id);
                $date = $sale?->date?->copy()->startOfDay();
                $product = $detail->product;

                return (object) [
                    'product'        => trim(($product?->brand->brand_name ?? '') . ' ' . ($product->model ?? '-')),
                    'serials'        => is_array($detail->serial_no) ? implode(', ', $detail->serial_no) : '',
                    'qty'            => $detail->qty,
                    'invoice'        => $sale?->sale_invoice_no,
                    'sale_id'        => $sale?->id,
                    'branch'         => $sale?->vendor->name ?? 'Head Office',
                    'sold_on'        => $date,
                    'warranty_until' => ($date && $product?->warranty_sales_months) ? $date->copy()->addMonths($product->warranty_sales_months) : null,
                    'usage_until'    => ($date && $product?->usage_months) ? $date->copy()->addMonths($product->usage_months) : null,
                ];
            });

        $expiringPoints = RewardLedger::where('customer_id', $customer->id)
            ->whereIn('type', [RewardLedgerType::EARN->value, RewardLedgerType::REFERRAL->value, RewardLedgerType::MILESTONE->value])
            ->where('points', '>', 0)
            ->whereBetween('expiry_date', [now(), now()->addDays(30)])
            ->selectRaw('SUM(points) as points, MIN(expiry_date) as first_expiry')
            ->first();

        return [
            'customer'       => $customer,
            'points'         => $customer->getAvailableRedeemPoints(),
            'expiringPoints' => (float) ($expiringPoints->points ?? 0),
            'firstExpiry'    => $expiringPoints->first_expiry ?? null,
            'sales'          => $sales,
            'devices'        => $devices,
            'tickets'        => Ticket::withoutGlobalScope('branch_owned')->where('customer_id', $customer->id)->latest()->limit(10)->get(),
            'appointments'   => Appointment::with(['service', 'user:id,name'])
                ->where(fn ($w) => $w->where('customer_id', $customer->id)->orWhere('lead_id', $customer->lead_id))
                ->orderByDesc('appointment_date')
                ->limit(10)
                ->get(),
        ];
    }
}
