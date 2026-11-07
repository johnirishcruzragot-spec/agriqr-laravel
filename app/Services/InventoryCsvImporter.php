<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class InventoryCsvImporter
{
    public const HEADERS = [
        'SUPPLY CODE',
        'SUPPLY NAME',
        'CATEGORY',
        'UNIT',
        'QUANTITY',
        'MINIMUM STOCK',
        'DESCRIPTION',
        'STATUS',
    ];

    private const HEADER_FIELDS = [
        'supply code' => 'supply_code',
        'supply name' => 'supply_name',
        'category' => 'category',
        'unit' => 'unit',
        'quantity' => 'quantity',
        'quantity on hand' => 'quantity',
        'minimum stock' => 'minimum_stock',
        'description' => 'description',
        'status' => 'status',
    ];

    /**
     * @return array{processed_rows: int, created: int, updated: int, failed: int, errors: list<string>, errors_truncated: bool}
     */
    public function import(UploadedFile $file, User $user, ?string $ipAddress, array $categories = Supply::CATEGORIES): array
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

            return DB::transaction(function () use ($stream, $columns, $headerCount, $user, $ipAddress, $categories): array {
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
                    $validator = Validator::make($data, [
                        'supply_code' => ['required', 'string', 'max:50'],
                        'supply_name' => ['required', 'string', 'max:150'],
                        'category' => ['required', Rule::in($categories)],
                        'unit' => [
                            Rule::requiredIf(! in_array($data['category'] ?? '', Supply::UNIT_OPTIONAL_CATEGORIES, true)),
                            'nullable', 'string', 'max:50',
                        ],
                        'quantity' => ['required', 'numeric', 'min:0', 'max:999999999'],
                        'minimum_stock' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
                        'description' => ['nullable', 'string', 'max:5000'],
                        'status' => ['nullable', Rule::in(['active', 'inactive'])],
                    ], [
                        'supply_code.required' => 'SUPPLY CODE is required.',
                        'supply_name.required' => 'SUPPLY NAME is required.',
                        'category.required' => 'CATEGORY is required.',
                        'unit.required' => 'UNIT is required.',
                        'category.in' => 'CATEGORY must match one of the supported inventory categories.',
                        'quantity.required' => 'QUANTITY is required.',
                        'quantity.numeric' => 'QUANTITY must be a number.',
                        'quantity.min' => 'QUANTITY must be zero or greater.',
                        'minimum_stock.numeric' => 'MINIMUM STOCK must be a number.',
                        'minimum_stock.min' => 'MINIMUM STOCK must be zero or greater.',
                        'status.in' => 'STATUS must be active or inactive.',
                    ]);

                    if ($validator->fails()) {
                        $this->addRowError($report, $rowNumber, implode(' ', $validator->errors()->all()));

                        continue;
                    }

                    $this->saveRow($validator->validated(), $user, $ipAddress, $rowNumber, $report);
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

        $missingHeaders = array_diff(
            ['supply_code', 'supply_name', 'category', 'quantity'],
            array_keys($columns),
        );

        if ($missingHeaders !== []) {
            throw ValidationException::withMessages([
                'file' => 'The CSV must include SUPPLY CODE, SUPPLY NAME, CATEGORY, and QUANTITY columns.',
            ]);
        }

        return $columns;
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<string, int>  $columns
     * @return array<string, string|null>
     */
    private function readRow(array $row, array $columns): array
    {
        $data = [];

        foreach ($columns as $field => $index) {
            $value = trim((string) ($row[$index] ?? ''));

            if ($value !== '') {
                $data[$field] = $value;
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{processed_rows: int, created: int, updated: int, failed: int, errors: list<string>, errors_truncated: bool}  $report
     */
    private function saveRow(
        array $data,
        User $user,
        ?string $ipAddress,
        int $rowNumber,
        array &$report,
    ): void {
        $supply = Supply::where('supply_code', $data['supply_code'])
            ->lockForUpdate()
            ->first();
        $isNew = $supply === null;

        if ($isNew) {
            $supply = Supply::create([
                'supply_code' => $data['supply_code'],
                'supply_name' => $data['supply_name'],
                'category' => $data['category'],
                'unit' => $data['unit'] ?? null,
                'minimum_stock' => $data['minimum_stock'] ?? 0,
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'active',
            ]);
        } else {
            $supply->update([
                'supply_name' => $data['supply_name'],
                'category' => $data['category'],
                'unit' => $data['unit'] ?? null,
                ...array_intersect_key($data, array_flip(['minimum_stock', 'description', 'status'])),
            ]);
        }

        $quantity = (float) $data['quantity'];
        $inventory = Inventory::where('supply_id', $supply->id)
            ->lockForUpdate()
            ->first();

        if ($inventory) {
            $inventory->increment('quantity', $quantity);
            $inventory->update(['last_updated_by' => $user->id]);
        } else {
            $supply->inventory()->create([
                'quantity' => $quantity,
                'last_updated_by' => $user->id,
            ]);
        }

        if ($quantity > 0) {
            InventoryMovement::create([
                'supply_id' => $supply->id,
                'movement_type' => 'IN',
                'quantity' => $quantity,
                'reference_type' => 'CSV_IMPORT',
                'performed_by' => $user->id,
                'remarks' => "Imported from inventory CSV row {$rowNumber}.",
            ]);
        }

        AuditLog::create([
            'user_id' => $user->id,
            'action' => $isNew ? 'CREATE' : ($quantity > 0 ? 'STOCK_IN' : 'UPDATE'),
            'module' => 'SUPPLY',
            'record_id' => $supply->id,
            'description' => $isNew
                ? "Imported supply item {$supply->supply_code} from CSV with {$quantity}".($supply->unit ? " {$supply->unit}" : '').'.'
                : "Updated supply item {$supply->supply_code} from CSV and received {$quantity}".($supply->unit ? " {$supply->unit}" : '').'.',
            'ip_address' => $ipAddress,
        ]);

        $report[$isNew ? 'created' : 'updated']++;
    }

    /**
     * @param  array{processed_rows: int, created: int, updated: int, failed: int, errors: list<string>, errors_truncated: bool}  $report
     */
    private function addRowError(array &$report, int $rowNumber, string $message): void
    {
        $report['failed']++;

        if (count($report['errors']) < 25) {
            $report['errors'][] = "Row {$rowNumber}: {$message}";
        } else {
            $report['errors_truncated'] = true;
        }
    }
}
