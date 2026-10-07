<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum Stage: string
{
    use HasOptions;

    case StageZero = 'stage_0';
    case StageOne = 'stage_1';
    case StageTwo = 'stage_2';
    case StageThree = 'stage_3';
    case StageFourA = 'stage_4a';
    case StageFourB = 'stage_4b';
    case StageFive = 'stage_5';
    case NotApplicable = 'not_applicable';
    case NotAssessable = 'not_assessable';

    /**
     * Get the numeric severity of the stage, or null when it carries none.
     */
    public function severity(): ?int
    {
        return match ($this) {
            self::StageZero => 0,
            self::StageOne => 1,
            self::StageTwo => 2,
            self::StageThree => 3,
            self::StageFourA, self::StageFourB => 4,
            self::StageFive => 5,
            default => null,
        };
    }

    /**
     * Determine whether the stage documents retinopathy.
     */
    public function indicatesRop(): bool
    {
        return ($this->severity() ?? 0) >= 1;
    }
}
