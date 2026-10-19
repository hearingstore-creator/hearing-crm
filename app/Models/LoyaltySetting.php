<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoyaltySetting extends Model
{
    use HasFactory;

    protected $table = 'loyalty_settings';

    protected $fillable = [
        'point_value',
        'expiry_months',
        'min_redeem_points',
        'max_redeem_percent',
    ];

    protected $casts = [
        'point_value' => 'decimal:2',
        'expiry_months' => 'integer',
        'min_redeem_points' => 'integer',
        'max_redeem_percent' => 'decimal:2',
    ];

    /**
     * What one point is worth in taka for this customer: their tier's own point value when it has one,
     * otherwise the loyalty setting's. Never zero, so a bad value cannot break a sale.
     */
    public static function pointValueFor(?\App\Models\Customer $customer): float
    {
        $tier = $customer?->membershipTier;
        if ($tier && (float) $tier->point_value > 0) {
            return (float) $tier->point_value;
        }
        $global = (float) static::current()->point_value;

        return $global > 0 ? $global : 1.0;
    }

    /**
     * Get or create the single row of loyalty settings
     */
    public static function current(): self
    {
        return static::firstOrCreate([], [
            'point_value' => 1.00,
            'expiry_months' => 12,
            'min_redeem_points' => 0,
            'max_redeem_percent' => 100.00,
        ]);
    }
}
