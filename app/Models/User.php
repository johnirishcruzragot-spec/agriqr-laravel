<?php

namespace App\Models;

use App\Services\ButtonAccess;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'username',
    'email',
    'password',
    'role',
    'is_active',
    'module_access',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    private const MODULE_LABELS = [
        'farmers' => 'Farmers',
        'distributions' => 'Distributions',
        'inventory' => 'Inventory',
        'programs' => 'Programs',
        'users' => 'Team access',
        'activity' => 'Audit log',
        'reports' => 'Reports',
        'settings' => 'System configuration',
        'database' => 'Database administration',
        'schema_editor' => 'Schema editor',
        'seasons' => 'Seasons and farmer groups',
        'requests' => 'Requests',
    ];

    private const ROLE_MODULES = [
        'admin' => [
            'farmers', 'distributions', 'inventory', 'programs',
            'users', 'activity', 'reports', 'settings', 'database',
            'seasons', 'requests',
        ],
        'technician' => [
            'farmers', 'distributions', 'inventory', 'programs',
            'reports', 'seasons', 'requests',
        ],
        'system_admin' => [
            'farmers', 'distributions', 'inventory', 'programs',
            'users', 'activity', 'reports', 'settings', 'database',
            'schema_editor', 'seasons', 'requests',
        ],
    ];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'module_access' => 'array',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function moduleLabels(): array
    {
        return self::MODULE_LABELS;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function roleModules(): array
    {
        return self::ROLE_MODULES;
    }

    /**
     * @return list<string>
     */
    public static function modulesForRole(string $role): array
    {
        return self::ROLE_MODULES[$role] ?? [];
    }

    public function canAccessModule(string $module): bool
    {
        if ($module === 'schema_editor') {
            return $this->role === 'system_admin';
        }

        if (in_array($this->role, ['admin', 'system_admin'], true)) {
            return true;
        }

        if (! in_array($module, self::modulesForRole($this->role), true)) {
            return false;
        }

        return ! is_array($this->module_access)
            || in_array($module, $this->module_access, true);
    }

    public function canUseButton(string $button): bool
    {
        return app(ButtonAccess::class)->allows($this, $button);
    }

    /**
     * @return list<string>
     */
    public function accessibleModules(): array
    {
        return array_values(array_filter(
            self::modulesForRole($this->role),
            fn (string $module): bool => $this->canAccessModule($module),
        ));
    }

    public function hasAnyRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true)
            || ($this->role === 'system_admin' && in_array('admin', $roles, true));
    }
}
