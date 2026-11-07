<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Supply;
use App\Models\User;
use App\Services\InventoryCsvImporter;
use App\Services\SystemConfiguration;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplyController extends Controller
{
    public function __construct(private SystemConfiguration $configuration) {}

    public function importForm(Request $request): View
    {
        return view('supplies.import', [
            'headers' => InventoryCsvImporter::HEADERS,
            'categories' => $this->configuration->supplyCategories(),
            'report' => $request->session()->get('inventoryImportReport'),
        ]);
    }

    public function downloadTemplate(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                throw new RuntimeException('The inventory CSV template could not be generated.');
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, InventoryCsvImporter::HEADERS, ',', '"', '');
            fclose($output);
        }, 'agriqr-inventory-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importCsv(Request $request, InventoryCsvImporter $importer): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'extensions:csv,txt', 'max:5120'],
        ]);
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $report = $importer->import($validated['file'], $user, $request->ip(), $this->configuration->supplyCategories());

        return redirect()->route('supplies.import')
            ->with('inventoryImportReport', $report);
    }

    public function index(Request $request): View
    {
        if ($request->query->has('search')) {
            abort_unless($request->user()->canUseButton('inventory.search'), 403);
        }

        $search = trim((string) $request->query('search', ''));
        $supplies = Supply::with('inventory')
            ->when($search !== '', fn ($query) => $query->where(
                fn ($query) => $query->where('supply_code', 'like', "%{$search}%")
                    ->orWhere('supply_name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
            ))
            ->orderBy('supply_name')
            ->paginate(12)
            ->withQueryString();

        return view('supplies.index', compact('supplies', 'search'));
    }

    public function create(): View
    {
        return view('supplies.form', [
            'supply' => new Supply,
            'categories' => $this->configuration->supplyCategories(),
            'unitOptionalCategories' => Supply::UNIT_OPTIONAL_CATEGORIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedSupply($request);

        DB::transaction(function () use ($data, $request) {
            $supply = Supply::create($data);
            $supply->inventory()->create(['last_updated_by' => $request->user()->id]);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'CREATE',
                'module' => 'SUPPLY',
                'record_id' => $supply->id,
                'description' => "Created supply item {$supply->supply_code}.",
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('supplies.index')->with('success', 'Supply item created.');
    }

    public function edit(Supply $supply): View
    {
        return view('supplies.form', [
            'supply' => $supply,
            'categories' => array_values(array_unique([
                ...$this->configuration->supplyCategories(),
                $supply->category,
            ])),
            'unitOptionalCategories' => Supply::UNIT_OPTIONAL_CATEGORIES,
        ]);
    }

    public function update(Request $request, Supply $supply): RedirectResponse
    {
        DB::transaction(function () use ($request, $supply) {
            $supply->update($this->validatedSupply($request, $supply));
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'UPDATE',
                'module' => 'SUPPLY',
                'record_id' => $supply->id,
                'description' => "Updated supply item {$supply->supply_code}.",
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('supplies.index')->with('success', 'Supply item updated.');
    }

    public function stockIn(Request $request, Supply $supply): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'supplier' => ['nullable', 'string', 'max:150'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($data, $request, $supply) {
            $inventory = Inventory::where('supply_id', $supply->id)->lockForUpdate()->firstOrFail();
            $inventory->increment('quantity', $data['quantity']);
            $inventory->update(['last_updated_by' => $request->user()->id]);

            InventoryMovement::create([
                'supply_id' => $supply->id,
                'movement_type' => 'IN',
                'quantity' => $data['quantity'],
                'reference_type' => 'STOCK_IN',
                'performed_by' => $request->user()->id,
                'remarks' => collect([$data['reference_no'] ?? null, $data['supplier'] ?? null, $data['remarks'] ?? null])
                    ->filter()
                    ->implode(' · ') ?: null,
            ]);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'STOCK_IN',
                'module' => 'SUPPLY',
                'record_id' => $supply->id,
                'description' => "Received {$data['quantity']}".($supply->unit ? " {$supply->unit}" : '')." of {$supply->supply_code}.",
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('supplies.index')->with('success', 'Stock received and inventory updated.');
    }

    private function validatedSupply(Request $request, ?Supply $supply = null): array
    {
        return $request->validate([
            'supply_code' => [
                'required', 'string', 'max:50',
                Rule::unique('supplies', 'supply_code')->ignore($supply),
            ],
            'supply_name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in($this->configuration->supplyCategories())],
            'unit' => [
                Rule::requiredIf(! in_array($request->input('category'), Supply::UNIT_OPTIONAL_CATEGORIES, true)),
                'nullable', 'string', 'max:50',
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'minimum_stock' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
