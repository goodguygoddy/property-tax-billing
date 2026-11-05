<?php

namespace Database\Factories;

use App\Enums\OutcomeStatus;
use App\Models\OutcomeEvent;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutcomeEvent>
 */
class OutcomeEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'source_id' => 'E'.fake()->unique()->numberBetween(1000, 999999),
            'recorded_at' => '2026-06-01',
            'status' => OutcomeStatus::Final,
            'noticed_value' => '300000',
            'final_value' => '280000',
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => OutcomeStatus::Pending, 'final_value' => null]);
    }
}
