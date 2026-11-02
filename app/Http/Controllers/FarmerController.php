<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Farmer;
use App\Models\User;
use App\Services\FarmerCsvImporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FarmerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');

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
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where('status', $status))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(12)
            ->withQueryString();

        return view('farmers.index', compact('farmers', 'search', 'status'));
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

    public function create(): View
    {
        return view('farmers.form', ['farmer' => new Farmer]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        $farmer = DB::transaction(function () use ($data, $request) {
            $farmer = Farmer::create([
                ...$data['farmer'],
                'farmer_id' => $this->newFarmerCode(),
            ]);

            $this->saveRegistrationDetails($farmer, $data);
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
        $farmer->load([
            'farmInfo',
            'rsbsaRecord',
            'parcels' => fn ($query) => $query->orderBy('parcel_number'),
            'distributions.items.supply',
            'distributions.program',
        ]);

        return view('farmers.show', compact('farmer'));
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

    public function edit(Farmer $farmer): View
    {
        $farmer->load(['farmInfo', 'rsbsaRecord']);

        return view('farmers.form', compact('farmer'));
    }

    public function update(Request $request, Farmer $farmer): RedirectResponse
    {
        $data = $this->validatedData($request);

        DB::transaction(function () use ($farmer, $data, $request) {
            $farmer->update($data['farmer']);
            $this->saveRegistrationDetails($farmer, $data);
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
     * @return array{farmer: array<string, mixed>, rsbsa: array<string, mixed>, farm: array<string, mixed>}
     */
    private function validatedData(Request $request): array
    {
        $farmer = $request->route('farmer');
        $rsbsaRecordId = $farmer instanceof Farmer ? $farmer->rsbsaRecord?->id : null;

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:2000'],
            'barangay' => ['required', 'string', 'max:100'],
            'municipality' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'farm_information' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:active,inactive'],
            'rsbsa_number' => [
                'nullable', 'string', 'max:100',
                Rule::unique('rsbsa_records', 'rsbsa_number')->ignore($rsbsaRecordId),
            ],
            'registration_status' => ['required', 'in:registered,unregistered,pending'],
            'verification_status' => ['required', 'in:verified,unverified,pending'],
            'registration_date' => ['nullable', 'date'],
            'farm_size' => ['nullable', 'numeric', 'min:0'],
            'farm_size_unit' => ['required', 'in:hectare,sqm,acre'],
            'crop_information' => ['nullable', 'string', 'max:5000'],
            'farm_location' => ['nullable', 'string', 'max:255'],
            'tenure_type' => ['nullable', 'in:owner,tenant'],
            'landowner_name' => ['nullable', 'string', 'max:150'],
            'landowner_contact' => ['nullable', 'string', 'max:30'],
            'farm_remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        $validated['farmer'] = array_intersect_key($validated, array_flip([
            'first_name', 'middle_name', 'last_name', 'suffix', 'contact_number',
            'address', 'barangay', 'municipality', 'province', 'farm_information', 'status',
        ]));

        $validated['rsbsa'] = array_intersect_key($validated, array_flip([
            'rsbsa_number', 'registration_status', 'verification_status', 'registration_date',
            'farm_size', 'farm_size_unit', 'crop_information',
        ]));

        $validated['farm'] = array_intersect_key($validated, array_flip([
            'farm_size', 'farm_location', 'tenure_type', 'landowner_name',
            'landowner_contact', 'crop_information', 'farm_remarks',
        ]));

        return $validated;
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
