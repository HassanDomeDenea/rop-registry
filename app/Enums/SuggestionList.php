<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum SuggestionList: string
{
    use HasOptions;

    case Illness = 'illness';
    case ReferringDoctor = 'referring_doctor';
    case EyeFinding = 'eye_finding';
}
