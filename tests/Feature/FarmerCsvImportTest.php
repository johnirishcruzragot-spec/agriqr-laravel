<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Farmer;
use App\Models\FarmerParcel;
use App\Models\User;
use App\Services\FarmerCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class FarmerCsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_the_csv_import_page_and_download_its_template(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get(route('farmers.import'))
            ->assertOk()
            ->assertSee('Import farmers from CSV')
            ->assertSee('LGU RSBSA Number');

        $this->get(route('farmers.index'))
            ->assertOk()
            ->assertSee('Import CSV');

        $template = $this->get(route('farmers.import.template'));
        $template->assertDownload('agriqr-rsbsa-template.csv');
        $this->assertStringStartsWith("\xEF\xBB\xBF", $template->streamedContent());
        $this->assertStringContainsString('RSBSA VERIFICATION', $template->streamedContent());
        $this->assertStringContainsString('FFRS System Generated No.', $template->streamedContent());
        $this->assertStringContainsString('REGISTRATION DATE', $template->streamedContent());
        $this->assertStringContainsString('FARM SIZE UNIT', $template->streamedContent());
    }

    public function test_csv_import_creates_farmer_and_multiple_parcels_from_bom_file(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $file = $this->csvFile([
            [
                'DATE ENCODED' => '2026-02-01',
                'REGISTRATION DATE' => '2026-01-15',
                'LGU RSBSA Number' => 'RSBSA-CSV-100',
                'FFRS System Generated No.' => 'FFRS-100',
                'FARM SIZE' => '5.75',
                'FARM SIZE UNIT' => 'hectare',
                'CROP INFORMATION' => 'Rice and corn',
                'REMARKS' => 'Farm record note',
                'LAST NAME' => 'Reyes',
                'FIRST NAME' => 'Maya',
                'MIDDLE NAME' => 'Santos',
                'GENDER' => 'Female',
                'BIRTHDATE' => '04/15/1987',
                'FARMER' => 'Yes',
                'FARMWORKER' => 'No',
                'FISHERFOLK' => '0',
                'AGRIBUSINESS' => '1',
                'IF IP' => 'No',
                'TRIBE' => 'Test tribe',
                'ORGANIC PRACTITIONERS' => 'No',
                'ARB' => 'Yes',
                'FARMER ADDRESS 1' => 'San Roque',
                'FARMER ADDRESS 2' => 'Baliuag',
                'FARMER ADDRESS 3' => 'Bulacan',
                'PARCEL NO' => 'P-001',
                'PARCEL ADDRESS 1' => 'North field',
                'PARCEL AREA' => '2.5',
                'COMMODITY NAME' => 'Rice',
                'CROP AREA' => '2.25',
                'FARM TYPE' => 'Irrigated',
                'NUMBER OF HEADS' => '0',
                'AGENCY' => 'Department of Agriculture',
                'OWNERSHIP TYPE' => 'Owner',
                'OWNER NAME' => 'Maya Reyes',
            ],
            [
                'LGU RSBSA Number' => 'RSBSA-CSV-100',
                'FIRST NAME' => 'Maya',
                'LAST NAME' => 'Reyes',
                'PARCEL NO' => 'P-002',
                'PARCEL ADDRESS 1' => 'South field',
                'PARCEL AREA' => '1.25',
                'COMMODITY NAME' => 'Corn',
                'CROP AREA' => '1.10',
            ],
        ], includeBom: true);

        $response = $this->actingAs($user)->post(route('farmers.import.store'), ['file' => $file]);

        $response->assertRedirect(route('farmers.import'))
            ->assertSessionHas('farmerImportReport', [
                'processed_rows' => 2,
                'created' => 1,
                'updated' => 1,
                'failed' => 0,
                'errors' => [],
                'errors_truncated' => false,
            ]);

        $farmer = Farmer::with(['rsbsaRecord', 'parcels'])->sole();
        $this->assertMatchesRegularExpression('/^F[A-Z0-9]{10}$/', $farmer->farmer_id);
        $this->assertSame('Maya Santos Reyes', $farmer->full_name);
        $this->assertSame('San Roque, Baliuag, Bulacan', $farmer->address);
        $this->assertSame('2026-02-01', $farmer->rsbsaRecord->date_encoded->toDateString());
        $this->assertSame('2026-01-15', $farmer->rsbsaRecord->registration_date->toDateString());
        $this->assertSame('5.75', $farmer->rsbsaRecord->farm_size);
        $this->assertSame('hectare', $farmer->rsbsaRecord->farm_size_unit);
        $this->assertSame('Rice and corn', $farmer->rsbsaRecord->crop_information);
        $this->assertSame('Farm record note', $farmer->rsbsaRecord->remarks);
        $this->assertSame('1987-04-15', $farmer->rsbsaRecord->birthdate->toDateString());
        $this->assertTrue($farmer->rsbsaRecord->is_farmer);
        $this->assertFalse($farmer->rsbsaRecord->is_farmworker);
        $this->assertSame('unverified', $farmer->rsbsaRecord->verification_status);
        $this->assertCount(2, $farmer->parcels);
        $this->assertDatabaseHas('farmer_parcels', [
            'farmer_id' => $farmer->id,
            'parcel_number' => 'P-001',
            'commodity_name' => 'Rice',
            'parcel_area' => 2.5,
        ]);
        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertSame(2, AuditLog::where('module', 'FARMER')->where('user_id', $user->id)->count());

        $this->actingAs($user)
            ->get(route('farmers.show', $farmer))
            ->assertOk()
            ->assertSee('Farm registration details')
            ->assertSee('5.75')
            ->assertSee('Registered Jan 15, 2026')
            ->assertSee('Rice and corn')
            ->assertSee('Farm record note');

        $this->get(route('farmers.index'))
            ->assertOk()
            ->assertSee('San Roque, Baliuag, Bulacan');
    }

    public function test_csv_import_saves_each_supported_rsbsa_verification_status(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $file = $this->csvFile([
            [
                'LGU RSBSA Number' => 'RSBSA-CSV-VERIFIED',
                'RSBSA VERIFICATION' => 'Verified',
                'FIRST NAME' => 'Veronica',
                'LAST NAME' => 'Farmer',
            ],
            [
                'LGU RSBSA Number' => 'RSBSA-CSV-UNVERIFIED',
                'RSBSA VERIFICATION' => 'UNVERIFIED',
                'FIRST NAME' => 'Uma',
                'LAST NAME' => 'Farmer',
            ],
            [
                'LGU RSBSA Number' => 'RSBSA-CSV-PENDING',
                'RSBSA VERIFICATION' => 'Pending',
                'FIRST NAME' => 'Penny',
                'LAST NAME' => 'Farmer',
            ],
        ]);

        $this->actingAs($user)
            ->post(route('farmers.import.store'), ['file' => $file])
            ->assertRedirect(route('farmers.import'))
            ->assertSessionHas('farmerImportReport.created', 3)
            ->assertSessionHas('farmerImportReport.failed', 0);

        $this->assertDatabaseHas('rsbsa_records', [
            'rsbsa_number' => 'RSBSA-CSV-VERIFIED',
            'verification_status' => 'verified',
        ]);
        $this->assertDatabaseHas('rsbsa_records', [
            'rsbsa_number' => 'RSBSA-CSV-UNVERIFIED',
            'verification_status' => 'unverified',
        ]);
        $this->assertDatabaseHas('rsbsa_records', [
            'rsbsa_number' => 'RSBSA-CSV-PENDING',
            'verification_status' => 'pending',
        ]);
    }

    public function test_csv_import_accepts_plural_farm_size_units_from_spreadsheets(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $file = $this->csvFile([
            [
                'LGU RSBSA Number' => 'RSBSA-CSV-HECTARES',
                'FIRST NAME' => 'Hector',
                'LAST NAME' => 'Farmer',
                'FARM SIZE' => '1',
                'FARM SIZE UNIT' => 'Hectares',
            ],
            [
                'LGU RSBSA Number' => 'RSBSA-CSV-ACRES',
                'FIRST NAME' => 'Acre',
                'LAST NAME' => 'Farmer',
                'FARM SIZE' => '2',
                'FARM SIZE UNIT' => 'Acres',
            ],
        ]);

        $this->actingAs($user)
            ->post(route('farmers.import.store'), ['file' => $file])
            ->assertRedirect(route('farmers.import'))
            ->assertSessionHas('farmerImportReport.created', 2)
            ->assertSessionHas('farmerImportReport.failed', 0);

        $this->assertDatabaseHas('rsbsa_records', [
            'rsbsa_number' => 'RSBSA-CSV-HECTARES',
            'farm_size_unit' => 'hectare',
        ]);
        $this->assertDatabaseHas('rsbsa_records', [
            'rsbsa_number' => 'RSBSA-CSV-ACRES',
            'farm_size_unit' => 'acre',
        ]);
    }

    public function test_csv_import_updates_existing_rsbsa_verification_status(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $farmer = Farmer::factory()->create();
        $farmer->rsbsaRecord()->create([
            'rsbsa_number' => 'RSBSA-CSV-STATUS-UPDATE',
            'registration_status' => 'registered',
            'verification_status' => 'unverified',
        ]);
        $file = $this->csvFile([
            [
                'LGU RSBSA Number' => 'RSBSA-CSV-STATUS-UPDATE',
                'RSBSA VERIFICATION' => 'pending',
                'FIRST NAME' => $farmer->first_name,
                'LAST NAME' => $farmer->last_name,
            ],
        ]);

        $this->actingAs($user)
            ->post(route('farmers.import.store'), ['file' => $file])
            ->assertRedirect(route('farmers.import'))
            ->assertSessionHas('farmerImportReport.updated', 1)
            ->assertSessionHas('farmerImportReport.failed', 0);

        $this->assertDatabaseHas('rsbsa_records', [
            'rsbsa_number' => 'RSBSA-CSV-STATUS-UPDATE',
            'verification_status' => 'pending',
        ]);
    }

    public function test_csv_import_rejects_an_unsupported_rsbsa_verification_status(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $file = $this->csvFile([
            [
                'LGU RSBSA Number' => 'RSBSA-CSV-INVALID-STATUS',
                'RSBSA VERIFICATION' => 'approved',
                'FIRST NAME' => 'Invalid',
                'LAST NAME' => 'Status',
            ],
        ]);

        $this->actingAs($user)
            ->post(route('farmers.import.store'), ['file' => $file])
            ->assertRedirect(route('farmers.import'))
            ->assertSessionHas('farmerImportReport.failed', 1)
            ->assertSessionHas('farmerImportReport.errors', [
                'Row 2: RSBSA VERIFICATION must be verified, unverified, or pending.',
            ]);

        $this->assertDatabaseMissing('rsbsa_records', [
            'rsbsa_number' => 'RSBSA-CSV-INVALID-STATUS',
        ]);
    }

    public function test_csv_import_updates_existing_rsbsa_record_without_overwriting_blank_fields_or_qr_id(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $farmer = Farmer::factory()->create([
            'farmer_id' => 'FSTABLE0001',
            'first_name' => 'Old',
            'middle_name' => 'Keep',
            'last_name' => 'Name',
            'address' => 'Saved address',
        ]);
        $record = $farmer->rsbsaRecord()->create([
            'rsbsa_number' => 'RSBSA-CSV-200',
            'registration_status' => 'registered',
            'verification_status' => 'verified',
            'gender' => 'Female',
            'farmer_address_1' => 'Saved line one',
            'farmer_address_2' => 'Saved line two',
        ]);
        $parcel = FarmerParcel::factory()->create([
            'farmer_id' => $farmer->id,
            'parcel_number' => 'P-KEEP',
            'commodity_name' => 'Rice',
            'parcel_area' => 3.5,
        ]);
        $file = $this->csvFile([
            [
                'LGU RSBSA Number' => 'RSBSA-CSV-200',
                'FIRST NAME' => 'Updated',
                'LAST NAME' => 'Farmer',
                'FARMER ADDRESS 1' => 'New line one',
                'PARCEL NO' => 'P-KEEP',
            ],
        ]);

        $response = $this->actingAs($user)->post(route('farmers.import.store'), ['file' => $file]);

        $response->assertRedirect(route('farmers.import'))
            ->assertSessionHas('farmerImportReport.updated', 1)
            ->assertSessionHas('farmerImportReport.created', 0);

        $farmer->refresh();
        $record->refresh();
        $parcel->refresh();

        $this->assertSame('FSTABLE0001', $farmer->farmer_id);
        $this->assertSame('Updated Keep Farmer', $farmer->full_name);
        $this->assertSame('New line one, Saved line two', $farmer->address);
        $this->assertSame('Female', $record->gender);
        $this->assertSame('verified', $record->verification_status);
        $this->assertSame('Saved line two', $record->farmer_address_2);
        $this->assertSame('Rice', $parcel->commodity_name);
        $this->assertSame('3.5000', $parcel->parcel_area);
        $this->assertDatabaseCount('farmers', 1);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'UPDATE',
            'module' => 'FARMER',
            'record_id' => $farmer->id,
        ]);
    }

    public function test_csv_import_skips_invalid_rows_and_reports_the_error_line(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $file = $this->csvFile([
            [
                'LGU RSBSA Number' => 'RSBSA-CSV-BAD',
                'FIRST NAME' => 'Invalid',
                'LAST NAME' => 'Date',
                'BIRTHDATE' => '2025-02-30',
            ],
            [
                'LGU RSBSA Number' => 'RSBSA-CSV-GOOD',
                'FIRST NAME' => 'Valid',
                'LAST NAME' => 'Farmer',
            ],
        ]);

        $response = $this->actingAs($user)->post(route('farmers.import.store'), ['file' => $file]);

        $response->assertRedirect(route('farmers.import'))
            ->assertSessionHas('farmerImportReport.processed_rows', 2)
            ->assertSessionHas('farmerImportReport.created', 1)
            ->assertSessionHas('farmerImportReport.failed', 1)
            ->assertSessionHas('farmerImportReport.errors', [
                'Row 2: BIRTHDATE must be a valid date.',
            ]);

        $this->assertDatabaseCount('farmers', 1);
        $this->assertDatabaseHas('rsbsa_records', ['rsbsa_number' => 'RSBSA-CSV-GOOD']);
        $this->assertDatabaseMissing('rsbsa_records', ['rsbsa_number' => 'RSBSA-CSV-BAD']);
    }

    public function test_csv_import_rejects_farm_sizes_above_the_selected_unit_limit(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $file = $this->csvFile([
            [
                'LGU RSBSA Number' => 'RSBSA-CSV-OVERSIZE',
                'FIRST NAME' => 'Large',
                'LAST NAME' => 'Farm',
                'FARM SIZE' => '1000.01',
                'FARM SIZE UNIT' => 'hectare',
            ],
        ]);

        $this->actingAs($user)
            ->post(route('farmers.import.store'), ['file' => $file])
            ->assertRedirect(route('farmers.import'))
            ->assertSessionHas('farmerImportReport.failed', 1)
            ->assertSessionHas('farmerImportReport.errors', [
                'Row 2: FARM SIZE exceeds the maximum for its unit.',
            ]);

        $this->assertDatabaseCount('farmers', 0);
    }

    public function test_csv_import_rejects_files_without_required_headers(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $file = $this->csvFile([], ['FIRST NAME', 'LAST NAME']);

        $this->actingAs($user)
            ->from(route('farmers.import'))
            ->post(route('farmers.import.store'), ['file' => $file])
            ->assertRedirect(route('farmers.import'))
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('farmers', 0);
    }

    public function test_users_without_farmer_access_cannot_open_the_csv_import_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'custodian']))
            ->get(route('farmers.import'))
            ->assertForbidden();
    }

    public function test_technicians_do_not_see_or_access_farmer_csv_import(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);

        $this->actingAs($technician)
            ->get(route('farmers.index'))
            ->assertOk()
            ->assertDontSee('Import CSV');

        $this->get(route('farmers.import'))->assertForbidden();
        $this->get(route('farmers.import.template'))->assertForbidden();
        $this->post(route('farmers.import.store'))->assertForbidden();
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @param  list<string>  $headers
     */
    private function csvFile(array $rows, array $headers = FarmerCsvImporter::HEADERS, bool $includeBom = false): UploadedFile
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

        return UploadedFile::fake()->createWithContent('rsbsa.csv', $content);
    }
}
