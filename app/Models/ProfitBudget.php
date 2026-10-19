<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A monthly target for one branch (or all branches) on one line of the profit statement. */
class ProfitBudget extends Model
{
    /** metric => label */
    public const METRICS = [
        'net_sales'          => 'Net sales',
        'gross_profit'       => 'Gross profit',
        'operating_expenses' => 'Operating expenses',
        'net_profit'         => 'Net profit',
    ];

    protected $fillable = ['month', 'branch_key', 'metric', 'amount', 'updated_by'];

    protected $casts = ['amount' => 'float'];
}
