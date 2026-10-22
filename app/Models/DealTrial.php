<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A hearing-aid trial on a deal: in the session (usual) or taken home against a deposit (rare). */
class DealTrial extends Model
{
    public const RESULTS = [
        'liked'        => 'Liked it',
        'thinking'     => 'Needs to think / ask family',
        'price'        => 'Price concern',
        'not_suitable' => 'Not suitable (sound, comfort, loss)',
        'other_model'  => 'Wants another model',
    ];

    protected $fillable = ['deal_id', 'lead_id', 'product_id', 'product_label', 'kind', 'result', 'serial_no', 'vendor_id', 'advance_id',
        'deposit', 'return_due', 'status', 'sale_master_id', 'closed_at', 'overdue_alerted_at', 'notes', 'user_id'];

    protected $casts = [
        'deposit' => 'decimal:2',
        'return_due' => 'date',
        'closed_at' => 'datetime',
        'overdue_alerted_at' => 'datetime',
    ];

    public function deal()
    {
        return $this->belongsTo(Deal::class)->withoutGlobalScopes();
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withoutGlobalScopes();
    }

    public function advance()
    {
        return $this->belongsTo(CustomerAdvance::class, 'advance_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isOut(): bool
    {
        return $this->kind === 'take_home' && $this->status === 'out';
    }
}
