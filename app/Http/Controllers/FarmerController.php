<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Distribution;
use App\Models\Farmer;
use App\Models\FarmerSeasonGroupAssignment;
use App\Models\RsbsaRecord;
use App\Models\Season;
use App\Models\SeasonGroup;
use App\Models\User;
use App\Services\FarmerCsvImporter;
use App\Services\SystemConfiguration;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FarmerController extends Controller
{
    public function __construct(private SystemConfiguration $configuration) {}

    public function index(Request $request): View
    {
        if (array_intersect(['search', 'status', 'season', 'seed_type'], array_keys($request->query->all())) !== []) {
            abort_unless($request->user()->canUseButton('farmers.filter'), 403);
        }

        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');
        $requestedSeasonId = filter_var($request->query('season'), FILTER_VALIDATE_INT);
        $seasonId = $requestedSeasonId
            ? Season::whereKey($requestedSeasonId)->where('status', 'active')->value('id')
            : null;
        $seedType = in_array($request->query('seed_type'), ['hybrid', 'inbred'], true) ? $request->query('seed_type') : null;

        $farmers = Farmer::query()
            ->with('rsbsaRecord')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('farmer_id', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('barangay', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, ['active', 'inactive', 'deceased'], true), fn ($query) => $query->where('status', $status))
            ->when($seasonId !== null || $seedType !== null, function ($query) use ($seasonId, $seedType): void {
                $query->whereHas('currentSeasonGroupAssignments.seasonGroup', function ($query) use ($seasonId, $seedType): void {
                    $query->when($seasonId !== null, fn ($query) => $query->where('season_id', $seasonId))
                        ->when($seedType !== null, fn ($query) => $query->where('seed_type', $seedType));
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(12)
            ->withQueryString();

        return view('farmers.index', [
            'farmers' => $farmers,
            'search' => $search,
            'status' => $status,
            'season' => $seasonId,
            'seedType' => $seedType,
            'seasons' => Season::where('status', 'active')->orderBy('name')->get(),
            'newFarmer' => new Farmer,
            'customFields' => $this->configuration->farmerFields(),
            'barangays' => $this->barangayOptions(),
            'seasonGroupChoices' => $this->seasonsForAssignment(),
            'currentSeasonGroupIds' => [],
            'canManageSeasonGroups' => $request->user()->hasAnyRole('admin'),
            'possibleDuplicateCandidates' => $request->session()->get('possibleDuplicateCandidates', []),
        ]);
    }

    public function importForm(Request $request): View
    {
        return view('farmers.import', [
            'headers' => FarmerCsvImporter::HEADERS,
            'report' => $request->session()->get('farmerImportReport'),
        ]);
    }

    public function downloadTemplate(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                throw new RuntimeException('The CSV template could not be generated.');
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, FarmerCsvImporter::HEADERS, ',', '"', '');
            fclose($output);
        }, 'agriqr-rsbsa-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importCsv(Request $request, FarmerCsvImporter $importer): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'extensions:csv,txt', 'max:5120'],
        ]);
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $report = $importer->import($validated['file'], $user, $request->ip());

        return redirect()->route('farmers.import')
            ->with('farmerImportReport', $report);
    }

    public function create(Request $request): View
    {
        return view('farmers.form', [
            'farmer' => new Farmer,
            'customFields' => $this->configuration->farmerFields(),
            'barangays' => $this->barangayOptions(),
            'seasonGroupChoices' => $this->seasonsForAssignment(),
            'currentSeasonGroupIds' => [],
            'canManageSeasonGroups' => $request->user()->hasAnyRole('admin'),
            'possibleDuplicateCandidates' => $request->session()->get('possibleDuplicateCandidates', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $possibleDuplicates = $this->possibleDuplicateCandidates($data['farmer']);

        if ($possibleDuplicates !== [] && ! $request->boolean('confirm_possible_duplicate')) {
            return back()
                ->withInput()
                ->withErrors(['possible_duplicate' => 'Review the possible matches below. Confirm only if this is a different farmer.'])
                ->with('possibleDuplicateCandidates', $possibleDuplicates);
        }

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $farmer = DB::transaction(function () use ($data, $request, $user) {
            $farmer = Farmer::create([
                ...$data['farmer'],
                'barangay_id' => $this->resolveBarangayId($data['farmer']['barangay']),
                'farmer_id' => $this->newFarmerCode(),
            ]);

            $this->saveRegistrationDetails($farmer, $data);
            $this->saveSeasonGroups($farmer, $data['season_groups'], $user);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'CREATE',
                'module' => 'FARMER',
                'record_id' => $farmer->id,
                'description' => "Created farmer profile {$farmer->farmer_id}.",
                'ip_address' => $request->ip(),
            ]);

            return $farmer;
        });

        return redirect()->route('farmers.show', $farmer)->with('success', 'Farmer profile created.');
    }

    public function show(Farmer $farmer): View
    {
        $relations = [
            'farmInfo',
            'rsbsaRecord',
            'seasonGroupAssignments.seasonGroup.season',
            'seasonGroupAssignments.assigner',
            'parcels' => fn ($query) => $query->orderBy('parcel_number'),
        ];

        if (auth()->user()->canAccessModule('distributions')) {
            $relations = [
                ...$relations,
                'distributions.items.supply',
                'distributions.program',
            ];
        }

        $farmer->load($relations);

        return view('farmers.show', [
            'farmer' => $farmer,
            'customFields' => $this->configuration->farmerFields(),
        ]);
    }

    public function photo(Farmer $farmer): StreamedResponse
    {
        $disk = Storage::disk('local');

        abort_unless(
            filled($farmer->photo_path) && $disk->exists($farmer->photo_path),
            404,
        );

        $extension = pathinfo($farmer->photo_path, PATHINFO_EXTENSION);

        return $disk->response(
            $farmer->photo_path,
            $farmer->farmer_id.'.'.$extension,
            [
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function updatePhoto(Request $request, Farmer $farmer): RedirectResponse
    {
        $validated = $request->validate([
            'photo' => [
                'required',
                'image',
                'mimes:jpeg,png,webp',
                'extensions:jpg,jpeg,png,webp',
                'max:3072',
                'dimensions:max_width=4000,max_height=4000',
            ],
        ]);
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $previousPath = $farmer->photo_path;
        $photoPath = $validated['photo']->store('farmer-photos', 'local');

        if ($photoPath === false) {
            throw new RuntimeException('The farmer photo could not be stored.');
        }

        DB::transaction(function () use ($farmer, $photoPath, $request, $user) {
            $farmer->update(['photo_path' => $photoPath]);
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'UPDATE',
                'module' => 'FARMER',
                'record_id' => $farmer->id,
                'description' => "Updated farmer photo for {$farmer->farmer_id}.",
                'ip_address' => $request->ip(),
            ]);
        });

        if ($previousPath && $previousPath !== $photoPath) {
            Storage::disk('local')->delete($previousPath);
        }

        return redirect()->route('farmers.show', $farmer)->with('success', 'Farmer photo updated.');
    }

    public function edit(Request $request, Farmer $farmer): View
    {
        $farmer->load([
            'farmInfo',
            'rsbsaRecord',
            'seasonGroupAssignments.seasonGroup.season',
            'currentSeasonGroupAssignments.seasonGroup.season',
        ]);

        return view('farmers.form', [
            'farmer' => $farmer,
            'customFields' => $this->configuration->farmerFields(),
            'barangays' => $this->barangayOptions($farmer),
            'seasonGroupChoices' => $this->seasonsForAssignment($farmer),
            'currentSeasonGroupIds' => $farmer->currentSeasonGroupAssignments
                ->mapWithKeys(fn (FarmerSeasonGroupAssignment $assignment): array => [
                    $assignment->seasonGroup->season_id => $assignment->season_group_id,
                ])->all(),
            'canManageSeasonGroups' => $request->user()->hasAnyRole('admin'),
            'possibleDuplicateCandidates' => $request->session()->get('possibleDuplicateCandidates', []),
        ]);
    }

    public function update(Request $request, Farmer $farmer): RedirectResponse
    {
        $data = $this->validatedData($request);
        $possibleDuplicates = $this->possibleDuplicateCandidates($data['farmer'], $farmer);

        if ($possibleDuplicates !== [] && ! $request->boolean('confirm_possible_duplicate')) {
            return back()
                ->withInput()
                ->withErrors(['possible_duplicate' => 'Review the possible matches below. Confirm only if this is a different farmer.'])
                ->with('possibleDuplicateCandidates', $possibleDuplicates);
        }

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        DB::transaction(function () use ($farmer, $data, $request, $user) {
            Farmer::query()->whereKey($farmer->id)->lockForUpdate()->firstOrFail();
            $farmer->update([
                ...$data['farmer'],
                'barangay_id' => $this->resolveBarangayId($data['farmer']['barangay']),
            ]);
            $this->saveRegistrationDetails($farmer, $data);
            $this->saveSeasonGroups($farmer, $data['season_groups'], $user);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'UPDATE',
                'module' => 'FARMER',
                'record_id' => $farmer->id,
                'description' => "Updated farmer profile {$farmer->farmer_id}.",
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('farmers.show', $farmer)->with('success', 'Farmer profile updated.');
    }

    /**
     * @return array{farmer: array<string, mixed>, rsbsa: array<string, mixed>, farm: array<string, mixed>, season_groups: array<int, int|null>}
     */
    private function validatedData(Request $request): array
    {
        $farmer = $request->route('farmer');
        $rsbsaRecordId = $farmer instanceof Farmer ? $farmer->rsbsaRecord?->id : null;
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $canManageSeasonGroups = $user->hasAnyRole('admin');

        if (! $canManageSeasonGroups && $request->exists('season_groups')) {
            abort(403);
        }

        $farmSizeUnit = $request->input('farm_size_unit');
        $farmSizeMaximum = is_string($farmSizeUnit)
            ? RsbsaRecord::FARM_SIZE_LIMITS[$farmSizeUnit] ?? RsbsaRecord::FARM_SIZE_LIMITS['hectare']
            : RsbsaRecord::FARM_SIZE_LIMITS['hectare'];

        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:2000'],
            'barangay' => ['required', 'string', Rule::in($this->barangayOptions($farmer instanceof Farmer ? $farmer : null))],
            'municipality' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'farm_information' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in($canManageSeasonGroups
                ? ['active', 'inactive', 'deceased']
                : ['active', 'inactive'])],
            'deceased_at' => ['nullable', 'date', 'before_or_equal:today'],
            'confirm_possible_duplicate' => ['nullable', 'boolean'],
            'rsbsa_number' => [
                'nullable', 'string', 'max:100',
                Rule::unique('rsbsa_records', 'rsbsa_number')->ignore($rsbsaRecordId),
            ],
            'registration_status' => ['required', 'in:registered,unregistered,pending'],
            'verification_status' => ['required', Rule::in($canManageSeasonGroups
                ? ['verified', 'unverified', 'pending']
                : ['unverified', 'pending'])],
            'registration_date' => ['nullable', 'date'],
            'farm_size' => ['nullable', 'numeric', 'gt:0', "max:{$farmSizeMaximum}"],
            'farm_size_unit' => ['required', 'in:hectare,sqm,acre'],
            'crop_information' => ['nullable', 'string', 'max:5000'],
            'farm_location' => ['nullable', 'string', 'max:255'],
            'tenure_type' => ['nullable', 'in:owner,tenant'],
            'landowner_name' => ['nullable', 'string', 'max:150'],
            'landowner_contact' => ['nullable', 'string', 'max:30'],
            'farm_remarks' => ['nullable', 'string', 'max:5000'],
        ];
        if ($canManageSeasonGroups) {
            $rules['season_groups'] = ['sometimes', 'array'];
            $rules['season_groups.*'] = ['nullable', 'integer', Rule::exists('season_groups', 'id')];
        }

        $customFieldDefinitions = $this->configuration->farmerFields();
        $customFieldValues = $farmer instanceof Farmer && is_array($farmer->custom_fields)
            ? $farmer->custom_fields
            : [];

        foreach ($customFieldDefinitions as $field) {
            $rules["custom_fields.{$field['key']}"] = match ($field['type']) {
                'number' => [$field['required'] ? 'required' : 'nullable', 'numeric', 'min:0', 'max:999999999'],
                'date' => [$field['required'] ? 'required' : 'nullable', 'date'],
                'select' => [$field['required'] ? 'required' : 'nullable', Rule::in($field['options'])],
                default => [$field['required'] ? 'required' : 'nullable', 'string', 'max:1000'],
            };
        }

        $validated = $request->validate($rules, [
            'barangay.in' => 'Select a barangay from the Admin-configured list.',
            'deceased_at.before_or_equal' => 'The date deceased cannot be in the future.',
            'farm_size.gt' => 'Farm size must be greater than zero.',
            'farm_size.max' => 'Farm size exceeds the maximum for the selected unit.',
        ]);
        $seasonAssignments = [];
        foreach ($validated['season_groups'] ?? [] as $seasonId => $seasonGroupId) {
            if (! filled($seasonGroupId)) {
                $seasonAssignments[(int) $seasonId] = null;

                continue;
            }

            $group = SeasonGroup::with('season')->find($seasonGroupId);
            $currentGroupId = $farmer instanceof Farmer
                ? $farmer->currentSeasonGroupAssignments()
                    ->whereHas('seasonGroup', fn ($query) => $query->where('season_id', $seasonId))
                    ->value('season_group_id')
                : null;

            if (
                ! $group
                || (int) $group->season_id !== (int) $seasonId
                || ($group->season->status !== 'active' && (int) $currentGroupId !== $group->id)
            ) {
                throw ValidationException::withMessages([
                    "season_groups.{$seasonId}" => 'Select a group from the matching active season.',
                ]);
            }

            $seasonAssignments[(int) $seasonId] = $group->id;
        }

        foreach ($customFieldDefinitions as $field) {
            $value = $validated['custom_fields'][$field['key']] ?? null;

            if ($value !== null) {
                $customFieldValues[$field['key']] = $value;
            } else {
                unset($customFieldValues[$field['key']]);
            }
        }

        $validated['farmer'] = array_intersect_key($validated, array_flip([
            'first_name', 'middle_name', 'last_name', 'suffix', 'contact_number',
            'address', 'barangay', 'municipality', 'province', 'farm_information', 'status', 'deceased_at',
        ]));
        if ($validated['farmer']['status'] !== 'deceased') {
            $validated['farmer']['deceased_at'] = null;
        }
        $validated['farmer']['custom_fields'] = $customFieldValues;

        $validated['rsbsa'] = array_intersect_key($validated, array_flip([
            'rsbsa_number', 'registration_status', 'verification_status', 'registration_date',
            'farm_size', 'farm_size_unit', 'crop_information',
        ]));

        $validated['farm'] = array_intersect_key($validated, array_flip([
            'farm_size', 'farm_location', 'tenure_type', 'landowner_name',
            'landowner_contact', 'crop_information', 'farm_remarks',
        ]));
        $validated['season_groups'] = $seasonAssignments;

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $farmerData
     * @return list<array{farmer_id: string, full_name: string, location: string}>
     */
    private function possibleDuplicateCandidates(array $farmerData, ?Farmer $currentFarmer = null): array
    {
        $firstName = Str::of($farmerData['first_name'])->squish()->lower()->toString();
        $lastName = Str::of($farmerData['last_name'])->squish()->lower()->toString();

        return Farmer::query()
            ->when($currentFarmer instanceof Farmer, fn ($query) => $query->where('id', '!=', $currentFarmer->id))
            ->whereRaw('LOWER(TRIM(first_name)) = ?', [$firstName])
            ->whereRaw('LOWER(TRIM(last_name)) = ?', [$lastName])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->filter(fn (Farmer $candidate): bool => $this->normalizedFarmerName($candidate) === $this->normalizedFarmerName($farmerData))
            ->take(5)
            ->map(fn (Farmer $candidate): array => [
                'farmer_id' => $candidate->farmer_id,
                'full_name' => $candidate->full_name,
                'location' => collect([$candidate->barangay, $candidate->municipality])->filter()->implode(', '),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Farmer|array<string, mixed>  $farmer
     */
    private function normalizedFarmerName(Farmer|array $farmer): string
    {
        return collect(['first_name', 'middle_name', 'last_name', 'suffix'])
            ->map(fn (string $field): string => Str::of((string) data_get($farmer, $field, ''))->squish()->lower()->toString())
            ->filter()
            ->implode(' ');
    }

    /**
     * @return list<string>
     */
    private function barangayOptions(?Farmer $farmer = null): array
    {
        $options = $this->configuration->barangays();

        if ($farmer instanceof Farmer && filled($farmer->barangay) && ! in_array($farmer->barangay, $options, true)) {
            $options[] = $farmer->barangay;
        }

        return $options;
    }

    private function resolveBarangayId(string $name): int
    {
        $normalizedName = mb_strtolower(trim($name));
        $barangay = Barangay::query()->firstOrCreate(
            ['normalized_name' => $normalizedName],
            ['name' => trim($name), 'is_active' => true],
        );

        if (! $barangay->is_active) {
            $barangay->update(['is_active' => true]);
        }

        return $barangay->id;
    }

    /**
     * @param  array<int, int|null>  $assignments
     */
    private function saveSeasonGroups(Farmer $farmer, array $assignments, User $user): void
    {
        foreach ($assignments as $seasonId => $seasonGroupId) {
            $currentAssignment = $farmer->currentSeasonGroupAssignments()
                ->whereHas('seasonGroup', fn ($query) => $query->where('season_id', $seasonId))
                ->lockForUpdate()
                ->first();

            $currentGroupId = $currentAssignment ? (int) $currentAssignment->season_group_id : null;
            $requestedGroupId = $seasonGroupId !== null ? (int) $seasonGroupId : null;

            if ($currentGroupId === $requestedGroupId) {
                continue;
            }

            if (Distribution::query()
                ->where('farmer_id', $farmer->id)
                ->where('season_id', $seasonId)
                ->where('transaction_status', 'completed')
                ->exists()) {
                throw ValidationException::withMessages([
                    "season_groups.{$seasonId}" => 'A farmer group cannot be changed after a completed distribution in that season.',
                ]);
            }

            if ($currentAssignment) {
                $currentAssignment->update(['ended_at' => now()]);
            }

            if ($seasonGroupId !== null) {
                FarmerSeasonGroupAssignment::create([
                    'farmer_id' => $farmer->id,
                    'season_group_id' => $seasonGroupId,
                    'assigned_by' => $user->id,
                    'started_at' => now(),
                ]);
            }
        }
    }

    /**
     * @return Collection<int, Season>
     */
    private function seasonsForAssignment(?Farmer $farmer = null): Collection
    {
        return Season::query()
            ->with('groups')
            ->where(function ($query) use ($farmer): void {
                $query->where('status', 'active')
                    ->when($farmer, fn ($query) => $query->orWhereHas(
                        'groups.assignments',
                        fn ($query) => $query->where('farmer_id', $farmer->id)->whereNull('ended_at'),
                    ));
            })
            ->orderBy('name')
            ->get();
    }

    private function saveRegistrationDetails(Farmer $farmer, array $data): void
    {
        if (filled($data['rsbsa']['rsbsa_number'] ?? null)) {
            $farmer->rsbsaRecord()->updateOrCreate([], $data['rsbsa']);
        } else {
            $farmer->rsbsaRecord()->delete();
        }

        $farm = [
            'farm_size_hectares' => ($data['farm']['farm_size'] ?? null)
                && ($data['rsbsa']['farm_size_unit'] ?? 'hectare') === 'hectare'
                    ? $data['farm']['farm_size']
                    : null,
            'farm_location' => $data['farm']['farm_location'] ?? null,
            'tenure_type' => $data['farm']['tenure_type'] ?? null,
            'landowner_name' => $data['farm']['landowner_name'] ?? null,
            'landowner_contact' => $data['farm']['landowner_contact'] ?? null,
            'crop_information' => $data['farm']['crop_information'] ?? null,
            'remarks' => $data['farm']['farm_remarks'] ?? null,
        ];

        if (collect($farm)->contains(fn ($value) => filled($value))) {
            $farmer->farmInfo()->updateOrCreate([], $farm);
        } else {
            $farmer->farmInfo()->delete();
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
