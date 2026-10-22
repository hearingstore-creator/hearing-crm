<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Raw website submission (the "Inbox"). Saved before any processing so nothing is ever lost. */
class LeadSubmission extends Model
{
    public const PENDING = 'pending';
    public const PROCESSED = 'processed';
    public const DUPLICATE = 'duplicate';
    public const SPAM = 'spam';
    public const FAILED = 'failed';

    protected $fillable = [
        'form_id', 'status', 'spam_reason', 'name', 'phone', 'normalized_phone', 'email', 'message', 'inquiry_for',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'fbclid', 'landing_page', 'referrer_url', 'page_url', 'ip', 'user_agent', 'lead_id', 'deal_id', 'error', 'processed_at',
    ];

    protected $casts = [
        'inquiry_for'  => 'array',
        'processed_at' => 'datetime',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(LeadCaptureForm::class, 'form_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class)->withoutGlobalScopes();
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class)->withoutGlobalScopes();
    }
}
