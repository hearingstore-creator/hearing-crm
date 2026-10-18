<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A website / landing page allowed to post leads. Requests are signed with the secret (HMAC-SHA256). */
class LeadCaptureForm extends Model
{
    protected $fillable = ['name', 'key', 'slug', 'secret', 'vendor_id', 'default_inquiry', 'sla_minutes', 'is_active', 'last_used_at', 'created_by'];

    protected $hidden = ['secret'];

    protected $casts = [
        'secret'          => 'encrypted',
        'default_inquiry' => 'array',
        'is_active'       => 'boolean',
        'last_used_at'    => 'datetime',
    ];

    public static function newKey(): string
    {
        return 'wf_' . Str::lower(Str::random(24));
    }

    public static function newSecret(): string
    {
        return Str::random(48);
    }

    /** Hosted form link: the short name when set, otherwise the key. */
    public function publicUrl(string $suffix = ''): string
    {
        return url('f/' . ($this->slug ?: $this->key) . $suffix);
    }

    /** Hosted form lookup by short name or key (both always work). */
    public static function findPublic(string $ref): self
    {
        return static::where('is_active', true)
            ->where(fn ($q) => $q->where('key', $ref)->orWhere('slug', $ref))
            ->firstOrFail();
    }

    /** Expected signature for a request: hex HMAC-SHA256 of "{timestamp}.{raw body}". */
    public function sign(string $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $body, $this->secret);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function submissions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LeadSubmission::class, 'form_id');
    }
}
