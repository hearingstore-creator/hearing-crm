<?php

namespace App\Models;

use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerReturnMaster extends Model
{
    use HasFactory, HelperTrait;
    protected $guarded = ['id'];

    public function sale()
    {
       return $this->belongsTo(SaleMaster::class, 'sale_invoice_no', 'sale_invoice_no');
    }

}
