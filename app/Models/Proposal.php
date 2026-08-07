<?php

namespace App\Models;

use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Proposal extends Model
{
    use HasFactory, HelperTrait;

    protected $fillable = [
        'email',
        'customer_id',
        'template_id',
        'title',
        'content',
        'proposal_date',
        'valid_until',
        'total_amount',
        'status'
    ];

    /**
     * Get the customer that owns the proposal.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the template that owns the proposal.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
