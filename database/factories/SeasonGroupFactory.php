<?php

namespace Database\Factories;

use App\Models\Season;
use App\Models\SeasonGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeasonGroup>
 */
class SeasonGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'seed_type' => fake()->randomElement(SeasonGroup::SEED_TYPES),
        ];
    }
}
