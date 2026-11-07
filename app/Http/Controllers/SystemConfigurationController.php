<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Supply;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SystemConfiguration;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SystemConfigurationController extends Controller
{
    public function index(SystemConfiguration $configuration): View
    {
        return view('settings.index', ['settings' => $configuration->all()]);
    }

    public function update(Request $request, SystemConfiguration $configuration): RedirectResponse
    {
        $validated = $request->validate([
            'supply_categories' => ['required', 'string', 'max:4000'],
            'farmer_fields' => ['nullable', 'array', 'max:20'],
            'farmer_fields.*.key' => ['required', 'string', 'regex:/\A[a-z][a-z0-9_]{0,39}\z/'],
            'farmer_fields.*.label' => ['required', 'string', 'max:80'],
            'farmer_fields.*.type' => ['required', 'in:text,number,date,select'],
            'farmer_fields.*.required' => ['nullable', 'boolean'],
            'farmer_fields.*.options' => ['nullable', 'string', 'max:1000'],
            'barangays' => ['nullable', 'string', 'max:10000'],
            'require_active_farmer' => ['required', 'boolean'],
            'require_verified_rsbsa' => ['required', 'boolean'],
            'require_active_program' => ['required', 'boolean'],
            'max_distribution_quantity' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
        ]);
        $categories = collect(preg_split('/\R/', $validated['supply_categories']) ?: [])
            ->map(static fn (string $category): string => trim($category))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($categories === []) {
            throw ValidationException::withMessages([
                'supply_categories' => 'Add at least one supply category.',
            ]);
        }

        $barangays = array_key_exists('barangays', $validated)
            ? $this->normalizeLines($validated['barangays'] ?? '')
            : $configuration->barangays();

        if (array_key_exists('barangays', $validated) && $barangays === []) {
            throw ValidationException::withMessages([
                'barangays' => 'Add at least one barangay so farmer registration can use the dropdown.',
            ]);
        }
        if (collect($barangays)->contains(static fn (string $barangay): bool => mb_strlen($barangay) > 100)) {
            throw ValidationException::withMessages([
                'barangays' => 'Each barangay must be no more than 100 characters.',
            ]);
        }

        $categoriesInUse = Supply::query()
            ->whereNotIn('category', $categories)
            ->distinct()
            ->pluck('category')
            ->all();

        if ($categoriesInUse !== []) {
            throw ValidationException::withMessages([
                'supply_categories' => 'These categories are still assigned to supplies and cannot be removed: '.implode(', ', $categoriesInUse).'.',
            ]);
        }

        $fields = [];
        $fieldKeys = [];

        foreach ($validated['farmer_fields'] ?? [] as $field) {
            if (in_array($field['key'], $fieldKeys, true)) {
                throw ValidationException::withMessages([
                    'farmer_fields' => 'Custom field keys must be unique.',
                ]);
            }

            $fieldKeys[] = $field['key'];
            $options = collect(preg_split('/\R/', $field['options'] ?? '') ?: [])
                ->map(static fn (string $option): string => trim($option))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($field['type'] === 'select' && $options === []) {
                throw ValidationException::withMessages([
                    'farmer_fields' => "Add at least one dropdown option for {$field['label']}.",
                ]);
            }

            $fields[] = [
                'key' => $field['key'],
                'label' => $field['label'],
                'type' => $field['type'],
                'required' => (bool) ($field['required'] ?? false),
                'options' => $field['type'] === 'select' ? $options : [],
            ];
        }

        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $canManageFarmerFields = $user->canUseButton('settings.fields');

        if (! $canManageFarmerFields && array_key_exists('farmer_fields', $validated)) {
            abort(403);
        }

        if (! $canManageFarmerFields) {
            $fields = $configuration->farmerFields();
        }

        DB::transaction(function () use ($validated, $categories, $fields, $barangays, $request, $user): void {
            foreach ([
                'supply_categories' => $categories,
                'farmer_fields' => $fields,
                'barangays' => $barangays,
                'distribution_settings' => [
                    'require_active_farmer' => (bool) $validated['require_active_farmer'],
                    'require_verified_rsbsa' => (bool) $validated['require_verified_rsbsa'],
                    'require_active_program' => (bool) $validated['require_active_program'],
                    'max_distribution_quantity' => filled($validated['max_distribution_quantity'] ?? null)
                        ? (float) $validated['max_distribution_quantity']
                        : null,
                ],
            ] as $key => $value) {
                SystemSetting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $value, 'updated_by' => $user->id],
                );
            }

            Barangay::query()->update(['is_active' => false]);

            foreach ($barangays as $barangay) {
                Barangay::query()->updateOrCreate(
                    ['normalized_name' => mb_strtolower($barangay)],
                    ['name' => $barangay, 'is_active' => true],
                );
            }

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'UPDATE',
                'module' => 'CONFIGURATION',
                'description' => 'Updated system configuration.',
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('settings.index')->with('success', 'System configuration updated.');
    }

    /**
     * @return list<string>
     */
    private function normalizeLines(string $lines): array
    {
        return collect(preg_split('/\R/', $lines) ?: [])
            ->map(static fn (string $value): string => trim($value))
            ->filter()
            ->unique(static fn (string $value): string => mb_strtolower($value))
            ->values()
            ->all();
    }
}
