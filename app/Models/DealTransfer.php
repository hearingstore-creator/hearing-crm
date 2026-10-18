<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DealTransfer extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['deal_id', 'from_vendor_id', 'to_vendor_id', 'from_owner_id', 'to_owner_id', 'note', 'transferred_by'];

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    public function fromVendor()
    {
        return $this->belongsTo(User::class, 'from_vendor_id');
    }

    public function toVendor()
    {
        return $this->belongsTo(User::class, 'to_vendor_id');
    }

    public function toOwner()
    {
        return $this->belongsTo(User::class, 'to_owner_id');
    }

    public function transferredBy()
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}
