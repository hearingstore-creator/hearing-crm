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

    /** Statuses where the ticket is waiting on the patient: the SLA clock is paused. */
    public const WAITING = ['Pending', 'On Hold'];

    /** Hours allowed for a priority (config crm.ticket_sla_hours). */
    public static function slaHoursFor(string $priority): int
    {
        $hours = config('crm.ticket_sla_hours', []);

        return (int) ($hours[$priority] ?? $hours['Medium'] ?? 72);
    }

    /** When the ticket is due: opened time plus the hours for its priority. */
    public function getDueAtAttribute(): \Illuminate\Support\Carbon
    {
        return $this->created_at->copy()->addHours(self::slaHoursFor($this->priority->value));
    }

    /**
     * What the inbox shows next to a ticket: state (done, waiting, overdue, soon, ok), a short label and a tone.
     * soon = due within the next 8 hours.
     */
    public function dueState(): array
    {
        if (!$this->isOpen()) {
            return ['state' => 'done', 'label' => $this->status->value, 'tone' => 'muted'];
        }
        if (in_array($this->status->value, self::WAITING, true)) {
            return ['state' => 'waiting', 'label' => 'Waiting on patient', 'tone' => 'muted'];
        }

        $minutes = (int) now()->diffInMinutes($this->due_at, false);
        $span = fn (int $m) => $m >= 2880 ? intdiv($m, 1440) . 'd' : ($m >= 120 ? intdiv($m, 60) . 'h' : max($m, 1) . 'm');

        if ($minutes < 0) {
            return ['state' => 'overdue', 'label' => 'Overdue ' . $span(-$minutes), 'tone' => 'danger'];
        }

        return ['state' => $minutes <= 480 ? 'soon' : 'ok', 'label' => 'Due in ' . $span($minutes), 'tone' => $minutes <= 480 ? 'warning' : 'muted'];
    }

    /** SQL for the due time of a ticket (opened time plus the hours of its priority). */
    public static function dueSql(string $table = 'tickets'): string
    {
        $cases = '';
        foreach (config('crm.ticket_sla_hours', []) as $priority => $hours) {
            $cases .= " WHEN '" . addslashes($priority) . "' THEN " . (int) $hours;
        }

        return "DATE_ADD({$table}.created_at, INTERVAL CASE {$table}.priority{$cases} ELSE " . self::slaHoursFor('Medium') . " END HOUR)";
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
