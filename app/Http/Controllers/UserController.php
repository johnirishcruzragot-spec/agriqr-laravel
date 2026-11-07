<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private const ROLES = ['admin', 'technician'];

    public function index(): View
    {
        return view('users.index', [
            'users' => User::orderBy('name')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('users.form', [
            'managedUser' => new User,
            'roles' => $this->availableRoles(request()),
            'moduleLabels' => $this->availableModuleLabels(request()),
            'roleModules' => User::roleModules(),
            'canManageModuleAccess' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $this->authorizeRoleAssignment($request, $data['role']);
        $data['password'] = Hash::make($data['password']);

        DB::transaction(function () use ($request, $data) {
            $managedUser = User::create($data);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'CREATE',
                'module' => 'USER',
                'record_id' => $managedUser->id,
                'description' => "Created {$managedUser->role} account {$managedUser->username} with module access: ".$this->moduleAccessDescription($managedUser->accessibleModules()).'.'.($managedUser->is_active ? ' Account active.' : ' Account inactive.'),
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('users.index')->with('success', 'Team member created.');
    }

    public function edit(User $user): View
    {
        abort_if(
            $user->role === 'system_admin' && request()->user()->role !== 'system_admin',
            403,
        );

        return view('users.form', [
            'managedUser' => $user,
            'roles' => $this->availableRoles(request()),
            'moduleLabels' => $this->availableModuleLabels(request()),
            'roleModules' => User::roleModules(),
            'canManageModuleAccess' => true,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validatedData($request, $user);
        $this->authorizeRoleAssignment($request, $data['role']);
        abort_if(
            $user->role === 'system_admin' && $request->user()->role !== 'system_admin',
            403,
        );

        if (filled($data['password'] ?? null)) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if ($request->user()->is($user) && ! $data['is_active']) {
            return back()->withErrors(['is_active' => 'You cannot deactivate your own account.'])->withInput();
        }

        if ($request->user()->is($user) && $data['role'] !== $user->role) {
            return back()->withErrors(['role' => 'You cannot change your own account role.'])->withInput();
        }

        if (
            $user->role === 'admin'
            && $user->is_active
            && ($data['role'] !== 'admin' || ! $data['is_active'])
            && User::where('role', 'admin')->where('is_active', true)->count() <= 1
        ) {
            return back()->withErrors(['role' => 'At least one active administrator must remain.'])->withInput();
        }

        if (
            $user->role === 'system_admin'
            && $user->is_active
            && ($data['role'] !== 'system_admin' || ! $data['is_active'])
            && User::where('role', 'system_admin')->where('is_active', true)->count() <= 1
        ) {
            return back()->withErrors(['role' => 'At least one active System Admin must remain.'])->withInput();
        }

        DB::transaction(function () use ($request, $user, $data) {
            $changes = [];

            if ($user->role !== $data['role']) {
                $changes[] = 'role changed from '.$user->role.' to '.$data['role'];
            }

            if ($user->is_active !== (bool) $data['is_active']) {
                $changes[] = $data['is_active'] ? 'account activated' : 'account deactivated';
            }

            if (filled($data['password'] ?? null)) {
                $changes[] = 'password reset';
            }

            if (array_key_exists('module_access', $data)) {
                $currentModules = $user->accessibleModules();
                $updatedModules = in_array($data['role'], ['admin', 'system_admin'], true)
                    ? User::modulesForRole($data['role'])
                    : ($data['module_access'] ?? User::modulesForRole($data['role']));

                if ($currentModules !== $updatedModules) {
                    $changes[] = 'module access changed from '.$this->moduleAccessDescription($currentModules)
                        .' to '.$this->moduleAccessDescription($updatedModules);
                }
            }

            $user->update($data);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'UPDATE',
                'module' => 'USER',
                'record_id' => $user->id,
                'description' => "Updated team account {$user->username}.".($changes === [] ? '' : ' Changes: '.implode('; ', $changes).'.'),
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('users.index')->with('success', 'Team member updated.');
    }

    private function validatedData(Request $request, ?User $user = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required', 'string', 'max:50',
                Rule::unique('users', 'username')->ignore($user),
            ],
            'email' => [
                'required', 'email', 'max:254',
                Rule::unique('users', 'email')->ignore($user),
            ],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:12', 'confirmed'],
            'role' => ['required', Rule::in($this->availableRoles($request))],
            'is_active' => ['required', 'boolean'],
        ];

        $role = $request->input('role');
        $allowedModules = User::modulesForRole(is_string($role) ? $role : '');
        $rules['module_access'] = ['sometimes', 'array', 'max:'.count($allowedModules)];
        $rules['module_access.*'] = ['required', 'string', Rule::in($allowedModules)];

        $validated = $request->validate($rules);
        $validated['module_access'] = in_array($validated['role'], ['admin', 'system_admin'], true)
            ? null
            : array_values(array_intersect(
                User::modulesForRole($validated['role']),
                array_unique($validated['module_access'] ?? User::modulesForRole($validated['role'])),
            ));

        return $validated;
    }

    /**
     * @param  list<string>  $modules
     */
    private function moduleAccessDescription(array $modules): string
    {
        $labels = User::moduleLabels();

        return $modules === []
            ? 'no modules'
            : implode(', ', array_map(static fn (string $module): string => $labels[$module], $modules));
    }

    private function authorizeRoleAssignment(Request $request, string $role): void
    {
        abort_if(
            $role === 'system_admin' && $request->user()->role !== 'system_admin',
            403,
        );
    }

    /**
     * @return list<string>
     */
    private function availableRoles(Request $request): array
    {
        return $request->user()->role === 'system_admin'
            ? ['system_admin', ...self::ROLES]
            : self::ROLES;
    }

    /**
     * @return array<string, string>
     */
    private function availableModuleLabels(Request $request): array
    {
        return array_filter(
            User::moduleLabels(),
            fn (string $label, string $module): bool => $module !== 'schema_editor'
                || $request->user()->role === 'system_admin',
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
