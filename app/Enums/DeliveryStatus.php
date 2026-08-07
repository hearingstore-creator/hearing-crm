<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case ORDERED = 'ordered';
    // case PROCESSING = 'processing';
    // case SHIPPED = 'shipped';
    // case IN_TRANSIT = 'in_transit';
    //  case CANCELLED = 'cancelled';
    case SENT_TO_LAB = 'sent_to_lab';

    case DELIVERED = 'delivered';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match($this) {
            self::ORDERED => 'Ordered',
            // self::PROCESSING => 'Processing',
            // self::SHIPPED => 'Shipped',
            // self::IN_TRANSIT => 'In Transit',
          //  self::CANCELLED => 'Cancelled',
            self::SENT_TO_LAB => 'Sent to Lab',
            self::DELIVERED => 'Delivered',
        };
    }

    /**
     * Get all values as an array for dropdowns.
     * Usage: DeliveryStatus::values()
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value', 'value');
    }
    
    /**
     * Get all cases with labels for front-end selects.
     * Usage: DeliveryStatus::options()
     */
    public static function options(): array
    {
        return array_map(fn(self $status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ], self::cases());
    }
}
