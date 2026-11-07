<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Distribution;
use App\Models\Farmer;
use App\Models\FarmerSeasonGroupAssignment;
use App\Models\RsbsaRecord;
use App\Models\Season;
use App\Models\SeasonGroup;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FarmerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_deceased_farmer_with_seasonal_groups_and_view_them(): void
    {
        [$wetSeason, $wetHybrid, $drySeason, $dryInbred] = $this->configureFarmerOptions();
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('farmers.store'), $this->validFarmerData([
            'first_name' => 'Maya',
            'last_name' => 'Reyes',
            'status' => 'deceased',
            'deceased_at' => '2020-06-15',
            'farm_size' => '2.5',
            'farm_size_unit' => 'hectare',
            'season_groups' => [
                $wetSeason->id => $wetHybrid->id,
                $drySeason->id => $dryInbred->id,
            ],
        ]));
        $farmer = Farmer::query()->sole();

        $response->assertRedirect(route('farmers.show', $farmer))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('farmers', [
            'id' => $farmer->id,
            'status' => 'deceased',
        ]);
        $this->assertSame('2020-06-15', $farmer->deceased_at->toDateString());
        $this->assertDatabaseHas('farmer_season_group_assignments', [
            'farmer_id' => $farmer->id,
            'season_group_id' => $wetHybrid->id,
            'assigned_by' => $admin->id,
            'ended_at' => null,
        ]);
        $this->assertDatabaseHas('farmer_season_group_assignments', [
            'farmer_id' => $farmer->id,
            'season_group_id' => $dryInbred->id,
            'assigned_by' => $admin->id,
            'ended_at' => null,
        ]);

        $this->get(route('farmers.show', $farmer))
            ->assertOk()
            ->assertSee('DATE DECEASED')
            ->assertSee('Jun 15, 2020')
            ->assertSee('Wet 2026')
            ->assertSee('Hybrid')
            ->assertSee('Dry 2026')
            ->assertSee('Inbred');

        $this->get(route('farmers.index', ['season' => $wetSeason->id, 'seed_type' => 'hybrid']))
            ->assertOk()
            ->assertSee('Maya Reyes');

        $this->get(route('farmers.index', ['status' => 'deceased']))
            ->assertOk()
            ->assertSee('Deceased')
            ->assertSee('Maya Reyes');
    }

    public function test_possible_duplicate_requires_review_but_can_be_confirmed_as_a_different_person(): void
    {
        $this->configureFarmerOptions();
        $admin = User::factory()->create(['role' => 'admin']);
        Farmer::factory()->create([
            'first_name' => 'Maya',
            'middle_name' => null,
            'last_name' => 'Reyes',
            'suffix' => null,
            'barangay' => 'San Roque',
            'municipality' => 'Baliuag',
        ]);

        $this->actingAs($admin)
            ->from(route('farmers.create'))
            ->followingRedirects()
            ->post(route('farmers.store'), $this->validFarmerData([
                'first_name' => 'Maya',
                'last_name' => 'Reyes',
            ]))
            ->assertOk()
            ->assertSee('Possible existing farmer profiles')
            ->assertSee('I reviewed these matches and confirm this is a different farmer.');

        $this->assertDatabaseCount('farmers', 1);

        $this->post(route('farmers.store'), $this->validFarmerData([
            'first_name' => 'Maya',
            'last_name' => 'Reyes',
            'contact_number' => '09170000000',
            'confirm_possible_duplicate' => '1',
        ]))->assertRedirect();

        $this->assertDatabaseCount('farmers', 2);
    }

    public function test_barangay_must_be_in_the_admin_configured_list(): void
    {
        $this->configureFarmerOptions();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('farmers.create'))
            ->post(route('farmers.store'), $this->validFarmerData([
                'barangay' => 'Not Configured',
            ]))
            ->assertRedirect(route('farmers.create'))
            ->assertSessionHasErrors([
                'barangay' => 'Select a barangay from the Admin-configured list.',
            ]);

        $this->assertDatabaseCount('farmers', 0);
    }

    public function test_seasonal_group_must_belong_to_the_selected_season(): void
    {
        [$wetSeason, , , $dryInbred] = $this->configureFarmerOptions();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('farmers.create'))
            ->post(route('farmers.store'), $this->validFarmerData([
                'season_groups' => [
                    $wetSeason->id => $dryInbred->id,
                ],
            ]))
            ->assertRedirect(route('farmers.create'))
            ->assertSessionHasErrors("season_groups.{$wetSeason->id}");

        $this->assertDatabaseCount('farmers', 0);
    }

    public function test_deceased_date_cannot_be_in_the_future(): void
    {
        $this->configureFarmerOptions();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('farmers.create'))
            ->post(route('farmers.store'), $this->validFarmerData([
                'status' => 'deceased',
                'deceased_at' => '2999-01-01',
            ]))
            ->assertRedirect(route('farmers.create'))
            ->assertSessionHasErrors([
                'deceased_at' => 'The date deceased cannot be in the future.',
            ]);

        $this->assertDatabaseCount('farmers', 0);
    }

    #[DataProvider('invalidFarmSizes')]
    public function test_farm_size_must_be_positive_and_within_the_unit_limit(string $size, string $unit, string $message): void
    {
        $this->configureFarmerOptions();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from(route('farmers.create'))
            ->post(route('farmers.store'), $this->validFarmerData([
                'farm_size' => $size,
                'farm_size_unit' => $unit,
            ]))
            ->assertRedirect(route('farmers.create'))
            ->assertSessionHasErrors(['farm_size' => $message]);

        $this->assertDatabaseCount('farmers', 0);
    }

    public static function invalidFarmSizes(): array
    {
        return [
            'zero hectares' => ['0', 'hectare', 'Farm size must be greater than zero.'],
            'over the hectare limit' => ['1000.01', 'hectare', 'Farm size exceeds the maximum for the selected unit.'],
            'over the square meter limit' => ['10000000.01', 'sqm', 'Farm size exceeds the maximum for the selected unit.'],
            'over the acre limit' => ['2500.01', 'acre', 'Farm size exceeds the maximum for the selected unit.'],
        ];
    }

    #[DataProvider('maximumFarmSizes')]
    public function test_farm_size_accepts_the_maximum_for_each_unit(string $size, string $unit): void
    {
        $this->configureFarmerOptions();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('farmers.store'), $this->validFarmerData([
                'farm_size' => $size,
                'farm_size_unit' => $unit,
                'rsbsa_number' => "RSBSA-BOUNDARY-{$unit}",
            ]))
            ->assertRedirect();

        $record = RsbsaRecord::query()->where('rsbsa_number', "RSBSA-BOUNDARY-{$unit}")->sole();

        $this->assertSame(number_format((float) $size, 2, '.', ''), $record->farm_size);
        $this->assertSame($unit, $record->farm_size_unit);
    }

    public static function maximumFarmSizes(): array
    {
        return [
            'hectare maximum' => ['1000', 'hectare'],
            'square meter maximum' => ['10000000', 'sqm'],
            'acre maximum' => ['2500', 'acre'],
        ];
    }

    public function test_switching_a_deceased_farmer_to_active_clears_the_deceased_date(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $farmer = Farmer::factory()->create([
            'status' => 'deceased',
            'deceased_at' => '2020-06-15',
            'barangay' => 'San Roque',
        ]);

        $this->actingAs($admin)
            ->put(route('farmers.update', $farmer), $this->validFarmerData([
                'first_name' => $farmer->first_name,
                'middle_name' => $farmer->middle_name,
                'last_name' => $farmer->last_name,
                'suffix' => $farmer->suffix,
                'barangay' => 'San Roque',
                'status' => 'active',
            ]))
            ->assertRedirect(route('farmers.show', $farmer));

        $this->assertDatabaseHas('farmers', [
            'id' => $farmer->id,
            'status' => 'active',
            'deceased_at' => null,
        ]);
    }

    public function test_farmer_registration_links_the_legacy_barangay_name_to_the_master_record(): void
    {
        $this->configureFarmerOptions();
        $technician = User::factory()->create(['role' => 'technician']);

        $this->actingAs($technician)
            ->post(route('farmers.store'), $this->validFarmerData([
                'first_name' => 'Leah',
                'last_name' => 'Cruz',
            ]))
            ->assertRedirect();

        $barangay = Barangay::query()->where('normalized_name', 'san roque')->sole();
        $farmer = Farmer::query()->sole();

        $this->assertDatabaseHas('farmers', [
            'id' => $farmer->id,
            'barangay' => 'San Roque',
            'barangay_id' => $barangay->id,
        ]);
        $this->assertTrue($barangay->is_active);
    }

    public function test_barangay_migration_preserves_legacy_text_and_backfills_the_master_foreign_key(): void
    {
        $farmer = Farmer::factory()->create(['barangay' => 'Santa Cruz']);
        SystemSetting::create(['key' => 'barangays', 'value' => ['Poblacion']]);
        $migration = require database_path('migrations/2026_11_06_222346_create_barangay_master_and_backfill_farmer_links.php');

        $migration->down();
        $migration->up();

        $farmer->refresh();
        $this->assertSame('Santa Cruz', $farmer->barangay);
        $this->assertNotNull($farmer->barangay_id);
        $this->assertSame('santa cruz', $farmer->barangayMaster->normalized_name);
        $this->assertDatabaseHas('barangays', [
            'name' => 'Poblacion',
            'normalized_name' => 'poblacion',
            'is_active' => true,
        ]);
    }

    public function test_technician_cannot_mark_a_farmer_deceased_or_verify_rsbsa(): void
    {
        $this->configureFarmerOptions();
        $technician = User::factory()->create(['role' => 'technician']);

        $this->actingAs($technician)
            ->from(route('farmers.create'))
            ->post(route('farmers.store'), $this->validFarmerData([
                'status' => 'deceased',
                'deceased_at' => '2020-06-15',
                'verification_status' => 'verified',
            ]))
            ->assertRedirect(route('farmers.create'))
            ->assertSessionHasErrors(['status', 'verification_status']);

        $this->assertDatabaseCount('farmers', 0);
    }

    public function test_reassigning_a_farmer_preserves_the_previous_group_assignment(): void
    {
        [$season, $hybrid] = $this->configureFarmerOptions();
        $inbred = $season->groups()->create(['seed_type' => 'inbred']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('farmers.store'), $this->validFarmerData([
            'season_groups' => [$season->id => $hybrid->id],
        ]))->assertRedirect();
        $farmer = Farmer::query()->sole();
        $previousAssignment = FarmerSeasonGroupAssignment::query()->sole();

        $this->put(route('farmers.update', $farmer), $this->validFarmerData([
            'season_groups' => [$season->id => $inbred->id],
        ]))->assertRedirect(route('farmers.show', $farmer));

        $previousAssignment->refresh();
        $this->assertSame($hybrid->id, $previousAssignment->season_group_id);
        $this->assertNotNull($previousAssignment->ended_at);
        $this->assertDatabaseHas('farmer_season_group_assignments', [
            'farmer_id' => $farmer->id,
            'season_group_id' => $inbred->id,
            'ended_at' => null,
            'assigned_by' => $admin->id,
        ]);

        $this->get(route('farmers.show', $farmer))
            ->assertOk()
            ->assertSee('Seasonal Hybrid / Inbred group history')
            ->assertSee('Previous')
            ->assertSee('Current');
    }

    public function test_admin_cannot_change_a_farmers_group_after_a_completed_distribution_in_that_season(): void
    {
        [$season, $hybrid] = $this->configureFarmerOptions();
        $inbred = $season->groups()->create(['seed_type' => 'inbred']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('farmers.store'), $this->validFarmerData([
            'season_groups' => [$season->id => $hybrid->id],
        ]))->assertRedirect();
        $farmer = Farmer::query()->sole();

        Distribution::create([
            'transaction_number' => 'AGR-SEASON-LOCK-1',
            'farmer_id' => $farmer->id,
            'season_id' => $season->id,
            'season_group_id' => $hybrid->id,
            'processed_by' => $admin->id,
            'transaction_status' => 'completed',
            'distributed_at' => now(),
        ]);

        $this->from(route('farmers.show', $farmer))
            ->put(route('farmers.update', $farmer), $this->validFarmerData([
                'season_groups' => [$season->id => $inbred->id],
            ]))
            ->assertRedirect(route('farmers.show', $farmer))
            ->assertSessionHasErrors([
                "season_groups.{$season->id}" => 'A farmer group cannot be changed after a completed distribution in that season.',
            ]);

        $this->assertDatabaseHas('farmer_season_group_assignments', [
            'farmer_id' => $farmer->id,
            'season_group_id' => $hybrid->id,
            'ended_at' => null,
        ]);
        $this->assertDatabaseCount('farmer_season_group_assignments', 1);
    }

    public function test_staff_cannot_change_seasonal_group_assignments(): void
    {
        [$season, $hybrid, , $inbred] = $this->configureFarmerOptions();
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'technician']);

        $this->actingAs($admin)->post(route('farmers.store'), $this->validFarmerData([
            'season_groups' => [$season->id => $hybrid->id],
        ]))->assertRedirect();
        $farmer = Farmer::query()->sole();

        $this->actingAs($staff)
            ->put(route('farmers.update', $farmer), $this->validFarmerData([
                'season_groups' => [$season->id => $inbred->id],
            ]))
            ->assertForbidden();

        $this->assertDatabaseCount('farmer_season_group_assignments', 1);
        $this->assertDatabaseHas('farmer_season_group_assignments', [
            'farmer_id' => $farmer->id,
            'season_group_id' => $hybrid->id,
            'ended_at' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validFarmerData(array $overrides = []): array
    {
        return array_replace([
            'first_name' => 'Maya',
            'middle_name' => '',
            'last_name' => 'Farmer',
            'suffix' => '',
            'contact_number' => '',
            'address' => '12 Farm Road',
            'barangay' => 'San Roque',
            'municipality' => 'Baliuag',
            'province' => 'Bulacan',
            'status' => 'active',
            'deceased_at' => '',
            'registration_status' => 'unregistered',
            'verification_status' => 'unverified',
            'registration_date' => '',
            'farm_size' => '',
            'farm_size_unit' => 'hectare',
            'crop_information' => '',
            'farm_location' => '',
            'tenure_type' => '',
            'landowner_name' => '',
            'landowner_contact' => '',
            'farm_remarks' => '',
        ], $overrides);
    }

    /**
     * @return array{Season, SeasonGroup, Season, SeasonGroup}
     */
    private function configureFarmerOptions(): array
    {
        SystemSetting::create(['key' => 'barangays', 'value' => ['San Roque', 'Poblacion']]);
        $wetSeason = Season::create(['name' => 'Wet 2026', 'status' => 'active']);
        $drySeason = Season::create(['name' => 'Dry 2026', 'status' => 'active']);

        return [
            $wetSeason,
            $wetSeason->groups()->create(['seed_type' => 'hybrid']),
            $drySeason,
            $drySeason->groups()->create(['seed_type' => 'inbred']),
        ];
    }
}
