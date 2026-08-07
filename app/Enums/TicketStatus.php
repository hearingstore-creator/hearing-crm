<?php

namespace App\Enums;

enum TicketStatus: string
{
    case OPEN = 'Open';
    case IN_PROGRESS = 'In Progress';
    case PENDING = 'Pending';
    case ON_HOLD = 'On Hold';
    case SOLVED = 'Solved';
    case CLOSED = 'Closed';

    /**
     * Get the color associated with this status
     */
    public function color(): string
    {
        return match($this) {
            self::OPEN => '#3498db',           // Blue - new ticket
            self::IN_PROGRESS => '#9b59b6',    // Purple - actively working
            self::PENDING => '#f1c40f',        // Yellow - waiting
            self::ON_HOLD => '#e67e22',        // Orange - paused
            self::SOLVED => '#2ecc71',         // Green - completed
            self::CLOSED => '#95a5a6',         // Gray - archived
        };
    }

    /**
     * Get all statuses as an array for select dropdowns
     */
    public static function toArray(): array
    {
        return array_map(
            fn(self $status) => ['name' => $status->value, 'color' => $status->color()],
            self::cases()
        );
    }

    /**
     * Get all statuses as a key-value array
     */
    public static function toSelectArray(): array
    {
        return array_column(self::toArray(), 'color', 'name');
    }
}
