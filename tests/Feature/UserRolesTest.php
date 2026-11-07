<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Farmer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_account_command_creates_a_system_admin(): void
    {
        $this->artisan('agriqr:create-admin')
            ->expectsQuestion('Administrator name', 'System Owner')
            ->expectsQuestion('Administrator email', 'system-owner@example.com')
            ->expectsQuestion('Username', 'system-owner')
            ->expectsQuestion('Password (at least 12 characters)', 'StrongPassword2026!')
            ->expectsQuestion('Confirm password', 'StrongPassword2026!')
            ->assertExitCode(0);

        $systemAdmin = User::query()->sole();

        $this->assertSame('system_admin', $systemAdmin->role);
        $this->assertSame('system-owner', $systemAdmin->username);
        $this->assertTrue($systemAdmin->canAccessModule('schema_editor'));
    }

    public function test_technician_can_view_inventory_and_see_its_navigation_link(): void
    {
        $staff = User::factory()->create(['role' => 'technician']);

        $this->actingAs($staff)
            ->get(route('supplies.index'))
            ->assertOk();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('supplies.index'));
    }

    public function test_user_form_offers_only_admin_and_technician_roles(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($superAdmin)
            ->get(route('users.create'))
            ->assertOk()
            ->assertSee('Admin')
            ->assertSee('Technician')
            ->assertSee('Module access')
            ->assertSee('name="module_access[]"', false)
            ->assertSee('Database administration')
            ->assertDontSee('Staff')
            ->assertDontSee('Super Admin')
            ->assertDontSee('Custodian')
            ->assertDontSee('Supply Officer');
        $this->assertFalse($superAdmin->canAccessModule('schema_editor'));
    }

    public function test_admin_can_assign_module_access_to_one_technician_account(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin']);
        $limitedStaff = User::factory()->create(['role' => 'technician']);
        $otherStaff = User::factory()->create(['role' => 'technician']);

        $this->actingAs($superAdmin)
            ->put(route('users.update', $limitedStaff), [
                'name' => $limitedStaff->name,
                'username' => $limitedStaff->username,
                'email' => $limitedStaff->email,
                'role' => 'technician',
                'is_active' => '1',
                'module_access' => ['farmers'],
            ])
            ->assertRedirect(route('users.index'));

        $this->assertSame(['farmers'], $limitedStaff->fresh()->module_access);
        $this->assertNull($otherStaff->fresh()->module_access);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $superAdmin->id,
            'module' => 'USER',
            'record_id' => $limitedStaff->id,
        ]);
        $this->assertStringContainsString(
            'module access changed from Farmers, Distributions, Inventory, Programs, Reports, Seasons and farmer groups, Requests to Farmers',
            AuditLog::query()->where('record_id', $limitedStaff->id)->value('description'),
        );

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee('MODULE ACCESS')
            ->assertSee('Farmers');
        $this->get(route('settings.index'))->assertOk();

        $this->actingAs($limitedStaff->fresh())
            ->get(route('farmers.index'))
            ->assertOk();
        $this->get(route('farmers.show', Farmer::factory()->create()))
            ->assertOk()
            ->assertDontSee('Distribution history');

        $this->get(route('supplies.index'))->assertForbidden();
        $this->get(route('distributions.index'))->assertForbidden();
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('farmers.index'))
            ->assertDontSee(route('supplies.index'))
            ->assertDontSee('INVENTORY ITEMS');

        $this->actingAs($otherStaff->fresh())
            ->get(route('supplies.index'))
            ->assertOk();
    }

    public function test_admin_can_limit_a_technicians_module_access(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'technician']);

        $this->actingAs($admin)
            ->put(route('users.update', $staff), [
                'name' => $staff->name,
                'username' => $staff->username,
                'email' => $staff->email,
                'role' => 'technician',
                'is_active' => '1',
                'module_access' => ['farmers'],
            ])
            ->assertRedirect(route('users.index'));

        $this->assertSame(['farmers'], $staff->fresh()->module_access);
        $this->actingAs($staff->fresh())->get(route('supplies.index'))->assertForbidden();
    }

    public function test_admin_can_assign_the_dedicated_technician_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->put(route('users.update', $staff), [
                'name' => $staff->name,
                'username' => $staff->username,
                'email' => $staff->email,
                'role' => 'technician',
                'is_active' => '1',
            ])
            ->assertRedirect(route('users.index'));

        $technician = $staff->fresh();
        $this->assertSame('technician', $technician->role);
        $this->assertTrue($technician->canAccessModule('farmers'));
        $this->assertTrue($technician->canAccessModule('seasons'));
        $this->assertTrue($technician->canAccessModule('inventory'));
    }

    public function test_only_system_admin_can_assign_or_manage_a_system_admin_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $systemAdmin = User::factory()->create(['role' => 'system_admin']);

        $this->actingAs($admin)
            ->get(route('users.create'))
            ->assertOk()
            ->assertSee('Technician')
            ->assertDontSee('System Admin');

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee(route('users.edit', $admin), false)
            ->assertDontSee(route('users.edit', $systemAdmin), false);

        $this->from(route('users.create'))
            ->post(route('users.store'), [
                'name' => 'Extra System Admin',
                'username' => 'extra-system-admin',
                'email' => 'extra-system-admin@example.com',
                'password' => 'StrongPassword2026!',
                'password_confirmation' => 'StrongPassword2026!',
                'role' => 'system_admin',
                'is_active' => '1',
            ])
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors('role');

        $this->get(route('users.edit', $systemAdmin))->assertForbidden();

        $this->assertDatabaseMissing('users', ['username' => 'extra-system-admin']);

        $this->actingAs($systemAdmin)
            ->get(route('users.create'))
            ->assertOk()
            ->assertSee('System Admin');

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee(route('users.edit', $systemAdmin), false);

        $this->get(route('users.edit', $systemAdmin))
            ->assertOk()
            ->assertSee($systemAdmin->username);

        $this->post(route('users.store'), [
            'name' => 'Additional System Admin',
            'username' => 'additional-system-admin',
            'email' => 'additional-system-admin@example.com',
            'password' => 'StrongPassword2026!',
            'password_confirmation' => 'StrongPassword2026!',
            'role' => 'system_admin',
            'is_active' => '1',
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'username' => 'additional-system-admin',
            'role' => 'system_admin',
        ]);
    }

    public function test_admin_cannot_assign_modules_outside_the_users_role(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'technician']);

        $this->actingAs($superAdmin)
            ->from(route('users.edit', $staff))
            ->put(route('users.update', $staff), [
                'name' => $staff->name,
                'username' => $staff->username,
                'email' => $staff->email,
                'role' => 'technician',
                'is_active' => '1',
                'module_access' => ['database'],
            ])
            ->assertRedirect(route('users.edit', $staff))
            ->assertSessionHasErrors('module_access.0');

        $this->assertNull($staff->fresh()->module_access);
    }

    #[DataProvider('retiredRoles')]
    public function test_retired_roles_cannot_access_inventory(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)
            ->get(route('supplies.index'))
            ->assertForbidden();
    }

    #[DataProvider('retiredRoles')]
    public function test_retired_roles_cannot_be_assigned(string $role): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $username = "retired-{$role}";

        $this->actingAs($admin)
            ->from(route('users.create'))
            ->post(route('users.store'), [
                'name' => 'Retired Role Account',
                'username' => $username,
                'email' => "{$username}@example.com",
                'password' => 'StrongPassword2026!',
                'password_confirmation' => 'StrongPassword2026!',
                'role' => $role,
                'is_active' => '1',
            ])
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['username' => $username]);
    }

    public static function retiredRoles(): array
    {
        return [
            'custodian' => ['custodian'],
            'supply officer' => ['supply_officer'],
        ];
    }

    public function test_migration_moves_retired_role_accounts_to_staff_without_deleting_them(): void
    {
        $custodian = User::factory()->create(['role' => 'custodian']);
        $supplyOfficer = User::factory()->create(['role' => 'supply_officer']);
        $migration = require database_path('migrations/2026_11_05_232107_migrate_removed_user_roles_to_staff.php');

        $migration->up();

        $this->assertDatabaseHas('users', ['id' => $custodian->id, 'role' => 'staff']);
        $this->assertDatabaseHas('users', ['id' => $supplyOfficer->id, 'role' => 'staff']);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_role_migration_maps_existing_accounts_to_admin_and_technician_without_deleting_them(): void
    {
        $roles = [
            'super_admin' => 'system_admin',
            'admin' => 'admin',
            'staff' => 'technician',
            'technician' => 'technician',
            'custodian' => 'technician',
            'supply_officer' => 'technician',
        ];
        $accounts = collect(array_keys($roles))
            ->map(fn (string $role): User => User::factory()->create(['role' => $role]));
        $migration = require database_path('migrations/2026_11_06_220328_migrate_users_to_admin_technician_roles.php');

        $migration->up();
        $systemAdminMigration = require database_path('migrations/2026_11_06_225823_promote_super_admins_to_system_admin_role.php');
        $systemAdminMigration->up();

        foreach ($accounts as $index => $account) {
            $role = array_values($roles)[$index];
            $this->assertDatabaseHas('users', ['id' => $account->id, 'role' => $role]);
        }

        $this->assertDatabaseCount('users', 6);
    }
}
