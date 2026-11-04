<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Farmer;
use App\Models\RsbsaRecord;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class FarmerCsvImporter
{
    public const HEADERS = [
        'DATE ENCODED',
        'REGISTRATION DATE',
        'LGU RSBSA Number',
        'FFRS System Generated No.',
        'FARM SIZE',
        'FARM SIZE UNIT',
        'CROP INFORMATION',
        'REMARKS',
        'LAST NAME',
        'FIRST NAME',
        'MIDDLE NAME',
        'EXT NAME',
        'GENDER',
        'BIRTHDATE',
        'FARMER',
        'FARMWORKER',
        'FISHERFOLK',
        'AGRIBUSINESS',
        'IF IP',
        'TRIBE',
        'ORGANIC PRACTITIONERS',
        'ARB',
        'FARMER ADDRESS 1',
        'FARMER ADDRESS 2',
        'FARMER ADDRESS 3',
        'PARCEL NO',
        'PARCEL ADDRESS 1',
        'PARCEL ADDRESS 2',
        'PARCEL ADDRESS 3',
        'PARCEL AREA',
        'COMMODITY NAME',
        'CROP AREA',
        'FARM TYPE',
        'NUMBER OF HEADS',
        'AGENCY',
        'OWNERSHIP TYPE',
        'OWNER NAME',
    ];

    private const HEADER_FIELDS = [
        'date encoded' => 'date_encoded',
        'registration date' => 'registration_date',
        'date of registration' => 'registration_date',
        'lgu rsbsa number' => 'rsbsa_number',
        'ffrs system generated no' => 'ffrs_generated_number',
        'farm size' => 'farm_size',
        'farm size ha' => 'farm_size',
        'farm size hectares' => 'farm_size',
        'farm size unit' => 'farm_size_unit',
        'crop information' => 'crop_information',
        'remarks' => 'remarks',
        'last name' => 'last_name',
        'first name' => 'first_name',
        'middle name' => 'middle_name',
        'ext name' => 'suffix',
        'gender' => 'gender',
        'birthdate' => 'birthdate',
        'farmer' => 'is_farmer',
        'farmworker' => 'is_farmworker',
        'fisherfolk' => 'is_fisherfolk',
        'agribusiness' => 'is_agribusiness',
        'if ip' => 'is_ip',
        'tribe' => 'tribe',
        'organic practitioners' => 'is_organic_practitioner',
        'arb' => 'is_arb',
        'farmer address 1' => 'farmer_address_1',
        'farmer address 2' => 'farmer_address_2',
        'farmer address 3' => 'farmer_address_3',
        'parcel no' => 'parcel_number',
        'parcel address 1' => 'parcel_address_1',
        'parcel address 2' => 'parcel_address_2',
        'parcel address 3' => 'parcel_address_3',
        'parcel area' => 'parcel_area',
        'commodity name' => 'commodity_name',
        'crop area' => 'crop_area',
        'farm type' => 'farm_type',
        'number of heads' => 'number_of_heads',
        'agency' => 'agency',
        'ownership type' => 'ownership_type',
        'owner name' => 'owner_name',
    ];

    private const DATE_FIELDS = ['date_encoded', 'registration_date', 'birthdate'];

    private const BOOLEAN_FIELDS = [
        'is_farmer',
        'is_farmworker',
        'is_fisherfolk',
        'is_agribusiness',
        'is_ip',
        'is_organic_practitioner',
        'is_arb',
    ];

    private const PARCEL_FIELDS = [
        'parcel_number',
        'parcel_address_1',
        'parcel_address_2',
        'parcel_address_3',
        'parcel_area',
        'commodity_name',
        'crop_area',
        'farm_type',
        'number_of_heads',
        'agency',
        'ownership_type',
        'owner_name',
    ];

    /**
     * @return array{processed_rows: int, created: int, updated: int, failed: int, errors: list<string>, errors_truncated: bool}
     */
    public function import(UploadedFile $file, User $user, ?string $ipAddress): array
    {
        $path = $file->getRealPath();

        if ($path === false) {
            throw new RuntimeException('The uploaded CSV file could not be opened.');
        }

        $stream = fopen($path, 'rb');

        if ($stream === false) {
            throw new RuntimeException('The uploaded CSV file could not be opened.');
        }

        try {
            $headers = fgetcsv($stream, null, ',', '"', '');

            if ($headers === false) {
                throw ValidationException::withMessages([
                    'file' => 'The CSV file is empty or could not be read.',
                ]);
            }

            $columns = $this->resolveColumns($headers);
            $headerCount = count($headers);

            return DB::transaction(function () use ($stream, $columns, $headerCount, $user, $ipAddress): array {
                $report = [
                    'processed_rows' => 0,
                    'created' => 0,
                    'updated' => 0,
                    'failed' => 0,
                    'errors' => [],
                    'errors_truncated' => false,
                ];
                $rowNumber = 1;

                while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
                    $rowNumber++;

                    if (collect($row)->every(fn ($value) => blank($value))) {
                        continue;
                    }

                    $report['processed_rows']++;

                    if ($report['processed_rows'] > 5000) {
                        throw ValidationException::withMessages([
                            'file' => 'A CSV import may contain no more than 5,000 data rows.',
                        ]);
                    }

                    if (count($row) > $headerCount) {
                        $this->addRowError($report, $rowNumber, 'The row contains more values than the header.');

                        continue;
                    }

                    $data = $this->readRow($row, $columns);
                    $validationErrors = $data['errors'];
                    unset($data['errors']);

                    if ($validationErrors !== []) {
                        $this->addRowError($report, $rowNumber, implode(' ', $validationErrors));

                        continue;
                    }

                    $validator = Validator::make($data, $this->rules(), [
                        'rsbsa_number.required' => 'LGU RSBSA Number is required.',
                        'first_name.required' => 'FIRST NAME is required.',
                        'last_name.required' => 'LAST NAME is required.',
                    ]);

                    if ($validator->fails()) {
                        $this->addRowError($report, $rowNumber, implode(' ', $validator->errors()->all()));

                        continue;
                    }

                    $this->saveRow($data, $user, $ipAddress, $report);
                }

                return $report;
            });
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  list<string|null>  $headers
     * @return array<string, int>
     */
    private function resolveColumns(array $headers): array
    {
        $columns = [];

        foreach ($headers as $index => $header) {
            $normalized = Str::of(ltrim((string) $header, "\xEF\xBB\xBF"))
                ->ascii()
                ->lower()
                ->replaceMatches('/[^a-z0-9]+/', ' ')
                ->trim()
                ->toString();
            $field = self::HEADER_FIELDS[$normalized] ?? null;

            if ($field === null) {
                continue;
            }

            if (array_key_exists($field, $columns)) {
                throw ValidationException::withMessages([
                    'file' => "The CSV header contains the {$header} column more than once.",
                ]);
            }

            $columns[$field] = $index;
        }

        $missingHeaders = array_diff(['rsbsa_number', 'first_name', 'last_name'], array_keys($columns));

        if ($missingHeaders !== []) {
            throw ValidationException::withMessages([
                'file' => 'The CSV must include LGU RSBSA Number, FIRST NAME, and LAST NAME columns.',
            ]);
        }

        return $columns;
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<string, int>  $columns
     * @return array<string, mixed>
     */
    private function readRow(array $row, array $columns): array
    {
        $data = [];
        $errors = [];

        foreach ($columns as $field => $index) {
            $value = trim((string) ($row[$index] ?? ''));

            if ($value === '') {
                continue;
            }

            if (in_array($field, self::DATE_FIELDS, true)) {
                $date = $this->parseDate($value);

                if ($date === null) {
                    $errors[] = strtoupper(str_replace('_', ' ', $field)).' must be a valid date.';
                } else {
                    $data[$field] = $date;
                }

                continue;
            }

            if (in_array($field, self::BOOLEAN_FIELDS, true)) {
                $boolean = $this->parseBoolean($value);

                if ($boolean === null) {
                    $errors[] = strtoupper(str_replace('_', ' ', $field)).' must be yes or no.';
                } else {
                    $data[$field] = $boolean;
                }

                continue;
            }

            $data[$field] = $value;
        }

        $data['errors'] = $errors;

        return $data;
    }

    private function parseDate(string $value): ?string
    {
        foreach (['Y-m-d', 'm/d/Y', 'n/j/Y', 'd/m/Y', 'j/n/Y', 'm/d/y', 'n/j/y', 'd/m/y', 'j/n/y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
            $errors = DateTimeImmutable::getLastErrors();

            if (
                $date !== false
                && $date->format($format) === $value
                && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            ) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function parseBoolean(string $value): ?bool
    {
        return match (Str::lower($value)) {
            '1', 'yes', 'y', 'true', 'x' => true,
            '0', 'no', 'n', 'false' => false,
            default => null,
        };
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        return [
            'rsbsa_number' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'ffrs_generated_number' => ['nullable', 'string', 'max:100'],
            'farm_size' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'farm_size_unit' => ['nullable', 'string', 'max:20'],
            'crop_information' => ['nullable', 'string', 'max:5000'],
            'registration_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'gender' => ['nullable', 'string', 'max:30'],
            'tribe' => ['nullable', 'string', 'max:150'],
            'farmer_address_1' => ['nullable', 'string', 'max:255'],
            'farmer_address_2' => ['nullable', 'string', 'max:255'],
            'farmer_address_3' => ['nullable', 'string', 'max:255'],
            'parcel_number' => ['nullable', 'string', 'max:50'],
            'parcel_address_1' => ['nullable', 'string', 'max:255'],
            'parcel_address_2' => ['nullable', 'string', 'max:255'],
            'parcel_address_3' => ['nullable', 'string', 'max:255'],
            'parcel_area' => ['nullable', 'numeric', 'min:0', 'max:99999999.9999'],
            'commodity_name' => ['nullable', 'string', 'max:150'],
            'crop_area' => ['nullable', 'numeric', 'min:0', 'max:99999999.9999'],
            'farm_type' => ['nullable', 'string', 'max:100'],
            'number_of_heads' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'agency' => ['nullable', 'string', 'max:150'],
            'ownership_type' => ['nullable', 'string', 'max:100'],
            'owner_name' => ['nullable', 'string', 'max:150'],
            'is_farmer' => ['nullable', 'boolean'],
            'is_farmworker' => ['nullable', 'boolean'],
            'is_fisherfolk' => ['nullable', 'boolean'],
            'is_agribusiness' => ['nullable', 'boolean'],
            'is_ip' => ['nullable', 'boolean'],
            'is_organic_practitioner' => ['nullable', 'boolean'],
            'is_arb' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{processed_rows: int, created: int, updated: int, failed: int, errors: list<string>, errors_truncated: bool}  $report
     */
    private function saveRow(array $data, User $user, ?string $ipAddress, array &$report): void
    {
        $record = RsbsaRecord::query()
            ->where('rsbsa_number', $data['rsbsa_number'])
            ->with('farmer')
            ->first();
        $isNew = $record === null;

        if ($isNew) {
            $farmer = Farmer::create([
                ...$this->farmerAttributes($data),
                'farmer_id' => $this->newFarmerCode(),
                'status' => 'active',
            ]);
            $record = $farmer->rsbsaRecord()->create([
                ...$this->rsbsaAttributes($data),
                'registration_status' => 'registered',
                'verification_status' => 'unverified',
            ]);
        } else {
            $farmer = $record->farmer;
            $farmer->update($this->farmerAttributes($data));
            $record->update($this->rsbsaAttributes($data));
        }

        if (Arr::hasAny($data, ['farmer_address_1', 'farmer_address_2', 'farmer_address_3'])) {
            $address = collect([
                $record->farmer_address_1,
                $record->farmer_address_2,
                $record->farmer_address_3,
            ])->filter()->implode(', ');

            if ($address !== '') {
                $farmer->update(['address' => $address]);
            }
        }

        $parcelAttributes = $this->parcelAttributes($data);

        if ($parcelAttributes !== []) {
            $farmer->parcels()->updateOrCreate(
                ['parcel_number' => $data['parcel_number'] ?? null],
                $parcelAttributes,
            );
        }

        AuditLog::create([
            'user_id' => $user->id,
            'action' => $isNew ? 'CREATE' : 'UPDATE',
            'module' => 'FARMER',
            'record_id' => $farmer->id,
            'description' => "Imported RSBSA profile {$data['rsbsa_number']} from CSV.",
            'ip_address' => $ipAddress,
        ]);

        $report[$isNew ? 'created' : 'updated']++;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function farmerAttributes(array $data): array
    {
        return Arr::only($data, ['first_name', 'middle_name', 'last_name', 'suffix']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function rsbsaAttributes(array $data): array
    {
        return Arr::only($data, [
            'rsbsa_number',
            'registration_date',
            'farm_size',
            'farm_size_unit',
            'crop_information',
            'remarks',
            'date_encoded',
            'ffrs_generated_number',
            'gender',
            'birthdate',
            'is_farmer',
            'is_farmworker',
            'is_fisherfolk',
            'is_agribusiness',
            'is_ip',
            'tribe',
            'is_organic_practitioner',
            'is_arb',
            'farmer_address_1',
            'farmer_address_2',
            'farmer_address_3',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function parcelAttributes(array $data): array
    {
        return Arr::only($data, self::PARCEL_FIELDS);
    }

    private function addRowError(array &$report, int $rowNumber, string $message): void
    {
        $report['failed']++;

        if (count($report['errors']) < 25) {
            $report['errors'][] = "Row {$rowNumber}: {$message}";
        } else {
            $report['errors_truncated'] = true;
        }
    }

    private function newFarmerCode(): string
    {
        do {
            $code = 'F'.Str::upper(Str::random(10));
        } while (Farmer::where('farmer_id', $code)->exists());

        return $code;
    }
}
