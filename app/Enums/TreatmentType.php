<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum TreatmentType: string
{
    use HasOptions;

    case Eylea = 'eylea';
    case OtherAntiVegf = 'other_anti_vegf';
    case Laser = 'laser';
    case Surgery = 'surgery';
    case Other = 'other';

    /**
     * Determine whether the treatment is an intravitreal injection.
     */
    public function isInjection(): bool
    {
        return in_array($this, [self::Eylea, self::OtherAntiVegf], true);
    }
}
