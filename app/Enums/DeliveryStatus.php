<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case ORDERED = 'ordered';
    case SENT_TO_LAB = 'sent_to_lab';
    case READY_FOR_DELIVERY = 'ready_for_delivery';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    /**
     * Get a human-readable label for the status.
     * Customizes terminology contextually for Services (Reports) vs Products.
     *
     * @param bool|null $isService
     * @return string
     */
    public function label(?bool $isService = false): string
    {
        if ($isService) {
            return match($this) {
                self::ORDERED => 'Assessment Booked',
                self::SENT_TO_LAB => 'Sent to Lab',
                self::READY_FOR_DELIVERY => 'Report Ready',
                self::DELIVERED => 'Report Delivered',
                self::CANCELLED => 'Cancelled',
            };
        }

        return match($this) {
            self::ORDERED => 'Order Placed',
            self::SENT_TO_LAB => 'Sent to Lab',
            self::READY_FOR_DELIVERY => 'Ready for Fitting',
            self::DELIVERED => 'Delivered & Fitted',
            self::CANCELLED => 'Cancelled',
        };
    }

    /**
     * Semantic CSS badge color classes for UI.
     *
     * @return string
     */
    public function badgeColor(): string
    {
        return match($this) {
            self::ORDERED => '#3b82f6',             // Blue
            self::SENT_TO_LAB => '#8b5cf6',         // Purple
            self::READY_FOR_DELIVERY => '#f59e0b',  // Amber / Warning
            self::DELIVERED => '#10b981',           // Emerald / Success
            self::CANCELLED => '#64748b',           // Slate / Gray
        };
    }

    /**
     * Background tint color for badges.
     *
     * @return string
     */
    public function badgeBg(): string
    {
        return match($this) {
            self::ORDERED => '#eff6ff',
            self::SENT_TO_LAB => '#f5f3ff',
            self::READY_FOR_DELIVERY => '#fffbeb',
            self::DELIVERED => '#ecfdf5',
            self::CANCELLED => '#f1f5f9',
        };
    }

    /**
     * FontAwesome icon representation.
     *
     * @return string
     */
    public function icon(): string
    {
        return match($this) {
            self::ORDERED => 'fa-clock',
            self::SENT_TO_LAB => 'fa-flask',
            self::READY_FOR_DELIVERY => 'fa-bell',
            self::DELIVERED => 'fa-check-circle',
            self::CANCELLED => 'fa-times-circle',
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
     * Get relevant cases for a service or product sale.
     *
     * @param bool $isService
     * @return array<self>
     */
    public static function casesFor(bool $isService = false): array
    {
        if ($isService) {
            return [
                self::ORDERED,
                self::READY_FOR_DELIVERY,
                self::DELIVERED,
                self::CANCELLED,
            ];
        }

        return [
            self::ORDERED,
            self::SENT_TO_LAB,
            self::READY_FOR_DELIVERY,
            self::DELIVERED,
            self::CANCELLED,
        ];
    }

    /**
     * Get options array for selects.
     *
     * @param bool $isService
     * @return array
     */
    public static function options(bool $isService = false): array
    {
        return array_map(fn(self $status) => [
            'value' => $status->value,
            'label' => $status->label($isService),
            'color' => $status->badgeColor(),
            'bg'    => $status->badgeBg(),
            'icon'  => $status->icon(),
        ], self::casesFor($isService));
    }
}
