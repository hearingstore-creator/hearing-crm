<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Money spent on one campaign in one month (and branch). Each row owns one expense row in the accounts. */
class MarketingSpend extends Model
{
    public const SOURCES = ['manual' => 'Entered', 'meta_csv' => 'Meta import', 'google_csv' => 'Google import', 'expense' => 'Linked expense'];

    protected $fillable = ['campaign_id', 'month', 'amount', 'vendor_id', 'ledger_id', 'source', 'note', 'created_by', 'updated_by'];

    protected $casts = [
        'month' => 'date',
        'amount' => 'decimal:2',
    ];

    public function campaign()
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id')->withTrashed();
    }

    public function ledger()
    {
        return $this->belongsTo(Ledger::class);
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }
}
