<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EyeSide: string
{
    use HasOptions;

    case Right = 'right';
    case Left = 'left';
    case Both = 'both';
}
