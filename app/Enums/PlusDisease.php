<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PlusDisease: string
{
    use HasOptions;

    case None = 'none';
    case PrePlus = 'pre_plus';
    case Plus = 'plus';
    case NotAssessable = 'not_assessable';

    /**
     * Get the severity of the vascular changes, or null when they could not be assessed.
     */
    public function severity(): ?int
    {
        return match ($this) {
            self::None => 0,
            self::PrePlus => 1,
            self::Plus => 2,
            self::NotAssessable => null,
        };
    }
}
