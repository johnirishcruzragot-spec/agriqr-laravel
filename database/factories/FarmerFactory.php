<?php

namespace Database\Factories;

use App\Models\Farmer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Farmer>
 */
class FarmerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farmer_id' => 'F'.$this->faker->unique()->numerify('##########'),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->optional()->firstName(),
            'last_name' => fake()->lastName(),
            'address' => fake()->streetAddress(),
            'barangay' => fake()->citySuffix(),
            'municipality' => fake()->city(),
            'province' => fake()->state(),
            'status' => 'active',
        ];
    }
}
