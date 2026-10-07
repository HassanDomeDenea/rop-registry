<?php

namespace App\Support;

use App\Enums\PlusDisease;
use App\Enums\RopType;
use App\Enums\Stage;
use App\Enums\Zone;

/**
 * Classifies an eye according to the ETROP study criteria.
 *
 * Type 1 (treatment indicated): zone I any stage with plus disease, zone I stage 3
 * without plus disease, zone II stage 2 or 3 with plus disease, or aggressive ROP.
 * Type 2 (close observation): zone I stage 1 or 2 without plus disease, or zone II
 * stage 3 without plus disease.
 */
class RopClassifier
{
    public static function classify(?Zone $zone, ?Stage $stage, ?PlusDisease $plus, ?bool $aggressive = null): ?RopType
    {
        if ($aggressive === true) {
            return RopType::TypeOne;
        }

        $severity = $stage?->severity();

        if ($zone === null || $severity === null || $severity < 1 || $severity > 3) {
            return null;
        }

        $hasPlus = $plus === PlusDisease::Plus;
        $isZoneOne = $zone === Zone::ZoneOne;
        $isZoneTwo = in_array($zone, [Zone::ZoneTwo, Zone::PosteriorZoneTwo], true);

        if ($isZoneOne) {
            return $hasPlus || $severity === 3 ? RopType::TypeOne : RopType::TypeTwo;
        }

        if ($isZoneTwo && $hasPlus && $severity >= 2) {
            return RopType::TypeOne;
        }

        if ($isZoneTwo && ! $hasPlus && $severity === 3) {
            return RopType::TypeTwo;
        }

        return null;
    }
}
