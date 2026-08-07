<?php

namespace App\Models;

use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use HasFactory, HelperTrait;

    protected $fillable = ['name', 'parent_id', 'route', 'icon', 'order'];

    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id');
    }

    public function permissions()
    {
        return $this->hasMany(Permission::class);
    }
}
