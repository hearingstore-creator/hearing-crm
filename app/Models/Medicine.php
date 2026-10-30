<?php

namespace App\Models;

use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Medicine extends Model
{
    use HasFactory, HelperTrait;
    
    protected $guarded = ['id'];
    
    public function leaf()
    {
        return $this->belongsTo(LeafSetting::class, 'leaf_setting_id');
    }

    public function batch()
    {
       return $this->hasMany(Batch::class);
    }
}
