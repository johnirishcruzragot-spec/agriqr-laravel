<?php

namespace Tests\Feature;

use App\Models\Distribution;
use App\Models\Farmer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_shows_global_distribution_activity(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $technician = User::factory()->create(['role' => 'technician']);
        $otherTechnician = User::factory()->create(['role' => 'technician']);
        $this->createDistribution($technician, 'ADMIN-VIEW-001');
        $this->createDistribution($otherTechnician, 'ADMIN-VIEW-002');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Distribution performance')
            ->assertSee('ACTIVE USERS')
            ->assertSee('ADMIN SHORTCUTS')
            ->assertSee('ADMIN-VIEW-001')
            ->assertSee('ADMIN-VIEW-002')
            ->assertSee('All completed distributions over the last seven days', false)
            ->assertViewHas('distributionChartTotal', 2)
            ->assertViewHas('distributionChart', fn (array $chart): bool => count($chart) === 7
                && $chart[6]['total'] === 2);
    }

    public function test_technician_dashboard_only_shows_their_own_distribution_activity(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        $otherTechnician = User::factory()->create(['role' => 'technician']);
        $this->createDistribution($technician, 'TECH-VIEW-001');
        $this->createDistribution($technician, 'TECH-VIEW-PENDING', 'pending');
        $this->createDistribution($otherTechnician, 'TECH-VIEW-OTHER');

        $this->actingAs($technician)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Your distribution activity')
            ->assertSee('Your recent distributions')
            ->assertSee('TECH-VIEW-001')
            ->assertSee('TECH-VIEW-PENDING')
            ->assertDontSee('TECH-VIEW-OTHER')
            ->assertDontSee('ACTIVE USERS')
            ->assertDontSee('ADMIN SHORTCUTS')
            ->assertSee('Your completed distributions over the last seven days', false)
            ->assertViewHas('distributionChartTotal', 1)
            ->assertViewHas('distributionChart', fn (array $chart): bool => count($chart) === 7
                && $chart[6]['total'] === 1);
    }

    private function createDistribution(User $processor, string $transactionNumber, string $status = 'completed'): Distribution
    {
        $farmer = Farmer::factory()->create();

        return Distribution::query()->create([
            'transaction_number' => $transactionNumber,
            'farmer_id' => $farmer->id,
            'processed_by' => $processor->id,
            'transaction_status' => $status,
            'distributed_at' => now(),
        ]);
    }
}
