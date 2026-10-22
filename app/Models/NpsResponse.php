<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One satisfaction survey (0–10) sent after a delivery, and its answer. */
class NpsResponse extends Model
{
    protected $fillable = ['token', 'sale_master_id', 'customer_id', 'vendor_id', 'score', 'comment', 'sent_at', 'answered_at'];

    protected $casts = ['sent_at' => 'datetime', 'answered_at' => 'datetime'];

    /** promoter 9–10, passive 7–8, detractor 0–6 */
    public static function group(?int $score): ?string
    {
        return $score === null ? null : ($score >= 9 ? 'promoter' : ($score >= 7 ? 'passive' : 'detractor'));
    }
}
