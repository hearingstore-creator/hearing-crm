<?php

namespace App\Enums;

enum ProposalStatus: string
{
    case ACCEPTED = 'Accepted';
    case DECLINED = 'Declined';
    case NEW = 'New';
    case DRAFT = 'Draft';
}
