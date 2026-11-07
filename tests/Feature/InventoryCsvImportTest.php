<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class InventoryCsvImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = [
        'SUPPLY CODE',
        'SUPPLY NAME',
        'CATEGORY',
        'UNIT',
        'QUANTITY',
        'MINIMUM STOCK',
        'DESCRIPTION',
        'STATUS',
    ];

    public function test_admin_can_open_the_csv_import_page_and_download_its_template(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('supplies.import'))
            ->assertOk()
            ->assertSee('Import inventory from CSV')
            ->assertSee('SUPPLY CODE')
            ->assertSee('adds the CSV quantity to current stock');

        $this->get(route('supplies.index'))
            ->assertOk()
            ->assertSee(route('supplies.import'));

        $template = $this->get(route('supplies.import.template'));
        $template->assertDownload('agriqr-inventory-template.csv');
        $this->assertStringStartsWith("\xEF\xBB\xBF", $template->streamedContent());
        $this->assertStringContainsString('MINIMUM STOCK', $template->streamedContent());
    }

    public function test_csv_import_creates_supplies_and_records_opening_stock(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $file = $this->csvFile([
            [
                'SUPPLY CODE' => 'SEED-001',
                'SUPPLY NAME' => 'Rice seed',
                'CATEGORY' => 'Seeds',
                'UNIT' => 'bag',
                'QUANTITY' => '12.5',
                'MINIMUM STOCK' => '2',
                'DESCRIPTION' => 'Certified seed',
                'STATUS' => 'active',
            ],
        ], includeBom: true);

        $response = $this->actingAs($admin)->post(route('supplies.import.store'), ['file' => $file]);

        $response->assertRedirect(route('supplies.import'))
            ->assertSessionHas('inventoryImportReport', [
                'processed_rows' => 1,
                'created' => 1,
                'updated' => 0,
                'failed' => 0,
                'errors' => [],
                'errors_truncated' => false,
            ]);

        $supply = Supply::where('supply_code', 'SEED-001')->sole();
        $this->assertDatabaseHas('inventories', [
            'supply_id' => $supply->id,
            'quantity' => 12.5,
            'last_updated_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'supply_id' => $supply->id,
            'movement_type' => 'IN',
            'quantity' => 12.5,
            'reference_type' => 'CSV_IMPORT',
            'performed_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'CREATE',
            'module' => 'SUPPLY',
            'record_id' => $supply->id,
        ]);
    }

    public function test_csv_import_updates_existing_supply_and_adds_stock_without_overwriting_blank_optional_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $supply = Supply::create([
            'supply_code' => 'FERT-001',
            'supply_name' => 'Old fertilizer',
            'category' => 'Old category',
            'unit' => 'sack',
            'description' => 'Keep this description',
            'minimum_stock' => 3,
            'status' => 'inactive',
        ]);
        Inventory::create([
            'supply_id' => $supply->id,
            'quantity' => 8.25,
        ]);
        $file = $this->csvFile([
            [
                'SUPPLY CODE' => 'FERT-001',
                'SUPPLY NAME' => 'Updated fertilizer',
                'CATEGORY' => 'Fertilizers',
                'UNIT' => 'bag',
                'QUANTITY' => '4.5',
                'MINIMUM STOCK' => '',
                'DESCRIPTION' => '',
                'STATUS' => '',
            ],
        ]);

        $response = $this->actingAs($admin)->post(route('supplies.import.store'), ['file' => $file]);

        $response->assertRedirect(route('supplies.import'))
            ->assertSessionHas('inventoryImportReport.updated', 1)
            ->assertSessionHas('inventoryImportReport.created', 0);

        $supply->refresh();
        $this->assertSame('Updated fertilizer', $supply->supply_name);
        $this->assertSame('Fertilizers', $supply->category);
        $this->assertSame('Keep this description', $supply->description);
        $this->assertSame(3.0, (float) $supply->minimum_stock);
        $this->assertSame('inactive', $supply->status);
        $this->assertSame('12.75', $supply->inventory->quantity);
        $this->assertDatabaseHas('inventory_movements', [
            'supply_id' => $supply->id,
            'movement_type' => 'IN',
            'quantity' => 4.5,
            'reference_type' => 'CSV_IMPORT',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'STOCK_IN',
            'module' => 'SUPPLY',
            'record_id' => $supply->id,
        ]);
    }

    public function test_csv_import_skips_invalid_rows_and_reports_row_errors(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $file = $this->csvFile([
            [
                'SUPPLY CODE' => 'SEED-BAD',
                'SUPPLY NAME' => 'Invalid seed',
                'CATEGORY' => 'Seeds',
                'UNIT' => 'bag',
                'QUANTITY' => 'many',
            ],
            [
                'SUPPLY CODE' => 'SEED-GOOD',
                'SUPPLY NAME' => 'Valid seed',
                'CATEGORY' => 'Seeds',
                'UNIT' => 'bag',
                'QUANTITY' => '2',
            ],
        ]);

        $this->actingAs($admin)
            ->post(route('supplies.import.store'), ['file' => $file])
            ->assertRedirect(route('supplies.import'))
            ->assertSessionHas('inventoryImportReport.processed_rows', 2)
            ->assertSessionHas('inventoryImportReport.created', 1)
            ->assertSessionHas('inventoryImportReport.failed', 1)
            ->assertSessionHas('inventoryImportReport.errors', [
                'Row 2: QUANTITY must be a number.',
            ]);

        $this->assertDatabaseCount('supplies', 1);
        $this->assertDatabaseHas('supplies', ['supply_code' => 'SEED-GOOD']);
        $this->assertDatabaseMissing('supplies', ['supply_code' => 'SEED-BAD']);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_csv_import_allows_blank_unit_for_cash_but_requires_it_for_seeds(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $file = $this->csvFile([
            [
                'SUPPLY CODE' => 'CASH-CSV',
                'SUPPLY NAME' => 'Cash assistance',
                'CATEGORY' => 'Cash',
                'UNIT' => '',
                'QUANTITY' => '100',
            ],
            [
                'SUPPLY CODE' => 'SEED-NO-UNIT',
                'SUPPLY NAME' => 'Rice seeds',
                'CATEGORY' => 'Seeds',
                'UNIT' => '',
                'QUANTITY' => '5',
            ],
        ]);

        $this->actingAs($admin)
            ->post(route('supplies.import.store'), ['file' => $file])
            ->assertRedirect(route('supplies.import'))
            ->assertSessionHas('inventoryImportReport.processed_rows', 2)
            ->assertSessionHas('inventoryImportReport.created', 1)
            ->assertSessionHas('inventoryImportReport.failed', 1)
            ->assertSessionHas('inventoryImportReport.errors', ['Row 3: UNIT is required.']);

        $this->assertDatabaseHas('supplies', ['supply_code' => 'CASH-CSV', 'unit' => null]);
        $this->assertDatabaseMissing('supplies', ['supply_code' => 'SEED-NO-UNIT']);
    }

    public function test_csv_import_allows_the_unit_column_to_be_omitted_for_cash(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $file = $this->csvFile([
            [
                'SUPPLY CODE' => 'CASH-NO-UNIT-COLUMN',
                'SUPPLY NAME' => 'Cash assistance',
                'CATEGORY' => 'Cash',
                'QUANTITY' => '50',
            ],
        ], array_values(array_diff(self::HEADERS, ['UNIT'])));

        $this->actingAs($admin)
            ->post(route('supplies.import.store'), ['file' => $file])
            ->assertRedirect(route('supplies.import'))
            ->assertSessionHas('inventoryImportReport.created', 1)
            ->assertSessionHas('inventoryImportReport.failed', 0);

        $this->assertDatabaseHas('supplies', [
            'supply_code' => 'CASH-NO-UNIT-COLUMN',
            'unit' => null,
        ]);
    }

    public function test_csv_import_rejects_files_without_required_headers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $file = $this->csvFile([], ['SUPPLY CODE', 'SUPPLY NAME']);

        $this->actingAs($admin)
            ->from(route('supplies.import'))
            ->post(route('supplies.import.store'), ['file' => $file])
            ->assertRedirect(route('supplies.import'))
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('supplies', 0);
    }

    public function test_users_without_inventory_access_cannot_open_the_csv_import_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'custodian']))
            ->get(route('supplies.import'))
            ->assertForbidden();
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @param  list<string>  $headers
     */
    private function csvFile(array $rows, array $headers = self::HEADERS, bool $includeBom = false): UploadedFile
    {
        $stream = fopen('php://temp', 'r+');

        if ($includeBom) {
            fwrite($stream, "\xEF\xBB\xBF");
        }

        fputcsv($stream, $headers, ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn (string $header) => $row[$header] ?? '', $headers), ',', '"', '');
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return UploadedFile::fake()->createWithContent('inventory.csv', $content);
    }
}
