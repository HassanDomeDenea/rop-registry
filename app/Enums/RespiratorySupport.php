<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum RespiratorySupport: string
{
    use HasOptions;

    case None = 'none';
    case Oxygen = 'o2';
    case Cpap = 'cpap';
    case OxygenAndCpap = 'o2_cpap';
    case Ventilator = 'ventilator';
}
