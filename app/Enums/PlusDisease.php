<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PlusDisease: string
{
    use HasOptions;

    case None = 'none';
    case PrePlus = 'pre_plus';
    case Plus = 'plus';
    case NotAssessable = 'not_assessable';
}
