<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SystemConfiguration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ButtonAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_system_admin_can_view_and_update_button_access_settings(): void
    {
        $systemAdmin = User::factory()->create(['role' => 'system_admin']);

        $this->actingAs($systemAdmin)
            ->get(route('button-access.index'))
            ->assertOk()
            ->assertSee('Button and action access')
            ->assertSee('Import farmers from CSV')
            ->assertSee('Sign out')
            ->assertSee('Technician');

        $this->put(route('button-access.update'), [
            'buttons' => [
                'admin' => ['farmers.import'],
                'technician' => [],
            ],
        ])->assertRedirect(route('button-access.index'));

        $settings = SystemSetting::query()->where('key', 'button_access')->firstOrFail();
        $this->assertSame(true, $settings->value['admin']['farmers.import']);
        $this->assertSame(false, $settings->value['technician']['farmers.import']);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $systemAdmin->id,
            'module' => 'BUTTON_ACCESS',
        ]);
    }

    public function test_admin_and_technician_use_their_default_import_access(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'technician']);
        $systemAdmin = User::factory()->create(['role' => 'system_admin']);

        $this->assertTrue($admin->canUseButton('farmers.import'));
        $this->assertFalse($technician->canUseButton('farmers.import'));
        $this->assertFalse($systemAdmin->canUseButton('unknown.action'));

        $this->actingAs($admin)
            ->get(route('farmers.import'))
            ->assertOk()
            ->assertSee('Import farmers');

        $this->actingAs($technician)
            ->get(route('farmers.index'))
            ->assertOk()
            ->assertDontSee('Import CSV');

        $this->actingAs($technician)
            ->get(route('farmers.import'))
            ->assertForbidden();

    }

    public function test_disabled_button_access_blocks_the_matching_route(): void
    {
        $systemAdmin = User::factory()->create(['role' => 'system_admin']);
        $technician = User::factory()->create(['role' => 'technician']);

        $this->actingAs($systemAdmin)
            ->put(route('button-access.update'), [
                'buttons' => [
                    'admin' => [],
                    'technician' => ['farmers.list', 'reports.view'],
                ],
            ])
            ->assertRedirect(route('button-access.index'));

        $this->actingAs($technician)
            ->get(route('farmers.index'))
            ->assertOk()
            ->assertDontSee('Sign out');

        $this->actingAs($technician)
            ->get(route('farmers.import'))
            ->assertForbidden();

        $this->actingAs($technician)
            ->get(route('farmers.index', ['search' => 'example']))
            ->assertForbidden();

        $this->actingAs($technician)
            ->get(route('reports.index', ['report' => 'inventory']))
            ->assertForbidden();

        $this->actingAs($technician)
            ->get(route('reports.index', ['download' => 1]))
            ->assertForbidden();

        $this->actingAs($technician)
            ->post(route('logout'))
            ->assertForbidden();
    }

    public function test_button_settings_reject_unknown_actions_and_non_system_admins(): void
    {
        $systemAdmin = User::factory()->create(['role' => 'system_admin']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('button-access.index'))
            ->assertForbidden();

        $this->actingAs($systemAdmin)
            ->from(route('button-access.index'))
            ->put(route('button-access.update'), [
                'buttons' => [
                    'admin' => ['schema-editor.destroy'],
                    'technician' => [],
                ],
            ])
            ->assertSessionHasErrors('buttons.admin.0');

        $this->assertDatabaseMissing('system_settings', ['key' => 'button_access']);
        $this->assertSame(0, AuditLog::query()->where('module', 'BUTTON_ACCESS')->count());
    }

    public function test_enabled_button_does_not_override_a_technicians_module_access(): void
    {
        $systemAdmin = User::factory()->create(['role' => 'system_admin']);
        $technician = User::factory()->create([
            'role' => 'technician',
            'module_access' => [],
        ]);

        $this->actingAs($systemAdmin)
            ->put(route('button-access.update'), [
                'buttons' => [
                    'admin' => [],
                    'technician' => ['farmers.import'],
                ],
            ])
            ->assertRedirect(route('button-access.index'));

        $this->assertFalse($technician->canUseButton('farmers.import'));
        $this->actingAs($technician)
            ->get(route('farmers.import'))
            ->assertForbidden();
    }

    public function test_disabled_custom_field_action_rejects_direct_field_updates(): void
    {
        $systemAdmin = User::factory()->create(['role' => 'system_admin']);
        $admin = User::factory()->create(['role' => 'admin']);
        $configuration = app(SystemConfiguration::class)->all();

        $this->actingAs($systemAdmin)
            ->put(route('button-access.update'), [
                'buttons' => [
                    'admin' => ['settings.view', 'settings.update'],
                    'technician' => [],
                ],
            ])
            ->assertRedirect(route('button-access.index'));

        $this->actingAs($admin)
            ->put(route('settings.update'), [
                'supply_categories' => implode("\n", $configuration['supply_categories']),
                'farmer_fields' => [
                    ['key' => 'preferred_crop', 'label' => 'Preferred crop', 'type' => 'text'],
                ],
                'barangays' => 'Test barangay',
                'require_active_farmer' => 1,
                'require_verified_rsbsa' => 1,
                'require_active_program' => 1,
                'max_distribution_quantity' => '',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('system_settings', ['key' => 'farmer_fields']);
    }

    public function test_disabled_distribution_item_action_rejects_additional_items(): void
    {
        $systemAdmin = User::factory()->create(['role' => 'system_admin']);
        $technician = User::factory()->create(['role' => 'technician']);

        $this->actingAs($systemAdmin)
            ->put(route('button-access.update'), [
                'buttons' => [
                    'admin' => [],
                    'technician' => ['distributions.create'],
                ],
            ])
            ->assertRedirect(route('button-access.index'));

        $this->actingAs($technician)
            ->post(route('distributions.store'), [
                'items' => [
                    ['supply_id' => 1, 'quantity' => 1],
                    ['supply_id' => 2, 'quantity' => 1],
                ],
            ])
            ->assertForbidden();
    }
}
