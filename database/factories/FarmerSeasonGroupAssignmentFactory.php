<?php

namespace Database\Factories;

use App\Models\Farmer;
use App\Models\FarmerSeasonGroupAssignment;
use App\Models\SeasonGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FarmerSeasonGroupAssignment>
 */
class FarmerSeasonGroupAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farmer_id' => Farmer::factory(),
            'season_group_id' => SeasonGroup::factory(),
            'started_at' => now(),
        ];
    }
}
