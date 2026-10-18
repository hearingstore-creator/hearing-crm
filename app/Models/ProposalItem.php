<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProposalItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'proposal_id',
        'item_type',
        'product_id',
        'service_id',
        'item_name',
        'description',
        'qty',
        'unit_price',
        'discount_percent',
        'discount',
        'total_price',
        'warranty_months',
        'sort_order',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount' => 'decimal:2',
        'total_price' => 'decimal:2',
        'warranty_months' => 'integer',
        'sort_order' => 'integer',
    ];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class, 'service_id');
    }

    public function isProduct(): bool
    {
        return $this->item_type === 'product';
    }

    public function isService(): bool
    {
        return $this->item_type === 'service';
    }
}
