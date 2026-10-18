<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A note about the person, written from a deal (Notes tab in the deal drawer). Pinned notes show first. */
class DealNote extends Model
{
    use SoftDeletes;

    protected $fillable = ['lead_id', 'deal_id', 'body', 'is_pinned', 'created_by'];

    protected $casts = ['is_pinned' => 'boolean'];

    public function deal()
    {
        return $this->belongsTo(Deal::class)->withoutGlobalScopes();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
