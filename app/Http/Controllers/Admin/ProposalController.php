<?php

namespace App\Http\Controllers\Admin;

use App\Classes\Helper;
use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Doctor;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\ServiceType;
use App\Models\Setting;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProposalController extends Controller
{
    /**
     * Configure middleware to handle authorization for different proposal operations
     */
    public function __construct()
    {
        $this->middleware('permission:proposals.index')->only(['index']);
        $this->middleware('permission:proposals.create')->only(['create', 'store', 'addRowItem']);
        $this->middleware('permission:proposals.edit')->only(['edit', 'update', 'updateStatus']);
        $this->middleware('permission:proposals.delete')->only(['destroy']);
        $this->middleware('permission:proposals.view')->only(['show', 'downloadPdf', 'convertToSale']);
    }

    /**
     * Display a listing of all proposals with metrics and filtering.
     */
    public function index(Request $request): View
    {
        $query = Proposal::with(['lead', 'customer.lead', 'doctor', 'vendor', 'convertedSale', 'items'])
            ->orderBy('id', 'desc');

        // Status tab filter
        $currentStatus = $request->input('status', 'all');
        if ($currentStatus !== 'all' && in_array($currentStatus, array_column(ProposalStatus::cases(), 'value'))) {
            $query->where('status', $currentStatus);
        }

        // Recipient Type filter
        if ($request->filled('recipient_type')) {
            $rtype = $request->input('recipient_type');
            if ($rtype === 'lead') {
                $query->whereNotNull('lead_id');
            } elseif ($rtype === 'customer') {
                $query->whereNotNull('customer_id');
            } elseif ($rtype === 'direct') {
                $query->whereNull('lead_id')->whereNull('customer_id');
            }
        }

        // Search filter (Proposal No, Title, Recipient Name, Phone)
        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('proposal_no', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%")
                    ->orWhere('recipient_name', 'like', "%{$q}%")
                    ->orWhere('recipient_phone', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhereHas('lead', function ($l) use ($q) {
                        $l->where('full_name', 'like', "%{$q}%")
                          ->orWhere('phone_number', 'like', "%{$q}%");
                    })
                    ->orWhereHas('customer.lead', function ($cl) use ($q) {
                        $cl->where('full_name', 'like', "%{$q}%")
                           ->orWhere('phone_number', 'like', "%{$q}%");
                    });
            });
        }

        // Date range filter
        if ($request->filled('from_date')) {
            $query->whereDate('proposal_date', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('proposal_date', '<=', $request->input('to_date'));
        }

        // KPIs calculation (using base scoped query without status filters)
        $metricsBase = Proposal::query();
        $totalCount = (clone $metricsBase)->count();
        $totalAmount = (clone $metricsBase)->sum('grand_total');
        $acceptedCount = (clone $metricsBase)->where('status', ProposalStatus::ACCEPTED->value)->count();
        $acceptedAmount = (clone $metricsBase)->where('status', ProposalStatus::ACCEPTED->value)->sum('grand_total');
        $pendingCount = (clone $metricsBase)->whereIn('status', [ProposalStatus::DRAFT->value, ProposalStatus::SENT->value, ProposalStatus::NEW->value])->count();
        $pendingAmount = (clone $metricsBase)->whereIn('status', [ProposalStatus::DRAFT->value, ProposalStatus::SENT->value, ProposalStatus::NEW->value])->sum('grand_total');
        $convertedCount = (clone $metricsBase)->where('status', ProposalStatus::CONVERTED->value)->count();
        $conversionRate = $totalCount > 0 ? round((($acceptedCount + $convertedCount) / $totalCount) * 100, 1) : 0;

        $proposals = $query->paginate(25)->withQueryString();

        return view('admin.proposal.index', compact(
            'proposals',
            'currentStatus',
            'totalCount',
            'totalAmount',
            'acceptedCount',
            'acceptedAmount',
            'pendingCount',
            'pendingAmount',
            'convertedCount',
            'conversionRate'
        ));
    }

    /**
     * Show the form for creating a new proposal.
     */
    public function create(Request $request): View
    {
        $leads = Lead::orderBy('full_name')->select('id', 'full_name', 'phone_number', 'email', 'vendor_id')->get();
        $customers = Customer::with('lead:id,full_name,phone_number,email')->orderBy('id', 'desc')->get();
        $doctors = Doctor::where('status', 1)->orderBy('name')->get();
        $templates = Template::where('status', 1)->orderBy('id', 'desc')->get();
        $products = Product::with(['category', 'brand'])->orderBy('model')->get();
        $serviceTypes = ServiceType::where('status', 1)->orderBy('service_type_name')->get();
        $branches = Helper::branchOptions();

        // Check if pre-populating from Lead, Deal, or Customer
        $prefillLead = null;
        $prefillCustomer = null;
        $prefillDeal = null;

        if ($request->filled('lead_id')) {
            $prefillLead = Lead::find($request->integer('lead_id'));
        }
        if ($request->filled('customer_id')) {
            $prefillCustomer = Customer::with('lead')->find($request->integer('customer_id'));
        }
        if ($request->filled('deal_id')) {
            $prefillDeal = Deal::with('lead')->find($request->integer('deal_id'));
            if ($prefillDeal && $prefillDeal->lead) {
                $prefillLead = $prefillDeal->lead;
            }
        }

        // Preview next proposal_no
        $prefix = 'QUO-' . date('Ym') . '-';
        $lastNo = Proposal::withoutGlobalScopes()
            ->where('proposal_no', 'LIKE', $prefix . '%')
            ->orderByDesc('id')
            ->value('proposal_no');
        $nextSeq = $lastNo ? ((int) substr($lastNo, strlen($prefix)) + 1) : 1;
        $nextProposalNo = $prefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);

        return view('admin.proposal.create', compact(
            'leads',
            'customers',
            'doctors',
            'templates',
            'products',
            'serviceTypes',
            'branches',
            'prefillLead',
            'prefillCustomer',
            'prefillDeal',
            'nextProposalNo'
        ));
    }

    /**
     * AJAX endpoint to return a new line item row.
     */
    public function addRowItem(Request $request): View
    {
        $index = $request->input('index', time());
        $itemType = $request->input('type', 'product');
        $products = Product::with(['category', 'brand'])->orderBy('model')->get();
        $serviceTypes = ServiceType::where('status', 1)->orderBy('service_type_name')->get();

        return view('admin.proposal.add_row_item', compact('index', 'itemType', 'products', 'serviceTypes'));
    }

    /**
     * Store a newly created proposal in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validate($request, [
            'recipient_type' => 'required|in:lead,customer,direct',
            'lead_id' => 'required_if:recipient_type,lead|nullable|exists:leads,id',
            'customer_id' => 'required_if:recipient_type,customer|nullable|exists:customers,id',
            'recipient_name' => 'required_if:recipient_type,direct|nullable|string|max:191',
            'recipient_phone' => 'nullable|string|max:50',
            'recipient_email' => 'nullable|email|max:191',
            'title' => 'required|string|max:255',
            'proposal_date' => 'required|date',
            'valid_until' => 'nullable|date|after_or_equal:proposal_date',
            'doctor_id' => 'nullable|exists:doctors,id',
            'vendor_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:' . implode(',', array_column(ProposalStatus::cases(), 'value')),
            'items' => 'nullable|array',
            'items.*.item_type' => 'nullable|string',
            'items.*.item_name' => 'nullable|string',
            'items.*.qty' => 'nullable|numeric|min:0.1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $proposal = new Proposal();
            $proposal->title = $request->title;
            $proposal->proposal_date = $request->proposal_date;
            $proposal->valid_until = $request->valid_until;
            $proposal->doctor_id = $request->doctor_id ?: null;
            $proposal->vendor_id = $request->vendor_id ?: (auth()->check() ? auth()->user()->branchId() : null);
            $proposal->template_id = $request->template_id ?: null;
            $proposal->status = $request->status ?: ProposalStatus::DRAFT->value;
            $proposal->terms_and_conditions = $request->terms_and_conditions;
            $proposal->notes = $request->notes;
            $proposal->content = $request->content;

            // Recipient binding
            $rtype = $request->recipient_type;
            if ($rtype === 'lead') {
                $lead = Lead::find($request->lead_id);
                $proposal->lead_id = $lead->id;
                $proposal->customer_id = $lead->customer?->id ?? null;
                $proposal->deal_id = $request->deal_id ?: null;
                $proposal->recipient_name = $lead->full_name;
                $proposal->recipient_phone = $lead->phone_number;
                $proposal->recipient_address = $lead->address;
                $proposal->email = $lead->email;
            } elseif ($rtype === 'customer') {
                $customer = Customer::with('lead')->find($request->customer_id);
                $proposal->customer_id = $customer->id;
                $proposal->lead_id = $customer->lead_id;
                $proposal->recipient_name = $customer->name;
                $proposal->recipient_phone = $customer->lead?->phone_number;
                $proposal->recipient_address = $customer->lead?->address;
                $proposal->email = $customer->lead?->email;
            } else {
                $proposal->customer_id = null;
                $proposal->lead_id = null;
                $proposal->deal_id = null;
                $proposal->recipient_name = $request->recipient_name;
                $proposal->recipient_phone = $request->recipient_phone;
                $proposal->recipient_address = $request->recipient_address;
                $proposal->email = $request->recipient_email;
            }

            // Financial Calculations
            $subtotal = 0;
            $rawItems = $request->input('items', []);
            $itemsToInsert = [];

            if (is_array($rawItems)) {
                $sort = 0;
                foreach ($rawItems as $raw) {
                    $itemName = trim($raw['item_name'] ?? '');
                    $productId = !empty($raw['product_id']) ? (int) $raw['product_id'] : null;
                    $serviceId = !empty($raw['service_id']) ? (int) $raw['service_id'] : null;

                    // If item name is empty, auto-fetch from product or service
                    if (empty($itemName)) {
                        if ($productId) {
                            $p = Product::find($productId);
                            $itemName = $p ? $p->product_name : 'Hearing Aid Item';
                        } elseif ($serviceId) {
                            $s = ServiceType::find($serviceId);
                            $itemName = $s ? $s->service_type_name : 'Clinical Service';
                        }
                    }

                    if (empty($itemName) && empty($productId) && empty($serviceId)) {
                        continue;
                    }

                    $qty = max(0.01, (float) ($raw['qty'] ?? 1));
                    $unitPrice = max(0, (float) ($raw['unit_price'] ?? 0));
                    $discount = max(0, (float) ($raw['discount'] ?? 0));
                    $discountPercent = max(0, (float) ($raw['discount_percent'] ?? 0));
                    $lineGross = $qty * $unitPrice;

                    if ($discountPercent > 0 && $discount == 0) {
                        $discount = round(($lineGross * $discountPercent) / 100, 2);
                    } elseif ($discount > 0 && $discountPercent == 0 && $lineGross > 0) {
                        $discountPercent = round(($discount / $lineGross) * 100, 2);
                    }

                    $lineTotal = max(0, $lineGross - $discount);
                    $subtotal += $lineTotal;

                    $itemsToInsert[] = [
                        'item_type' => $raw['item_type'] ?? ($productId ? 'product' : ($serviceId ? 'service' : 'custom')),
                        'product_id' => $productId,
                        'service_id' => $serviceId,
                        'item_name' => $itemName ?: 'Item',
                        'description' => $raw['description'] ?? null,
                        'qty' => $qty,
                        'unit_price' => $unitPrice,
                        'discount_percent' => $discountPercent,
                        'discount' => $discount,
                        'total_price' => $lineTotal,
                        'warranty_months' => !empty($raw['warranty_months']) ? (int) $raw['warranty_months'] : null,
                        'sort_order' => $sort++,
                    ];
                }
            }

            // If no items were entered but flat total was entered, use flat total
            if (empty($itemsToInsert) && $request->filled('flat_total_amount')) {
                $subtotal = max(0, (float) $request->flat_total_amount);
            }

            $discountType = $request->input('discount_type', 'fixed');
            $discountValue = max(0, (float) $request->input('discount_amount', 0));
            $discountPercent = max(0, (float) $request->input('discount_percent', 0));

            if ($discountPercent > 0 && $discountValue == 0) {
                $discountValue = round($subtotal * ($discountPercent / 100), 2);
            } elseif ($discountValue > 0 && $discountPercent == 0 && $subtotal > 0) {
                $discountPercent = round(($discountValue / $subtotal) * 100, 2);
            }

            $overallDiscount = $discountValue;
            $vatAmount = max(0, (float) $request->input('vat_amount', 0));
            $grandTotal = max(0, ($subtotal - $overallDiscount) + $vatAmount);

            $proposal->subtotal = $subtotal;
            $proposal->discount_type = $discountType;
            $proposal->discount_percent = $discountPercent;
            $proposal->discount_amount = $discountValue;
            $proposal->vat_amount = $vatAmount;
            $proposal->grand_total = $grandTotal;
            $proposal->total_amount = $grandTotal;

            $proposal->save();

            // Insert line items
            foreach ($itemsToInsert as $itemData) {
                $proposal->items()->create($itemData);
            }

            DB::commit();

            return redirect()->route('proposal.show', $proposal->id)->with('success', "Proposal {$proposal->proposal_no} generated successfully!");
        } catch (\Throwable $ex) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('warning', 'Failed to save proposal: ' . $ex->getMessage());
        }
    }

    /**
     * Display the specified proposal in medical quotation layout.
     */
    public function show(int $id): View
    {
        $proposal = Proposal::with([
            'items.product.category',
            'items.product.brand',
            'items.service',
            'lead',
            'customer.lead',
            'doctor',
            'vendor',
            'convertedSale',
            'template'
        ])->findOrFail($id);

        $setting = Setting::first();

        return view('admin.proposal.show', compact('proposal', 'setting'));
    }

    /**
     * Show the form for editing the specified proposal.
     */
    public function edit(int $id): View
    {
        $proposal = Proposal::with(['items.product', 'items.service', 'lead', 'customer.lead'])->findOrFail($id);

        $leads = Lead::orderBy('full_name')->select('id', 'full_name', 'phone_number', 'email', 'vendor_id')->get();
        $customers = Customer::with('lead:id,full_name,phone_number,email')->orderBy('id', 'desc')->get();
        $doctors = Doctor::where('status', 1)->orderBy('name')->get();
        $templates = Template::where('status', 1)->orderBy('id', 'desc')->get();
        $products = Product::with(['category', 'brand'])->orderBy('model')->get();
        $serviceTypes = ServiceType::where('status', 1)->orderBy('service_type_name')->get();
        $branches = Helper::branchOptions();

        return view('admin.proposal.edit', compact(
            'proposal',
            'leads',
            'customers',
            'doctors',
            'templates',
            'products',
            'serviceTypes',
            'branches'
        ));
    }

    /**
     * Update the specified proposal in storage.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $proposal = Proposal::findOrFail($id);

        $this->validate($request, [
            'recipient_type' => 'required|in:lead,customer,direct',
            'lead_id' => 'required_if:recipient_type,lead|nullable|exists:leads,id',
            'customer_id' => 'required_if:recipient_type,customer|nullable|exists:customers,id',
            'recipient_name' => 'required_if:recipient_type,direct|nullable|string|max:191',
            'recipient_phone' => 'nullable|string|max:50',
            'recipient_email' => 'nullable|email|max:191',
            'title' => 'required|string|max:255',
            'proposal_date' => 'required|date',
            'valid_until' => 'nullable|date|after_or_equal:proposal_date',
            'doctor_id' => 'nullable|exists:doctors,id',
            'vendor_id' => 'nullable|exists:users,id',
            'status' => 'nullable|in:' . implode(',', array_column(ProposalStatus::cases(), 'value')),
            'items' => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            $proposal->title = $request->title;
            $proposal->proposal_date = $request->proposal_date;
            $proposal->valid_until = $request->valid_until;
            $proposal->doctor_id = $request->doctor_id ?: null;
            if ($request->filled('vendor_id')) {
                $proposal->vendor_id = $request->vendor_id;
            }
            $proposal->template_id = $request->template_id ?: null;
            if ($request->filled('status')) {
                $proposal->status = $request->status;
            }
            $proposal->terms_and_conditions = $request->terms_and_conditions;
            $proposal->notes = $request->notes;
            $proposal->content = $request->content;

            // Recipient binding
            $rtype = $request->recipient_type;
            if ($rtype === 'lead') {
                $lead = Lead::find($request->lead_id);
                $proposal->lead_id = $lead->id;
                $proposal->customer_id = $lead->customer?->id ?? null;
                $proposal->recipient_name = $lead->full_name;
                $proposal->recipient_phone = $lead->phone_number;
                $proposal->recipient_address = $lead->address;
                $proposal->email = $lead->email;
            } elseif ($rtype === 'customer') {
                $customer = Customer::with('lead')->find($request->customer_id);
                $proposal->customer_id = $customer->id;
                $proposal->lead_id = $customer->lead_id;
                $proposal->recipient_name = $customer->name;
                $proposal->recipient_phone = $customer->lead?->phone_number;
                $proposal->recipient_address = $customer->lead?->address;
                $proposal->email = $customer->lead?->email;
            } else {
                $proposal->customer_id = null;
                $proposal->lead_id = null;
                $proposal->recipient_name = $request->recipient_name;
                $proposal->recipient_phone = $request->recipient_phone;
                $proposal->recipient_address = $request->recipient_address;
                $proposal->email = $request->recipient_email;
            }

            // Sync line items
            $subtotal = 0;
            $rawItems = $request->input('items', []);
            $proposal->items()->forceDelete();

            if (is_array($rawItems)) {
                $sort = 0;
                foreach ($rawItems as $raw) {
                    $itemName = trim($raw['item_name'] ?? '');
                    $productId = !empty($raw['product_id']) ? (int) $raw['product_id'] : null;
                    $serviceId = !empty($raw['service_id']) ? (int) $raw['service_id'] : null;

                    if (empty($itemName)) {
                        if ($productId) {
                            $p = Product::find($productId);
                            $itemName = $p ? $p->product_name : 'Hearing Aid Item';
                        } elseif ($serviceId) {
                            $s = ServiceType::find($serviceId);
                            $itemName = $s ? $s->service_type_name : 'Clinical Service';
                        }
                    }

                    if (empty($itemName) && empty($productId) && empty($serviceId)) {
                        continue;
                    }

                    $qty = max(0.01, (float) ($raw['qty'] ?? 1));
                    $unitPrice = max(0, (float) ($raw['unit_price'] ?? 0));
                    $discount = max(0, (float) ($raw['discount'] ?? 0));
                    $discountPercent = max(0, (float) ($raw['discount_percent'] ?? 0));
                    $lineGross = $qty * $unitPrice;

                    if ($discountPercent > 0 && $discount == 0) {
                        $discount = round(($lineGross * $discountPercent) / 100, 2);
                    } elseif ($discount > 0 && $discountPercent == 0 && $lineGross > 0) {
                        $discountPercent = round(($discount / $lineGross) * 100, 2);
                    }

                    $lineTotal = max(0, $lineGross - $discount);
                    $subtotal += $lineTotal;

                    $proposal->items()->create([
                        'item_type' => $raw['item_type'] ?? ($productId ? 'product' : ($serviceId ? 'service' : 'custom')),
                        'product_id' => $productId,
                        'service_id' => $serviceId,
                        'item_name' => $itemName ?: 'Item',
                        'description' => $raw['description'] ?? null,
                        'qty' => $qty,
                        'unit_price' => $unitPrice,
                        'discount_percent' => $discountPercent,
                        'discount' => $discount,
                        'total_price' => $lineTotal,
                        'warranty_months' => !empty($raw['warranty_months']) ? (int) $raw['warranty_months'] : null,
                        'sort_order' => $sort++,
                    ]);
                }
            }

            if ($subtotal == 0 && $request->filled('flat_total_amount')) {
                $subtotal = max(0, (float) $request->flat_total_amount);
            }

            $discountType = $request->input('discount_type', 'fixed');
            $discountValue = max(0, (float) $request->input('discount_amount', 0));
            $discountPercent = max(0, (float) $request->input('discount_percent', 0));

            if ($discountPercent > 0 && $discountValue == 0) {
                $discountValue = round($subtotal * ($discountPercent / 100), 2);
            } elseif ($discountValue > 0 && $discountPercent == 0 && $subtotal > 0) {
                $discountPercent = round(($discountValue / $subtotal) * 100, 2);
            }

            $overallDiscount = $discountValue;
            $vatAmount = max(0, (float) $request->input('vat_amount', 0));
            $grandTotal = max(0, ($subtotal - $overallDiscount) + $vatAmount);

            $proposal->subtotal = $subtotal;
            $proposal->discount_type = $discountType;
            $proposal->discount_percent = $discountPercent;
            $proposal->discount_amount = $discountValue;
            $proposal->vat_amount = $vatAmount;
            $proposal->grand_total = $grandTotal;
            $proposal->total_amount = $grandTotal;

            $proposal->save();

            DB::commit();

            return redirect()->route('proposal.show', $proposal->id)->with('success', "Proposal {$proposal->proposal_no} updated successfully!");
        } catch (\Throwable $ex) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('warning', 'Failed to update proposal: ' . $ex->getMessage());
        }
    }

    /**
     * Quick status update endpoint.
     */
    public function updateStatus(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $proposal = Proposal::findOrFail($id);
        $status = $request->input('status');

        if (!in_array($status, array_column(ProposalStatus::cases(), 'value'))) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Invalid status'], 422);
            }
            return redirect()->back()->with('warning', 'Invalid status specified');
        }

        $proposal->status = $status;
        $proposal->save();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'status' => $proposal->status,
                'color' => $proposal->status_color,
                'message' => "Proposal status updated to {$proposal->status}"
            ]);
        }

        return redirect()->back()->with('success', "Proposal status updated to {$proposal->status}");
    }

    /**
     * 1-Click Convert to Sale / Invoice handler.
     */
    public function convertToSale(Request $request, int $id): RedirectResponse
    {
        $proposal = Proposal::with(['items', 'lead', 'customer.lead'])->findOrFail($id);

        // Ensure recipient has a Customer record
        $customer = $proposal->customer;
        if (!$customer && $proposal->lead_id) {
            $customer = Customer::firstOrCreate(
                ['lead_id' => $proposal->lead_id],
                ['referral_code' => 'REF-' . strtoupper(Str::random(6))]
            );
            $proposal->customer_id = $customer->id;
            $proposal->save();
        }

        if (!$customer) {
            return redirect()->back()->with('warning', 'Please link a Patient / Customer to this proposal before converting to invoice.');
        }

        // Redirect to Sale Create with pre-fill query parameters
        $proposal->status = ProposalStatus::CONVERTED->value;
        $proposal->save();

        return redirect()->route('sale.create', [
            'from_proposal_id' => $proposal->id,
            'customer_id' => $customer->id,
        ])->with('success', "Proposal {$proposal->proposal_no} linked! Please review the pre-filled invoice and confirm payment.");
    }

    /**
     * Send quotation summary via email.
     */
    public function sendEmail(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $proposal = Proposal::with(['items', 'vendor', 'lead', 'customer'])->findOrFail($id);
        $setting = Setting::first();
        $senderName = $proposal->vendor ? $proposal->vendor->name : ($setting->company_name ?? 'ZenHear Hearing Care');

        try {
            \Illuminate\Support\Facades\Mail::raw($request->input('message'), function ($message) use ($request, $senderName) {
                $message->to($request->input('email'))
                        ->subject($request->input('subject'));
            });

            // Mark proposal as Sent if it was draft
            if ($proposal->status === ProposalStatus::DRAFT->value) {
                $proposal->status = ProposalStatus::SENT->value;
                $proposal->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Quotation email sent successfully to ' . $request->input('email') . '!',
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Proposal email sending failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Could not send via system SMTP (' . $e->getMessage() . '). Please click "Open in Email App" to send via your local email client.',
            ], 422);
        }
    }

    /**
     * Print / PDF view.
     */
    public function downloadPdf(int $id): View
    {
        $proposal = Proposal::with([
            'items.product.brand',
            'items.service',
            'lead',
            'customer.lead',
            'doctor',
            'vendor'
        ])->findOrFail($id);

        $setting = Setting::first();

        return view('admin.proposal.show', compact('proposal', 'setting'));
    }

    /**
     * Remove the specified proposal from storage.
     */
    public function destroy(int $id): RedirectResponse
    {
        $proposal = Proposal::findOrFail($id);
        $no = $proposal->proposal_no;
        $proposal->items()->delete();
        $proposal->delete();

        return redirect()->route('proposal.index')->with('success', "Proposal {$no} deleted successfully.");
    }
}
