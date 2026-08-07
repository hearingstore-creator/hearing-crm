<?php

namespace App\Models;

use App\Classes\Helper;
use App\Enums\HearingDegree;
use App\Enums\HearingType;
use App\Enums\InquiryType;
use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Enums\SourceOfEntry;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory, HelperTrait;
    
     protected $fillable = [
        // Lead identifiers
        'lead_uid',
        'vendor_id',

        // Personal Information
        'full_name',
        'email',
        'district_id',
        'address',
        'age',
        'gender',
        'phone_number',
        'contact_person_name',
        'relation',
        'contact_person_phone',

        // Lead Details
        'inquiry_for',
        'interest_brand_product',
        'hearing_loss',
        'approx_budget',
        'ref_by',
        'notes',
        'loss_reason',
        'loss_to_whom',

        // Source and Type
        'source_of_entry',
        'lead_type',

        // Lead Status
        'lead_status',
    ];

    protected $casts = [
        'inquiry_for' => 'array',
        'hearing_loss' => 'array'
    ];

    // Accessor for status label
    public function getStatusLabelAttribute(): ?string
    {
        $status = $this->lead_status instanceof LeadStatus
            ? $this->lead_status
            : LeadStatus::tryFrom($this->lead_status);

        return $status?->label() ?? ucfirst($this->lead_status);
    }

    // Accessor for status color
    public function getStatusColorAttribute(): ?string
    {
        $status = $this->lead_status instanceof LeadStatus
            ? $this->lead_status
            : LeadStatus::tryFrom($this->lead_status);

        return $status?->color();
    }

    public function getSourceOfEntryLabelAttribute(): ?string
    {
        $sourceOfEntry = $this->source_of_entry instanceof SourceOfEntry
            ? $this->source_of_entry
            : SourceOfEntry::tryFrom($this->source_of_entry);

        return $sourceOfEntry?->label();
    }

    public function getInquiryForLabelAttribute()
    {
        $items = $this->inquiry_for ?? [];

        if (!is_array($items)) {
            $items = json_decode($items, true) ?? [];
        }

        $labels = collect($items)->map(function ($item) {
            return InquiryType::tryFrom($item)?->label() ?? ucfirst(str_replace('_', ' ', $item));
        });

        return $labels->implode(', ');
    }

    public function getHearingLossLabelAttribute()
    {
        $items = $this->hearing_loss ?? [];

        if (!is_array($items)) {
            $items = json_decode($items, true) ?? [];
        }

        $parts = [];
        foreach (['right', 'left'] as $ear) {
            if (isset($items[$ear]['enabled']) && $items[$ear]['enabled']) {
                $earLabel = ucfirst($ear);
                $degree = HearingDegree::tryFrom($items[$ear]['degree'] ?? '')?->label() ?? '';
                $type = HearingType::tryFrom($items[$ear]['type'] ?? '')?->label() ?? '';
                $detail = implode(', ', array_filter([$degree, $type]));
                $parts[] = $detail ? "$ear: $detail" : $earLabel;
            }
        }

        return implode(' | ', $parts);
    }

    public function getLeadTypeLabelAttribute(): ?string
    {
        $leadType = $this->lead_type instanceof LeadType
            ? $this->lead_type
            : LeadType::tryFrom($this->lead_type);

        return $leadType?->label() ?? ucfirst($this->lead_type);
    }

    public function followUps()
    {
        return $this->hasMany(LeadFollowUp::class, 'lead_id', 'id');
    }

    public function statusHistories()
    {
        return $this->hasMany(LeadStatusHistory::class, 'lead_id', 'id')->orderBy('created_at');
    }

     public function district()
    {
        return $this->belongsTo(District::class, 'district_id', 'id');
    }

    public function profileCompletion()
    {
        $groups = [
            'personal' => [
                'weight' => 44,
                'fields' => [
                    'full_name',
                    'email',
                    'phone_number',
                    'age',
                    'gender',
                    'district_id',
                    'address',
                ]
            ],
            'contact_person' => [
                'weight' => 22,
                'fields' => [
                    'contact_person_name',
                    'relation',
                    'contact_person_phone',
                ]
            ],
            'lead_details' => [
                'weight' => 34, 
                'fields' => [
                    'lead_status',
                    'inquiry_for',
                    'interest_brand_product',
                    'hearing_loss',
                    'approx_budget',
                    'source_of_entry',
                    'lead_type',
                ]
            ]
        ];

        $totalPercentage = 0;

        foreach ($groups as $group) {
            $fields = $group['fields'];
            $weight = $group['weight'];

            $filled = 0;
            $totalFields = count($fields);

            foreach ($fields as $field) {
                if (!empty($this->{$field})) {
                    $filled++;
                }
            }

            // group completion = (filled/total) * group weight
            if ($totalFields > 0) {
                $totalPercentage += ($filled / $totalFields) * $weight;
            }
        }

        return round($totalPercentage);
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id', 'id');
    }

    protected static function booted(): void
    {
        static::addGlobalScope('vendor_filter', function ($builder) {
            if (auth()->check() && auth()->user()->isVendor()) {
                $builder->where('vendor_id', auth()->id());
            }
        });
    }
}
