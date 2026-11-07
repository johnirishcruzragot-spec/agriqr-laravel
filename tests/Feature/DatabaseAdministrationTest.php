<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDOException;
use Tests\TestCase;

class DatabaseAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_check_database_health_without_exposing_credentials(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'admin',
            'module_access' => [],
        ]);
        $connectionName = (string) config('database.default');

        config([
            "database.connections.{$connectionName}.host" => 'hidden-host.example.test',
            "database.connections.{$connectionName}.database" => 'hidden-database',
            "database.connections.{$connectionName}.username" => 'hidden-username',
            "database.connections.{$connectionName}.password" => 'hidden-password',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('database.index'))
            ->assertOk()
            ->assertSee('Database administration')
            ->assertSee('Connection health')
            ->assertSee('Connected')
            ->assertSee('Database status')
            ->assertDontSee('hidden-host.example.test')
            ->assertDontSee('hidden-database')
            ->assertDontSee('hidden-username')
            ->assertDontSee('hidden-password');
    }

    public function test_database_administration_is_for_admins_only(): void
    {
        $this->get(route('database.index'))
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('database.index'))
            ->assertOk();

        $this->actingAs(User::factory()->create(['role' => 'technician']))
            ->get(route('database.index'))
            ->assertForbidden();
    }

    public function test_database_connection_failures_are_reported_without_exposing_exception_details(): void
    {
        $superAdmin = User::factory()->create(['role' => 'admin']);
        $connectionName = (string) config('database.default');

        DB::shouldReceive('connection')
            ->once()
            ->with($connectionName)
            ->andThrow(new PDOException('hidden-password'));
        Log::shouldReceive('warning')
            ->once()
            ->with('Admin database health check failed.', [
                'connection' => $connectionName,
                'exception' => PDOException::class,
            ]);

        $this->actingAs($superAdmin)
            ->get(route('database.index'))
            ->assertOk()
            ->assertSee('Unavailable')
            ->assertSee('The connection check failed.')
            ->assertDontSee('hidden-password');
    }
}
