<?php

namespace App\Models;

use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use HasFactory, HelperTrait;

    protected $fillable = ['name', 'label', 'parent_id', 'route', 'icon', 'order', 'category'];

    /**
     * Get the display name for sidebar/UI. Falls back to 'name' if no label set.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->label ?? $this->name;
    }

    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('order');
    }

    public function permissions()
    {
        return $this->hasMany(Permission::class);
    }
}
