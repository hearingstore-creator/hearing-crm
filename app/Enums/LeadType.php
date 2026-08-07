<?php

namespace App\Enums;

enum LeadType: string
{
    case NEW_CUSTOMER = 'new_customer';
    case EXISTING_CUSTOMER = 'existing_customer';
    case VENDOR_REFERRAL = 'vendor_referral';

    public function label(): string
    {
        return match($this) {
            self::NEW_CUSTOMER => 'New Customer',
            self::EXISTING_CUSTOMER => 'Existing Customer',
            self::VENDOR_REFERRAL => 'Vendor Referral',
        };
    }
}
