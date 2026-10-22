<?php

namespace App\Models;

use App\Enums\MarketingChannel;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Model;

/** A marketing campaign: its code is the utm_campaign value used in ad links and QR codes. */
class MarketingCampaign extends Model
{
    use HelperTrait;   // HelperTrait brings soft deletes

    public const STATUSES = ['active' => 'Active', 'paused' => 'Paused', 'ended' => 'Ended'];

    protected $fillable = ['name', 'code', 'channel', 'vendor_id', 'start_date', 'end_date', 'budget', 'status', 'notes'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'budget' => 'decimal:2',
    ];

    public function spends()
    {
        return $this->hasMany(MarketingSpend::class, 'campaign_id');
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function channelEnum(): MarketingChannel
    {
        return MarketingChannel::tryFrom((string) $this->channel) ?? MarketingChannel::OTHER;
    }

    /** Codes are matched without caring about case or spaces around them. */
    public static function normalizeCode(?string $code): string
    {
        return mb_strtolower(trim((string) $code));
    }

    public static function findByCode(?string $code): ?self
    {
        $code = self::normalizeCode($code);

        return $code === '' ? null : static::where('code', $code)->first();
    }
}
