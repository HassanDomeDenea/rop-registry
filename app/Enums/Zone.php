<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Zone: string
{
    use HasOptions;

    case ZoneOne = 'zone_1';
    case PosteriorZoneTwo = 'posterior_zone_2';
    case ZoneTwo = 'zone_2';
    case ZoneThree = 'zone_3';
    case NotApplicable = 'not_applicable';
    case NotAssessable = 'not_assessable';
}
