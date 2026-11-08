<?php

namespace Tests\Feature;

use App\Models\Distribution;
use App\Models\DistributionItem;
use App\Models\Farmer;
use App\Models\SubsidyProgram;
use App\Models\Supply;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportDistributionSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_distribution_report_summarizes_completed_transactions_and_quantities(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $program = SubsidyProgram::query()->create([
            'program_code' => 'REPORT-SEED-1',
            'program_name' => 'Rice Seed Assistance',
            'status' => 'active',
        ]);
        $firstFarmer = Farmer::factory()->create();
        $secondFarmer = Farmer::factory()->create();
        $firstSupply = Supply::query()->create([
            'supply_code' => 'REPORT-RICE-1',
            'supply_name' => 'Rice seed',
            'category' => 'Seeds',
            'unit' => 'bag',
        ]);
        $secondSupply = Supply::query()->create([
            'supply_code' => 'REPORT-FERT-1',
            'supply_name' => 'Fertilizer',
            'category' => 'Fertilizers',
            'unit' => 'sack',
        ]);
        $firstDistribution = $this->createDistribution($program, $firstFarmer, $admin, 'REPORT-TXN-001', '2026-03-10 09:00:00');
        $secondDistribution = $this->createDistribution($program, $secondFarmer, $admin, 'REPORT-TXN-002', '2026-03-12 10:00:00');
        $this->createItem($firstDistribution, $firstSupply, '2', '20');
        $this->createItem($firstDistribution, $secondSupply, '3', '30');
        $this->createItem($secondDistribution, $firstSupply, '4', '40');
        $this->createDistribution($program, Farmer::factory()->create(), $admin, 'REPORT-TXN-PENDING', '2026-03-13 10:00:00', 'pending');
        $otherProgram = SubsidyProgram::query()->create([
            'program_code' => 'REPORT-SEED-2',
            'program_name' => 'Vegetable Support',
            'status' => 'active',
        ]);
        $otherDistribution = $this->createDistribution(
            $otherProgram,
            Farmer::factory()->create(),
            $admin,
            'REPORT-TXN-003',
            '2026-03-15 10:00:00',
        );
        $this->createItem($otherDistribution, $firstSupply, '1', '10');

        $response = $this->actingAs($admin)->get(route('reports.index', [
            'report' => 'program-distributions',
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
        ]));
        $programId = $program->id;

        $response->assertOk()
            ->assertSee('Rice Seed Assistance')
            ->assertSee('QUANTITY DISTRIBUTED')
            ->assertSee(route('reports.programs.distributions', [
                'program' => $program->id,
                'date_from' => '2026-03-01',
                'date_to' => '2026-03-31',
            ]))
            ->assertViewHas('rows', function ($rows) use ($programId): bool {
                $row = $rows->firstWhere('program_id', $programId);

                return $rows->total() === 2
                    && (int) $row->transactions === 2
                    && (int) $row->farmers === 2
                    && (int) $row->items_issued === 3
                    && (float) $row->quantity === 9.0
                    && (float) $row->distributed_value === 90.0;
            });

        $this->get(route('reports.index', [
            'report' => 'program-distributions',
            'program_id' => $program->id,
        ]))
            ->assertOk()
            ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 1
                && (int) $rows->first()->transactions === 2);
    }

    public function test_program_distribution_details_show_recipient_date_and_items(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $program = SubsidyProgram::query()->create([
            'program_code' => 'REPORT-DETAIL-1',
            'program_name' => 'Corn Seed Assistance',
            'status' => 'active',
        ]);
        $farmer = Farmer::factory()->create([
            'first_name' => 'Maya',
            'middle_name' => 'Santos',
            'last_name' => 'Reyes',
            'barangay' => 'San Roque',
            'municipality' => 'Baliuag',
            'province' => 'Bulacan',
        ]);
        $supply = Supply::query()->create([
            'supply_code' => 'REPORT-CORN-1',
            'supply_name' => 'Hybrid corn seed',
            'category' => 'Seeds',
            'unit' => 'bag',
        ]);
        $distribution = $this->createDistribution($program, $farmer, $admin, 'REPORT-DETAIL-TXN', '2026-04-15 14:30:00');
        $distribution->update(['remarks' => 'Received in good condition.']);
        $this->createItem($distribution, $supply, '5', '1250');

        $response = $this->actingAs($admin)
            ->get(route('reports.programs.distributions', [
                'program' => $program,
                'date_from' => '2026-04-01',
                'date_to' => '2026-04-30',
            ]));

        $response->assertOk()
            ->assertSee('Corn Seed Assistance')
            ->assertSee('Maya Santos Reyes')
            ->assertSee('San Roque, Baliuag, Bulacan')
            ->assertSee('Apr 15, 2026 · 2:30 PM')
            ->assertSee('REPORT-DETAIL-TXN')
            ->assertSee('Hybrid corn seed')
            ->assertSee('5.00 bag')
            ->assertSee('Received in good condition.');
    }

    public function test_technician_without_report_filter_access_cannot_filter_program_details(): void
    {
        SystemSetting::create([
            'key' => 'button_access',
            'value' => ['technician' => ['reports.filter' => false]],
        ]);
        $technician = User::factory()->create([
            'role' => 'technician',
            'module_access' => ['reports'],
        ]);
        $program = SubsidyProgram::query()->create([
            'program_code' => 'REPORT-ACCESS-1',
            'program_name' => 'Restricted date filter',
            'status' => 'active',
        ]);

        $this->actingAs($technician)
            ->get(route('reports.programs.distributions', [
                'program' => $program,
                'date_from' => '2026-04-01',
            ]))
            ->assertForbidden();
    }

    private function createDistribution(
        SubsidyProgram $program,
        Farmer $farmer,
        User $processor,
        string $transactionNumber,
        string $distributedAt,
        string $status = 'completed',
    ): Distribution {
        return Distribution::query()->create([
            'transaction_number' => $transactionNumber,
            'farmer_id' => $farmer->id,
            'subsidy_program_id' => $program->id,
            'processed_by' => $processor->id,
            'transaction_status' => $status,
            'distributed_at' => $distributedAt,
        ]);
    }

    private function createItem(Distribution $distribution, Supply $supply, string $quantity, string $lineValue): DistributionItem
    {
        return DistributionItem::query()->create([
            'distribution_id' => $distribution->id,
            'supply_id' => $supply->id,
            'quantity' => $quantity,
            'unit' => $supply->unit,
            'unit_value' => $lineValue,
            'line_value' => $lineValue,
        ]);
    }
}
