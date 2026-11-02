<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Distribution;
use App\Models\DistributionItem;
use App\Models\Farmer;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\SubsidyProgram;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DistributionController extends Controller
{
    public function index(): View
    {
        $distributions = Distribution::with(['farmer', 'program', 'processor', 'items.supply'])
            ->latest('distributed_at')
            ->paginate(15);

        return view('distributions.index', compact('distributions'));
    }

    public function create(): View
    {
        $programs = SubsidyProgram::with(['supplies.inventory'])
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('start_date')->orWhereDate('start_date', '<=', today()))
            ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', today()))
            ->orderBy('program_name')
            ->get();
        $farmerCode = request()->query('farmer');
        $farmer = $farmerCode
            ? Farmer::with('rsbsaRecord')->where('farmer_id', $farmerCode)->first()
            : null;

        return view('distributions.create', compact('programs', 'farmer'));
    }

    public function lookupFarmer(Request $request): RedirectResponse
    {
        $data = $request->validate(['farmer_code' => ['required', 'string', 'max:30']]);
        $farmer = Farmer::where('farmer_id', $data['farmer_code'])->first();

        if (! $farmer) {
            return back()->withErrors(['farmer_code' => 'No farmer matches that QR code.'])->withInput();
        }

        return redirect()->route('distributions.create', ['farmer' => $farmer->farmer_id]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'farmer_code' => ['required', 'string', 'max:30'],
            'subsidy_program_id' => ['required', 'integer', 'exists:subsidy_programs,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.supply_id' => ['required', 'integer', 'distinct', 'exists:supplies,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $distribution = DB::transaction(function () use ($data, $request) {
            $farmer = Farmer::with('rsbsaRecord')
                ->where('farmer_id', $data['farmer_code'])
                ->lockForUpdate()
                ->first();

            if (! $farmer || $farmer->status !== 'active') {
                throw ValidationException::withMessages([
                    'farmer_code' => 'This farmer is not active or could not be found.',
                ]);
            }

            $record = $farmer->rsbsaRecord;
            if (! $record || $record->registration_status !== 'registered' || $record->verification_status !== 'verified') {
                throw ValidationException::withMessages([
                    'farmer_code' => 'A registered and verified RSBSA record is required for distribution.',
                ]);
            }

            $program = SubsidyProgram::with('supplies')
                ->whereKey($data['subsidy_program_id'])
                ->lockForUpdate()
                ->first();

            if (! $program || $program->status !== 'active'
                || ($program->start_date && $program->start_date->greaterThan(today()))
                || ($program->end_date && $program->end_date->lessThan(today()))) {
                throw ValidationException::withMessages([
                    'subsidy_program_id' => 'This subsidy program is not active for the current date.',
                ]);
            }

            $transactionNumber = 'AGR-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(6));
            $distribution = Distribution::create([
                'transaction_number' => $transactionNumber,
                'farmer_id' => $farmer->id,
                'subsidy_program_id' => $program->id,
                'processed_by' => $request->user()->id,
                'eligibility_status' => 'eligible',
                'transaction_status' => 'completed',
                'remarks' => $data['remarks'] ?? null,
                'distributed_at' => now(),
            ]);

            $items = collect($data['items'])->keyBy('supply_id');
            $programSupplies = $program->supplies->keyBy('id');
            $inventories = Inventory::whereIn('supply_id', $items->keys())
                ->orderBy('supply_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('supply_id');

            foreach ($items as $supplyId => $item) {
                $supply = $programSupplies->get($supplyId);
                $inventory = $inventories->get($supplyId);
                $quantity = (float) $item['quantity'];
                $available = $inventory
                    ? (float) $inventory->quantity - (float) $inventory->reserved_quantity
                    : 0;
                $maximum = $supply?->pivot?->maximum_quantity;

                if (! $supply || $supply->status !== 'active' || ! $inventory) {
                    throw ValidationException::withMessages([
                        'items' => 'A selected supply is inactive or is not included in this program.',
                    ]);
                }

                if ($quantity > $available) {
                    throw ValidationException::withMessages([
                        'items' => "Insufficient available stock for {$supply->supply_name}.",
                    ]);
                }

                if ($maximum !== null && $quantity > (float) $maximum) {
                    throw ValidationException::withMessages([
                        'items' => "The requested quantity exceeds the program limit for {$supply->supply_name}.",
                    ]);
                }

                $inventory->decrement('quantity', $quantity);
                $inventory->update(['last_updated_by' => $request->user()->id]);
                DistributionItem::create([
                    'distribution_id' => $distribution->id,
                    'supply_id' => $supply->id,
                    'quantity' => $quantity,
                    'unit' => $supply->unit,
                ]);
                InventoryMovement::create([
                    'supply_id' => $supply->id,
                    'movement_type' => 'OUT',
                    'quantity' => $quantity,
                    'reference_type' => 'DISTRIBUTION',
                    'reference_id' => $distribution->id,
                    'performed_by' => $request->user()->id,
                    'remarks' => "Distributed to farmer {$farmer->farmer_id} ({$transactionNumber}).",
                ]);
            }

            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'CREATE',
                'module' => 'DISTRIBUTION',
                'record_id' => $distribution->id,
                'description' => "Recorded distribution {$transactionNumber} for farmer {$farmer->farmer_id}.",
                'ip_address' => $request->ip(),
            ]);

            return $distribution;
        });

        return redirect()->route('distributions.show', $distribution)->with('success', 'Distribution recorded and stock updated.');
    }

    public function show(Distribution $distribution): View
    {
        $distribution->load(['farmer.rsbsaRecord', 'program', 'processor', 'items.supply']);

        return view('distributions.show', compact('distribution'));
    }
}
