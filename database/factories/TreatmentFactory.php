<?php

namespace Database\Factories;

use App\Enums\EyeSide;
use App\Enums\TreatmentType;
use App\Models\Patient;
use App\Models\Treatment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Treatment>
 */
class TreatmentFactory extends Factory
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
            'type' => TreatmentType::Eylea,
            'eye' => EyeSide::Both,
            'performed_date' => now()->subWeek()->format('Y-m-d'),
        ];
    }
}
