<?php

namespace App\Models;

use App\Classes\Helper;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ledger extends Model
{
    use HasFactory, HelperTrait;

    protected $fillable = [
        'income_expense_head_id',
        'vendor_id',
        'invoice_no',
        'expense_amount',
        'income_amount',
        'amount_type',
        'date',
        'details',
        'type',
        'ref_invoice_no',
    ];

    public function head()
    {
        return $this->belongsTo(IncomeExpenseHead::class, 'income_expense_head_id');
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    protected static function booted(): void
    {
        static::addGlobalScope('vendor_filter', function ($builder) {
            Helper::vendorScope($builder);
        });
    }
}
