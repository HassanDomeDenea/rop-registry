<?php

namespace Database\Factories;

use App\Enums\DeliveryMode;
use App\Enums\Multiplicity;
use App\Enums\PatientStatus;
use App\Enums\RespiratorySupport;
use App\Enums\Sex;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dob = fake()->dateTimeBetween('-5 months', '-5 weeks');

        return [
            'file_number' => (string) fake()->unique()->numberBetween(1, 99999),
            'name' => fake()->name(),
            'dob' => $dob->format('Y-m-d'),
            'sex' => fake()->randomElement([Sex::Male, Sex::Female]),
            'birth_weight_g' => fake()->numberBetween(700, 2400),
            'ga_weeks' => fake()->numberBetween(25, 35),
            'ga_days' => fake()->numberBetween(0, 6),
            'multiplicity' => fake()->randomElement([Multiplicity::Single, Multiplicity::Single, Multiplicity::Twin]),
            'delivery_mode' => fake()->randomElement(DeliveryMode::cases()),
            'referral_date' => (clone $dob)->modify('+4 weeks')->format('Y-m-d'),
            'nicu_days' => fake()->numberBetween(3, 60),
            'respiratory_support' => fake()->randomElement(RespiratorySupport::cases()),
            'support_days' => fake()->numberBetween(1, 30),
            'status' => PatientStatus::Active,
        ];
    }
}
