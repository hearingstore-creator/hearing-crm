<?php

namespace App\Models;

use App\Classes\Helper;
use App\Enums\ProposalStatus;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proposal extends Model
{
    use HasFactory, HelperTrait;

    protected $fillable = [
        'proposal_no',
        'lead_id',
        'deal_id',
        'customer_id',
        'doctor_id',
        'vendor_id',
        'template_id',
        'email',
        'recipient_name',
        'recipient_phone',
        'recipient_address',
        'title',
        'content',
        'proposal_date',
        'valid_until',
        'subtotal',
        'discount_type',
        'discount_percent',
        'discount_amount',
        'vat_amount',
        'grand_total',
        'total_amount',
        'terms_and_conditions',
        'notes',
        'status',
        'converted_sale_id',
    ];

    protected $casts = [
        'proposal_date' => 'date',
        'valid_until' => 'date',
        'subtotal' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Get the customer that owns the proposal.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the lead associated with this proposal.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the deal associated with this proposal.
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /**
     * Get the referring doctor.
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * Get the branch/vendor.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    /**
     * Get the template that owns the proposal.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * Get the converted sale / invoice if any.
     */
    public function convertedSale(): BelongsTo
    {
        return $this->belongsTo(SaleMaster::class, 'converted_sale_id');
    }

    /**
     * Get line items for this proposal.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProposalItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function productItems(): HasMany
    {
        return $this->hasMany(ProposalItem::class)->where('item_type', 'product')->orderBy('sort_order')->orderBy('id');
    }

    public function serviceItems(): HasMany
    {
        return $this->hasMany(ProposalItem::class)->where('item_type', 'service')->orderBy('sort_order')->orderBy('id');
    }

    public function customItems(): HasMany
    {
        return $this->hasMany(ProposalItem::class)->where('item_type', 'custom')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Recipient full name display
     */
    public function getRecipientDisplayNameAttribute(): string
    {
        if ($this->lead) {
            return $this->lead->full_name;
        }
        if ($this->customer) {
            return $this->customer->name;
        }
        return $this->recipient_name ?: ($this->email ?: 'N/A');
    }

    /**
     * Recipient phone display
     */
    public function getRecipientPhoneDisplayAttribute(): string
    {
        return $this->lead?->phone_number
            ?: ($this->recipient_phone
            ?: ($this->customer?->lead?->phone_number ?: 'N/A'));
    }

    /**
     * Recipient email display
     */
    public function getRecipientEmailDisplayAttribute(): string
    {
        return $this->lead?->email
            ?: ($this->email
            ?: ($this->customer?->lead?->email ?: 'N/A'));
    }

    /**
     * Formatted grand total accessor
     */
    public function getFormattedGrandTotalAttribute(): string
    {
        $amount = $this->grand_total ?: $this->total_amount;
        return '৳' . number_format($amount, 2);
    }

    /**
     * Status badge HTML helper
     */
    public function getStatusBadgeHtmlAttribute(): string
    {
        $color = $this->status_color;
        $label = strtoupper($this->status);
        return '<span class="badge" style="background-color: ' . $color . '; color: #ffffff; font-weight: 700; padding: 4px 8px; border-radius: 4px;">' . $label . '</span>';
    }

    /**
     * Status color helper
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            ProposalStatus::ACCEPTED->value => '#10b981',
            ProposalStatus::DECLINED->value => '#ef4444',
            ProposalStatus::CONVERTED->value => '#8b5cf6',
            ProposalStatus::SENT->value => '#06b6d4',
            ProposalStatus::NEW->value => '#3b82f6',
            ProposalStatus::EXPIRED->value => '#f59e0b',
            default => '#6b7280',
        };
    }

    /**
     * Generate the next unique proposal / quotation number
     */
    public static function generateProposalNo(): string
    {
        $prefix = 'QUO-' . date('Ym') . '-';
        $lastNo = static::withoutGlobalScopes()
            ->where('proposal_no', 'LIKE', $prefix . '%')
            ->orderByDesc('id')
            ->value('proposal_no');

        if ($lastNo) {
            $lastSeq = (int) substr($lastNo, strlen($prefix));
            $nextSeq = $lastSeq + 1;
        } else {
            $nextSeq = 1;
        }

        return $prefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Booted method for automatic quote numbering and vendor scoping
     */
    protected static function booted(): void
    {
        static::addGlobalScope('vendor_filter', function ($builder) {
            Helper::vendorScope($builder);
        });

        static::creating(function ($proposal) {
            if (empty($proposal->proposal_no)) {
                $proposal->proposal_no = static::generateProposalNo();
            }

            // Keep total_amount in sync with grand_total
            if ($proposal->grand_total && empty($proposal->total_amount)) {
                $proposal->total_amount = $proposal->grand_total;
            } elseif ($proposal->total_amount && empty($proposal->grand_total)) {
                $proposal->grand_total = $proposal->total_amount;
            }

            // Assign current branch if vendor_id is not supplied
            if (empty($proposal->vendor_id) && auth()->check() && !auth()->user()->isSuperAdmin()) {
                $proposal->vendor_id = auth()->user()->branchId();
            }
        });

        static::updating(function ($proposal) {
            // Keep total_amount and grand_total in sync
            if ($proposal->isDirty('grand_total') && !$proposal->isDirty('total_amount')) {
                $proposal->total_amount = $proposal->grand_total;
            } elseif ($proposal->isDirty('total_amount') && !$proposal->isDirty('grand_total')) {
                $proposal->grand_total = $proposal->total_amount;
            }
        });
    }
}
