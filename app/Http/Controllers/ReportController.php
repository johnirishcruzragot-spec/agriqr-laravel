<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const REPORTS = [
        'daily-distributions' => 'Daily distribution summary',
        'monthly-distributions' => 'Monthly distribution summary',
        'farmers' => 'Registered farmers',
        'received' => 'Farmers who received a subsidy',
        'not-received' => 'Farmers who have not received a subsidy',
        'inventory' => 'Remaining inventory',
        'movements' => 'Inventory movement history',
        'staff-activity' => 'Staff activity and audit log',
        'failed-verifications' => 'Failed QR and eligibility checks',
    ];

    public function index(Request $request): View|StreamedResponse
    {
        $user = $request->user();
        $filterKeys = ['report', 'date_from', 'date_to', 'program_id'];

        if (array_intersect($filterKeys, array_keys($request->query->all())) !== []) {
            abort_unless($user->canUseButton('reports.filter'), 403);
        }

        if ($request->boolean('download')) {
            abort_unless($user->canUseButton('reports.export'), 403);
        }

        $reports = $request->user()->hasAnyRole('admin')
            ? self::REPORTS
            : array_diff_key(self::REPORTS, [
                'staff-activity' => true,
                'failed-verifications' => true,
                'movements' => true,
            ]);

        $filters = $request->validate([
            'report' => ['nullable', Rule::in(array_keys($reports))],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'program_id' => ['nullable', 'integer', 'exists:subsidy_programs,id'],
        ]);
        $report = $filters['report'] ?? 'daily-distributions';

        if ($report === 'not-received' && empty($filters['program_id'])) {
            throw ValidationException::withMessages([
                'program_id' => 'Select a subsidy program to identify farmers who have not received it.',
            ]);
        }

        $headers = $this->headers($report);
        $query = $this->reportQuery($report, $filters);

        if ($request->boolean('download')) {
            return $this->download($query, $headers, $report);
        }

        return view('reports.index', [
            'reports' => $reports,
            'report' => $report,
            'filters' => $filters,
            'headers' => $headers,
            'rows' => $query->paginate(30)->withQueryString(),
            'programs' => DB::table('subsidy_programs')->orderBy('program_name')->get(['id', 'program_name']),
            'faceVerificationAvailable' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    private function headers(string $report): array
    {
        return match ($report) {
            'daily-distributions', 'monthly-distributions' => [
                'period' => 'Period',
                'transactions' => 'Transactions',
                'farmers' => 'Farmers',
                'quantity' => 'Quantity distributed',
            ],
            'farmers' => [
                'farmer_id' => 'Farmer ID',
                'name' => 'Farmer',
                'barangay' => 'Barangay',
                'municipality' => 'Municipality',
                'rsbsa_number' => 'RSBSA number',
                'verification_status' => 'Verification',
                'status' => 'Profile status',
                'created_at' => 'Registered at',
            ],
            'received' => [
                'transaction_number' => 'Transaction',
                'farmer_id' => 'Farmer ID',
                'farmer_name' => 'Farmer',
                'program_name' => 'Program',
                'transaction_status' => 'Status',
                'distributed_at' => 'Distributed at',
                'processed_by' => 'Processed by',
            ],
            'not-received' => [
                'farmer_id' => 'Farmer ID',
                'name' => 'Farmer',
                'barangay' => 'Barangay',
                'municipality' => 'Municipality',
                'rsbsa_number' => 'RSBSA number',
                'verification_status' => 'Verification',
            ],
            'inventory' => [
                'supply_code' => 'Supply code',
                'supply_name' => 'Supply',
                'category' => 'Category',
                'quantity' => 'On hand',
                'minimum_stock' => 'Minimum stock',
                'status' => 'Item status',
            ],
            'movements' => [
                'created_at' => 'Date',
                'supply_code' => 'Supply code',
                'supply_name' => 'Supply',
                'movement_type' => 'Movement',
                'quantity' => 'Quantity',
                'reference_type' => 'Reference',
                'performed_by' => 'Performed by',
                'remarks' => 'Notes',
            ],
            'staff-activity' => [
                'created_at' => 'Date',
                'user_name' => 'Staff member',
                'action' => 'Action',
                'module' => 'Module',
                'description' => 'Activity',
                'ip_address' => 'IP address',
            ],
            'failed-verifications' => [
                'created_at' => 'Date',
                'user_name' => 'Staff member',
                'description' => 'Failure details',
                'record_id' => 'Farmer record ID',
                'ip_address' => 'IP address',
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function reportQuery(string $report, array $filters): Builder
    {
        if (in_array($report, ['daily-distributions', 'monthly-distributions'], true)) {
            $periodExpression = $report === 'monthly-distributions'
                ? 'SUBSTR(distributions.distributed_at, 1, 7)'
                : 'DATE(distributions.distributed_at)';

            return DB::table('distributions')
                ->leftJoin('distribution_items', 'distribution_items.distribution_id', '=', 'distributions.id')
                ->where('distributions.transaction_status', 'completed')
                ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('distributions.distributed_at', '>=', $date))
                ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('distributions.distributed_at', '<=', $date))
                ->selectRaw("{$periodExpression} AS period, COUNT(DISTINCT distributions.id) AS transactions, COUNT(DISTINCT distributions.farmer_id) AS farmers, COALESCE(SUM(distribution_items.quantity), 0) AS quantity")
                ->groupBy('period')
                ->orderByDesc('period');
        }

        if ($report === 'farmers') {
            return DB::table('farmers')
                ->leftJoin('rsbsa_records', 'rsbsa_records.farmer_id', '=', 'farmers.id')
                ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('farmers.created_at', '>=', $date))
                ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('farmers.created_at', '<=', $date))
                ->selectRaw('farmers.farmer_id, '.$this->farmerNameExpression().' AS name, farmers.barangay, farmers.municipality, rsbsa_records.rsbsa_number, rsbsa_records.verification_status, farmers.status, farmers.created_at')
                ->orderBy('farmers.last_name')
                ->orderBy('farmers.first_name');
        }

        if ($report === 'not-received') {
            return DB::table('farmers')
                ->leftJoin('rsbsa_records', 'rsbsa_records.farmer_id', '=', 'farmers.id')
                ->whereNotExists(function ($query) use ($filters): void {
                    $query->selectRaw('1')
                        ->from('distributions')
                        ->whereColumn('distributions.farmer_id', 'farmers.id')
                        ->where('distributions.subsidy_program_id', $filters['program_id'])
                        ->where('distributions.transaction_status', 'completed');
                })
                ->selectRaw('farmers.farmer_id, '.$this->farmerNameExpression().' AS name, farmers.barangay, farmers.municipality, rsbsa_records.rsbsa_number, rsbsa_records.verification_status')
                ->orderBy('farmers.last_name')
                ->orderBy('farmers.first_name');
        }

        if ($report === 'inventory') {
            return DB::table('supplies')
                ->leftJoin('inventories', 'inventories.supply_id', '=', 'supplies.id')
                ->selectRaw('supplies.supply_code, supplies.supply_name, supplies.category, COALESCE(inventories.quantity, 0) AS quantity, supplies.minimum_stock, supplies.status')
                ->orderBy('supplies.supply_name');
        }

        if ($report === 'movements') {
            return DB::table('inventory_movements')
                ->join('supplies', 'supplies.id', '=', 'inventory_movements.supply_id')
                ->leftJoin('users', 'users.id', '=', 'inventory_movements.performed_by')
                ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('inventory_movements.created_at', '>=', $date))
                ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('inventory_movements.created_at', '<=', $date))
                ->selectRaw('inventory_movements.created_at, supplies.supply_code, supplies.supply_name, inventory_movements.movement_type, inventory_movements.quantity, inventory_movements.reference_type, users.name AS performed_by, inventory_movements.remarks')
                ->orderByDesc('inventory_movements.created_at');
        }

        $auditQuery = DB::table('audit_logs')
            ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('audit_logs.created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('audit_logs.created_at', '<=', $date));

        if ($report === 'failed-verifications') {
            return $auditQuery
                ->where('audit_logs.action', 'FAILED_VERIFICATION')
                ->selectRaw('audit_logs.created_at, users.name AS user_name, audit_logs.description, audit_logs.record_id, audit_logs.ip_address')
                ->orderByDesc('audit_logs.created_at');
        }

        if ($report === 'staff-activity') {
            return $auditQuery
                ->where('users.role', 'technician')
                ->selectRaw('audit_logs.created_at, users.name AS user_name, audit_logs.action, audit_logs.module, audit_logs.description, audit_logs.ip_address')
                ->orderByDesc('audit_logs.created_at');
        }

        return DB::table('distributions')
            ->join('farmers', 'farmers.id', '=', 'distributions.farmer_id')
            ->leftJoin('subsidy_programs', 'subsidy_programs.id', '=', 'distributions.subsidy_program_id')
            ->leftJoin('users as processors', 'processors.id', '=', 'distributions.processed_by')
            ->where('distributions.transaction_status', 'completed')
            ->when($filters['program_id'] ?? null, fn ($query, $programId) => $query->where('distributions.subsidy_program_id', $programId))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('distributions.distributed_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('distributions.distributed_at', '<=', $date))
            ->selectRaw('distributions.transaction_number, farmers.farmer_id, '.$this->farmerNameExpression().' AS farmer_name, subsidy_programs.program_name, distributions.transaction_status, distributions.distributed_at, processors.name AS processed_by')
            ->orderByDesc('distributions.distributed_at');
    }

    private function farmerNameExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "TRIM(farmers.first_name || ' ' || farmers.last_name)"
            : "TRIM(CONCAT(farmers.first_name, ' ', farmers.last_name))";
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function download(Builder $query, array $headers, string $report): StreamedResponse
    {
        return response()->streamDownload(function () use ($query, $headers): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                throw new RuntimeException('The report CSV could not be generated.');
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_values($headers), ',', '"', '');

            foreach ($query->cursor() as $row) {
                $values = [];

                foreach (array_keys($headers) as $key) {
                    $value = (string) ($row->{$key} ?? '');
                    $values[] = preg_match('/^[=+\-@]/u', $value) === 1 ? "'".$value : $value;
                }

                fputcsv($output, $values, ',', '"', '');
            }

            fclose($output);
        }, "agriqr-{$report}-".now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
