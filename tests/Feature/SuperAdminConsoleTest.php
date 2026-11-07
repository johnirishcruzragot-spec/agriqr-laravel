<?php

namespace Tests\Feature;

use App\Models\Distribution;
use App\Models\Farmer;
use App\Models\FarmerSeasonGroupAssignment;
use App\Models\Inventory;
use App\Models\ProgramSupplyGroupRate;
use App\Models\RsbsaRecord;
use App\Models\Season;
use App\Models\SubsidyProgram;
use App\Models\Supply;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SuperAdminConsoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_has_full_reports_and_system_configuration_while_technician_reports_are_limited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'technician']);

        $this->actingAs($admin)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Reports and analytics');

        $this->get(route('settings.index'))
            ->assertOk()
            ->assertSee('System configuration');

        $this->actingAs($technician)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertDontSee('Staff activity and audit log');

        $this->get(route('reports.index', ['report' => 'failed-verifications']))
            ->assertSessionHasErrors('report');

        $this->get(route('settings.index'))->assertForbidden();
    }

    public function test_system_admin_receives_admin_report_access(): void
    {
        $systemAdmin = User::factory()->create(['role' => 'system_admin']);

        $this->actingAs($systemAdmin)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Staff activity and audit log')
            ->assertSee('Failed QR and eligibility checks');
    }

    public function test_system_admin_sees_links_for_all_available_modules(): void
    {
        $systemAdmin = User::factory()->create(['role' => 'system_admin']);

        $this->actingAs($systemAdmin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('farmers.index'), false)
            ->assertSee(route('distributions.index'), false)
            ->assertSee(route('supplies.index'), false)
            ->assertSee(route('programs.index'), false)
            ->assertSee(route('seasons.index'), false)
            ->assertSee(route('users.index'), false)
            ->assertSee(route('activity.index'), false)
            ->assertSee(route('reports.index'), false)
            ->assertSee(route('settings.index'), false)
            ->assertSee(route('database.index'), false)
            ->assertSee(route('button-access.index'), false)
            ->assertSee(route('schema-editor.index'), false);
    }

    public function test_system_admin_can_edit_team_access(): void
    {
        $systemAdmin = User::factory()->create(['role' => 'system_admin']);
        User::factory()->create(['role' => 'admin']);
        $teamMember = User::factory()->create(['role' => 'admin']);

        $this->actingAs($systemAdmin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee(route('users.edit', $teamMember), false);

        $this->get(route('users.edit', $teamMember))
            ->assertOk()
            ->assertSee('Edit team member');

        $this->put(route('users.update', $teamMember), [
            'name' => 'Updated Team Member',
            'username' => $teamMember->username,
            'email' => $teamMember->email,
            'role' => 'technician',
            'is_active' => '1',
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $teamMember->id,
            'name' => 'Updated Team Member',
            'role' => 'technician',
        ]);
    }

    public function test_admin_can_configure_supply_categories_farmer_fields_and_distribution_checks(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($superAdmin)
            ->put(route('settings.update'), [
                'supply_categories' => "Seeds\nCash",
                'barangays' => "San Roque\nPoblacion",
                'farmer_fields' => [
                    [
                        'key' => 'preferred_crop',
                        'label' => 'Preferred crop',
                        'type' => 'select',
                        'options' => "Rice\nCorn",
                        'required' => '1',
                    ],
                ],
                'require_active_farmer' => '1',
                'require_verified_rsbsa' => '0',
                'require_active_program' => '1',
                'max_distribution_quantity' => '25',
            ])
            ->assertRedirect(route('settings.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(['Seeds', 'Cash'], SystemSetting::read('supply_categories'));
        $this->assertSame(['San Roque', 'Poblacion'], SystemSetting::read('barangays'));
        $this->assertDatabaseHas('barangays', [
            'normalized_name' => 'san roque',
            'name' => 'San Roque',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('barangays', [
            'normalized_name' => 'poblacion',
            'name' => 'Poblacion',
            'is_active' => true,
        ]);
        $this->assertSame([
            [
                'key' => 'preferred_crop',
                'label' => 'Preferred crop',
                'type' => 'select',
                'required' => true,
                'options' => ['Rice', 'Corn'],
            ],
        ], SystemSetting::read('farmer_fields'));
        $this->assertDatabaseHas('system_settings', ['key' => 'distribution_settings']);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $superAdmin->id,
            'module' => 'CONFIGURATION',
            'action' => 'UPDATE',
        ]);

        $this->get(route('supplies.create'))
            ->assertOk()
            ->assertSee('Cash');

        $this->from(route('farmers.index'))
            ->post(route('farmers.store'), [
                'first_name' => 'Maya',
                'last_name' => 'Reyes',
                'address' => '12 Farm Road',
                'barangay' => 'San Roque',
                'municipality' => 'Baliuag',
                'province' => 'Bulacan',
                'status' => 'active',
                'registration_status' => 'unregistered',
                'verification_status' => 'unverified',
                'farm_size_unit' => 'hectare',
                'custom_fields' => ['preferred_crop' => 'Rice'],
            ])
            ->assertRedirect();

        $this->assertSame(['preferred_crop' => 'Rice'], Farmer::query()->sole()->custom_fields);

        $farmer = Farmer::query()->sole();
        Storage::fake('local');
        $photoPath = 'farmer-photos/'.$farmer->farmer_id.'.jpg';
        Storage::disk('local')->put($photoPath, 'verified-photo-fixture');
        $farmer->update(['photo_path' => $photoPath]);
        RsbsaRecord::create([
            'farmer_id' => $farmer->id,
            'rsbsa_number' => 'RSBSA-UNVERIFIED-1',
            'registration_status' => 'pending',
            'verification_status' => 'unverified',
            'farm_size' => 1,
            'farm_size_unit' => 'hectare',
        ]);
        $season = Season::create(['name' => 'Wet 2026', 'status' => 'active']);
        $hybridGroup = $season->groups()->create(['seed_type' => 'hybrid']);
        $assignment = FarmerSeasonGroupAssignment::create([
            'farmer_id' => $farmer->id,
            'season_group_id' => $hybridGroup->id,
            'assigned_by' => $superAdmin->id,
        ]);
        $program = SubsidyProgram::create([
            'program_code' => 'PROGRAM-CONFIG-1',
            'program_name' => 'Seed Support',
            'season_id' => $season->id,
            'requires_verified_rsbsa' => false,
            'status' => 'active',
            'created_by' => $superAdmin->id,
        ]);
        $supply = Supply::create([
            'supply_code' => 'SUP-CONFIG-1',
            'supply_name' => 'Rice Seeds',
            'category' => 'Seeds',
            'unit' => 'kg',
            'minimum_stock' => 0,
            'status' => 'active',
        ]);
        $program->supplies()->attach($supply->id, ['unit_value' => 10]);
        $program->seasonGroups()->attach($hybridGroup->id, ['rate_per_hectare' => null]);
        ProgramSupplyGroupRate::create([
            'subsidy_program_id' => $program->id,
            'supply_id' => $supply->id,
            'season_group_id' => $hybridGroup->id,
            'quantity_per_hectare' => 100,
            'maximum_quantity_per_farmer' => 100,
        ]);
        Inventory::create(['supply_id' => $supply->id, 'quantity' => 50]);

        $this->from(route('distributions.create'))->post(route('distributions.store'), [
            'farmer_code' => $farmer->farmer_id,
            'subsidy_program_id' => $program->id,
            'farmer_season_group_assignment_id' => $assignment->id,
            'face_verified' => '1',
            'items' => [['supply_id' => $supply->id, 'quantity' => 30]],
        ])
            ->assertRedirect(route('distributions.create'))
            ->assertSessionHasErrors([
                'items.0.quantity' => 'The requested quantity exceeds the system limit of 25.00 kg for Rice Seeds.',
            ]);
        $this->assertDatabaseCount('distributions', 0);

        $this->post(route('distributions.store'), [
            'farmer_code' => $farmer->farmer_id,
            'subsidy_program_id' => $program->id,
            'farmer_season_group_assignment_id' => $assignment->id,
            'face_verified' => '1',
            'items' => [[
                'supply_id' => $supply->id,
                'quantity' => 5,
                'reduction_reason' => 'Limited quantity for this test.',
            ]],
        ])->assertRedirect();
        $this->assertDatabaseHas('distributions', [
            'farmer_id' => $farmer->id,
            'subsidy_program_id' => $program->id,
            'transaction_status' => 'completed',
        ]);

        $this->from(route('farmers.index'))
            ->post(route('farmers.store'), [
                'first_name' => 'Lina',
                'last_name' => 'Santos',
                'address' => 'North Road',
                'barangay' => 'San Roque',
                'municipality' => 'Baliuag',
                'province' => 'Bulacan',
                'status' => 'active',
                'registration_status' => 'unregistered',
                'verification_status' => 'unverified',
                'farm_size_unit' => 'hectare',
                'custom_fields' => ['preferred_crop' => 'Wheat'],
            ])
            ->assertRedirect(route('farmers.index'))
            ->assertSessionHasErrors('custom_fields.preferred_crop');

        $this->assertDatabaseCount('farmers', 1);
    }

    public function test_configuration_cannot_remove_a_supply_category_that_is_in_use(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin']);
        Supply::create([
            'supply_code' => 'SUP-CATEGORY-1',
            'supply_name' => 'Rice Seeds',
            'category' => 'Seeds',
            'unit' => 'kg',
            'minimum_stock' => 0,
            'status' => 'active',
        ]);

        $this->actingAs($superAdmin)
            ->from(route('settings.index'))
            ->put(route('settings.update'), [
                'supply_categories' => 'Cash',
                'require_active_farmer' => '1',
                'require_verified_rsbsa' => '1',
                'require_active_program' => '1',
            ])
            ->assertRedirect(route('settings.index'))
            ->assertSessionHasErrors('supply_categories');

        $this->assertSame(Supply::CATEGORIES, SystemSetting::read('supply_categories', Supply::CATEGORIES));
    }

    public function test_admin_can_export_received_subsidy_report_as_csv(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin']);
        $farmer = Farmer::factory()->create(['first_name' => 'Maya', 'last_name' => 'Reyes']);
        $notReceivedFarmer = Farmer::factory()->create(['first_name' => 'Lina', 'last_name' => 'Santos']);
        $program = SubsidyProgram::create([
            'program_code' => 'PROGRAM-REPORT-1',
            'program_name' => 'Rice Assistance',
            'status' => 'active',
            'created_by' => $superAdmin->id,
        ]);
        Distribution::create([
            'transaction_number' => 'AGR-REPORT-1',
            'farmer_id' => $farmer->id,
            'subsidy_program_id' => $program->id,
            'processed_by' => $superAdmin->id,
            'eligibility_status' => 'eligible',
            'transaction_status' => 'completed',
            'distributed_at' => now(),
        ]);

        $this->actingAs($superAdmin)
            ->get(route('reports.index', ['report' => 'received']))
            ->assertOk()
            ->assertSee('AGR-REPORT-1')
            ->assertSee('Maya Reyes');

        $this->get(route('reports.index', ['report' => 'daily-distributions']))
            ->assertOk()
            ->assertSee(now()->toDateString());
        $this->get(route('reports.index', ['report' => 'monthly-distributions']))
            ->assertOk()
            ->assertSee(now()->format('Y-m'));
        $this->get(route('reports.index', [
            'report' => 'not-received',
            'program_id' => $program->id,
        ]))
            ->assertOk()
            ->assertSee($notReceivedFarmer->farmer_id)
            ->assertDontSee($farmer->farmer_id);

        $response = $this->get(route('reports.index', ['report' => 'received', 'download' => 1]));

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('AGR-REPORT-1', $csv);
        $this->assertStringContainsString('Rice Assistance', $csv);
    }

    public function test_sign_in_and_sign_out_are_recorded_in_audit_history(): void
    {
        $user = User::factory()->create([
            'username' => 'audit-admin',
            'password' => 'StrongPassword2026!',
            'role' => 'admin',
        ]);

        $this->post(route('login.store'), [
            'login' => $user->username,
            'password' => 'StrongPassword2026!',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'module' => 'AUTH',
            'action' => 'LOGIN',
        ]);

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'module' => 'AUTH',
            'action' => 'LOGOUT',
        ]);

        $this->post(route('login.store'), [
            'login' => 'unknown-user',
            'password' => 'IncorrectPassword2026!',
        ])->assertSessionHasErrors('login');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => null,
            'module' => 'AUTH',
            'action' => 'LOGIN_FAILED',
        ]);
    }

    public function test_failed_qr_lookup_is_included_in_admin_verification_reports(): void
    {
        $staff = User::factory()->create(['role' => 'technician']);
        $superAdmin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($staff)
            ->post(route('distributions.lookup'), ['farmer_code' => 'UNKNOWN-QR'])
            ->assertSessionHasErrors('farmer_code');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $staff->id,
            'module' => 'DISTRIBUTION',
            'action' => 'FAILED_VERIFICATION',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('reports.index', ['report' => 'failed-verifications']))
            ->assertOk()
            ->assertSee('QR lookup did not match a farmer record.')
            ->assertSee('Face verification is not currently implemented.');
    }

    public function test_editing_farmer_preserves_values_for_fields_removed_from_configuration(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin']);
        $farmer = Farmer::factory()->create([
            'custom_fields' => ['legacy_field' => 'Keep this value'],
        ]);

        $this->actingAs($superAdmin)
            ->put(route('farmers.update', $farmer), [
                'first_name' => $farmer->first_name,
                'last_name' => $farmer->last_name,
                'address' => $farmer->address,
                'barangay' => $farmer->barangay,
                'municipality' => $farmer->municipality,
                'province' => $farmer->province,
                'status' => $farmer->status,
                'registration_status' => 'unregistered',
                'verification_status' => 'unverified',
                'farm_size_unit' => 'hectare',
            ])
            ->assertRedirect(route('farmers.show', $farmer))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['legacy_field' => 'Keep this value'],
            $farmer->fresh()->custom_fields,
        );
    }
}
