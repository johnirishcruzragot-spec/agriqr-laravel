<?php

namespace Tests\Feature;

use App\Models\Farmer;
use App\Models\FarmerSeasonGroupAssignment;
use App\Models\ProgramSupplyGroupRate;
use App\Models\Season;
use App\Models\SeasonGroup;
use App\Models\SubsidyProgram;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProgramTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_form_explains_each_subsidy_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->activeSeasonGroup();
        Supply::create([
            'supply_code' => 'SUP-FORM',
            'supply_name' => 'Rice seed',
            'category' => 'Seeds',
            'unit' => 'bag',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('programs.create'))
            ->assertOk()
            ->assertSee('In-kind (supplies)')
            ->assertSee('Hybrid')
            ->assertSee('Embedded')
            ->assertSee('Hybrid subsidy combines assistance such as cash and agricultural supplies')
            ->assertSee('Embedded subsidy provides a discounted product price')
            ->assertSee('Eligible farmer groups')
            ->assertSee('Quantity per hectare')
            ->assertSee('Maximum per farmer')
            ->assertSee('Require registered and verified RSBSA');
    }

    public function test_program_creation_saves_the_selected_rsbsa_requirement(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $group = $this->activeSeasonGroup();
        $supply = Supply::create([
            'supply_code' => 'SUP-RSBSA-RULE',
            'supply_name' => 'Rice seed',
            'category' => 'Seeds',
            'unit' => 'bag',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('programs.store'), [
                'program_code' => 'PROGRAM-RSBSA-OPTIONAL',
                'program_name' => 'Optional RSBSA program',
                'season_id' => $group->season_id,
                'subsidy_type' => 'in_kind',
                'requires_verified_rsbsa' => '0',
                'start_date' => today()->toDateString(),
                'end_date' => today()->addDays(30)->toDateString(),
                'status' => 'draft',
                'items' => [['supply_id' => $supply->id]],
                'season_group_ids' => [$group->id],
                'rates' => [[
                    'supply_id' => $supply->id,
                    'season_group_id' => $group->id,
                    'quantity_per_hectare' => '3',
                    'maximum_quantity_per_farmer' => '10',
                ]],
            ])
            ->assertRedirect(route('programs.index'));

        $this->assertDatabaseHas('subsidy_programs', [
            'program_code' => 'PROGRAM-RSBSA-OPTIONAL',
            'requires_verified_rsbsa' => false,
        ]);
    }

    public function test_subsidy_type_is_shown_in_program_lists_and_distribution_selection(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $group = $this->activeSeasonGroup();
        $supply = Supply::create([
            'supply_code' => 'SUP-HYBRID',
            'supply_name' => 'Rice seed',
            'category' => 'Seeds',
            'unit' => 'bag',
            'status' => 'active',
        ]);
        $program = SubsidyProgram::create([
            'program_code' => 'PROGRAM-HYBRID',
            'program_name' => 'Combined farmer support',
            'season_id' => $group->season_id,
            'subsidy_type' => 'hybrid',
            'status' => 'active',
        ]);
        $program->supplies()->attach($supply, ['unit_value' => 5]);
        $program->seasonGroups()->attach($group, ['rate_per_hectare' => null]);
        ProgramSupplyGroupRate::create([
            'subsidy_program_id' => $program->id,
            'supply_id' => $supply->id,
            'season_group_id' => $group->id,
            'quantity_per_hectare' => 3,
            'maximum_quantity_per_farmer' => 10,
        ]);
        $farmer = Farmer::create([
            'farmer_id' => 'FARMER-HYBRID',
            'first_name' => 'Maya',
            'last_name' => 'Reyes',
            'address' => '12 Farm Road',
            'barangay' => 'San Roque',
            'municipality' => 'Baliuag',
            'province' => 'Bulacan',
            'status' => 'active',
        ]);
        $farmer->rsbsaRecord()->create([
            'rsbsa_number' => 'RSBSA-HYBRID',
            'registration_status' => 'registered',
            'verification_status' => 'verified',
            'farm_size' => 1,
            'farm_size_unit' => 'hectare',
        ]);
        FarmerSeasonGroupAssignment::create([
            'farmer_id' => $farmer->id,
            'season_group_id' => $group->id,
            'assigned_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('programs.index'))
            ->assertOk()
            ->assertSee('Hybrid');

        $this->get(route('distributions.create', ['farmer' => $farmer->farmer_id]))
            ->assertOk()
            ->assertSee('Combined farmer support')
            ->assertSee('Hybrid')
            ->assertSee('3.0000 bag/ha')
            ->assertSee('10.00 max bag')
            ->assertSee('3.00 allowed');
    }

    #[DataProvider('subsidyTypes')]
    public function test_program_creation_saves_the_selected_subsidy_type(string $subsidyType): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $group = $this->activeSeasonGroup();
        $supply = Supply::create([
            'supply_code' => 'SUP-'.$subsidyType,
            'supply_name' => 'Rice seed',
            'category' => 'Seeds',
            'unit' => 'bag',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('programs.store'), [
                'program_code' => 'PROGRAM-'.$subsidyType,
                'program_name' => 'Farmer support',
                'season_id' => $group->season_id,
                'subsidy_type' => $subsidyType,
                'start_date' => today()->toDateString(),
                'end_date' => today()->addDays(30)->toDateString(),
                'status' => 'draft',
                'items' => [
                    ['supply_id' => $supply->id, 'unit_value' => '15.50'],
                ],
                'season_group_ids' => [$group->id],
                'rates' => [
                    [
                        'supply_id' => $supply->id,
                        'season_group_id' => $group->id,
                        'quantity_per_hectare' => '3',
                        'maximum_quantity_per_farmer' => '10',
                    ],
                ],
            ])
            ->assertRedirect(route('programs.index'));

        $this->assertDatabaseHas('subsidy_programs', [
            'program_code' => 'PROGRAM-'.$subsidyType,
            'subsidy_type' => $subsidyType,
            'season_id' => $group->season_id,
        ]);
        $this->assertDatabaseHas('subsidy_items', [
            'supply_id' => $supply->id,
            'unit_value' => '15.50',
        ]);
        $this->assertDatabaseHas('program_supply_group_rates', [
            'supply_id' => $supply->id,
            'season_group_id' => $group->id,
            'quantity_per_hectare' => '3.0000',
            'maximum_quantity_per_farmer' => '10.00',
        ]);
    }

    public function test_program_creation_rejects_an_unknown_subsidy_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $group = $this->activeSeasonGroup();
        $supply = Supply::create([
            'supply_code' => 'SUP-INVALID',
            'supply_name' => 'Rice seed',
            'category' => 'Seeds',
            'unit' => 'bag',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->from(route('programs.create'))
            ->post(route('programs.store'), [
                'program_code' => 'PROGRAM-INVALID',
                'program_name' => 'Farmer support',
                'season_id' => $group->season_id,
                'subsidy_type' => 'unknown',
                'start_date' => today()->toDateString(),
                'end_date' => today()->addDays(30)->toDateString(),
                'status' => 'draft',
                'items' => [
                    ['supply_id' => $supply->id, 'unit_value' => '15.50'],
                ],
                'season_group_ids' => [$group->id],
                'rates' => [
                    [
                        'supply_id' => $supply->id,
                        'season_group_id' => $group->id,
                        'quantity_per_hectare' => '3',
                        'maximum_quantity_per_farmer' => '10',
                    ],
                ],
            ])
            ->assertRedirect(route('programs.create'))
            ->assertSessionHasErrors('subsidy_type');

        $this->assertDatabaseMissing('subsidy_programs', [
            'program_code' => 'PROGRAM-INVALID',
        ]);
    }

    public function test_program_saves_different_supply_rates_and_maximums_for_each_group(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $hybridGroup = $this->activeSeasonGroup();
        $inbredGroup = $hybridGroup->season->groups()->create(['seed_type' => 'inbred']);
        $supply = Supply::create([
            'supply_code' => 'SUP-GROUP-RATES',
            'supply_name' => 'Rice seed',
            'category' => 'Seeds',
            'unit' => 'bag',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('programs.store'), [
                'program_code' => 'PROGRAM-GROUP-RATES',
                'program_name' => 'Group-specific support',
                'season_id' => $hybridGroup->season_id,
                'subsidy_type' => 'in_kind',
                'start_date' => today()->toDateString(),
                'end_date' => today()->addDays(30)->toDateString(),
                'status' => 'active',
                'items' => [['supply_id' => $supply->id]],
                'season_group_ids' => [$hybridGroup->id, $inbredGroup->id],
                'rates' => [
                    [
                        'supply_id' => $supply->id,
                        'season_group_id' => $hybridGroup->id,
                        'quantity_per_hectare' => '3',
                        'maximum_quantity_per_farmer' => '10',
                    ],
                    [
                        'supply_id' => $supply->id,
                        'season_group_id' => $inbredGroup->id,
                        'quantity_per_hectare' => '2',
                        'maximum_quantity_per_farmer' => '8',
                    ],
                ],
            ])
            ->assertRedirect(route('programs.index'));

        $this->assertDatabaseHas('program_supply_group_rates', [
            'supply_id' => $supply->id,
            'season_group_id' => $hybridGroup->id,
            'quantity_per_hectare' => '3.0000',
            'maximum_quantity_per_farmer' => '10.00',
        ]);
        $this->assertDatabaseHas('program_supply_group_rates', [
            'supply_id' => $supply->id,
            'season_group_id' => $inbredGroup->id,
            'quantity_per_hectare' => '2.0000',
            'maximum_quantity_per_farmer' => '8.00',
        ]);
    }

    public function test_program_form_renders_a_rate_row_for_every_included_supply_and_group(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $hybridGroup = $this->activeSeasonGroup();
        $inbredGroup = $hybridGroup->season->groups()->create(['seed_type' => 'inbred']);
        $firstSupply = Supply::create([
            'supply_code' => 'SUP-RATE-ROW-1',
            'supply_name' => 'Rice seed',
            'category' => 'Seeds',
            'unit' => 'bag',
            'status' => 'active',
        ]);
        $secondSupply = Supply::create([
            'supply_code' => 'SUP-RATE-ROW-2',
            'supply_name' => 'Corn seed',
            'category' => 'Seeds',
            'unit' => 'bag',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->from(route('programs.create'))
            ->post(route('programs.store'), [
                'program_code' => 'PROGRAM-RATE-ROWS',
                'program_name' => 'Rate row generation',
                'season_id' => $hybridGroup->season_id,
                'subsidy_type' => 'in_kind',
                'start_date' => today()->toDateString(),
                'end_date' => today()->addDays(30)->toDateString(),
                'status' => 'draft',
                'items' => [
                    ['supply_id' => $firstSupply->id],
                    ['supply_id' => $secondSupply->id],
                ],
                'season_group_ids' => [$hybridGroup->id, $inbredGroup->id],
                'rates' => [[
                    'supply_id' => $firstSupply->id,
                    'season_group_id' => $hybridGroup->id,
                    'quantity_per_hectare' => '3',
                    'maximum_quantity_per_farmer' => '10',
                ]],
            ])
            ->assertRedirect(route('programs.create'))
            ->assertSessionHasErrors('rates');

        $response = $this->get(route('programs.create'))->assertOk();

        $this->assertSame(4, substr_count($response->getContent(), 'data-program-rate-row data-supply-id='));
        $response
            ->assertSee('name="rates[0][supply_id]" value="'.$firstSupply->id.'"', false)
            ->assertSee('name="rates[1][supply_id]" value="'.$firstSupply->id.'"', false)
            ->assertSee('name="rates[2][supply_id]" value="'.$secondSupply->id.'"', false)
            ->assertSee('name="rates[3][supply_id]" value="'.$secondSupply->id.'"', false)
            ->assertSee('name="rates[0][quantity_per_hectare]" data-rate-quantity min="0.0001" step="0.0001" value="3"', false);
    }

    public function test_program_requires_a_rate_for_each_included_supply_and_group(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $hybridGroup = $this->activeSeasonGroup();
        $inbredGroup = $hybridGroup->season->groups()->create(['seed_type' => 'inbred']);
        $supply = Supply::create([
            'supply_code' => 'SUP-MISSING-RATE',
            'supply_name' => 'Rice seed',
            'category' => 'Seeds',
            'unit' => 'bag',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->from(route('programs.create'))
            ->post(route('programs.store'), [
                'program_code' => 'PROGRAM-MISSING-RATE',
                'program_name' => 'Incomplete support',
                'season_id' => $hybridGroup->season_id,
                'subsidy_type' => 'in_kind',
                'start_date' => today()->toDateString(),
                'end_date' => today()->addDays(30)->toDateString(),
                'status' => 'draft',
                'items' => [['supply_id' => $supply->id]],
                'season_group_ids' => [$hybridGroup->id, $inbredGroup->id],
                'rates' => [[
                    'supply_id' => $supply->id,
                    'season_group_id' => $hybridGroup->id,
                    'quantity_per_hectare' => '3',
                    'maximum_quantity_per_farmer' => '10',
                ]],
            ])
            ->assertRedirect(route('programs.create'))
            ->assertSessionHasErrors('rates');

        $this->assertDatabaseMissing('subsidy_programs', [
            'program_code' => 'PROGRAM-MISSING-RATE',
        ]);
    }

    public function test_program_update_preserves_existing_program_and_supply_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $group = $this->activeSeasonGroup();
        $supply = Supply::create([
            'supply_code' => 'SUP-PRESERVE',
            'supply_name' => 'Rice seed',
            'category' => 'Seeds',
            'unit' => 'bag',
            'status' => 'active',
        ]);
        $program = SubsidyProgram::create([
            'program_code' => 'PROGRAM-EXISTING',
            'program_name' => 'Existing support',
            'status' => 'draft',
        ]);
        $program->supplies()->attach($supply, ['maximum_quantity' => 6, 'unit_value' => 12]);
        $program->seasonGroups()->attach($group, ['rate_per_hectare' => 200]);

        $this->actingAs($admin)
            ->put(route('programs.update', $program), [
                'program_code' => 'PROGRAM-EXISTING',
                'program_name' => 'Updated support',
                'season_id' => $group->season_id,
                'subsidy_type' => 'in_kind',
                'start_date' => today()->toDateString(),
                'end_date' => today()->addDays(30)->toDateString(),
                'status' => 'active',
                'items' => [['supply_id' => $supply->id]],
                'season_group_ids' => [$group->id],
                'rates' => [[
                    'supply_id' => $supply->id,
                    'season_group_id' => $group->id,
                    'quantity_per_hectare' => '4',
                    'maximum_quantity_per_farmer' => '12',
                ]],
            ])
            ->assertRedirect(route('programs.index'));

        $this->assertDatabaseHas('subsidy_programs', [
            'id' => $program->id,
            'program_code' => 'PROGRAM-EXISTING',
            'program_name' => 'Updated support',
        ]);
        $this->assertDatabaseHas('subsidy_items', [
            'subsidy_program_id' => $program->id,
            'supply_id' => $supply->id,
            'maximum_quantity' => 6,
            'unit_value' => 12,
        ]);
        $this->assertDatabaseHas('program_season_groups', [
            'subsidy_program_id' => $program->id,
            'season_group_id' => $group->id,
            'rate_per_hectare' => 200,
        ]);
        $this->assertDatabaseHas('program_supply_group_rates', [
            'subsidy_program_id' => $program->id,
            'supply_id' => $supply->id,
            'season_group_id' => $group->id,
            'quantity_per_hectare' => 4,
            'maximum_quantity_per_farmer' => 12,
        ]);
    }

    public function test_programs_without_a_selected_type_default_to_in_kind(): void
    {
        $program = SubsidyProgram::create([
            'program_code' => 'PROGRAM-DEFAULT',
            'program_name' => 'Existing supply program',
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('subsidy_programs', [
            'id' => $program->id,
            'subsidy_type' => 'in_kind',
        ]);
    }

    public static function subsidyTypes(): array
    {
        return [
            'in-kind' => ['in_kind'],
            'hybrid' => ['hybrid'],
            'embedded' => ['embedded'],
        ];
    }

    private function activeSeasonGroup(): SeasonGroup
    {
        $season = Season::create([
            'name' => 'Wet 2026',
            'status' => 'active',
        ]);

        return $season->groups()->create(['seed_type' => 'hybrid']);
    }
}
