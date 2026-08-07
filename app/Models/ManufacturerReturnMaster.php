<?php

namespace App\Models;

use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManufacturerReturnMaster extends Model
{
    use HasFactory, HelperTrait;
    protected $guarded = ['id'];

    public function purchase()
    {
       return $this->belongsTo(PurchaseMaster::class, 'purchase_invoice_no', 'purchase_invoice_no');
    }
}
