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
        'date_of_birth',
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
        'lead_temperature',
    ];

    protected $casts = [
        'inquiry_for' => 'array',
        'hearing_loss' => 'array',
        'date_of_birth' => 'date:Y-m-d',
    ];

    /** Old statuses that were folded into the 5-stage pipeline. */
    public const LEGACY_STAGE = [
        'verified'                 => 'new',
        'tele_counsel_appointment' => 'tele_counsel',
        'store_appointment'        => 'tele_counsel',
    ];

    /** Active pipeline stages plus Won/Lost, keyed by status key: [label, color, icon]. */
    public static function stageMap(): array
    {
        static $map;

        return $map ??= \App\Models\Pipeline::default()->activeStages()->get(['name', 'status_key', 'color'])
            ->mapWithKeys(fn ($s) => [$s->status_key => [
                'label' => $s->name,
                'color' => $s->color,
                'icon'  => LeadStatus::tryFrom($s->status_key)?->icon() ?? 'fas fa-circle',
            ]])
            ->put('won', ['label' => 'Won', 'color' => '#10b981', 'icon' => 'fas fa-trophy'])
            ->put('lost', ['label' => 'Lost', 'color' => '#ef4444', 'icon' => 'fas fa-times-circle'])
            ->all();
    }

    /** Current stage key, with old statuses mapped onto the current pipeline. */
    public function getStageKeyAttribute(): string
    {
        $key = $this->lead_status instanceof LeadStatus ? $this->lead_status->value : (string) $this->lead_status;

        return self::LEGACY_STAGE[$key] ?? $key;
    }

    // Accessor for status label
    public function getStatusLabelAttribute(): ?string
    {
        return self::stageMap()[$this->stage_key]['label'] ?? ucfirst(str_replace('_', ' ', $this->stage_key));
    }

    // Accessor for status color
    public function getStatusColorAttribute(): ?string
    {
        return self::stageMap()[$this->stage_key]['color'] ?? '#94a3b8';
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

    /**
     * Ears with hearing loss: [['ear' => 'Right', 'degree' => 'Moderate'|null, 'type' => '...'|null], ...].
     * Reads both stored formats: old ["right","left"] (side only) and new {right: {enabled, degree, type}}.
     */
    public function hearingEars(): array
    {
        $items = $this->hearing_loss ?? [];
        if (!is_array($items)) {
            $items = json_decode((string) $items, true) ?? [];
        }

        $ears = [];
        foreach (['right', 'left'] as $ear) {
            $d = $items[$ear] ?? null;
            $legacy = in_array($ear, $items, true);
            if (!$legacy && empty($d['enabled'])) {
                continue;
            }
            $ears[] = [
                'ear'    => ucfirst($ear),
                'degree' => is_array($d) ? HearingDegree::tryFrom($d['degree'] ?? '')?->label() : null,
                'type'   => is_array($d) ? HearingType::tryFrom($d['type'] ?? '')?->label() : null,
            ];
        }

        return $ears;
    }

    public function getHearingLossLabelAttribute(): string
    {
        return collect($this->hearingEars())->map(function ($e) {
            $detail = implode(', ', array_filter([$e['degree'], $e['type']]));

            return $detail ? "{$e['ear']}: $detail" : $e['ear'];
        })->implode(' | ');
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

    /** Next lead ID for today: L-YYYYMMDD0001 (soft-deleted leads count, so IDs are never reused). */
    public static function generateUid(): string
    {
        $prefix = 'L-' . date('Ymd');
        $last = static::withoutGlobalScopes()->withTrashed()
            ->where('lead_uid', 'LIKE', $prefix . '%')
            ->orderByDesc('lead_uid')
            ->value('lead_uid');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
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

    /** Sales opportunities of this person (newest first). */
    public function deals()
    {
        return $this->hasMany(Deal::class)->latest('id');
    }

    /** Customer record (exists once the person has bought). */
    public function customer()
    {
        return $this->hasOne(Customer::class);
    }

    public function proposals()
    {
        return $this->hasMany(Proposal::class, 'lead_id');
    }

    protected static function booted(): void
    {
        static::addGlobalScope('vendor_filter', function ($builder) {
            $branchId = auth()->check() ? auth()->user()->branchId() : null;
            if ($branchId) {
                $builder->where('vendor_id', $branchId);
            }
        });

        // Canonical phone for duplicate detection (017.., +88017.., 88017.. are the same number)
        static::saving(function (Lead $lead) {
            if ($lead->isDirty('phone_number') || $lead->normalized_phone === null) {
                $lead->normalized_phone = \App\Classes\Helper::normalizePhone($lead->phone_number);
            }
        });
    }
}
