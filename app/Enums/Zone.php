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

    /**
     * Get the rank of the zone from the posterior pole outwards, or null when it names no zone.
     */
    public function posteriority(): ?int
    {
        return match ($this) {
            self::ZoneOne => 1,
            self::PosteriorZoneTwo => 2,
            self::ZoneTwo => 3,
            self::ZoneThree => 4,
            default => null,
        };
    }
}
