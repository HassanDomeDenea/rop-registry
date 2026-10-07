<?php

namespace Database\Factories;

use App\Enums\ManagementPlan;
use App\Enums\PlusDisease;
use App\Enums\RopStatus;
use App\Enums\VisitKind;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visit>
 */
class VisitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'kind' => VisitKind::Examination,
            'visit_date' => now()->subWeeks(2)->format('Y-m-d'),
            'right_rop_status' => RopStatus::NoRop,
            'right_plus' => PlusDisease::None,
            'left_rop_status' => RopStatus::NoRop,
            'left_plus' => PlusDisease::None,
            'management_plan' => ManagementPlan::Observe,
            'next_visit_date' => now()->addWeeks(2)->format('Y-m-d'),
        ];
    }
}
