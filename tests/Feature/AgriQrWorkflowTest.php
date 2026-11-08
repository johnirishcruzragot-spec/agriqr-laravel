<?php

namespace Tests\Feature;

use App\Models\Distribution;
use App\Models\Farmer;
use App\Models\FarmerSeasonGroupAssignment;
use App\Models\Inventory;
use App\Models\ProgramSupplyGroupRate;
use App\Models\Season;
use App\Models\SeasonGroup;
use App\Models\SubsidyProgram;
use App\Models\Supply;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AgriQrWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_technician_can_sign_in_and_open_the_dashboard(): void
    {
        $user = User::factory()->create([
            'username' => 'field-staff',
            'password' => 'password',
            'role' => 'technician',
        ]);

        $this->post(route('login.store'), [
            'login' => $user->username,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_sign_in_accepts_email_and_rejects_inactive_accounts(): void
    {
        $activeUser = User::factory()->create([
            'email' => 'field.staff@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->post(route('login.store'), [
            'login' => $activeUser->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->post(route('logout'));

        $inactiveUser = User::factory()->create([
            'username' => 'inactive-staff',
            'password' => 'password',
            'is_active' => false,
        ]);

        $this->from(route('login'))
            ->post(route('login.store'), [
                'login' => $inactiveUser->username,
                'password' => 'password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('login');
    }

    public function test_technician_can_view_but_not_manage_subsidy_programs(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'technician']))
            ->get(route('programs.index'))
            ->assertOk();

        $this->get(route('programs.create'))->assertForbidden();
    }

    public function test_admin_can_edit_another_admin_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $anotherAdmin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('users.edit', $anotherAdmin))
            ->assertOk();
    }

    public function test_administrators_can_open_each_core_workspace(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        foreach ([
            'dashboard',
            'farmers.index',
            'farmers.create',
            'supplies.index',
            'supplies.create',
            'programs.index',
            'programs.create',
            'seasons.index',
            'distributions.index',
            'distributions.create',
            'users.index',
            'users.create',
            'activity.index',
        ] as $routeName) {
            $this->get(route($routeName))->assertOk();
        }
    }

    public function test_farmer_list_opens_registration_dialog_and_reopens_it_after_validation_errors(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get(route('farmers.index'))
            ->assertOk()
            ->assertSee('data-open-dialog="farmer-register-dialog"', false)
            ->assertSee('id="farmer-register-dialog"', false)
            ->assertSee('Register a farmer')
            ->assertSee('name="first_name"', false);

        $this->get(route('farmers.index'))
            ->assertOk()
            ->assertSee('farmer-action-grid', false)
            ->assertSee('Import CSV')
            ->assertSee('Register a farmer')
            ->assertSee('data-registration-step="1"', false)
            ->assertSee('data-registration-step="2" hidden', false)
            ->assertSee('data-registration-next', false)
            ->assertSee('data-registration-submit hidden', false);

        $this->followingRedirects()
            ->from(route('farmers.index'))
            ->post(route('farmers.store'), ['first_name' => 'Maya'])
            ->assertOk()
            ->assertSee('data-open-on-load', false)
            ->assertSee('value="Maya"', false)
            ->assertSee('Please correct the following');
    }

    public function test_standalone_farmer_registration_is_a_two_step_card(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get(route('farmers.create'))
            ->assertOk()
            ->assertSee('farmer-registration-page', false)
            ->assertSee('data-registration-wizard-container', false)
            ->assertSee('Step 1 of 2: Personal information')
            ->assertSee('data-registration-step="1"', false)
            ->assertSee('data-registration-step="2" hidden', false)
            ->assertSee('data-registration-next', false)
            ->assertSee('data-registration-back hidden', false)
            ->assertSee('data-registration-submit hidden', false);

        $this->followingRedirects()
            ->from(route('farmers.create'))
            ->post(route('farmers.store'), ['first_name' => 'Maya'])
            ->assertOk()
            ->assertSee('data-open-step="2"', false)
            ->assertSee('value="Maya"', false);
    }

    public function test_farmer_registration_reopens_on_farm_step_after_farm_validation_errors(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->followingRedirects()
            ->from(route('farmers.index'))
            ->post(route('farmers.store'), [
                'first_name' => 'Maya',
                'last_name' => 'Reyes',
                'address' => '12 Farm Road',
                'barangay' => 'San Roque',
                'municipality' => 'Baliuag',
                'province' => 'Bulacan',
                'status' => 'active',
            ])
            ->assertOk()
            ->assertSee('data-open-step="2"', false)
            ->assertSee('data-open-on-load', false)
            ->assertSee('value="Maya"', false);
    }

    public function test_farmer_profile_and_verified_rsbsa_record_can_be_created(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        SystemSetting::create(['key' => 'barangays', 'value' => ['San Roque']]);

        $response = $this->actingAs($user)->post(route('farmers.store'), [
            'first_name' => 'Maya',
            'last_name' => 'Reyes',
            'address' => '12 Farm Road',
            'barangay' => 'San Roque',
            'municipality' => 'Baliuag',
            'province' => 'Bulacan',
            'status' => 'active',
            'rsbsa_number' => 'RSBSA-1001',
            'registration_status' => 'registered',
            'verification_status' => 'verified',
            'farm_size' => '1.50',
            'farm_size_unit' => 'hectare',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $farmer = Farmer::with('rsbsaRecord')->firstOrFail();
        $response->assertRedirect(route('farmers.show', $farmer));
        $this->assertMatchesRegularExpression('/^F[A-Z0-9]{10}$/', $farmer->farmer_id);
        $this->assertSame('verified', $farmer->rsbsaRecord->verification_status);
    }

    public function test_duplicate_rsbsa_number_is_rejected_without_a_server_error(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $existingFarmer = $this->eligibleFarmer();

        $this->actingAs($user)
            ->from(route('farmers.create'))
            ->post(route('farmers.store'), [
                'first_name' => 'Nina',
                'last_name' => 'Santos',
                'address' => 'Farm Road',
                'barangay' => 'San Roque',
                'municipality' => 'Baliuag',
                'province' => 'Bulacan',
                'status' => 'active',
                'rsbsa_number' => $existingFarmer->rsbsaRecord->rsbsa_number,
                'registration_status' => 'registered',
                'verification_status' => 'verified',
                'farm_size_unit' => 'hectare',
            ])
            ->assertRedirect(route('farmers.create'))
            ->assertSessionHasErrors('rsbsa_number');

        $this->assertDatabaseCount('farmers', 1);
    }

    public function test_distribution_records_items_and_decrements_inventory_atomically(): void
    {
        $user = User::factory()->create(['role' => 'technician']);
        $farmer = $this->eligibleFarmer();
        [$program, $supply] = $this->programWithSupply(12, 4, 10, 4);

        $response = $this->actingAs($user)->post(route('distributions.store'), [
            'farmer_code' => $farmer->farmer_id,
            'subsidy_program_id' => $program->id,
            'farmer_season_group_assignment_id' => $farmer->currentSeasonGroupAssignments()->value('id'),
            'face_verified' => '1',
            'items' => [[
                'supply_id' => $supply->id,
                'quantity' => 3,
                'reduction_reason' => 'Farmer requested a smaller amount.',
            ]],
            'remarks' => 'Seed distribution',
        ]);

        $distribution = Distribution::firstOrFail();
        $response->assertRedirect(route('distributions.show', $distribution));
        $this->assertSame(9.0, (float) Inventory::where('supply_id', $supply->id)->value('quantity'));
        $this->assertDatabaseHas('distribution_items', [
            'distribution_id' => $distribution->id,
            'supply_id' => $supply->id,
            'quantity' => 3,
            'quantity_rate_per_hectare' => '4.0000',
            'quantity_cap' => '4.00',
            'maximum_quantity_per_farmer' => '4.00',
            'computed_quantity' => '4.00',
            'reduction_reason' => 'Farmer requested a smaller amount.',
            'unit_value' => 10,
            'line_value' => 30,
        ]);
        $this->assertSame($user->id, $distribution->face_verified_by);
        $this->assertNotNull($distribution->face_verified_at);
        $this->assertSame('30.00', $distribution->assistance_value);
        $this->assertNull($distribution->value_cap);
        $this->assertDatabaseHas('inventory_movements', [
            'supply_id' => $supply->id,
            'movement_type' => 'OUT',
            'quantity' => 3,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'DISTRIBUTION',
            'record_id' => $distribution->id,
        ]);
    }

    public function test_distribution_rejects_quantity_above_the_group_limit_without_changing_stock(): void
    {
        $user = User::factory()->create(['role' => 'technician']);
        $farmer = $this->eligibleFarmer();
        [$program, $supply] = $this->programWithSupply(12, 2);

        $this->actingAs($user)
            ->from(route('distributions.create'))
            ->post(route('distributions.store'), [
                'farmer_code' => $farmer->farmer_id,
                'subsidy_program_id' => $program->id,
                'farmer_season_group_assignment_id' => $farmer->currentSeasonGroupAssignments()->value('id'),
                'face_verified' => '1',
                'items' => [['supply_id' => $supply->id, 'quantity' => 3]],
            ])
            ->assertRedirect(route('distributions.create'))
            ->assertSessionHasErrors([
                'items.0.quantity' => 'The requested quantity for Rice seed exceeds the calculated maximum of 2.00 bag.',
            ]);

        $this->assertSame(12.0, (float) Inventory::where('supply_id', $supply->id)->value('quantity'));
        $this->assertDatabaseCount('distributions', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_distribution_blocks_a_second_issue_of_the_supply_to_the_same_farmer_in_a_season(): void
    {
        $user = User::factory()->create(['role' => 'technician']);
        $farmer = $this->eligibleFarmer();
        [$program, $supply] = $this->programWithSupply(12, 10, 10, 6);
        $assignmentId = $farmer->currentSeasonGroupAssignments()->value('id');
        $secondProgram = SubsidyProgram::create([
            'program_code' => 'SUB-TEST-SECOND',
            'program_name' => 'Second seed support',
            'season_id' => $program->season_id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
            'status' => 'active',
        ]);
        $secondProgram->supplies()->attach($supply, [
            'maximum_quantity' => 10,
            'unit_value' => 10,
        ]);
        $secondProgram->seasonGroups()->attach(SeasonGroup::query()->sole(), ['rate_per_hectare' => null]);
        ProgramSupplyGroupRate::create([
            'subsidy_program_id' => $secondProgram->id,
            'supply_id' => $supply->id,
            'season_group_id' => SeasonGroup::query()->sole()->id,
            'quantity_per_hectare' => 6,
            'maximum_quantity_per_farmer' => 10,
        ]);
        $payload = [
            'farmer_code' => $farmer->farmer_id,
            'subsidy_program_id' => $program->id,
            'farmer_season_group_assignment_id' => $assignmentId,
            'face_verified' => '1',
        ];

        $this->actingAs($user)
            ->post(route('distributions.store'), [
                ...$payload,
                'items' => [['supply_id' => $supply->id, 'quantity' => 6]],
            ])
            ->assertRedirect();

        $this->from(route('distributions.create'))
            ->post(route('distributions.store'), [
                ...$payload,
                'subsidy_program_id' => $secondProgram->id,
                'items' => [['supply_id' => $supply->id, 'quantity' => 5]],
            ])
            ->assertRedirect(route('distributions.create'))
            ->assertSessionHasErrors([
                'items.0.supply_id' => 'Rice seed has already been issued to this farmer for this season.',
            ]);

        $this->assertDatabaseCount('distributions', 1);
        $this->assertSame(6.0, (float) Inventory::where('supply_id', $supply->id)->value('quantity'));
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_distribution_limits_quantity_by_farm_size_and_group_rate(): void
    {
        $user = User::factory()->create(['role' => 'technician']);
        $farmer = $this->eligibleFarmer();
        $farmer->rsbsaRecord()->update(['farm_size' => 0.5]);
        [$program, $supply] = $this->programWithSupply(12, 10, 10, 6);

        $this->actingAs($user)
            ->from(route('distributions.create'))
            ->post(route('distributions.store'), [
                'farmer_code' => $farmer->farmer_id,
                'subsidy_program_id' => $program->id,
                'farmer_season_group_assignment_id' => $farmer->currentSeasonGroupAssignments()->value('id'),
                'face_verified' => '1',
                'items' => [['supply_id' => $supply->id, 'quantity' => 4]],
            ])
            ->assertRedirect(route('distributions.create'))
            ->assertSessionHasErrors([
                'items.0.quantity' => 'The requested quantity for Rice seed exceeds the calculated maximum of 3.00 bag.',
            ]);

        $this->assertDatabaseCount('distributions', 0);
        $this->assertSame(12.0, (float) Inventory::where('supply_id', $supply->id)->value('quantity'));
    }

    public function test_distribution_rejects_a_program_that_does_not_cover_the_farmer_group(): void
    {
        $user = User::factory()->create(['role' => 'technician']);
        $farmer = $this->eligibleFarmer();
        [$program, $supply] = $this->programWithSupply(12, 10);
        $inbredGroup = SeasonGroup::create([
            'season_id' => Season::query()->sole()->id,
            'seed_type' => 'inbred',
        ]);
        $program->seasonGroups()->sync([$inbredGroup->id => ['rate_per_hectare' => null]]);

        $this->actingAs($user)
            ->from(route('distributions.create'))
            ->post(route('distributions.store'), [
                'farmer_code' => $farmer->farmer_id,
                'subsidy_program_id' => $program->id,
                'farmer_season_group_assignment_id' => $farmer->currentSeasonGroupAssignments()->value('id'),
                'face_verified' => '1',
                'items' => [['supply_id' => $supply->id, 'quantity' => 1]],
            ])
            ->assertRedirect(route('distributions.create'))
            ->assertSessionHasErrors('subsidy_program_id');

        $this->assertDatabaseCount('distributions', 0);
        $this->assertSame(12.0, (float) Inventory::where('supply_id', $supply->id)->value('quantity'));
    }

    public function test_distribution_requires_farm_size_for_supply_quantity_limits(): void
    {
        $user = User::factory()->create(['role' => 'technician']);
        $farmer = $this->eligibleFarmer();
        $farmer->rsbsaRecord()->update(['farm_size' => null]);
        [$program, $supply] = $this->programWithSupply(12, 10);

        $this->actingAs($user)
            ->from(route('distributions.create'))
            ->post(route('distributions.store'), [
                'farmer_code' => $farmer->farmer_id,
                'subsidy_program_id' => $program->id,
                'farmer_season_group_assignment_id' => $farmer->currentSeasonGroupAssignments()->value('id'),
                'face_verified' => '1',
                'items' => [['supply_id' => $supply->id, 'quantity' => 1]],
            ])
            ->assertRedirect(route('distributions.create'))
            ->assertSessionHasErrors([
                'farmer_code' => 'A valid RSBSA farm size is required to calculate supply quantity limits.',
            ]);

        $this->assertDatabaseCount('distributions', 0);
        $this->assertSame(12.0, (float) Inventory::where('supply_id', $supply->id)->value('quantity'));
    }

    public function test_distribution_requires_face_verification_before_creating_a_record(): void
    {
        $user = User::factory()->create(['role' => 'technician']);
        $farmer = $this->eligibleFarmer();
        [$program, $supply] = $this->programWithSupply(12, 4, 10, 4);

        $this->actingAs($user)
            ->from(route('distributions.create'))
            ->post(route('distributions.store'), [
                'farmer_code' => $farmer->farmer_id,
                'subsidy_program_id' => $program->id,
                'farmer_season_group_assignment_id' => $farmer->currentSeasonGroupAssignments()->value('id'),
                'items' => [['supply_id' => $supply->id, 'quantity' => 4]],
            ])
            ->assertRedirect(route('distributions.create'))
            ->assertSessionHasErrors('face_verified');

        $this->assertDatabaseCount('distributions', 0);
        $this->assertSame(12.0, (float) Inventory::where('supply_id', $supply->id)->value('quantity'));
    }

    public function test_distribution_requires_a_stored_photo_for_face_verification(): void
    {
        $user = User::factory()->create(['role' => 'technician']);
        $farmer = $this->eligibleFarmer();
        $farmer->update(['photo_path' => null]);
        [$program, $supply] = $this->programWithSupply(12, 4, 10, 4);

        $this->actingAs($user)
            ->from(route('distributions.create'))
            ->post(route('distributions.store'), [
                'farmer_code' => $farmer->farmer_id,
                'subsidy_program_id' => $program->id,
                'farmer_season_group_assignment_id' => $farmer->currentSeasonGroupAssignments()->value('id'),
                'face_verified' => '1',
                'items' => [['supply_id' => $supply->id, 'quantity' => 4]],
            ])
            ->assertRedirect(route('distributions.create'))
            ->assertSessionHasErrors([
                'face_verified' => 'A current farmer photo is required before face verification can be completed.',
            ]);

        $this->assertDatabaseCount('distributions', 0);
        $this->assertSame(12.0, (float) Inventory::where('supply_id', $supply->id)->value('quantity'));
    }

    public function test_distribution_requires_rsbsa_verification_when_the_program_requires_it(): void
    {
        $user = User::factory()->create(['role' => 'technician']);
        $farmer = $this->eligibleFarmer();
        $farmer->rsbsaRecord()->update(['verification_status' => 'pending']);
        [$program, $supply] = $this->programWithSupply(12, 4, 10, 4);

        $this->actingAs($user)
            ->from(route('distributions.create'))
            ->post(route('distributions.store'), [
                'farmer_code' => $farmer->farmer_id,
                'subsidy_program_id' => $program->id,
                'farmer_season_group_assignment_id' => $farmer->currentSeasonGroupAssignments()->value('id'),
                'face_verified' => '1',
                'items' => [['supply_id' => $supply->id, 'quantity' => 4]],
            ])
            ->assertRedirect(route('distributions.create'))
            ->assertSessionHasErrors([
                'farmer_code' => 'This program requires a registered and verified RSBSA record.',
            ]);

        $this->assertDatabaseCount('distributions', 0);
        $this->assertSame(12.0, (float) Inventory::where('supply_id', $supply->id)->value('quantity'));
    }

    public function test_distribution_blocks_deceased_farmers(): void
    {
        $user = User::factory()->create(['role' => 'technician']);
        $farmer = $this->eligibleFarmer();
        $farmer->update(['status' => 'deceased', 'deceased_at' => today()]);
        [$program, $supply] = $this->programWithSupply(12, 4, 10, 4);

        $this->actingAs($user)
            ->from(route('distributions.create'))
            ->post(route('distributions.store'), [
                'farmer_code' => $farmer->farmer_id,
                'subsidy_program_id' => $program->id,
                'farmer_season_group_assignment_id' => $farmer->currentSeasonGroupAssignments()->value('id'),
                'face_verified' => '1',
                'items' => [['supply_id' => $supply->id, 'quantity' => 4]],
            ])
            ->assertRedirect(route('distributions.create'))
            ->assertSessionHasErrors([
                'farmer_code' => 'Distribution is blocked because this farmer is marked deceased.',
            ]);

        $this->assertDatabaseCount('distributions', 0);
        $this->assertSame(12.0, (float) Inventory::where('supply_id', $supply->id)->value('quantity'));
    }

    public function test_distribution_requires_a_reason_for_a_reduced_quantity(): void
    {
        $user = User::factory()->create(['role' => 'technician']);
        $farmer = $this->eligibleFarmer();
        [$program, $supply] = $this->programWithSupply(12, 4, 10, 4);

        $this->actingAs($user)
            ->from(route('distributions.create'))
            ->post(route('distributions.store'), [
                'farmer_code' => $farmer->farmer_id,
                'subsidy_program_id' => $program->id,
                'farmer_season_group_assignment_id' => $farmer->currentSeasonGroupAssignments()->value('id'),
                'face_verified' => '1',
                'items' => [['supply_id' => $supply->id, 'quantity' => 3]],
            ])
            ->assertRedirect(route('distributions.create'))
            ->assertSessionHasErrors([
                'items.0.reduction_reason' => 'A reason is required when the issued quantity is reduced.',
            ]);

        $this->assertDatabaseCount('distributions', 0);
        $this->assertSame(12.0, (float) Inventory::where('supply_id', $supply->id)->value('quantity'));
    }

    public function test_distribution_reports_stock_shortage_without_mutating_inventory(): void
    {
        $user = User::factory()->create(['role' => 'technician']);
        $farmer = $this->eligibleFarmer();
        [$program, $supply] = $this->programWithSupply(2, 4, 10, 4);

        $this->actingAs($user)
            ->from(route('distributions.create'))
            ->post(route('distributions.store'), [
                'farmer_code' => $farmer->farmer_id,
                'subsidy_program_id' => $program->id,
                'farmer_season_group_assignment_id' => $farmer->currentSeasonGroupAssignments()->value('id'),
                'face_verified' => '1',
                'items' => [['supply_id' => $supply->id, 'quantity' => 4]],
            ])
            ->assertRedirect(route('distributions.create'))
            ->assertSessionHasErrors([
                'items' => 'Insufficient stock for Rice seed: 2.00 available; shortage 2.00. Reduce the quantity and provide a reason.',
            ]);

        $this->assertDatabaseCount('distributions', 0);
        $this->assertSame(2.0, (float) Inventory::where('supply_id', $supply->id)->value('quantity'));
    }

    public function test_technicians_can_process_distributions(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        $farmer = $this->eligibleFarmer();
        [$program, $supply] = $this->programWithSupply(12, 2, 10, 2);

        $this->actingAs($technician)
            ->get(route('distributions.create', ['farmer' => $farmer->farmer_id]))
            ->assertOk()
            ->assertSee('name="face_verified"', false)
            ->assertSee('Confirm distribution');

        $this->post(route('distributions.store'), [
            'farmer_code' => $farmer->farmer_id,
            'subsidy_program_id' => $program->id,
            'farmer_season_group_assignment_id' => $farmer->currentSeasonGroupAssignments()->value('id'),
            'face_verified' => '1',
            'items' => [['supply_id' => $supply->id, 'quantity' => 2]],
        ])->assertRedirect();

        $this->assertDatabaseHas('distributions', [
            'farmer_id' => $farmer->id,
            'processed_by' => $technician->id,
            'face_verified_by' => $technician->id,
        ]);
    }

    private function eligibleFarmer(): Farmer
    {
        Storage::fake('local');
        $photoPath = 'farmer-photos/FTEST001.jpg';
        Storage::disk('local')->put($photoPath, 'verified-photo-fixture');

        $farmer = Farmer::create([
            'farmer_id' => 'FTEST001',
            'first_name' => 'Maya',
            'last_name' => 'Reyes',
            'address' => '12 Farm Road',
            'barangay' => 'San Roque',
            'municipality' => 'Baliuag',
            'province' => 'Bulacan',
            'status' => 'active',
            'photo_path' => $photoPath,
        ]);

        $farmer->rsbsaRecord()->create([
            'rsbsa_number' => 'RSBSA-TEST-1',
            'registration_status' => 'registered',
            'verification_status' => 'verified',
            'farm_size' => 1,
            'farm_size_unit' => 'hectare',
        ]);
        $season = Season::create(['name' => 'Wet 2026', 'status' => 'active']);
        $group = $season->groups()->create(['seed_type' => 'hybrid']);
        FarmerSeasonGroupAssignment::create([
            'farmer_id' => $farmer->id,
            'season_group_id' => $group->id,
            'started_at' => now(),
        ]);

        return $farmer;
    }

    /**
     * @return array{SubsidyProgram, Supply}
     */
    private function programWithSupply(float $stock, float $maximum, float $unitValue = 10, float $rate = 100): array
    {
        $supply = Supply::create([
            'supply_code' => 'SEED-TEST',
            'supply_name' => 'Rice seed',
            'category' => 'Seeds',
            'unit' => 'bag',
            'minimum_stock' => 2,
            'status' => 'active',
        ]);
        Inventory::create(['supply_id' => $supply->id, 'quantity' => $stock]);

        $program = SubsidyProgram::create([
            'program_code' => 'SUB-TEST',
            'program_name' => 'Seed support',
            'season_id' => SeasonGroup::query()->sole()->season_id,
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
            'status' => 'active',
        ]);
        $program->supplies()->attach($supply, [
            'maximum_quantity' => $maximum,
            'unit_value' => $unitValue,
        ]);
        $program->seasonGroups()->attach(
            SeasonGroup::query()->sole(),
            ['rate_per_hectare' => null],
        );
        ProgramSupplyGroupRate::create([
            'subsidy_program_id' => $program->id,
            'supply_id' => $supply->id,
            'season_group_id' => SeasonGroup::query()->sole()->id,
            'quantity_per_hectare' => $rate,
            'maximum_quantity_per_farmer' => $maximum,
        ]);

        return [$program, $supply];
    }
}
