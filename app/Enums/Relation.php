<?php

namespace App\Enums;

enum Relation: string
{
    case FATHER = 'father';
    case MOTHER = 'mother';
    case SON = 'son';
    case DAUGHTER = 'daughter';
    case BROTHER = 'brother';
    case SISTER = 'sister';
    case RELATIVE = 'relative';
    case CAREGIVER = 'caregiver';
    case OTHER = 'other';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
