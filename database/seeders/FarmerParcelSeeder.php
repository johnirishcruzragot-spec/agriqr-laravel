<?php

namespace Database\Seeders;

use App\Models\FarmerParcel;
use Illuminate\Database\Seeder;

class FarmerParcelSeeder extends Seeder
{
    public function run(): void
    {
        FarmerParcel::factory()->count(10)->create();
    }
}
