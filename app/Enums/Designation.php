<?php

namespace App\Enums;

enum Designation: string
{
    case AUDIOLOGIST = 'audiologist';
    case ENT_SPECIALIST = 'ent_specialist';
    case HEARING_THERAPIST = 'hearing_therapist';
    case PATIENT_COORDINATOR = 'patient_coordinator';
    case CLINICAL_AUDIOMETRIST = 'clinical_audiometrist';
    case SPEECH_THERAPIST = 'speech_therapist';
    case EAR_NOSE_THROAT_SURGEON = 'ear_nose_throat_surgeon';

    public function label(): string
    {
        return match ($this) {
            self::AUDIOLOGIST => 'Audiologist',
            self::ENT_SPECIALIST => 'ENT Specialist',
            self::HEARING_THERAPIST => 'Hearing Therapist',
            self::PATIENT_COORDINATOR => 'Patient Coordinator',
            self::CLINICAL_AUDIOMETRIST => 'Clinical Audiometrist',
            self::SPEECH_THERAPIST => 'Speech Therapist',
            self::EAR_NOSE_THROAT_SURGEON => 'Ear Nose Throat Surgeon',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::AUDIOLOGIST => 'blue',
            self::ENT_SPECIALIST => 'green',
            self::HEARING_THERAPIST => 'purple',
            self::PATIENT_COORDINATOR => 'orange',
            self::CLINICAL_AUDIOMETRIST => 'indigo',
            self::SPEECH_THERAPIST => 'pink',
            self::EAR_NOSE_THROAT_SURGEON => 'red',
        };
    }
}
