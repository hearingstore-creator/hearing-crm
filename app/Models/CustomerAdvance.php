<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class CustomerAdvance extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE            = 'active';
    public const STATUS_APPLIED           = 'applied';
    public const STATUS_PARTIALLY_APPLIED = 'partially_applied';
    public const STATUS_REFUNDED          = 'refunded';
    public const STATUS_CREDIT_KEPT       = 'credit_kept';

    protected $fillable = [
        'receipt_no',
        'customer_id',
        'lead_id',
        'appointment_id',
        'sale_master_id',
        'date',
        'amount',
        'applied_amount',
        'refunded_amount',
        'deduction_fee',
        'balance',
        'payment_method',
        'reference',
        'status',
        'notes',
        'refund_date',
        'refund_method',
        'refund_reference',
        'refund_reason',
        'refund_by',
        'refund_receipt_no',
        'vendor_id',
        'created_by',
    ];

    protected $casts = [
        'date'            => 'date',
        'refund_date'     => 'date',
        'amount'          => 'float',
        'applied_amount'  => 'float',
        'refunded_amount' => 'float',
        'deduction_fee'   => 'float',
        'balance'         => 'float',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function saleMaster()
    {
        return $this->belongsTo(SaleMaster::class);
    }

    public function refundByUser()
    {
        return $this->belongsTo(User::class, 'refund_by');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function vendorUser()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE            => 'Active (Available)',
            self::STATUS_APPLIED           => 'Applied to Sale',
            self::STATUS_PARTIALLY_APPLIED => 'Partially Applied',
            self::STATUS_REFUNDED          => 'Refunded',
            self::STATUS_CREDIT_KEPT       => 'Kept as Credit',
            default                        => ucfirst($this->status),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE            => 'badge badge-success',
            self::STATUS_APPLIED           => 'badge badge-primary',
            self::STATUS_PARTIALLY_APPLIED => 'badge badge-info',
            self::STATUS_REFUNDED          => 'badge badge-danger',
            self::STATUS_CREDIT_KEPT       => 'badge badge-secondary',
            default                        => 'badge badge-light',
        };
    }

    /**
     * Generate unique advance receipt number: ADV-YYYYMMDD-XXXX
     */
    public static function generateReceiptNo(): string
    {
        $prefix = 'ADV-' . date('Ymd') . '-';
        $max = DB::table('customer_advances')
            ->where('receipt_no', 'LIKE', $prefix . '%')
            ->lockForUpdate()
            ->count();
        $next = str_pad($max + 1, 4, '0', STR_PAD_LEFT);
        return $prefix . $next;
    }

    /**
     * Generate unique refund receipt number: REF-YYYYMMDD-XXXX
     */
    public static function generateRefundReceiptNo(): string
    {
        $prefix = 'REF-' . date('Ymd') . '-';
        $max = DB::table('customer_advances')
            ->whereNotNull('refund_receipt_no')
            ->where('refund_receipt_no', 'LIKE', $prefix . '%')
            ->lockForUpdate()
            ->count();
        $next = str_pad($max + 1, 4, '0', STR_PAD_LEFT);
        return $prefix . $next;
    }
}
