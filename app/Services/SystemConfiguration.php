<?php

namespace App\Services;

use App\Models\Supply;
use App\Models\SystemSetting;

class SystemConfiguration
{
    /**
     * @return list<string>
     */
    public function supplyCategories(): array
    {
        $categories = SystemSetting::read('supply_categories', Supply::CATEGORIES);

        return is_array($categories) && $categories !== []
            ? array_values(array_filter($categories, 'is_string'))
            : Supply::CATEGORIES;
    }

    /**
     * @return list<array{key: string, label: string, type: string, required: bool, options: list<string>}>
     */
    public function farmerFields(): array
    {
        $fields = SystemSetting::read('farmer_fields', []);

        if (! is_array($fields)) {
            return [];
        }

        return array_values(array_filter($fields, static fn ($field): bool => is_array($field)
            && isset($field['key'], $field['label'], $field['type'])
            && is_string($field['key'])
            && is_string($field['label'])
            && is_string($field['type'])
            && in_array($field['type'], ['text', 'number', 'date', 'select'], true)));
    }

    /**
     * @return list<string>
     */
    public function barangays(): array
    {
        return $this->configuredList('barangays');
    }

    /**
     * @return array{require_active_farmer: bool, require_verified_rsbsa: bool, require_active_program: bool, max_distribution_quantity: float|null}
     */
    public function distributionSettings(): array
    {
        $settings = SystemSetting::read('distribution_settings', []);
        $settings = is_array($settings) ? $settings : [];

        return [
            'require_active_farmer' => (bool) ($settings['require_active_farmer'] ?? true),
            'require_verified_rsbsa' => (bool) ($settings['require_verified_rsbsa'] ?? true),
            'require_active_program' => (bool) ($settings['require_active_program'] ?? true),
            'max_distribution_quantity' => isset($settings['max_distribution_quantity'])
                ? (float) $settings['max_distribution_quantity']
                : null,
        ];
    }

    /**
     * @return array{supply_categories: list<string>, farmer_fields: list<array{key: string, label: string, type: string, required: bool, options: list<string>}>, barangays: list<string>, distribution_settings: array{require_active_farmer: bool, require_verified_rsbsa: bool, require_active_program: bool, max_distribution_quantity: float|null}}
     */
    public function all(): array
    {
        return [
            'supply_categories' => $this->supplyCategories(),
            'farmer_fields' => $this->farmerFields(),
            'barangays' => $this->barangays(),
            'distribution_settings' => $this->distributionSettings(),
        ];
    }

    /**
     * @return list<string>
     */
    private function configuredList(string $key): array
    {
        $values = SystemSetting::read($key, []);

        if (! is_array($values)) {
            return [];
        }

        return collect($values)
            ->filter(static fn (mixed $value): bool => is_string($value))
            ->map(static fn (string $value): string => trim($value))
            ->filter()
            ->unique(static fn (string $value): string => mb_strtolower($value))
            ->values()
            ->all();
    }
}
