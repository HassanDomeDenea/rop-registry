<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Multiplicity: string
{
    use HasOptions;

    case Single = 'single';
    case Twin = 'twin';
    case Triplet = 'triplet';
    case Higher = 'higher';
    case Unknown = 'unknown';
}
