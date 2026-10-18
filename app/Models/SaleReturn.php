<?php

namespace App\Models;

use App\Classes\Helper;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleReturn extends Model
{
    use HasFactory, HelperTrait;

    protected $table = 'sale_returns';

    protected $fillable = [
        'credit_note_no',
        'sale_master_id',
        'sale_invoice_no',
        'customer_id',
        'vendor_id',
        'date',
        'return_subtotal',
        'deduction_amount',
        'credit_note_amount',
        'due_amount_at_return',
        'due_reduced_amount',
        'patient_refund_amount',
        'settlement_type',
        'payment_type_id',
        'doctor_id',
        'doctor_commission_adjusted',
        'reason',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'date'                       => 'date',
        'return_subtotal'            => 'decimal:2',
        'deduction_amount'           => 'decimal:2',
        'credit_note_amount'         => 'decimal:2',
        'due_amount_at_return'       => 'decimal:2',
        'due_reduced_amount'         => 'decimal:2',
        'patient_refund_amount'      => 'decimal:2',
        'doctor_commission_adjusted' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function saleMaster()
    {
        return $this->belongsTo(SaleMaster::class, 'sale_master_id');
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function branch()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function details()
    {
        return $this->hasMany(SaleReturnDetail::class, 'sale_return_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted(): void
    {
        static::addGlobalScope('vendor_filter', function ($builder) {
            Helper::vendorScope($builder);
        });
    }

    /**
     * Scope to check if accessible by current authenticated user
     */
    public function isAccessibleByAuthUser(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->canViewAllBranches()) return true;
        if ($user->branchId()) {
            return (int) $this->vendor_id === (int) $user->branchId();
        }
        return is_null($this->vendor_id);
    }
}
