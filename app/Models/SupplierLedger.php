<?php

namespace App\Models;

use App\Classes\Helper;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierLedger extends Model
{
    use HasFactory, HelperTrait;
    protected $fillable = [
        'supplier_id',
        'date',
        'details',
        'debit',
        'credit',
        'type',
        'ref_invoice_no',
        'payment_method',
        'transaction_no',
        'bank_name',
        'linked_purchase_id',
        'supplier_memo_no',
    ];

    protected $casts = [
       'date' => 'date',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function linkedPurchase()
    {
        return $this->belongsTo(PurchaseMaster::class, 'linked_purchase_id');
    }

    protected static function booted(): void
    {
        static::addGlobalScope('vendor_relation_filter', function ($builder) {
            Helper::vendorRelationScope($builder, 'supplier');
        });
    }
}
