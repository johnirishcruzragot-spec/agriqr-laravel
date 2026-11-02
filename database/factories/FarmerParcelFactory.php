<?php

namespace Database\Factories;

use App\Models\Farmer;
use App\Models\FarmerParcel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FarmerParcel>
 */
class FarmerParcelFactory extends Factory
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
            'parcel_number' => fake()->unique()->numerify('PARCEL-####'),
            'parcel_address_1' => fake()->streetAddress(),
            'parcel_area' => fake()->randomFloat(4, 0.1, 25),
            'commodity_name' => fake()->randomElement(['Rice', 'Corn', 'Vegetables']),
            'crop_area' => fake()->randomFloat(4, 0.1, 20),
            'farm_type' => 'Crop',
            'number_of_heads' => null,
            'agency' => 'Department of Agriculture',
            'ownership_type' => 'Owner',
            'owner_name' => fake()->name(),
        ];
    }
}
