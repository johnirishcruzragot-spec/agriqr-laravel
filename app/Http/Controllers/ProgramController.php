<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Season;
use App\Models\SeasonGroup;
use App\Models\SubsidyProgram;
use App\Models\Supply;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProgramController extends Controller
{
    public function index(): View
    {
        $programs = SubsidyProgram::withCount('supplies')
            ->with([
                'supplies',
                'season',
                'seasonGroups.season',
                'supplyGroupRates.supply',
                'supplyGroupRates.seasonGroup.season',
            ])
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('programs.index', compact('programs'));
    }

    public function create(): View
    {
        return view('programs.form', [
            'program' => new SubsidyProgram,
            'supplies' => Supply::where('status', 'active')->orderBy('supply_name')->get(),
            'seasons' => Season::where('status', 'active')->orderBy('name')->get(),
            'seasonGroups' => SeasonGroup::with('season')
                ->whereHas('season', fn ($query) => $query->where('status', 'active'))
                ->orderBy('season_id')
                ->orderBy('seed_type')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        DB::transaction(function () use ($data, $request) {
            $program = SubsidyProgram::create([
                ...$data['program'],
                'created_by' => $request->user()->id,
            ]);
            $program->supplies()->sync($data['supplies']);
            $program->seasonGroups()->sync($data['season_groups']);
            $program->supplyGroupRates()->createMany($data['supply_group_rates']);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'CREATE',
                'module' => 'PROGRAM',
                'record_id' => $program->id,
                'description' => "Created subsidy program {$program->program_code}.",
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('programs.index')->with('success', 'Subsidy program created.');
    }

    public function edit(SubsidyProgram $program): View
    {
        $program->load(['supplies', 'seasonGroups', 'supplyGroupRates']);
        $existingSeasonIds = $program->seasonGroups()->select('season_groups.season_id');

        return view('programs.form', [
            'program' => $program,
            'supplies' => Supply::where('status', 'active')->orderBy('supply_name')->get(),
            'seasons' => Season::where('status', 'active')
                ->orWhereIn('id', $existingSeasonIds)
                ->orderBy('name')
                ->get(),
            'seasonGroups' => SeasonGroup::with('season')
                ->whereHas('season', fn ($query) => $query->where('status', 'active'))
                ->orWhereIn('id', $program->seasonGroups()->select('season_groups.id'))
                ->orderBy('season_id')
                ->orderBy('seed_type')
                ->get(),
        ]);
    }

    public function update(Request $request, SubsidyProgram $program): RedirectResponse
    {
        $data = $this->validatedData($request, $program);

        DB::transaction(function () use ($data, $program, $request) {
            $program->update($data['program']);
            $program->supplies()->sync($data['supplies']);
            $program->seasonGroups()->sync($data['season_groups']);
            $program->supplyGroupRates()->delete();
            $program->supplyGroupRates()->createMany($data['supply_group_rates']);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'UPDATE',
                'module' => 'PROGRAM',
                'record_id' => $program->id,
                'description' => "Updated subsidy program {$program->program_code}.",
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('programs.index')->with('success', 'Subsidy program updated.');
    }

    private function validatedData(Request $request, ?SubsidyProgram $program = null): array
    {
        $validated = $request->validate([
            'program_code' => [
                'required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('subsidy_programs', 'program_code')->ignore($program),
            ],
            'program_name' => ['required', 'string', 'max:150'],
            'season_id' => ['required', 'integer', Rule::exists('seasons', 'id')],
            'requires_verified_rsbsa' => ['sometimes', 'boolean'],
            'subsidy_type' => ['required', Rule::in(['in_kind', 'hybrid', 'embedded'])],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'eligibility_rules' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:draft,active,paused,completed,archived'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.supply_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('supplies', 'id')->where('status', 'active'),
            ],
            'items.*.unit_value' => ['nullable', 'numeric', 'gte:0', 'max:999999999'],
            'season_group_ids' => ['required', 'array', 'min:1'],
            'season_group_ids.*' => ['required', 'integer', 'distinct', Rule::exists('season_groups', 'id')],
            'rates' => ['required', 'array', 'min:1'],
            'rates.*.supply_id' => ['required', 'integer', Rule::exists('supplies', 'id')],
            'rates.*.season_group_id' => ['required', 'integer', Rule::exists('season_groups', 'id')],
            'rates.*.quantity_per_hectare' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'rates.*.maximum_quantity_per_farmer' => ['required', 'numeric', 'gt:0', 'max:999999999'],
        ]);

        $season = Season::findOrFail($validated['season_id']);
        $existingSeasonId = (int) ($program?->season_id ?? $program?->seasonGroups()->value('season_id'));

        if ($season->status !== 'active' && $season->id !== $existingSeasonId) {
            throw ValidationException::withMessages([
                'season_id' => 'New program configurations can only use an active season.',
            ]);
        }

        $groupIds = collect($validated['season_group_ids'])->map(fn ($id): int => (int) $id);
        $groups = SeasonGroup::with('season')->whereIn('id', $groupIds)->get();
        $existingGroupIds = $program?->seasonGroups()->pluck('season_groups.id')->map(fn ($id): int => (int) $id)->all() ?? [];
        $existingGroupRates = $program?->seasonGroups()->get()
            ->mapWithKeys(fn (SeasonGroup $group): array => [$group->id => $group->pivot->rate_per_hectare])
            ?? collect();

        foreach ($groups as $group) {
            if ($group->season_id !== $season->id) {
                throw ValidationException::withMessages([
                    'season_group_ids' => 'Every eligible group must belong to the selected program season.',
                ]);
            }

            if ($group->season->status !== 'active' && ! in_array($group->id, $existingGroupIds, true)) {
                throw ValidationException::withMessages([
                    'season_group_ids' => 'New program eligibility can only be assigned to groups in an active season.',
                ]);
            }
        }

        $supplyRows = collect($validated['items']);
        $supplyIds = $supplyRows->pluck('supply_id')->map(fn ($id): int => (int) $id);
        $existingSupplies = $program?->supplies()->get()->keyBy('id') ?? collect();
        $supplies = $supplyRows->mapWithKeys(function (array $item) use ($existingSupplies): array {
            $supplyId = (int) $item['supply_id'];

            return [
                $supplyId => [
                    'maximum_quantity' => $existingSupplies->get($supplyId)?->pivot->maximum_quantity,
                    'unit_value' => $item['unit_value'] ?? $existingSupplies->get($supplyId)?->pivot->unit_value ?? 0,
                ],
            ];
        })->all();

        $rateRows = collect($validated['rates']);
        $groupRates = [];

        foreach ($rateRows as $rate) {
            $supplyId = (int) $rate['supply_id'];
            $groupId = (int) $rate['season_group_id'];
            $key = "{$supplyId}:{$groupId}";

            if (! $supplyIds->contains($supplyId) || ! $groupIds->contains($groupId)) {
                throw ValidationException::withMessages([
                    'rates' => 'Each rate must match an included supply and an eligible farmer group.',
                ]);
            }

            if (isset($groupRates[$key])) {
                throw ValidationException::withMessages([
                    'rates' => 'Each supply and farmer group combination can only have one rate.',
                ]);
            }

            $groupRates[$key] = [
                'supply_id' => $supplyId,
                'season_group_id' => $groupId,
                'quantity_per_hectare' => $rate['quantity_per_hectare'],
                'maximum_quantity_per_farmer' => $rate['maximum_quantity_per_farmer'],
            ];
        }

        foreach ($supplyIds as $supplyId) {
            foreach ($groupIds as $groupId) {
                if (! isset($groupRates["{$supplyId}:{$groupId}"])) {
                    throw ValidationException::withMessages([
                        'rates' => 'Enter a quantity rate and per-farmer maximum for every included supply and eligible group.',
                    ]);
                }
            }
        }

        return [
            'program' => [
                ...collect($validated)->except(['items', 'season_group_ids', 'rates'])->all(),
                'requires_verified_rsbsa' => $request->boolean('requires_verified_rsbsa', true),
            ],
            'supplies' => $supplies,
            'season_groups' => $groupIds->mapWithKeys(fn (int $id): array => [
                $id => ['rate_per_hectare' => $existingGroupRates->get($id)],
            ])->all(),
            'supply_group_rates' => array_values($groupRates),
        ];
    }
}
