<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PatientStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Discharged = 'discharged';
    case Referred = 'referred';
    case LostToFollowUp = 'lost';
    case Deceased = 'deceased';
}
