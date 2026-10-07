<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum RopType: string
{
    use HasOptions;

    case TypeOne = 'type_1';
    case TypeTwo = 'type_2';
}
