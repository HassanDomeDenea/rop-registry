<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Sex: string
{
    use HasOptions;

    case Male = 'male';
    case Female = 'female';
    case Unknown = 'unknown';
}
