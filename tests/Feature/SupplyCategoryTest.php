<?php

namespace Tests\Feature;

use App\Models\Distribution;
use App\Models\Farmer;
use App\Models\FarmerSeasonGroupAssignment;
use App\Models\Inventory;
use App\Models\ProgramSupplyGroupRate;
use App\Models\Season;
use App\Models\SubsidyProgram;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SupplyCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_supply_form_offers_the_standard_categories(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('supplies.create'));

        $response->assertOk();

        foreach (Supply::CATEGORIES as $category) {
            $response->assertSee($category);
        }

        $response->assertSee('Unit of measurement')
            ->assertSee('Not required for Cash, Farm Tools, or Other Agricultural Supplies.');
    }

    #[DataProvider('unitOptionalCategories')]
    public function test_supply_can_be_created_without_a_unit_for_optional_categories(string $category): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('supplies.store'), [
                'supply_code' => 'SUP-'.str_replace(' ', '-', strtoupper($category)),
                'supply_name' => "{$category} support",
                'category' => $category,
                'unit' => '',
                'minimum_stock' => '0',
                'status' => 'active',
            ])
            ->assertRedirect(route('supplies.index'));

        $supply = Supply::where('category', $category)->sole();

        $this->assertSame(null, $supply->unit);
        $this->assertDatabaseHas('inventories', ['supply_id' => $supply->id, 'quantity' => 0]);
    }

    public function test_supply_requires_a_unit_for_measured_categories(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('supplies.create'))
            ->post(route('supplies.store'), [
                'supply_code' => 'SEED-UNIT-REQUIRED',
                'supply_name' => 'Rice seed',
                'category' => 'Seeds',
                'unit' => '',
                'minimum_stock' => '0',
                'status' => 'active',
            ])
            ->assertRedirect(route('supplies.create'))
            ->assertSessionHasErrors('unit');

        $this->assertDatabaseMissing('supplies', ['supply_code' => 'SEED-UNIT-REQUIRED']);
    }

    public function test_supply_form_rejects_a_category_outside_the_standard_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('supplies.create'))
            ->post(route('supplies.store'), [
                'supply_code' => 'SUP-CUSTOM',
                'supply_name' => 'Custom supply',
                'category' => 'Custom category',
                'unit' => 'bag',
                'minimum_stock' => '0',
                'status' => 'active',
            ])
            ->assertRedirect(route('supplies.create'))
            ->assertSessionHasErrors('category');

        $this->assertDatabaseMissing('supplies', ['supply_code' => 'SUP-CUSTOM']);
    }

    public function test_distribution_can_save_a_supply_without_a_unit(): void
    {
        $staff = User::factory()->create(['role' => 'technician']);
        Storage::fake('local');
        $photoPath = 'farmer-photos/FUNITLESS001.jpg';
        Storage::disk('local')->put($photoPath, 'verified-photo-fixture');
        $farmer = Farmer::factory()->create([
            'farmer_id' => 'FUNITLESS001',
            'status' => 'active',
            'photo_path' => $photoPath,
        ]);
        $farmer->rsbsaRecord()->create([
            'rsbsa_number' => 'RSBSA-UNITLESS',
            'registration_status' => 'registered',
            'verification_status' => 'verified',
            'farm_size' => 1,
            'farm_size_unit' => 'hectare',
        ]);
        $season = Season::create(['name' => 'Wet 2026', 'status' => 'active']);
        $group = $season->groups()->create(['seed_type' => 'hybrid']);
        $assignment = FarmerSeasonGroupAssignment::create([
            'farmer_id' => $farmer->id,
            'season_group_id' => $group->id,
            'started_at' => now(),
        ]);
        $supply = Supply::create([
            'supply_code' => 'CASH-001',
            'supply_name' => 'Cash assistance',
            'category' => 'Cash',
            'unit' => null,
            'minimum_stock' => 0,
            'status' => 'active',
        ]);
        Inventory::create([
            'supply_id' => $supply->id,
            'quantity' => 100,
        ]);
        $program = SubsidyProgram::create([
            'program_code' => 'CASH-PROGRAM',
            'program_name' => 'Cash assistance program',
            'season_id' => $season->id,
            'status' => 'active',
        ]);
        $program->supplies()->attach($supply, ['unit_value' => 1]);
        $program->seasonGroups()->attach($group, ['rate_per_hectare' => null]);
        ProgramSupplyGroupRate::create([
            'subsidy_program_id' => $program->id,
            'supply_id' => $supply->id,
            'season_group_id' => $group->id,
            'quantity_per_hectare' => 25,
            'maximum_quantity_per_farmer' => 100,
        ]);

        $response = $this->actingAs($staff)->post(route('distributions.store'), [
            'farmer_code' => $farmer->farmer_id,
            'subsidy_program_id' => $program->id,
            'farmer_season_group_assignment_id' => $assignment->id,
            'face_verified' => '1',
            'items' => [
                ['supply_id' => $supply->id, 'quantity' => '25'],
            ],
        ]);

        $distribution = Distribution::sole();
        $response->assertRedirect(route('distributions.show', $distribution));
        $this->assertDatabaseHas('distribution_items', [
            'distribution_id' => $distribution->id,
            'supply_id' => $supply->id,
            'quantity' => 25,
            'unit' => null,
        ]);

        $this->get(route('distributions.show', $distribution))
            ->assertOk()
            ->assertSee('25.00');
    }

    public static function unitOptionalCategories(): array
    {
        return [
            'cash' => ['Cash'],
            'farm tools' => ['Farm Tools'],
            'other agricultural supplies' => ['Other Agricultural Supplies'],
        ];
    }
}
