<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum RopStatus: string
{
    use HasOptions;

    case NoRop = 'no_rop';
    case Present = 'present';
    case Regressing = 'regressing';
    case Regressed = 'regressed';
    case FullyVascularized = 'fully_vascularized';
    case IncompleteVascularization = 'incomplete_vascularization';
    case NotAssessable = 'not_assessable';

    /**
     * Determine whether the status documents current or previous retinopathy.
     */
    public function indicatesRop(): bool
    {
        return in_array($this, [self::Present, self::Regressing, self::Regressed], true);
    }

    /**
     * Determine whether the status documents an eye without retinopathy.
     */
    public function excludesRop(): bool
    {
        return in_array($this, [self::NoRop, self::FullyVascularized, self::IncompleteVascularization], true);
    }
}
