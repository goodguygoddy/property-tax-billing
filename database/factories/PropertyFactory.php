<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => 'P'.fake()->unique()->numberBetween(1000, 999999),
            'client_id' => Client::factory(),
            'homestead' => false,
        ];
    }

    public function homestead(): static
    {
        return $this->state(['homestead' => true]);
    }

    /**
     * @param  list<string>  $codes
     */
    public function inJurisdictions(array $codes = ['ISD', 'CNTY', 'CITY']): static
    {
        return $this->afterCreating(fn (Property $property) => $property->jurisdictions()->sync($codes));
    }
}
