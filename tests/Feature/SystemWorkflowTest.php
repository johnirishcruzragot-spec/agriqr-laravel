<?php

namespace Tests\Feature;

use App\Models\Distribution;
use App\Models\DistributionItem;
use App\Models\Farmer;
use App\Models\FarmerSeasonGroupAssignment;
use App\Models\Inventory;
use App\Models\Season;
use App\Models\SubsidyProgram;
use App\Models\Supply;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_setup_and_technician_distribution_complete_the_system_workflow(): void
    {
        Storage::fake('local');
        SystemSetting::create(['key' => 'barangays', 'value' => ['San Roque']]);
        $admin = User::factory()->create([
            'username' => 'system-admin',
            'password' => 'StrongPassword2026!',
            'role' => 'admin',
        ]);
        $technician = User::factory()->create([
            'username' => 'system-technician',
            'password' => 'StrongPassword2026!',
            'role' => 'technician',
        ]);

        $this->post(route('login.store'), [
            'login' => $admin->username,
            'password' => 'StrongPassword2026!',
        ])->assertRedirect(route('dashboard'));

        $this->post(route('seasons.store'), ['name' => 'System Test Wet 2026'])
            ->assertRedirect(route('seasons.index'));
        $season = Season::query()->where('name', 'System Test Wet 2026')->sole();
        $hybridGroup = $season->groups()->where('seed_type', 'hybrid')->sole();

        $this->post(route('supplies.store'), [
            'supply_code' => 'SYSTEM-SEED-1',
            'supply_name' => 'System test rice seed',
            'category' => 'Seeds',
            'unit' => 'bag',
            'minimum_stock' => '2',
            'status' => 'active',
        ])->assertRedirect(route('supplies.index'));
        $supply = Supply::query()->where('supply_code', 'SYSTEM-SEED-1')->sole();

        $this->post(route('supplies.stock-in', $supply), [
            'quantity' => '20',
            'reference_no' => 'SYSTEM-DELIVERY-1',
            'supplier' => 'Test supplier',
        ])->assertRedirect(route('supplies.index'));

        $this->post(route('farmers.store'), [
            'first_name' => 'System',
            'last_name' => 'Farmer',
            'address' => '1 Test Farm Road',
            'barangay' => 'San Roque',
            'municipality' => 'Baliuag',
            'province' => 'Bulacan',
            'status' => 'active',
            'registration_status' => 'registered',
            'verification_status' => 'verified',
            'rsbsa_number' => 'SYSTEM-RSBSA-1',
            'farm_size' => '2',
            'farm_size_unit' => 'hectare',
            'season_groups' => [$season->id => $hybridGroup->id],
        ])->assertRedirect();
        $farmer = Farmer::query()->where('first_name', 'System')->sole();

        $this->post(route('farmers.photo.update', $farmer), [
            'photo' => UploadedFile::fake()->createWithContent(
                'farmer.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lNEAAAAASUVORK5CYII='),
            ),
        ])->assertRedirect(route('farmers.show', $farmer));
        $farmer->refresh();
        Storage::disk('local')->assertExists($farmer->photo_path);

        $this->post(route('programs.store'), [
            'program_code' => 'SYSTEM-PROGRAM-1',
            'program_name' => 'System test seed support',
            'season_id' => $season->id,
            'subsidy_type' => 'in_kind',
            'requires_verified_rsbsa' => '1',
            'start_date' => today()->toDateString(),
            'end_date' => today()->addDays(30)->toDateString(),
            'status' => 'active',
            'items' => [['supply_id' => $supply->id, 'unit_value' => '0']],
            'season_group_ids' => [$hybridGroup->id],
            'rates' => [[
                'supply_id' => $supply->id,
                'season_group_id' => $hybridGroup->id,
                'quantity_per_hectare' => '3',
                'maximum_quantity_per_farmer' => '10',
            ]],
        ])->assertRedirect(route('programs.index'));
        $program = SubsidyProgram::query()->where('program_code', 'SYSTEM-PROGRAM-1')->sole();
        $assignment = FarmerSeasonGroupAssignment::query()->where('farmer_id', $farmer->id)->sole();

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login.store'), [
            'login' => $technician->username,
            'password' => 'StrongPassword2026!',
        ])->assertRedirect(route('dashboard'));
        $this->get(route('distributions.create', ['farmer' => $farmer->farmer_id]))
            ->assertOk()
            ->assertSee('6.00 allowed');

        $distributionResponse = $this->post(route('distributions.store'), [
            'farmer_code' => $farmer->farmer_id,
            'subsidy_program_id' => $program->id,
            'farmer_season_group_assignment_id' => $assignment->id,
            'face_verified' => '1',
            'items' => [['supply_id' => $supply->id, 'quantity' => '6']],
        ]);
        $distribution = Distribution::query()->sole();

        $distributionResponse->assertRedirect(route('distributions.show', $distribution));
        $this->assertDatabaseHas('distribution_items', [
            'distribution_id' => $distribution->id,
            'supply_id' => $supply->id,
            'quantity' => 6,
            'quantity_rate_per_hectare' => 3,
            'maximum_quantity_per_farmer' => 10,
            'computed_quantity' => 6,
            'reduction_reason' => null,
        ]);
        $this->assertDatabaseHas('distributions', [
            'id' => $distribution->id,
            'farmer_id' => $farmer->id,
            'subsidy_program_id' => $program->id,
            'season_id' => $season->id,
            'season_group_id' => $hybridGroup->id,
            'processed_by' => $technician->id,
            'face_verified_by' => $technician->id,
            'transaction_status' => 'completed',
        ]);
        $this->assertSame(14.0, (float) Inventory::query()->where('supply_id', $supply->id)->value('quantity'));
        $this->assertDatabaseHas('inventory_movements', [
            'supply_id' => $supply->id,
            'movement_type' => 'OUT',
            'quantity' => 6,
            'reference_type' => 'DISTRIBUTION',
            'reference_id' => $distribution->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $technician->id,
            'module' => 'DISTRIBUTION',
            'record_id' => $distribution->id,
            'action' => 'CREATE',
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'supply_id' => $supply->id,
            'movement_type' => 'IN',
            'quantity' => 20,
            'reference_type' => 'STOCK_IN',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'module' => 'PROGRAM',
            'record_id' => $program->id,
            'action' => 'CREATE',
        ]);

        $this->from(route('distributions.create', ['farmer' => $farmer->farmer_id]))
            ->post(route('distributions.store'), [
                'farmer_code' => $farmer->farmer_id,
                'subsidy_program_id' => $program->id,
                'farmer_season_group_assignment_id' => $assignment->id,
                'face_verified' => '1',
                'items' => [['supply_id' => $supply->id, 'quantity' => '1']],
            ])
            ->assertRedirect(route('distributions.create', ['farmer' => $farmer->farmer_id]))
            ->assertSessionHasErrors([
                'items.0.supply_id' => 'System test rice seed has already been issued to this farmer for this season.',
            ]);

        $this->assertSame(1, Distribution::query()->count());
        $this->assertSame(1, DistributionItem::query()->count());
        $this->assertSame(14.0, (float) Inventory::query()->where('supply_id', $supply->id)->value('quantity'));
    }
}
