<?php

namespace App\Models;

use App\Enums\SmsTemplateCategory;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    use HasFactory, HelperTrait;

    protected $fillable = [
        'name',
        'category',
        'body',
        'variables',
        'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    public function getCategoryLabelAttribute(): ?string
    {
        $category = $this->category instanceof SmsTemplateCategory
            ? $this->category
            : SmsTemplateCategory::tryFrom($this->category);

        return $category?->label() ?? ucfirst($this->category);
    }

    public function getCategoryColorAttribute(): ?string
    {
        $category = $this->category instanceof SmsTemplateCategory
            ? $this->category
            : SmsTemplateCategory::tryFrom($this->category);

        return $category?->color();
    }

    public function campaigns()
    {
        return $this->hasMany(SmsCampaign::class, 'sms_template_id', 'id');
    }
}
