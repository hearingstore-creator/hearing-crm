<?php

namespace App\Models;

use App\Classes\Helper;
use App\Traits\HelperTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RepairJob extends Model
{
    use HasFactory, HelperTrait;

    protected $table = 'repair_jobs';

    protected $fillable = [
        'job_no',
        'vendor_id',
        'customer_id',
        'sale_master_id',
        'sale_detail_id',
        'product_id',
        'is_third_party',
        'custom_brand',
        'custom_model',
        'original_serial_no',
        'replaced_serial_no',
        'side',
        'job_type',
        'pipeline_path',
        'is_quick_service',
        'warranty_status',
        'parent_repair_job_id',
        'void_reason',
        'void_by',
        'reported_issues',
        'physical_condition',
        'accessories_included',
        'current_status',
        'supplier_id',
        'dispatch_type',
        'courier_name',
        'courier_tracking_no',
        'carrier_person_name',
        'dispatched_at',
        'confirmed_to_lab_at',
        'lab_ref_no',
        'supplier_memo_date',
        'sent_to_lab_at',
        'lab_received_at',
        'lab_diagnosis',
        'is_chargeable',
        'inspection_fee',
        'declined_inspection_fee',
        'estimate_amount',
        'estimate_approved',
        'approved_at',
        'total_customer_cost',
        'total_customer_paid',
        'advance_receipt_no',
        'total_supplier_cost',
        'supplier_paid',
        'supplier_bill_id',
        'repair_warranty_months',
        'repair_warranty_until',
        'promised_date',
        'delivered_at',
        'delivered_by',
        'received_by',
        'created_by',
        'remarks',
        'cancellation_reason',
        'cancellation_mode',
    ];

    protected $casts = [
        'accessories_included'    => 'array',
        'sent_to_lab_at'          => 'datetime',
        'dispatched_at'           => 'datetime',
        'confirmed_to_lab_at'     => 'datetime',
        'lab_received_at'         => 'datetime',
        'approved_at'             => 'datetime',
        'repair_warranty_until'   => 'date',
        'supplier_memo_date'      => 'date',
        'promised_date'           => 'datetime',
        'delivered_at'            => 'datetime',
        'is_third_party'          => 'boolean',
        'is_quick_service'        => 'boolean',
        'is_chargeable'           => 'boolean',
        'estimate_approved'       => 'boolean',
        'inspection_fee'          => 'decimal:2',
        'declined_inspection_fee' => 'decimal:2',
        'estimate_amount'         => 'decimal:2',
        'total_customer_cost'     => 'decimal:2',
        'total_customer_paid'     => 'decimal:2',
        'total_supplier_cost'     => 'decimal:2',
        'supplier_paid'           => 'decimal:2',
    ];

    /**
     * Scope for multi-branch filtering
     */
    public function scopeBranchScope($query)
    {
        $user = auth()->user();
        if (!$user) {
            return $query;
        }

        if (method_exists($user, 'canViewAllBranches') && $user->canViewAllBranches()) {
            return $query;
        }

        $branchId = method_exists($user, 'branchId') ? $user->branchId() : ($user->vendor_id ?? null);
        if ($branchId) {
            return $query->where('vendor_id', $branchId);
        }

        return $query;
    }

    // Relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function branch()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function saleMaster()
    {
        return $this->belongsTo(SaleMaster::class);
    }

    public function saleDetail()
    {
        return $this->belongsTo(SaleDetail::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lab()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function deliveredBy()
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voidBy()
    {
        return $this->belongsTo(User::class, 'void_by');
    }

    public function parentRepairJob()
    {
        return $this->belongsTo(RepairJob::class, 'parent_repair_job_id');
    }

    public function childRepairJobs()
    {
        return $this->hasMany(RepairJob::class, 'parent_repair_job_id');
    }

    public function statusLogs()
    {
        return $this->hasMany(RepairStatusLog::class, 'repair_job_id')->orderBy('created_at', 'desc');
    }

    public function items()
    {
        return $this->hasMany(RepairItem::class, 'repair_job_id');
    }

    public function attachments()
    {
        return $this->hasMany(RepairAttachment::class, 'repair_job_id');
    }

    public function customerLedgers()
    {
        return $this->hasMany(CustomerLedger::class, 'ref_invoice_no', 'job_no');
    }

    public function supplierLedgers()
    {
        return $this->hasMany(SupplierLedger::class, 'ref_invoice_no', 'job_no');
    }

    public function replacementChain()
    {
        return $this->hasOne(SerialChain::class, 'replacement_job_id');
    }

    public function originalSerialChain()
    {
        return $this->hasOne(SerialChain::class, 'serial_no', 'original_serial_no');
    }

    public function tasks()
    {
        return $this->hasMany(RepairTask::class, 'repair_job_id')->orderBy('due_date', 'asc');
    }

    public function pendingTasks()
    {
        return $this->hasMany(RepairTask::class, 'repair_job_id')->where('is_completed', false)->orderBy('due_date', 'asc');
    }

    public function supplierBill()
    {
        return $this->belongsTo(PurchaseMaster::class, 'supplier_bill_id');
    }

    // Accessors & Helpers
    public function getCustomerDueAttribute(): float
    {
        return max(0, (float)$this->total_customer_cost - (float)$this->total_customer_paid);
    }

    public function getGrossProfitAttribute(): float
    {
        $billed = (float)$this->total_customer_cost;
        $labCost = (float)$this->total_supplier_cost;
        $partsCost = (float)$this->items->where('item_type', 'part')->sum('supplier_total');
        return $billed - $labCost - $partsCost;
    }

    public function getGrossMarginPercentAttribute(): float
    {
        $billed = (float)$this->total_customer_cost;
        if ($billed <= 0) {
            return 0.0;
        }
        return round(($this->gross_profit / $billed) * 100, 1);
    }

    public function getDeviceNameAttribute(): string
    {
        if ($this->is_third_party) {
            $name = trim(($this->custom_brand ?? '') . ' ' . ($this->custom_model ?? ''));
            return $name ?: 'Third-Party Hearing Aid';
        }

        if ($this->product) {
            return $this->product->product_name ?: 'Hearing Aid #' . $this->product->id;
        }

        return $this->custom_model ?: ($this->custom_brand ?: 'Hearing Aid');
    }

    public function getProductDisplayNameAttribute(): string
    {
        return $this->device_name;
    }

    public function getOutcomeAttribute(): string
    {
        if ($this->current_status === 'cancelled') {
            return 'Cancelled';
        }
        if (in_array($this->current_status, ['returned_unrepaired', 'patient_declined'])) {
            return 'Declined';
        }
        if ($this->is_quick_service) {
            return 'In-house';
        }
        $isReplaced = !empty($this->replaced_serial_no) && $this->replaced_serial_no !== $this->original_serial_no;
        $isWarranty = ($this->warranty_status === 'under_product_warranty' || $this->pipeline_path === 'supplier_warranty');
        if ($isReplaced) {
            return $isWarranty ? 'Warranty replacement' : 'Paid replacement';
        }
        return $isWarranty ? 'Warranty repair' : 'Paid repair';
    }

    /**
     * Map any current_status to the 6 Plan B Pipeline Stages
     */
    public function getStageAttribute(): string
    {
        // If estimate was approved and not yet ready/delivered, it is in Stage 4: In Repair
        if ($this->estimate_approved && in_array($this->current_status, ['patient_approved', 'in_repair', 'in_lab'])) {
            return 'in_repair';
        }

        return match ($this->current_status) {
            'received'                                      => 'received',
            'sent_to_lab', 'at_lab', 'in_lab'               => 'at_lab',
            'waiting_patient_approval', 'waiting_approval'   => 'waiting_approval',
            'patient_approved', 'in_repair'                 => 'in_repair',
            'lab_received', 'ready_for_pickup'              => 'ready_for_pickup',
            'delivered'                                     => 'delivered',
            'patient_declined', 'returned_unrepaired'       => 'returned_unrepaired',
            'cancelled'                                     => 'cancelled',
            default                                         => 'received',
        };
    }

    public function getStageNameAttribute(): string
    {
        return match ($this->stage) {
            'received'            => '1. Received',
            'at_lab'              => '2. At Lab',
            'waiting_approval'    => '3. Waiting Approval',
            'in_repair'           => '4. In Repair',
            'ready_for_pickup'    => '5. Ready for Pickup',
            'delivered'           => '6. Delivered',
            'returned_unrepaired' => 'Returned Unrepaired',
            'cancelled'           => 'Cancelled',
            default               => 'Received',
        };
    }

    public function getDaysInStageAttribute(): int
    {
        $lastLog = $this->statusLogs()->first();
        $date = $lastLog ? $lastLog->created_at : $this->updated_at;
        return max(0, (int)Carbon::parse($date)->diffInDays(now()));
    }

    /**
     * Generate human-friendly sequential Job Number: REP-YYMM-0001
     */
    public static function generateJobNo(): string
    {
        $prefix = 'REP-' . date('ym') . '-';
        $latest = self::where('job_no', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->value('job_no');

        if ($latest) {
            $num = (int)substr($latest, -4) + 1;
        } else {
            $num = 1;
        }

        return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
    }
}
