<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line in a ticket's timeline: a note somebody wrote, or a change the system recorded. */
class TicketNote extends Model
{
    public const KINDS = ['note', 'created', 'status', 'assigned', 'priority', 'system'];

    protected $fillable = ['ticket_id', 'user_id', 'kind', 'body'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
