<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserButtonPermission;

class ButtonAccess
{
    private const ROLES = ['admin' => 'Admin', 'technician' => 'Technician'];

    /**
     * defaults maps roles to their initial setting. routes are endpoints guarded by this action.
     *
     * @var array<string, array{section: string, label: string, module: string|null, routes: list<string>, defaults: array<string, bool>, server_protected?: bool}>
     */
    private const ACTIONS = [
        'dashboard' => ['section' => 'Workspace', 'label' => 'Overview', 'module' => null, 'routes' => ['dashboard'], 'defaults' => ['admin' => true, 'technician' => true]],
        'workspace.mobile_menu' => ['section' => 'Workspace', 'label' => 'Toggle mobile navigation', 'module' => null, 'routes' => [], 'defaults' => ['admin' => true, 'technician' => true]],
        'workspace.theme' => ['section' => 'Workspace', 'label' => 'Toggle dark mode', 'module' => null, 'routes' => [], 'defaults' => ['admin' => true, 'technician' => true]],
        'workspace.sign_out' => ['section' => 'Workspace', 'label' => 'Sign out', 'module' => null, 'routes' => ['logout'], 'defaults' => ['admin' => true, 'technician' => true]],
        'farmers.list' => ['section' => 'Farmers', 'label' => 'Open farmer directory', 'module' => 'farmers', 'routes' => ['farmers.index'], 'defaults' => ['admin' => true, 'technician' => true]],
        'farmers.filter' => ['section' => 'Farmers', 'label' => 'Search and filter farmers', 'module' => 'farmers', 'routes' => [], 'defaults' => ['admin' => true, 'technician' => true], 'server_protected' => true],
        'farmers.create' => ['section' => 'Farmers', 'label' => 'Register a farmer', 'module' => 'farmers', 'routes' => ['farmers.create', 'farmers.store'], 'defaults' => ['admin' => true, 'technician' => true]],
        'farmers.import' => ['section' => 'Farmers', 'label' => 'Import farmers from CSV', 'module' => 'farmers', 'routes' => ['farmers.import', 'farmers.import.template', 'farmers.import.store'], 'defaults' => ['admin' => true, 'technician' => false]],
        'farmers.view' => ['section' => 'Farmers', 'label' => 'View farmer profile', 'module' => 'farmers', 'routes' => ['farmers.show', 'farmers.photo'], 'defaults' => ['admin' => true, 'technician' => true]],
        'farmers.edit' => ['section' => 'Farmers', 'label' => 'Edit farmer profile and photo', 'module' => 'farmers', 'routes' => ['farmers.edit', 'farmers.update', 'farmers.photo.update'], 'defaults' => ['admin' => true, 'technician' => false]],
        'farmers.print' => ['section' => 'Farmers', 'label' => 'Print farmer record', 'module' => 'farmers', 'routes' => [], 'defaults' => ['admin' => true, 'technician' => true]],
        'distributions.list' => ['section' => 'Distributions', 'label' => 'Open distributions', 'module' => 'distributions', 'routes' => ['distributions.index'], 'defaults' => ['admin' => true, 'technician' => true]],
        'distributions.create' => ['section' => 'Distributions', 'label' => 'Record a distribution', 'module' => 'distributions', 'routes' => ['distributions.create', 'distributions.lookup', 'distributions.store', 'distributions.farmer-photo'], 'defaults' => ['admin' => true, 'technician' => true]],
        'distributions.view' => ['section' => 'Distributions', 'label' => 'View distribution details', 'module' => 'distributions', 'routes' => ['distributions.show'], 'defaults' => ['admin' => true, 'technician' => true]],
        'distributions.print' => ['section' => 'Distributions', 'label' => 'Print distribution receipt', 'module' => 'distributions', 'routes' => [], 'defaults' => ['admin' => true, 'technician' => true]],
        'distributions.scan' => ['section' => 'Distributions', 'label' => 'Scan a farmer QR code', 'module' => 'distributions', 'routes' => [], 'defaults' => ['admin' => true, 'technician' => true]],
        'distributions.items' => ['section' => 'Distributions', 'label' => 'Add or remove items while recording a distribution', 'module' => 'distributions', 'routes' => [], 'defaults' => ['admin' => true, 'technician' => true], 'server_protected' => true],
        'inventory.list' => ['section' => 'Inventory', 'label' => 'Open inventory', 'module' => 'inventory', 'routes' => ['supplies.index'], 'defaults' => ['admin' => true, 'technician' => true]],
        'inventory.search' => ['section' => 'Inventory', 'label' => 'Search inventory', 'module' => 'inventory', 'routes' => [], 'defaults' => ['admin' => true, 'technician' => true], 'server_protected' => true],
        'inventory.create' => ['section' => 'Inventory', 'label' => 'Add inventory item', 'module' => 'inventory', 'routes' => ['supplies.create', 'supplies.store'], 'defaults' => ['admin' => true, 'technician' => false]],
        'inventory.import' => ['section' => 'Inventory', 'label' => 'Import inventory from CSV', 'module' => 'inventory', 'routes' => ['supplies.import', 'supplies.import.template', 'supplies.import.store'], 'defaults' => ['admin' => true, 'technician' => false]],
        'inventory.edit' => ['section' => 'Inventory', 'label' => 'Edit inventory item', 'module' => 'inventory', 'routes' => ['supplies.edit', 'supplies.update'], 'defaults' => ['admin' => true, 'technician' => false]],
        'inventory.stock_in' => ['section' => 'Inventory', 'label' => 'Receive stock', 'module' => 'inventory', 'routes' => ['supplies.stock-in'], 'defaults' => ['admin' => true, 'technician' => false]],
        'programs.list' => ['section' => 'Programs', 'label' => 'Open subsidy programs', 'module' => 'programs', 'routes' => ['programs.index'], 'defaults' => ['admin' => true, 'technician' => true]],
        'programs.create' => ['section' => 'Programs', 'label' => 'Create a subsidy program', 'module' => 'programs', 'routes' => ['programs.create', 'programs.store'], 'defaults' => ['admin' => true, 'technician' => false]],
        'programs.edit' => ['section' => 'Programs', 'label' => 'Edit a subsidy program', 'module' => 'programs', 'routes' => ['programs.edit', 'programs.update'], 'defaults' => ['admin' => true, 'technician' => false]],
        'seasons.list' => ['section' => 'Seasons and groups', 'label' => 'Open seasons and groups', 'module' => 'seasons', 'routes' => ['seasons.index'], 'defaults' => ['admin' => true, 'technician' => true]],
        'seasons.create' => ['section' => 'Seasons and groups', 'label' => 'Create a season', 'module' => 'seasons', 'routes' => ['seasons.create', 'seasons.store'], 'defaults' => ['admin' => true, 'technician' => false]],
        'seasons.edit' => ['section' => 'Seasons and groups', 'label' => 'Manage a season and groups', 'module' => 'seasons', 'routes' => ['seasons.edit', 'seasons.update'], 'defaults' => ['admin' => true, 'technician' => false]],
        'users.list' => ['section' => 'Team access', 'label' => 'Open team access', 'module' => 'users', 'routes' => ['users.index'], 'defaults' => ['admin' => true]],
        'users.create' => ['section' => 'Team access', 'label' => 'Add a team member', 'module' => 'users', 'routes' => ['users.create', 'users.store'], 'defaults' => ['admin' => true]],
        'users.edit' => ['section' => 'Team access', 'label' => 'Edit a team account', 'module' => 'users', 'routes' => ['users.edit', 'users.update'], 'defaults' => ['admin' => true]],
        'activity.list' => ['section' => 'Audit log', 'label' => 'Open audit log', 'module' => 'activity', 'routes' => ['activity.index'], 'defaults' => ['admin' => true]],
        'activity.filter' => ['section' => 'Audit log', 'label' => 'Filter audit log', 'module' => 'activity', 'routes' => [], 'defaults' => ['admin' => true], 'server_protected' => true],
        'reports.filter' => ['section' => 'Reports', 'label' => 'Filter report data', 'module' => 'reports', 'routes' => [], 'defaults' => ['admin' => true, 'technician' => true], 'server_protected' => true],
        'reports.export' => ['section' => 'Reports', 'label' => 'Export report data', 'module' => 'reports', 'routes' => [], 'defaults' => ['admin' => true, 'technician' => true], 'server_protected' => true],
        'reports.view' => ['section' => 'Reports', 'label' => 'Open reports', 'module' => 'reports', 'routes' => ['reports.index'], 'defaults' => ['admin' => true, 'technician' => true]],
        'settings.view' => ['section' => 'System settings', 'label' => 'Open system settings', 'module' => 'settings', 'routes' => ['settings.index'], 'defaults' => ['admin' => true]],
        'settings.update' => ['section' => 'System settings', 'label' => 'Save system settings', 'module' => 'settings', 'routes' => ['settings.update'], 'defaults' => ['admin' => true]],
        'settings.fields' => ['section' => 'System settings', 'label' => 'Add or remove custom farmer fields', 'module' => 'settings', 'routes' => [], 'defaults' => ['admin' => true], 'server_protected' => true],
        'database.view' => ['section' => 'Database administration', 'label' => 'Check database status', 'module' => 'database', 'routes' => ['database.index'], 'defaults' => ['admin' => true]],
    ];

    /** @var array<string, array<string, bool>>|null */
    private ?array $configured = null;

    /** @var array<int, array<string, bool>> */
    private array $userOverrides = [];

    /**
     * @return array<string, array{section: string, label: string, module: string|null, routes: list<string>, defaults: array<string, bool>, server_protected?: bool}>
     */
    public function actions(): array
    {
        return self::ACTIONS;
    }

    /**
     * @return array<string, string>
     */
    public function roles(): array
    {
        return self::ROLES;
    }

    public function allows(User $user, string $action): bool
    {
        $definition = self::ACTIONS[$action] ?? null;

        if ($definition === null) {
            return false;
        }

        if ($user->role === 'system_admin') {
            return true;
        }

        if (! array_key_exists($user->role, $definition['defaults'])) {
            return false;
        }

        if ($definition['module'] !== null && ! $user->canAccessModule($definition['module'])) {
            return false;
        }

        $overrides = $this->userOverrides($user);

        if (array_key_exists($action, $overrides)) {
            return $overrides[$action];
        }

        return $this->configured()[$user->role][$action]
            ?? $definition['defaults'][$user->role];
    }

    /**
     * Actions that can be customized for one account. Workspace navigation and sign-out
     * remain inherited so a restricted user can still enter and safely leave the app.
     *
     * @return array<string, array<string, mixed>>
     */
    public function userConfigurableActions(User $user): array
    {
        return array_filter(
            self::ACTIONS,
            fn (array $definition): bool => $definition['section'] !== 'Workspace'
                && array_key_exists($user->role, $definition['defaults']),
        );
    }

    /**
     * @return array<string, bool>
     */
    public function valuesForUserForm(User $user): array
    {
        $values = [];
        $overrides = $this->userOverrides($user);

        foreach (self::ACTIONS as $key => $definition) {
            if (! array_key_exists($user->role, $definition['defaults'])) {
                continue;
            }

            $values[$key] = $overrides[$key]
                ?? $this->configured()[$user->role][$key]
                ?? $definition['defaults'][$user->role];
        }

        return $values;
    }

    public function userHasOverrides(User $user): bool
    {
        return $this->userOverrides($user) !== [];
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public function valuesForForm(): array
    {
        $values = [];

        foreach (self::ACTIONS as $action => $definition) {
            foreach ($definition['defaults'] as $role => $default) {
                $values[$role][$action] = $this->configured()[$role][$action] ?? $default;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, array<string, bool>>  $values
     */
    public function save(array $values, int $updatedBy): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'button_access'],
            ['value' => $values, 'updated_by' => $updatedBy],
        );

        $this->configured = $values;
    }

    /**
     * @param  array<string, bool>  $values
     */
    public function saveForUser(User $user, array $values): void
    {
        foreach ($this->userConfigurableActions($user) as $key => $definition) {
            UserButtonPermission::query()->updateOrCreate(
                ['user_id' => $user->id, 'button_key' => $key],
                ['is_allowed' => (bool) ($values[$key] ?? false)],
            );
        }

        unset($this->userOverrides[$user->id]);
        $user->unsetRelation('buttonPermissions');
    }

    public function resetForUser(User $user): void
    {
        UserButtonPermission::query()->where('user_id', $user->id)->delete();
        unset($this->userOverrides[$user->id]);
        $user->unsetRelation('buttonPermissions');
    }

    /**
     * @return array<string, bool>
     */
    private function userOverrides(User $user): array
    {
        if (! array_key_exists($user->id, $this->userOverrides)) {
            $user->loadMissing('buttonPermissions');
            $this->userOverrides[$user->id] = $user->buttonPermissions
                ->mapWithKeys(fn (UserButtonPermission $permission): array => [
                    $permission->button_key => (bool) $permission->is_allowed,
                ])->all();
        }
        return $this->userOverrides[$user->id];
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function configured(): array
    {
        if ($this->configured !== null) {
            return $this->configured;
        }

        $value = SystemSetting::read('button_access', []);
        $this->configured = is_array($value) ? $value : [];

        return $this->configured;
    }
}
