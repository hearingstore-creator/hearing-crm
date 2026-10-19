<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory, HelperTrait, \App\Traits\BranchOwned;

    protected $fillable = [
        'subject',
        'description',
        'vendor_id',
        'customer_id',
        'assigned_to',
        'status',
        'priority',
        'priority_id',
        'resolved_at',
    ];

    protected $casts = [
        'status' => TicketStatus::class,
        'priority' => TicketPriority::class,
        'resolved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Resolved time follows the status: set when a ticket is Solved or Closed, cleared when it is reopened
        static::saving(function (Ticket $ticket) {
            if (!$ticket->isDirty('status')) {
                return;
            }
            if (in_array($ticket->status, [TicketStatus::SOLVED, TicketStatus::CLOSED], true)) {
                $ticket->resolved_at ??= now();
            } else {
                $ticket->resolved_at = null;
            }
        });

        static::created(function (Ticket $ticket) {
            $ticket->addNote('created', 'Ticket created' . ($ticket->assigned_to ? ' and assigned to ' . self::personName($ticket->assigned_to) : ''));
        });

        // Every change of status, owner or priority leaves a line in the timeline, whoever (or whatever) made it
        static::updated(function (Ticket $ticket) {
            if ($ticket->wasChanged('status')) {
                $ticket->addNote('status', 'Status changed from ' . $ticket->getRawOriginal('status') . ' to ' . $ticket->status->value);
            }
            if ($ticket->wasChanged('assigned_to')) {
                $was = $ticket->getRawOriginal('assigned_to');
                $ticket->addNote('assigned', match (true) {
                    !$ticket->assigned_to => 'Unassigned (was ' . self::personName($was) . ')',
                    !$was => 'Assigned to ' . self::personName($ticket->assigned_to),
                    default => 'Reassigned from ' . self::personName($was) . ' to ' . self::personName($ticket->assigned_to),
                });
            }
            if ($ticket->wasChanged('priority')) {
                $ticket->addNote('priority', 'Priority changed from ' . $ticket->getRawOriginal('priority') . ' to ' . $ticket->priority->value);
            }
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function notes()
    {
        return $this->hasMany(TicketNote::class)->latest('id');
    }

    /** Add a line to the timeline. The author is the signed-in user; with nobody signed in (an automation) it is the system. */
    public function addNote(string $kind, string $body): TicketNote
    {
        return $this->notes()->create(['user_id' => auth()->id(), 'kind' => $kind, 'body' => $body]);
    }

    /** Ticket number shown to staff, e.g. TK-0142. */
    public function getNumberAttribute(): string
    {
        return 'TK-' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /** The description as plain text. Older tickets hold HTML from the former rich-text editor. */
    public function getDescriptionTextAttribute(): string
    {
        $html = preg_replace('#<(br\s*/?|/p|/div|/li)>#i', "\n", (string) $this->description);

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public function isOpen(): bool
    {
        return !in_array($this->status, [TicketStatus::SOLVED, TicketStatus::CLOSED], true);
    }

    protected static function personName($id): string
    {
        return $id ? (User::find($id)?->name ?? 'user #' . $id) : 'nobody';
    }
}
