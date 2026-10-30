<?php

namespace App\Support;

/**
 * A doctor, referrer (quack / agent), employee or branch manager as an SMS recipient.
 * Shaped like the Lead / Customer rows the campaign sender already handles.
 */
class SmsPerson
{
    public $lead = null;

    public function __construct(
        public int $id,
        public string $full_name,
        public ?string $phone_number,
        /** What the Send Log stores: doctor, referrer, employee, branch */
        public string $sms_recipient_type,
        public string $segment = '',
    ) {
    }

    /** Where the Send Log links to for this kind of person. */
    public static function profileUrl(string $type, int $id): ?string
    {
        return match ($type) {
            'doctor', 'referrer' => url('admin/doctor/' . $id . '/edit'),
            'employee', 'branch' => url('admin/user/' . $id),
            default => null,
        };
    }
}
