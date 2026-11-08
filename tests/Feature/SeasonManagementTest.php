<?php

namespace Tests\Feature;

use App\Models\Farmer;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeasonManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_a_season_with_hybrid_and_inbred_groups(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('seasons.store'), ['name' => 'Wet 2026'])
            ->assertRedirect(route('seasons.index'))
            ->assertSessionHasNoErrors();

        $season = Season::query()->sole();

        $this->assertSame('active', $season->status);
        $this->assertDatabaseHas('season_groups', [
            'season_id' => $season->id,
            'seed_type' => 'hybrid',
        ]);
        $this->assertDatabaseHas('season_groups', [
            'season_id' => $season->id,
            'seed_type' => 'inbred',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'module' => 'SEASON',
            'record_id' => $season->id,
        ]);

        $this->from(route('seasons.create'))
            ->post(route('seasons.store'), ['name' => 'Wet 2026'])
            ->assertRedirect(route('seasons.create'))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('seasons', 1);
        $this->assertDatabaseCount('season_groups', 2);
    }

    public function test_closing_a_season_preserves_its_groups_and_assignments(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $season = Season::factory()->create(['name' => 'Dry 2026']);
        $group = $season->groups()->create(['seed_type' => 'hybrid']);
        $farmer = Farmer::factory()->create();
        $assignment = $farmer->seasonGroupAssignments()->create([
            'season_group_id' => $group->id,
            'assigned_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->put(route('seasons.update', $season), [
                'name' => 'Dry 2026',
                'status' => 'closed',
            ])
            ->assertRedirect(route('seasons.index'));

        $this->assertDatabaseHas('seasons', ['id' => $season->id, 'status' => 'closed']);
        $this->assertDatabaseHas('season_groups', ['id' => $group->id]);
        $this->assertDatabaseHas('farmer_season_group_assignments', [
            'id' => $assignment->id,
            'ended_at' => null,
        ]);
    }

    public function test_technician_has_read_only_configuration_access_and_can_process_distributions(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        $season = Season::factory()->create(['name' => 'Wet 2026']);
        $season->groups()->createMany([
            ['seed_type' => 'hybrid'],
            ['seed_type' => 'inbred'],
        ]);
        $farmer = Farmer::factory()->create();

        $this->actingAs($technician)
            ->get(route('seasons.index'))
            ->assertOk()
            ->assertSee('Wet 2026')
            ->assertDontSee('Create season');

        $this->get(route('farmers.index'))
            ->assertOk()
            ->assertSee($farmer->full_name)
            ->assertSee('Register a farmer');
        $this->get(route('farmers.show', $farmer))
            ->assertOk()
            ->assertDontSee('Edit profile')
            ->assertDontSee('Save photo');
        $this->get(route('programs.index'))
            ->assertOk()
            ->assertDontSee('Create program');
        $this->get(route('distributions.index'))
            ->assertOk()
            ->assertSee('New distribution');

        $this->get(route('seasons.create'))->assertForbidden();
        $this->post(route('seasons.store'), ['name' => 'Dry 2026'])->assertForbidden();
        $this->get(route('farmers.create'))->assertOk();
        $this->get(route('farmers.edit', $farmer))->assertForbidden();
        $this->get(route('programs.create'))->assertForbidden();
        $this->get(route('distributions.create'))->assertOk();
        $this->assertDatabaseMissing('seasons', ['name' => 'Dry 2026']);
    }
}
