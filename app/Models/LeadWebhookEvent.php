<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One call received from Meta, WhatsApp, Google or a webhook, with what became of it. */
class LeadWebhookEvent extends Model
{
    public const SOURCES = ['meta' => 'Meta Lead Ads', 'whatsapp' => 'WhatsApp', 'google' => 'Google lead form', 'webhook' => 'Webhook'];

    protected $fillable = ['source', 'external_id', 'status', 'note', 'payload', 'submission_id', 'form_id'];

    protected $casts = ['payload' => 'array'];

    public function mark(string $status, ?string $note = null, ?int $submissionId = null): self
    {
        $this->update(array_filter([
            'status' => $status,
            'note' => $note !== null ? mb_substr($note, 0, 500) : null,
            'submission_id' => $submissionId,
        ], fn ($v) => $v !== null) + ($note === null ? ['note' => null] : []));

        return $this;
    }

    public function submission()
    {
        return $this->belongsTo(LeadSubmission::class, 'submission_id');
    }
}
