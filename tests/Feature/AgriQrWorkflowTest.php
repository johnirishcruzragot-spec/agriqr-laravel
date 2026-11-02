<?php

namespace Tests\Feature;

use App\Models\Distribution;
use App\Models\Farmer;
use App\Models\Inventory;
use App\Models\SubsidyProgram;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgriQrWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_sign_in_and_open_the_dashboard(): void
    {
        $user = User::factory()->create([
            'username' => 'field-staff',
            'password' => 'password',
            'role' => 'staff',
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

    public function test_staff_cannot_manage_subsidy_programs(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->get(route('programs.index'))
            ->assertForbidden();
    }

    public function test_admin_cannot_edit_a_super_administrator_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->get(route('users.edit', $superAdmin))
            ->assertForbidden();
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
            'distributions.index',
            'distributions.create',
            'users.index',
            'users.create',
            'activity.index',
        ] as $routeName) {
            $this->get(route($routeName))->assertOk();
        }
    }

    public function test_farmer_profile_and_verified_rsbsa_record_can_be_created(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

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
        $user = User::factory()->create(['role' => 'staff']);
        $farmer = $this->eligibleFarmer();
        [$program, $supply] = $this->programWithSupply(12, 4);

        $response = $this->actingAs($user)->post(route('distributions.store'), [
            'farmer_code' => $farmer->farmer_id,
            'subsidy_program_id' => $program->id,
            'items' => [['supply_id' => $supply->id, 'quantity' => 3]],
            'remarks' => 'Seed distribution',
        ]);

        $distribution = Distribution::firstOrFail();
        $response->assertRedirect(route('distributions.show', $distribution));
        $this->assertSame(9.0, (float) Inventory::where('supply_id', $supply->id)->value('quantity'));
        $this->assertDatabaseHas('distribution_items', [
            'distribution_id' => $distribution->id,
            'supply_id' => $supply->id,
            'quantity' => 3,
        ]);
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

    public function test_distribution_rejects_program_limit_without_changing_stock(): void
    {
        $user = User::factory()->create(['role' => 'staff']);
        $farmer = $this->eligibleFarmer();
        [$program, $supply] = $this->programWithSupply(12, 2);

        $this->actingAs($user)
            ->from(route('distributions.create'))
            ->post(route('distributions.store'), [
                'farmer_code' => $farmer->farmer_id,
                'subsidy_program_id' => $program->id,
                'items' => [['supply_id' => $supply->id, 'quantity' => 3]],
            ])
            ->assertRedirect(route('distributions.create'))
            ->assertSessionHasErrors('items');

        $this->assertSame(12.0, (float) Inventory::where('supply_id', $supply->id)->value('quantity'));
        $this->assertDatabaseCount('distributions', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function eligibleFarmer(): Farmer
    {
        $farmer = Farmer::create([
            'farmer_id' => 'FTEST001',
            'first_name' => 'Maya',
            'last_name' => 'Reyes',
            'address' => '12 Farm Road',
            'barangay' => 'San Roque',
            'municipality' => 'Baliuag',
            'province' => 'Bulacan',
            'status' => 'active',
        ]);

        $farmer->rsbsaRecord()->create([
            'rsbsa_number' => 'RSBSA-TEST-1',
            'registration_status' => 'registered',
            'verification_status' => 'verified',
        ]);

        return $farmer;
    }

    /**
     * @return array{SubsidyProgram, Supply}
     */
    private function programWithSupply(float $stock, float $maximum): array
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
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
            'status' => 'active',
        ]);
        $program->supplies()->attach($supply, ['maximum_quantity' => $maximum]);

        return [$program, $supply];
    }
}
