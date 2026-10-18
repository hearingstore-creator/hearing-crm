<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\RepairJob;
use App\Models\SaleDetail;
use App\Models\SaleMaster;
use App\Models\SerialChain;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WarrantyVerificationService
{
    /**
     * Check intake eligibility for a serial number.
     * Centralized business logic used by verifyWarranty (AJAX) and RepairController@store.
     *
     * @return array{
     *     allow_repair: bool,
     *     block_reason: 'open_job'|'replaced'|'decommissioned'|null,
     *     is_decommissioned: bool,
     *     is_replaced: bool,
     *     message: string|null,
     *     details: array
     * }
     */
    public function intakeEligibility(string $serial): array
    {
        $serial = trim($serial);
        if (empty($serial)) {
            return [
                'allow_repair'      => false,
                'block_reason'      => null,
                'is_decommissioned' => false,
                'is_replaced'       => false,
                'message'           => 'Please provide a serial number.',
                'details'           => [],
            ];
        }

        // Rule 3: Check if device is already in an open repair job (not delivered / cancelled / returned unrepaired / patient declined)
        $openJob = RepairJob::where(function ($q) use ($serial) {
            $q->where('original_serial_no', $serial)
              ->orWhere('replaced_serial_no', $serial);
        })
        ->whereNotIn('current_status', ['delivered', 'cancelled', 'patient_declined', 'returned_unrepaired'])
        ->latest('id')
        ->first();

        if ($openJob) {
            $stageName = $openJob->stage_name ?? ucfirst(str_replace('_', ' ', $openJob->current_status));
            $jobUrl = route('repairs.show', $openJob->id);
            return [
                'allow_repair'      => false,
                'block_reason'      => 'open_job',
                'is_decommissioned' => false,
                'is_replaced'       => false,
                'message'           => "This device is already in repair job #{$openJob->job_no} (stage: {$stageName}).",
                'details'           => [
                    'job_id'         => $openJob->id,
                    'job_no'         => $openJob->job_no,
                    'job_url'        => $jobUrl,
                    'current_status' => $openJob->current_status,
                    'stage'          => $openJob->stage,
                    'stage_name'     => $stageName,
                ],
            ];
        }

        // Rule 2 & 4: Check if device was replaced by a newer, DIFFERENT serial
        // A device counts as "replaced" only when a newer serial exists that is DIFFERENT from it
        // (a SerialChain child with a different serial_no, or a job whose replaced_serial_no is not null, not empty, and not equal to the original).
        $childChain = SerialChain::where('parent_serial_no', $serial)
            ->whereNotNull('serial_no')
            ->where('serial_no', '<>', '')
            ->where('serial_no', '<>', $serial)
            ->latest('id')
            ->first();

        $replacementJob = RepairJob::where('original_serial_no', $serial)
            ->whereNotNull('replaced_serial_no')
            ->where('replaced_serial_no', '<>', '')
            ->where('replaced_serial_no', '<>', $serial)
            ->latest('id')
            ->first();

        $activeSerial = null;
        if ($childChain || $replacementJob) {
            // Traverse chain to the ultimate active serial with cycle detection & max 20 steps
            $visited = [$serial];
            $steps = 0;
            $maxSteps = 20;

            if ($childChain) {
                $curr = $childChain;
                $activeSerial = $curr->serial_no;
                $visited[] = $curr->serial_no;

                while ($steps < $maxSteps) {
                    $steps++;
                    $next = SerialChain::where('parent_serial_no', $curr->serial_no)
                        ->whereNotNull('serial_no')
                        ->where('serial_no', '<>', '')
                        ->where('serial_no', '<>', $curr->serial_no)
                        ->latest('id')
                        ->first();

                    if (!$next || in_array($next->serial_no, $visited, true)) {
                        break;
                    }
                    $visited[] = $next->serial_no;
                    $activeSerial = $next->serial_no;
                    $curr = $next;
                }
            } elseif ($replacementJob) {
                $activeSerial = $replacementJob->replaced_serial_no;
                $visited[] = $activeSerial;

                // Also check if that replaced serial was further replaced in SerialChain
                $currSerial = $activeSerial;
                while ($steps < $maxSteps) {
                    $steps++;
                    $next = SerialChain::where('parent_serial_no', $currSerial)
                        ->whereNotNull('serial_no')
                        ->where('serial_no', '<>', '')
                        ->where('serial_no', '<>', $currSerial)
                        ->latest('id')
                        ->first();

                    if (!$next || in_array($next->serial_no, $visited, true)) {
                        break;
                    }
                    $visited[] = $next->serial_no;
                    $activeSerial = $next->serial_no;
                    $currSerial = $next->serial_no;
                }
            }

            if (!empty($activeSerial) && $activeSerial !== $serial) {
                $challanNo = $replacementJob?->lab_ref_no;
                $supplierName = $replacementJob?->supplier?->supplier_name;
                $replacedDate = $replacementJob?->lab_received_at?->format('d M Y')
                    ?? ($replacementJob?->updated_at?->format('d M Y')
                    ?? ($childChain?->created_at?->format('d M Y')));

                $msg = "This device (S/N: {$serial}) was surrendered to supplier and replaced by new unit S/N: {$activeSerial}" .
                    ($challanNo ? " under Supplier Challan #{$challanNo}" : "") .
                    ($replacedDate ? " on {$replacedDate}" : "") .
                    ". The defective unit is retained by supplier and can no longer be serviced.";

                return [
                    'allow_repair'           => false,
                    'block_reason'           => 'replaced',
                    'is_decommissioned'      => true,
                    'is_replaced'            => true,
                    'serial_no'              => $serial,
                    'replaced_by_serial'     => $activeSerial,
                    'replacement_job_id'     => $replacementJob?->id,
                    'replacement_job_no'     => $replacementJob?->job_no,
                    'replacement_date'       => $replacedDate,
                    'replacement_challan_no' => $challanNo,
                    'supplier_name'          => $supplierName,
                    'status'                 => 'retained_by_supplier',
                    'status_label'           => 'Replaced & Retained by Supplier',
                    'message'                => $msg,
                    'details'                => [
                        'replaced_by_serial'     => $activeSerial,
                        'replacement_job_id'     => $replacementJob?->id,
                        'replacement_job_no'     => $replacementJob?->job_no,
                        'replacement_date'       => $replacedDate,
                        'replacement_challan_no' => $challanNo,
                        'supplier_name'          => $supplierName,
                    ],
                ];
            }
        }

        // Rule: Check decommissioned status in SerialChain without replacement
        $chain = SerialChain::where('serial_no', $serial)->first();
        if ($chain && in_array($chain->status, [
            'retained_by_supplier', 'credited_by_supplier', 'scrapped', 'with_supplier_lab'
        ])) {
            $statusLabel = ucwords(str_replace('_', ' ', $chain->status));
            return [
                'allow_repair'      => false,
                'block_reason'      => 'decommissioned',
                'is_decommissioned' => true,
                'is_replaced'       => false,
                'status'            => $chain->status,
                'status_label'      => $statusLabel,
                'serial_no'         => $serial,
                'message'           => "This unit (S/N: {$serial}) is decommissioned (Status: {$statusLabel}) and cannot be accepted for repair.",
                'details'           => [
                    'status'       => $chain->status,
                    'status_label' => $statusLabel,
                ],
            ];
        }

        // Allowed to proceed with repair intake
        return [
            'allow_repair'      => true,
            'block_reason'      => null,
            'is_decommissioned' => false,
            'is_replaced'       => false,
            'message'           => null,
            'details'           => [],
        ];
    }

    /**
     * Check warranty and history by serial number
     */
    public function checkBySerial(string $serial): array
    {
        $serial = trim($serial);
        if (empty($serial)) {
            return [
                'found'             => false,
                'allow_repair'      => false,
                'block_reason'      => null,
                'is_decommissioned' => false,
                'is_replaced'       => false,
                'message'           => 'Please provide a serial number.',
            ];
        }

        // 1. Unified intake eligibility check
        $eligibility = $this->intakeEligibility($serial);

        // 2. Check Serial Chains (for replacements, prior jobs, or chain history)
        $chain = SerialChain::with(['product', 'customer.lead', 'originalSale', 'replacementJob'])
            ->where('serial_no', $serial)
            ->first();

        // 3. Check previous repair jobs on this serial
        $priorRepairs = RepairJob::with(['receivedBy', 'supplier'])
            ->where(function ($q) use ($serial) {
                $q->where('original_serial_no', $serial)
                  ->orWhere('replaced_serial_no', $serial);
            })
            ->orderBy('id', 'desc')
            ->get();

        $activeServiceWarrantyJob = $priorRepairs->first(function ($job) {
            return $job->repair_warranty_until && (Carbon::parse($job->repair_warranty_until)->isFuture() || Carbon::parse($job->repair_warranty_until)->isToday());
        });

        // 4. Search in SaleDetails JSON array
        $saleDetail = SaleDetail::with(['saleMaster.customer.lead', 'product'])
            ->where(function ($q) use ($serial) {
                $q->whereJsonContains('serial_no', $serial)
                  ->orWhere('serial_no', 'like', '%"' . $serial . '"%');
            })
            ->latest('id')
            ->first();

        $saleMaster = $saleDetail ? $saleDetail->saleMaster : ($chain ? $chain->originalSale : null);
        $customer = $saleMaster ? $saleMaster->customer : ($chain ? $chain->customer : ($priorRepairs->first()?->customer));
        $product = $saleDetail ? $saleDetail->product : ($chain ? $chain->product : ($priorRepairs->first()?->product));

        // If NOT eligible for repair (blocked due to open_job, replaced, or decommissioned)
        if (!$eligibility['allow_repair']) {
            return array_merge($eligibility, [
                'found'               => true,
                'serial_no'           => $serial,
                'active_tier'         => $eligibility['block_reason'] === 'open_job' ? 'open_job' : 'decommissioned',
                'customer'            => $customer ? [
                    'id'      => $customer->id,
                    'name'    => $customer->lead ? $customer->lead->full_name : 'Customer #' . $customer->id,
                    'phone'   => $customer->lead ? $customer->lead->phone_number : '',
                    'uid'     => $customer->customer_uid ?? '',
                    'address' => $customer->lead ? $customer->lead->address : '',
                ] : null,
                'product'             => $product ? [
                    'id'              => $product->id,
                    'name'            => $product->product_name,
                    'code'            => $product->product_code ?? '',
                    'brand_id'        => $product->brand_id ?? null,
                    'warranty_months' => (int)($product->warranty_sales_months ?? 0),
                ] : null,
                'sale_master_id'      => $saleMaster?->id,
                'sale_invoice_no'     => $saleMaster?->sale_invoice_no,
                'sale_date'           => $saleMaster?->date ? Carbon::parse($saleMaster->date)->format('d M Y') : null,
                'prior_repairs_count' => $priorRepairs->count(),
                'prior_repairs'       => $priorRepairs->map(function ($job) {
                    return [
                        'id'              => $job->id,
                        'job_no'          => $job->job_no,
                        'status'          => $job->current_status,
                        'warranty_status' => $job->warranty_status,
                        'date'            => $job->created_at ? $job->created_at->format('d M Y') : '',
                        'cost'            => (float)$job->total_customer_cost,
                        'service_warr'    => $job->repair_warranty_until ? Carbon::parse($job->repair_warranty_until)->format('d M Y') : null,
                    ];
                })->toArray(),
            ]);
        }

        // If eligible for repair, check records
        if (!$saleDetail && !$chain) {
            // Check if there are only repair jobs for a third party device
            if ($priorRepairs->isNotEmpty()) {
                $latestJob = $priorRepairs->first();
                return $this->formatResult(
                    found: true,
                    matchType: 'repair_history_only',
                    serial: $serial,
                    saleDetail: null,
                    saleMaster: null,
                    customer: $latestJob->customer,
                    product: $latestJob->product,
                    customBrand: $latestJob->custom_brand,
                    customModel: $latestJob->custom_model,
                    chain: $chain,
                    activeServiceWarrantyJob: $activeServiceWarrantyJob,
                    priorRepairs: $priorRepairs
                );
            }

            // Third-party / walk-in device not found anywhere
            return [
                'found'             => false,
                'allow_repair'      => true,
                'block_reason'      => null,
                'is_decommissioned' => false,
                'is_replaced'       => false,
                'serial_no'         => $serial,
                'message'           => "Serial number '{$serial}' was not found in sales or service records.",
            ];
        }

        return $this->formatResult(
            found: true,
            matchType: $chain && $chain->parent_serial_no ? 'replacement_chain' : 'sale_record',
            serial: $serial,
            saleDetail: $saleDetail,
            saleMaster: $saleMaster,
            customer: $customer,
            product: $product,
            customBrand: null,
            customModel: null,
            chain: $chain,
            activeServiceWarrantyJob: $activeServiceWarrantyJob,
            priorRepairs: $priorRepairs
        );
    }

    /**
     * Search available sold devices by invoice or customer phone/name
     */
    public function searchPurchases(string $query): Collection
    {
        $query = trim($query);
        $results = collect();

        // 1. By Invoice No
        $salesByInvoice = SaleMaster::with(['customer.lead', 'saleDetails.product'])
            ->where('sale_invoice_no', 'like', "%{$query}%")
            ->take(5)
            ->get();

        foreach ($salesByInvoice as $sale) {
            foreach ($sale->saleDetails as $detail) {
                $serials = is_array($detail->serial_no) ? $detail->serial_no : (json_decode($detail->serial_no ?? '[]', true) ?: []);
                if (empty($serials)) {
                    $results->push($this->formatPurchaseItem($sale, $detail, null));
                } else {
                    foreach ($serials as $sn) {
                        $results->push($this->formatPurchaseItem($sale, $detail, $sn));
                    }
                }
            }
        }

        // 2. By Customer Phone / Name / UID
        $customers = Customer::with(['lead', 'saleMasters.saleDetails.product'])
            ->whereHas('lead', function ($q) use ($query) {
                $q->where('phone_number', 'like', "%{$query}%")
                  ->orWhere('full_name', 'like', "%{$query}%");
            })
            ->orWhere('customer_uid', 'like', "%{$query}%")
            ->take(5)
            ->get();

        foreach ($customers as $cust) {
            foreach ($cust->saleMasters as $sale) {
                foreach ($sale->saleDetails as $detail) {
                    $serials = is_array($detail->serial_no) ? $detail->serial_no : (json_decode($detail->serial_no ?? '[]', true) ?: []);
                    if (empty($serials)) {
                        $results->push($this->formatPurchaseItem($sale, $detail, null));
                    } else {
                        foreach ($serials as $sn) {
                            $results->push($this->formatPurchaseItem($sale, $detail, $sn));
                        }
                    }
                }
            }
        }

        return $results->unique(function ($item) {
            return ($item['sale_id'] ?? '') . '-' . ($item['product_id'] ?? '') . '-' . ($item['serial_no'] ?? '');
        })->values();
    }

    /**
     * Helper to compute warranty status and dates
     */
    protected function formatResult(
        bool $found,
        string $matchType,
        string $serial,
        ?SaleDetail $saleDetail,
        ?SaleMaster $saleMaster,
        ?Customer $customer,
        ?Product $product,
        ?string $customBrand,
        ?string $customModel,
        ?SerialChain $chain,
        ?RepairJob $activeServiceWarrantyJob,
        Collection $priorRepairs
    ): array {
        $today = Carbon::today();

        // 1. Calculate Product Warranty (Supplier-Backed)
        $warrantyMonths = (int)($product->warranty_sales_months ?? 0);
        $saleDate = $saleMaster ? Carbon::parse($saleMaster->date) : null;
        
        $productWarrantyUntil = null;
        $isProductWarrantyValid = false;
        $productWarrantyDaysRemaining = 0;

        if ($chain && $chain->warranty_end_date) {
            $productWarrantyUntil = Carbon::parse($chain->warranty_end_date);
        } elseif ($saleDate && $warrantyMonths > 0) {
            $productWarrantyUntil = $saleDate->copy()->addMonths($warrantyMonths);
        }

        if ($productWarrantyUntil) {
            $isProductWarrantyValid = $productWarrantyUntil->isFuture() || $productWarrantyUntil->isToday();
            $productWarrantyDaysRemaining = (int)$today->diffInDays($productWarrantyUntil, false);
        }

        // Check if voided
        $isVoid = ($chain && $chain->is_void);
        $voidReason = $chain ? $chain->void_reason : null;

        // 2. Calculate Tier 2: Service Warranty (Post-Warranty Repair Guarantee)
        $isServiceWarrantyValid = false;
        $serviceWarrantyUntil = null;
        $serviceWarrantyDaysRemaining = 0;

        if ($activeServiceWarrantyJob) {
            $serviceWarrantyUntil = Carbon::parse($activeServiceWarrantyJob->repair_warranty_until);
            $isServiceWarrantyValid = $serviceWarrantyUntil->isFuture() || $serviceWarrantyUntil->isToday();
            $serviceWarrantyDaysRemaining = (int)$today->diffInDays($serviceWarrantyUntil, false);
        }

        // 3. Determine Overall Active Tier
        $activeTier = 'out_of_warranty';
        if ($isVoid) {
            $activeTier = 'void';
        } elseif ($isProductWarrantyValid) {
            $activeTier = 'product_warranty'; // Tier 1 (Supplier-Backed, ৳0)
        } elseif ($isServiceWarrantyValid) {
            $activeTier = 'service_warranty'; // Tier 2 (Post-Warranty Repair Guarantee)
        }

        return [
            'found'                         => true,
            'allow_repair'                  => true,
            'block_reason'                  => null,
            'is_decommissioned'             => false,
            'is_replaced'                   => false,
            'match_type'                    => $matchType,
            'serial_no'                     => $serial,
            'active_tier'                   => $activeTier,
            'is_void'                       => $isVoid,
            'void_reason'                   => $voidReason,
            // Tier 1 Details
            'is_under_product_warranty'     => $isProductWarrantyValid && !$isVoid,
            'product_warranty_months'       => $warrantyMonths,
            'product_warranty_until'        => $productWarrantyUntil ? $productWarrantyUntil->format('d M Y') : null,
            'product_warranty_until_raw'    => $productWarrantyUntil ? $productWarrantyUntil->format('Y-m-d') : null,
            'product_warranty_days_left'    => max(0, $productWarrantyDaysRemaining),
            // Tier 2 Details
            'is_under_service_warranty'     => $isServiceWarrantyValid && !$isProductWarrantyValid,
            'service_warranty_until'        => $serviceWarrantyUntil ? $serviceWarrantyUntil->format('d M Y') : null,
            'service_warranty_days_left'    => max(0, $serviceWarrantyDaysRemaining),
            'parent_service_job_id'         => $activeServiceWarrantyJob ? $activeServiceWarrantyJob->id : null,
            'parent_service_job_no'         => $activeServiceWarrantyJob ? $activeServiceWarrantyJob->job_no : null,
            // Chain Lineage
            'is_replacement_unit'           => $chain && !empty($chain->parent_serial_no),
            'parent_serial_no'              => $chain ? $chain->parent_serial_no : null,
            // Linked Records
            'sale_master_id'                => $saleMaster ? $saleMaster->id : null,
            'sale_invoice_no'               => $saleMaster ? $saleMaster->sale_invoice_no : null,
            'sale_date'                     => $saleDate ? $saleDate->format('d M Y') : null,
            'sale_detail_id'                => $saleDetail ? $saleDetail->id : null,
            'product'                       => $product ? [
                'id'       => $product->id,
                'name'     => $product->product_name,
                'code'     => $product->product_code ?? '',
                'brand_id' => $product->brand_id ?? null,
                'warranty_months' => $warrantyMonths,
            ] : null,
            'custom_brand'                  => $customBrand,
            'custom_model'                  => $customModel,
            'customer'                      => $customer ? [
                'id'       => $customer->id,
                'name'     => $customer->lead ? $customer->lead->full_name : 'Customer #' . $customer->id,
                'phone'    => $customer->lead ? $customer->lead->phone_number : '',
                'uid'      => $customer->customer_uid ?? '',
                'address'  => $customer->lead ? $customer->lead->address : '',
            ] : null,
            'prior_repairs_count'           => $priorRepairs->count(),
            'prior_repairs'                 => $priorRepairs->map(function ($job) {
                return [
                    'id'             => $job->id,
                    'job_no'         => $job->job_no,
                    'status'         => $job->current_status,
                    'warranty_status'=> $job->warranty_status,
                    'date'           => $job->created_at ? $job->created_at->format('d M Y') : '',
                    'cost'           => (float)$job->total_customer_cost,
                    'service_warr'   => $job->repair_warranty_until ? Carbon::parse($job->repair_warranty_until)->format('d M Y') : null,
                ];
            })->toArray(),
        ];
    }

    protected function formatPurchaseItem(SaleMaster $sale, SaleDetail $detail, ?string $serial): array
    {
        $product = $detail->product;
        $saleDate = Carbon::parse($sale->date);
        $warrantyMonths = (int)($product->warranty_sales_months ?? 0);
        $warrantyUntil = $warrantyMonths > 0 ? $saleDate->copy()->addMonths($warrantyMonths) : null;
        $isWarranty = $warrantyUntil && ($warrantyUntil->isFuture() || $warrantyUntil->isToday());

        $isReplaced = false;
        $replacedBySerial = null;
        if (!empty($serial)) {
            $child = SerialChain::where('parent_serial_no', $serial)
                ->whereNotNull('serial_no')
                ->where('serial_no', '<>', '')
                ->where('serial_no', '<>', $serial)
                ->latest('id')
                ->first();

            $replJob = RepairJob::where('original_serial_no', $serial)
                ->whereNotNull('replaced_serial_no')
                ->where('replaced_serial_no', '<>', '')
                ->where('replaced_serial_no', '<>', $serial)
                ->latest('id')
                ->first();

            if ($child) {
                $isReplaced = true;
                $curr = $child;
                $visited = [$serial, $curr->serial_no];
                $steps = 0;
                $maxSteps = 20;
                while ($steps < $maxSteps) {
                    $steps++;
                    $next = SerialChain::where('parent_serial_no', $curr->serial_no)
                        ->whereNotNull('serial_no')
                        ->where('serial_no', '<>', '')
                        ->where('serial_no', '<>', $curr->serial_no)
                        ->latest('id')
                        ->first();
                    if (!$next || in_array($next->serial_no, $visited, true)) {
                        break;
                    }
                    $visited[] = $next->serial_no;
                    $curr = $next;
                }
                $replacedBySerial = $curr->serial_no;
            } elseif ($replJob) {
                $isReplaced = true;
                $replacedBySerial = $replJob->replaced_serial_no;
            }
        }

        return [
            'sale_id'            => $sale->id,
            'sale_invoice_no'    => $sale->sale_invoice_no,
            'sale_date'          => $saleDate->format('d M Y'),
            'product_id'         => $product ? $product->id : null,
            'product_name'       => $product ? $product->product_name : 'Hearing Aid',
            'serial_no'          => $serial ?? '',
            'customer_id'        => $sale->customer_id,
            'customer_name'      => $sale->customer && $sale->customer->lead ? $sale->customer->lead->full_name : 'Customer',
            'customer_phone'     => $sale->customer && $sale->customer->lead ? $sale->customer->lead->phone_number : '',
            'warranty_until'     => $warrantyUntil ? $warrantyUntil->format('d M Y') : 'No Warranty',
            'is_warranty_valid'  => $isWarranty,
            'is_replaced'        => $isReplaced,
            'replaced_by_serial' => $replacedBySerial,
            'active_serial'      => $isReplaced ? $replacedBySerial : ($serial ?? ''),
        ];
    }
}

