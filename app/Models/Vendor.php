<?php

namespace App\Models;

use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use HasFactory, HelperTrait;
    protected $fillable = [
        'supplier_name',
        'phone',
        'mobile_no',
        'email1',
        'email2',
        'address1',
        'address2',
        'fax',
        'state',
        'city',
        'country',
        'zip',
        'status'
    ];
}
