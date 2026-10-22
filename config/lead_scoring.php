<?php

/**
 * Lead score (0–100): points added for signs a person is likely to buy soon. These are the starting weights;
 * they can be changed in Pipeline Settings → Lead score. Hot from 70, warm from 40.
 */
return [
    'base' => 20,
    'hot_from' => 70,
    'warm_from' => 40,

    'weights' => [
        'interest_device'    => ['points' => 20, 'label' => 'Wants a hearing aid or implant'],
        'interest_test'      => ['points' => 12, 'label' => 'Wants a hearing test'],
        'interest_service'   => ['points' => 6,  'label' => 'Mould, repair or equipment'],
        'budget_high'        => ['points' => 15, 'label' => 'Budget / value ৳50,000 or more'],
        'budget_some'        => ['points' => 8,  'label' => 'Budget / value given'],
        'loss_moderate'      => ['points' => 10, 'label' => 'Moderate or worse hearing loss recorded'],
        'loss_any'           => ['points' => 5,  'label' => 'Hearing loss recorded'],
        'age_55'             => ['points' => 5,  'label' => 'Aged 55 or more'],
        'source_referral'    => ['points' => 15, 'label' => 'Referred (doctor or patient)'],
        'source_direct'      => ['points' => 8,  'label' => 'Called or came in'],
        'returning'          => ['points' => 10, 'label' => 'Bought from us before'],
        'stage_assessment'   => ['points' => 10, 'label' => 'Reached assessment'],
        'stage_trial'        => ['points' => 15, 'label' => 'Had a trial'],
        'recent_contact'     => ['points' => 5,  'label' => 'Spoke in the last 7 days'],
        'overdue_activity'   => ['points' => -5, 'label' => 'Next step is overdue'],
        'quiet_14'           => ['points' => -10, 'label' => 'No contact for 14 days'],
        'stuck'              => ['points' => -10, 'label' => 'Stuck in the stage too long'],
    ],
];
