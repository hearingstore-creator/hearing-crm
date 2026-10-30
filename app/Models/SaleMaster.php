<?php

namespace App\Models;

use App\Classes\Helper;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleMaster extends Model
{
    // HelperTrait's soft delete also records deleted_by
    use HasFactory, HelperTrait, SoftDeletes {
        HelperTrait::runSoftDelete insteadof SoftDeletes;
    }
    
    protected $fillable = [
        'sale_invoice_no',
        'customer_id',
        'vendor_id',
        'date',
        'details',
        'payment_id',
        'reference_by',
        'doctor_chamber_id',
        'reference_amount',
        'total_amount',
        'vat',
        'total_discount',
        'paid',
        'due_date',
        'delivery_status',
        'delivery_date_time',
        'lab_name',
        'delivered_at',
        'delivered_by',
        'is_percent_product_commission',
        'is_percent_service_commission',
        'applied_advance_id',
        'applied_advance_amount',
        'referral_settlement_status',
        'payment_method',
        'payment_trx_no',
        'pos_terminal_name',
        'gateway_settlement_status',
        'gateway_charge_amount',
        'gateway_net_amount',
        'gateway_settled_at',
        'gateway_settlement_id',
    ];

    protected $casts = [
       'date' => 'date',
       'due_date' => 'date',
       'delivery_date_time' => 'datetime',
       'delivered_at' => 'datetime',
       'is_percent_product_commission' => 'boolean',
       'is_percent_service_commission' => 'boolean',
       'applied_advance_amount' => 'float',
       'gateway_settled_at' => 'datetime',
       'gateway_charge_amount' => 'float',
       'gateway_net_amount' => 'float',
    ];

    public function gatewaySettlement()
    {
        return $this->belongsTo(PaymentReconciliation::class, 'gateway_settlement_id');
    }

    public function getGatewayStatusBadgeClassAttribute(): string
    {
        return match ($this->gateway_settlement_status) {
            'reconciled' => 'badge-success',
            'pending'    => 'badge-warning',
            default      => 'badge-secondary',
        };
    }

    public function appliedAdvance()
    {
        return $this->belongsTo(CustomerAdvance::class, 'applied_advance_id');
    }

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

    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'reference_by');
    }

    public function referrals()
    {
        return $this->hasMany(SaleReferral::class, 'sale_master_id');
    }

    public function doctorChamber()
    {
        return $this->belongsTo(DoctorChamber::class, 'doctor_chamber_id');
    }

    public function saleDetails()
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function serviceDetails()
    {
        return $this->hasMany(ServiceDetail::class);
    }

    public function returns()
    {
        return $this->hasMany(SaleReturn::class, 'sale_master_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveredBy()
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    /**
     * Check if this sale is purely an Assessment / Service sale.
     */
    public function isServiceSale(): bool
    {
        if ($this->relationLoaded('serviceDetails') && $this->relationLoaded('saleDetails')) {
            return $this->serviceDetails->isNotEmpty() && $this->saleDetails->isEmpty();
        }
        return $this->serviceDetails()->exists() && !$this->saleDetails()->exists();
    }

    /**
     * Scope sales where a referrer was tagged but the commission negotiation is pending.
     */
    public function scopePendingReferralSettlement($query)
    {
        return $query->whereNotNull('reference_by')
            ->where(function ($q) {
                $q->where('referral_settlement_status', 'pending')
                  ->orWhere(function ($sq) {
                      $sq->where(function ($ssq) {
                          $ssq->whereNull('referral_settlement_status')
                              ->orWhere('referral_settlement_status', '!=', 'settled');
                      })->where(function ($ssq2) {
                          $ssq2->whereNull('reference_amount')->orWhere('reference_amount', '<=', 0);
                      });
                  });
            });
    }

    /**
     * Check if this sale has products (Hearing Aid, mold, etc).
     */
    public function isProductSale(): bool
    {
        if ($this->relationLoaded('saleDetails')) {
            return $this->saleDetails->isNotEmpty();
        }
        return $this->saleDetails()->exists();
    }

    /**
     * Typed DeliveryStatus enum instance.
     */
    public function deliveryStatusEnum(): \App\Enums\DeliveryStatus
    {
        return \App\Enums\DeliveryStatus::tryFrom($this->delivery_status ?? 'ordered') ?? \App\Enums\DeliveryStatus::ORDERED;
    }

    /**
     * Contextual human label (e.g. "Report Ready" vs "Ready for Fitting").
     */
    public function deliveryStatusLabel(): string
    {
        return $this->deliveryStatusEnum()->label($this->isServiceSale());
    }

    protected static function booted(): void
    {
        static::addGlobalScope('vendor_filter', function ($builder) {
            Helper::vendorScope($builder);
        });

        // Pipeline: attach the new invoice to the customer's recently won deal (only touches deals.sale_master_id)
        static::created(function (SaleMaster $sale) {
            app(\App\Services\DealService::class)->linkSale($sale);
            // SMS Center "First Sale" automation (read-only: only reads the sale count)
            rescue(fn () => app(\App\Services\SmsAutomationService::class)->onSaleCreated($sale));
        });
    }

    /**
     * Check if the sale is accessible to the authenticated user
     *
     * @return bool
     */
    public function isAccessibleByAuthUser(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if ($user->canViewAllBranches()) {
            return true;
        }

        $branchId = $user->branchId();
        if ($branchId) {
            // Branch login / branch staff can only access their branch's sales
            return (int) $this->vendor_id === $branchId;
        }

        // General users can only access global sales
        return $this->vendor_id === null;
    }
}
