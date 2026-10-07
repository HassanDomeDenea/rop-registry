<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ManagementPlan: string
{
    use HasOptions;

    case Observe = 'observe';
    case Eylea = 'eylea';
    case Laser = 'laser';
    case EyleaAndLaser = 'eylea_laser';
    case Referred = 'referred';
    case Discharge = 'discharge';
    case Other = 'other';

    /**
     * Determine whether the plan recommends an intravitreal injection.
     */
    public function includesInjection(): bool
    {
        return in_array($this, [self::Eylea, self::EyleaAndLaser], true);
    }

    /**
     * Determine whether the plan recommends laser photocoagulation.
     */
    public function includesLaser(): bool
    {
        return in_array($this, [self::Laser, self::EyleaAndLaser], true);
    }
}
