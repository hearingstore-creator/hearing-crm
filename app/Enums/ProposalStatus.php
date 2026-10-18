<?php

namespace App\Enums;

enum ProposalStatus: string
{
    case DRAFT = 'Draft';
    case SENT = 'Sent';
    case NEW = 'New';
    case ACCEPTED = 'Accepted';
    case DECLINED = 'Declined';
    case CONVERTED = 'Converted';
    case EXPIRED = 'Expired';

    public function badgeClass(): string
    {
        return match($this) {
            self::DRAFT => 'badge-secondary',
            self::SENT => 'badge-info',
            self::NEW => 'badge-primary',
            self::ACCEPTED => 'badge-success',
            self::DECLINED => 'badge-danger',
            self::CONVERTED => 'badge-dark',
            self::EXPIRED => 'badge-warning',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::DRAFT => '#6c757d',
            self::SENT => '#17a2b8',
            self::NEW => '#007bff',
            self::ACCEPTED => '#28a745',
            self::DECLINED => '#dc3545',
            self::CONVERTED => '#6f42c1',
            self::EXPIRED => '#fd7e14',
        };
    }
}
