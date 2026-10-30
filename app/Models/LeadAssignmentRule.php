<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Who receives a new lead: conditions (source, interest, branch) and how to pick the person. */
class LeadAssignmentRule extends Model
{
    public const METHODS = [
        'round_robin' => 'Take turns',
        'least_busy'  => 'Fewest open deals',
        'fixed'       => 'First available in the list',
    ];

    protected $fillable = ['name', 'sort_order', 'is_active', 'sources', 'inquiries', 'vendor_ids', 'pipeline_ids', 'include_manual', 'method', 'user_ids',
        'set_vendor_id', 'max_open', 'reassign_after_minutes', 'last_user_id', 'assigned_count', 'created_by', 'updated_by'];

    protected $casts = [
        'is_active' => 'boolean',
        'include_manual' => 'boolean',
        'sources' => 'array',
        'inquiries' => 'array',
        'vendor_ids' => 'array',
        'pipeline_ids' => 'array',
        'user_ids' => 'array',
    ];
}
