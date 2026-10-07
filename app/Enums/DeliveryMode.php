<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum DeliveryMode: string
{
    use HasOptions;

    case Vaginal = 'vaginal';
    case Cesarean = 'cesarean';
    case Other = 'other';
}
