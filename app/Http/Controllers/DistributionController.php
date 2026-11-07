<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Distribution;
use App\Models\DistributionItem;
use App\Models\Farmer;
use App\Models\FarmerSeasonGroupAssignment;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\ProgramSupplyGroupRate;
use App\Models\SubsidyProgram;
use App\Models\User;
use App\Services\SystemConfiguration;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DistributionController extends Controller
{
    public function __construct(private SystemConfiguration $configuration) {}

    public function index(): View
    {
        $distributions = Distribution::with(['farmer', 'program', 'processor', 'season', 'seasonGroup', 'items.supply'])
            ->latest('distributed_at')
            ->paginate(15);

        return view('distributions.index', compact('distributions'));
    }

    public function create(): View
    {
        $settings = $this->configuration->distributionSettings();
        $farmerCode = request()->query('farmer');
        $farmer = $farmerCode
            ? Farmer::with([
                'rsbsaRecord',
                'currentSeasonGroupAssignments.seasonGroup.season',
            ])->where('farmer_id', $farmerCode)->first()
            : null;
        $hasFarmerPhoto = $farmer !== null
            && filled($farmer->photo_path)
            && Storage::disk('local')->exists($farmer->photo_path);
        $farmerHasFarmSize = $farmer !== null && $this->eligibleHectares($farmer) !== null;
        $eligibleGroupIds = $farmer?->currentSeasonGroupAssignments
            ->filter(fn (FarmerSeasonGroupAssignment $assignment): bool => $assignment->seasonGroup->season->status === 'active')
            ->pluck('season_group_id')
            ->all() ?? [];
        $programs = SubsidyProgram::with([
            'supplies.inventory',
            'seasonGroups.season',
            'supplyGroupRates',
        ])
            ->when($settings['require_active_program'], fn ($query) => $query
                ->where('status', 'active')
                ->where(fn ($query) => $query->whereNull('start_date')->orWhereDate('start_date', '<=', today()))
                ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', today())))
            ->when($farmer, fn ($query) => $query->whereHas(
                'seasonGroups',
                fn ($query) => $query->whereIn('season_groups.id', $eligibleGroupIds)
                    ->whereHas('season', fn ($query) => $query->where('status', 'active')),
            ))
            ->orderBy('program_name')
            ->get();
        $distributionEligibilityOptions = collect();
        $distributionSupplyOptions = collect();

        if ($farmer) {
            foreach ($farmer->currentSeasonGroupAssignments as $assignment) {
                if ($assignment->seasonGroup->season->status !== 'active') {
                    continue;
                }

                foreach ($programs as $program) {
                    $programGroup = $program->seasonGroups->firstWhere('id', $assignment->season_group_id);

                    if (! $programGroup || ($program->season_id && $program->season_id !== $assignment->seasonGroup->season_id)) {
                        continue;
                    }

                    $eligibleHectares = $this->eligibleHectares($farmer);
                    $issuedSupplyIds = DistributionItem::query()
                        ->whereHas('distribution', fn ($query) => $query
                            ->where('farmer_id', $farmer->id)
                            ->where('season_id', $assignment->seasonGroup->season_id)
                            ->where('transaction_status', 'completed'))
                        ->pluck('supply_id')
                        ->map(fn ($supplyId): int => (int) $supplyId)
                        ->all();
                    $groupSupplyOptions = collect();

                    foreach ($program->supplies as $supply) {
                        $rate = $program->supplyGroupRates->first(
                            fn (ProgramSupplyGroupRate $mapping): bool => $mapping->supply_id === $supply->id
                                && $mapping->season_group_id === $assignment->season_group_id,
                        );

                        if (! $rate) {
                            continue;
                        }

                        $farmHectares = $eligibleHectares === null ? 0 : round($eligibleHectares, 4);
                        $calculatedQuantity = $this->calculatedQuantity(
                            (float) $rate->quantity_per_hectare,
                            $farmHectares,
                        );
                        $quantityCap = min($calculatedQuantity, (float) $rate->maximum_quantity_per_farmer);
                        $allowedQuantity = min(
                            $quantityCap,
                            $settings['max_distribution_quantity'] ?? PHP_FLOAT_MAX,
                        );
                        $available = max(
                            0,
                            (float) ($supply->inventory?->quantity ?? 0)
                                - (float) ($supply->inventory?->reserved_quantity ?? 0),
                        );
                        $groupSupplyOptions->push([
                            'program_id' => $program->id,
                            'assignment_id' => $assignment->id,
                            'supply_id' => $supply->id,
                            'rate_per_hectare' => (float) $rate->quantity_per_hectare,
                            'calculated_quantity' => $calculatedQuantity,
                            'maximum_quantity' => (float) $rate->maximum_quantity_per_farmer,
                            'quantity_cap' => $quantityCap,
                            'allowed_quantity' => $allowedQuantity,
                            'available_quantity' => $available,
                            'shortage' => max(0, round($allowedQuantity - $available, 2)),
                            'already_issued' => in_array((int) $supply->id, $issuedSupplyIds, true),
                            'requires_verified_rsbsa' => (bool) $program->requires_verified_rsbsa,
                        ]);
                    }

                    if ($program->supplies->isEmpty() || $groupSupplyOptions->count() !== $program->supplies->count()) {
                        continue;
                    }

                    $distributionEligibilityOptions->push([
                        'program_id' => $program->id,
                        'assignment_id' => $assignment->id,
                        'season_id' => $assignment->seasonGroup->season_id,
                        'season_group_id' => $assignment->season_group_id,
                        'label' => $assignment->seasonGroup->season->name.' · '.str($assignment->seasonGroup->seed_type)->title(),
                        'eligible_hectares' => $eligibleHectares,
                    ]);
                    $distributionSupplyOptions = $distributionSupplyOptions->merge($groupSupplyOptions);
                }
            }

            $eligibleProgramIds = $distributionEligibilityOptions->pluck('program_id')->unique();
            $programs = $programs->whereIn('id', $eligibleProgramIds)->values();
        }

        return view('distributions.create', [
            'programs' => $programs,
            'farmer' => $farmer,
            'hasFarmerPhoto' => $hasFarmerPhoto,
            'farmerHasFarmSize' => $farmerHasFarmSize,
            'distributionEligibilityOptions' => $distributionEligibilityOptions,
            'distributionSupplyOptions' => $distributionSupplyOptions,
            'requireActiveFarmer' => $settings['require_active_farmer'],
            'requireVerifiedRsbsa' => $settings['require_verified_rsbsa'],
        ]);
    }

    public function lookupFarmer(Request $request): RedirectResponse
    {
        $data = $request->validate(['farmer_code' => ['required', 'string', 'max:30']]);
        $farmer = Farmer::where('farmer_id', $data['farmer_code'])->first();

        if (! $farmer) {
            $this->recordVerificationFailure($request, 'QR lookup did not match a farmer record.');

            return back()->withErrors(['farmer_code' => 'No farmer matches that QR code.'])->withInput();
        }

        return redirect()->route('distributions.create', ['farmer' => $farmer->farmer_id]);
    }

    public function store(Request $request): RedirectResponse
    {
        $items = $request->input('items', []);

        if (! $request->user()->canUseButton('distributions.items') && is_array($items) && count($items) > 1) {
            abort(403);
        }

        $data = $request->validate([
            'farmer_code' => ['required', 'string', 'max:30'],
            'subsidy_program_id' => ['required', 'integer', 'exists:subsidy_programs,id'],
            'farmer_season_group_assignment_id' => [
                'required', 'integer', 'exists:farmer_season_group_assignments,id',
            ],
            'face_verified' => ['accepted'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.supply_id' => ['required', 'integer', 'distinct', 'exists:supplies,id'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999'],
            'items.*.reduction_reason' => ['nullable', 'string', 'max:500'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $settings = $this->configuration->distributionSettings();

        try {
            $distribution = DB::transaction(function () use ($data, $request, $settings) {
                $farmer = Farmer::with('rsbsaRecord')
                    ->where('farmer_id', $data['farmer_code'])
                    ->lockForUpdate()
                    ->first();

                if (! $farmer) {
                    throw ValidationException::withMessages([
                        'farmer_code' => 'This farmer could not be found.',
                    ]);
                }

                if (! filled($farmer->photo_path) || ! Storage::disk('local')->exists($farmer->photo_path)) {
                    throw ValidationException::withMessages([
                        'face_verified' => 'A current farmer photo is required before face verification can be completed.',
                    ]);
                }

                if ($farmer->status === 'deceased' || $farmer->deceased_at !== null) {
                    throw ValidationException::withMessages([
                        'farmer_code' => 'Distribution is blocked because this farmer is marked deceased.',
                    ]);
                }

                if ($settings['require_active_farmer'] && $farmer->status !== 'active') {
                    throw ValidationException::withMessages([
                        'farmer_code' => 'Only active farmers may receive a distribution.',
                    ]);
                }

                $program = SubsidyProgram::with(['supplies', 'seasonGroups.season', 'supplyGroupRates'])
                    ->whereKey($data['subsidy_program_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $program) {
                    throw ValidationException::withMessages([
                        'subsidy_program_id' => 'This subsidy program could not be found.',
                    ]);
                }

                if (($program->requires_verified_rsbsa || $settings['require_verified_rsbsa'])
                    && (! $farmer->rsbsaRecord
                        || $farmer->rsbsaRecord->registration_status !== 'registered'
                        || $farmer->rsbsaRecord->verification_status !== 'verified')) {
                    throw ValidationException::withMessages([
                        'farmer_code' => 'This program requires a registered and verified RSBSA record.',
                    ]);
                }

                if ($settings['require_active_program'] && ($program->status !== 'active'
                    || ($program->start_date && $program->start_date->greaterThan(today()))
                    || ($program->end_date && $program->end_date->lessThan(today())))) {
                    throw ValidationException::withMessages([
                        'subsidy_program_id' => 'This subsidy program is not active for the current date.',
                    ]);
                }

                $assignment = FarmerSeasonGroupAssignment::with('seasonGroup.season')
                    ->whereKey($data['farmer_season_group_assignment_id'])
                    ->where('farmer_id', $farmer->id)
                    ->whereNull('ended_at')
                    ->lockForUpdate()
                    ->first();

                if (! $assignment || $assignment->seasonGroup->season->status !== 'active') {
                    throw ValidationException::withMessages([
                        'farmer_season_group_assignment_id' => 'The farmer must have a current assignment in an active season.',
                    ]);
                }

                $programGroup = $program->seasonGroups->firstWhere('id', $assignment->season_group_id);

                if (! $programGroup
                    || $programGroup->season->status !== 'active'
                    || ($program->season_id && $program->season_id !== $assignment->seasonGroup->season_id)) {
                    throw ValidationException::withMessages([
                        'subsidy_program_id' => 'This program is not available for the farmer’s current season group.',
                    ]);
                }

                $eligibleHectares = $this->eligibleHectares($farmer);
                if ($eligibleHectares === null) {
                    throw ValidationException::withMessages([
                        'farmer_code' => 'A valid RSBSA farm size is required to calculate supply quantity limits.',
                    ]);
                }
                $eligibleHectares = round($eligibleHectares, 4);

                $items = collect($data['items'])->map(
                    fn (array $item, int $index): array => [...$item, '_index' => $index],
                );
                $programSupplies = $program->supplies->keyBy('id');
                $preparedItems = [];
                $assistanceValueCents = 0;

                foreach ($items as $item) {
                    $supplyId = $item['supply_id'];
                    $supply = $programSupplies->get($supplyId);
                    $quantity = (float) $item['quantity'];
                    if (! $supply || $supply->status !== 'active') {
                        throw ValidationException::withMessages([
                            'items' => 'A selected supply is inactive or is not included in this program.',
                        ]);
                    }

                    $groupRate = $program->supplyGroupRates->first(
                        fn (ProgramSupplyGroupRate $mapping): bool => (int) $mapping->supply_id === (int) $supplyId
                            && (int) $mapping->season_group_id === $assignment->season_group_id,
                    );

                    if (! $groupRate) {
                        throw ValidationException::withMessages([
                            'items' => "A quantity rate and per-farmer maximum are not configured for {$supply->supply_name} and this farmer group.",
                        ]);
                    }

                    $alreadyIssued = DistributionItem::query()
                        ->where('supply_id', $supply->id)
                        ->whereHas('distribution', fn ($query) => $query
                            ->where('farmer_id', $farmer->id)
                            ->where('season_id', $assignment->seasonGroup->season_id)
                            ->where('transaction_status', 'completed'))
                        ->exists();

                    if ($alreadyIssued) {
                        throw ValidationException::withMessages([
                            "items.{$item['_index']}.supply_id" => "{$supply->supply_name} has already been issued to this farmer for this season.",
                        ]);
                    }

                    $calculatedQuantity = $this->calculatedQuantity(
                        (float) $groupRate->quantity_per_hectare,
                        $eligibleHectares,
                    );
                    $quantityCap = min(
                        $calculatedQuantity,
                        (float) $groupRate->maximum_quantity_per_farmer,
                    );
                    $allowedQuantity = min(
                        $quantityCap,
                        $settings['max_distribution_quantity'] ?? PHP_FLOAT_MAX,
                    );

                    if ($quantityCap <= 0) {
                        throw ValidationException::withMessages([
                            "items.{$item['_index']}.quantity" => "The calculated quantity for {$supply->supply_name} is below the supported distribution precision.",
                        ]);
                    }

                    if ($quantity > $quantityCap) {
                        throw ValidationException::withMessages([
                            "items.{$item['_index']}.quantity" => 'The requested quantity for '.$supply->supply_name
                                    .' exceeds the calculated maximum of '.number_format($quantityCap, 2)
                                    .($supply->unit ? " {$supply->unit}" : '').'.',
                        ]);
                    }

                    if ($quantity > $allowedQuantity) {
                        throw ValidationException::withMessages([
                            "items.{$item['_index']}.quantity" => 'The requested quantity exceeds the system limit of '.number_format($allowedQuantity, 2)
                                    .($supply->unit ? " {$supply->unit}" : '')." for {$supply->supply_name}.",
                        ]);
                    }

                    $reductionReason = trim((string) ($item['reduction_reason'] ?? ''));
                    if ($quantity < $quantityCap && $reductionReason === '') {
                        throw ValidationException::withMessages([
                            "items.{$item['_index']}.reduction_reason" => 'A reason is required when the issued quantity is reduced.',
                        ]);
                    }

                    $unitValue = (float) $supply->pivot->unit_value;
                    $lineValueCents = (int) round($quantity * $unitValue * 100);
                    $assistanceValueCents += $lineValueCents;
                    $preparedItems[] = [
                        'supply' => $supply,
                        'inventory' => null,
                        'quantity' => $quantity,
                        'unit_value' => $unitValue,
                        'line_value' => $lineValueCents / 100,
                        'quantity_rate_per_hectare' => $groupRate->quantity_per_hectare,
                        'quantity_cap' => $quantityCap,
                        'maximum_quantity_per_farmer' => $groupRate->maximum_quantity_per_farmer,
                        'computed_quantity' => $calculatedQuantity,
                        'reduction_reason' => $quantity < $quantityCap ? $reductionReason : null,
                    ];
                }

                $inventories = Inventory::whereIn(
                    'supply_id',
                    collect($preparedItems)->map(fn (array $item): int => (int) $item['supply']->id),
                )
                    ->orderBy('supply_id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('supply_id');

                foreach ($preparedItems as &$preparedItem) {
                    $inventory = $inventories->get($preparedItem['supply']->id);
                    $available = $inventory
                        ? (float) $inventory->quantity - (float) $inventory->reserved_quantity
                        : 0;

                    if (! $inventory) {
                        throw ValidationException::withMessages([
                            'items' => "No inventory record exists for {$preparedItem['supply']->supply_name}.",
                        ]);
                    }

                    if ($preparedItem['quantity'] > $available) {
                        $shortage = $preparedItem['quantity'] - $available;
                        throw ValidationException::withMessages([
                            'items' => "Insufficient stock for {$preparedItem['supply']->supply_name}: "
                                .number_format($available, 2).' available; shortage '
                                .number_format($shortage, 2).'. Reduce the quantity and provide a reason.',
                        ]);
                    }

                    $preparedItem['inventory'] = $inventory;
                }
                unset($preparedItem);

                $transactionNumber = 'AGR-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(6));
                $distribution = Distribution::create([
                    'transaction_number' => $transactionNumber,
                    'farmer_id' => $farmer->id,
                    'subsidy_program_id' => $program->id,
                    'season_id' => $assignment->seasonGroup->season_id,
                    'season_group_id' => $assignment->season_group_id,
                    'processed_by' => $request->user()->id,
                    'eligibility_status' => 'eligible',
                    'transaction_status' => 'completed',
                    'remarks' => $data['remarks'] ?? null,
                    'distributed_at' => now(),
                    'rate_per_hectare' => null,
                    'eligible_hectares' => round($eligibleHectares, 4),
                    'face_verified_at' => now(),
                    'face_verified_by' => $request->user()->id,
                    'value_cap' => null,
                    'assistance_value' => $assistanceValueCents / 100,
                ]);

                foreach ($preparedItems as $preparedItem) {
                    $supply = $preparedItem['supply'];
                    $inventory = $preparedItem['inventory'];
                    $quantity = $preparedItem['quantity'];
                    $inventory->decrement('quantity', $quantity);
                    $inventory->update(['last_updated_by' => $request->user()->id]);
                    DistributionItem::create([
                        'distribution_id' => $distribution->id,
                        'supply_id' => $supply->id,
                        'quantity' => $quantity,
                        'quantity_rate_per_hectare' => $preparedItem['quantity_rate_per_hectare'],
                        'quantity_cap' => $preparedItem['quantity_cap'],
                        'maximum_quantity_per_farmer' => $preparedItem['maximum_quantity_per_farmer'],
                        'computed_quantity' => $preparedItem['computed_quantity'],
                        'reduction_reason' => $preparedItem['reduction_reason'],
                        'unit' => $supply->unit,
                        'unit_value' => $preparedItem['unit_value'],
                        'line_value' => $preparedItem['line_value'],
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
        } catch (ValidationException $exception) {
            if (array_key_exists('farmer_code', $exception->errors())) {
                $this->recordVerificationFailure(
                    $request,
                    'Distribution eligibility check failed.',
                    Farmer::where('farmer_id', $data['farmer_code'])->value('id'),
                );
            }

            throw $exception;
        }

        return redirect()->route('distributions.show', $distribution)->with('success', 'Distribution recorded and stock updated.');
    }

    public function show(Distribution $distribution): View
    {
        $distribution->load(['farmer.rsbsaRecord', 'program', 'processor', 'season', 'seasonGroup', 'items.supply']);

        return view('distributions.show', compact('distribution'));
    }

    private function eligibleHectares(Farmer $farmer): ?float
    {
        $farmSize = (float) ($farmer->rsbsaRecord?->farm_size ?? 0);

        if ($farmSize <= 0) {
            return null;
        }

        return match ($farmer->rsbsaRecord?->farm_size_unit) {
            'hectare' => $farmSize,
            'sqm' => $farmSize / 10000,
            'acre' => $farmSize * 0.40468564224,
            default => null,
        };
    }

    private function calculatedQuantity(float $ratePerHectare, float $farmSizeHectares): float
    {
        return floor($ratePerHectare * $farmSizeHectares * 100) / 100;
    }

    private function recordVerificationFailure(Request $request, string $description, ?int $farmerId = null): void
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'FAILED_VERIFICATION',
            'module' => 'DISTRIBUTION',
            'record_id' => $farmerId,
            'description' => $description,
            'ip_address' => $request->ip(),
        ]);
    }
}
