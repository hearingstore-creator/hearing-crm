<?php

namespace App\Enums;

enum TicketPriority: string
{
    case LOW = 'Low';
    case MEDIUM = 'Medium';
    case HIGH = 'High';
    case CRITICAL = 'Critical';

    /**
     * Get the color associated with this priority
     */
    public function color(): string
    {
        return match($this) {
            self::LOW => '#27ae60',        // Green - low priority
            self::MEDIUM => '#f39c12',     // Orange/Yellow - medium priority
            self::HIGH => '#e74c3c',       // Red - high priority
            self::CRITICAL => '#8b0000',   // Dark Red - critical priority
        };
    }

    /**
     * Get all priorities as an array for select dropdowns
     */
    public static function toArray(): array
    {
        return array_map(
            fn(self $priority) => ['name' => $priority->value, 'color' => $priority->color()],
            self::cases()
        );
    }

    /**
     * Get all priorities as a key-value array
     */
    public static function toSelectArray(): array
    {
        return array_column(self::toArray(), 'color', 'name');
    }
}
