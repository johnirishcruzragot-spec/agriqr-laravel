<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\ButtonAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ButtonAccessController extends Controller
{
    public function index(Request $request, ButtonAccess $access): View
    {
        $users = User::query()
            ->whereIn('role', array_keys($access->roles()))
            ->orderBy('role')
            ->orderBy('name')
            ->get();

        $selectedUser = $users->firstWhere('id', (int) $request->query('user_id'));

        return view('button-access.index', [
            'actions' => collect($access->actions())->groupBy('section', true),
            'roles' => $access->roles(),
            'values' => $access->valuesForForm(),
            'users' => $users,
            'selectedUser' => $selectedUser,
            'userValues' => $selectedUser ? $access->valuesForUserForm($selectedUser) : [],
            'hasUserOverrides' => $selectedUser ? $access->userHasOverrides($selectedUser) : false,
        ]);
    }

    public function update(Request $request, ButtonAccess $access): RedirectResponse
    {
        $rules = [
            'buttons' => ['nullable', 'array:admin,technician'],
        ];

        foreach ($access->roles() as $role => $label) {
            $roleActions = collect($access->actions())
                ->filter(fn (array $action): bool => array_key_exists($role, $action['defaults']))
                ->keys()
                ->all();

            $rules["buttons.{$role}"] = ['sometimes', 'array'];
            $rules["buttons.{$role}.*"] = ['string', Rule::in($roleActions)];
        }

        $validated = $request->validate($rules);
        $selectedButtons = $validated['buttons'] ?? [];
        $values = [];

        foreach ($access->actions() as $key => $action) {
            foreach ($action['defaults'] as $role => $default) {
                $values[$role][$key] = in_array($key, $selectedButtons[$role] ?? [], true);
            }
        }

        $actor = $request->user();

        DB::transaction(function () use ($access, $values, $actor, $request): void {
            $access->save($values, $actor->id);

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'UPDATE',
                'module' => 'BUTTON_ACCESS',
                'description' => 'Updated default button and action access for Admin and Technician roles.',
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()
            ->route('button-access.index')
            ->with('success', 'Default role button access settings saved.');
    }

    public function updateUser(Request $request, User $managedUser, ButtonAccess $access): RedirectResponse
    {
        abort_unless(array_key_exists($managedUser->role, $access->roles()), 404);

        $allowedActions = array_keys($access->userConfigurableActions($managedUser));

        $validated = $request->validate([
            'user_buttons' => ['nullable', 'array'],
            'user_buttons.*' => ['string', Rule::in($allowedActions)],
        ]);

        $selectedButtons = $validated['user_buttons'] ?? [];
        $values = [];

        foreach ($allowedActions as $key) {
            $values[$key] = in_array($key, $selectedButtons, true);
        }

        $actor = $request->user();

        DB::transaction(function () use ($access, $managedUser, $values, $actor, $request): void {
            $access->saveForUser($managedUser, $values);

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'UPDATE',
                'module' => 'BUTTON_ACCESS',
                'record_id' => $managedUser->id,
                'description' => "Updated individual button and action permissions for {$managedUser->name} ({$managedUser->role}).",
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()
            ->route('button-access.index', ['user_id' => $managedUser->id])
            ->with('success', "Individual permissions saved for {$managedUser->name}.");
    }

    public function resetUser(Request $request, User $managedUser, ButtonAccess $access): RedirectResponse
    {
        abort_unless(array_key_exists($managedUser->role, $access->roles()), 404);

        $actor = $request->user();

        DB::transaction(function () use ($access, $managedUser, $actor, $request): void {
            $access->resetForUser($managedUser);

            AuditLog::create([
                'user_id' => $actor->id,
                'action' => 'UPDATE',
                'module' => 'BUTTON_ACCESS',
                'record_id' => $managedUser->id,
                'description' => "Reset individual button permissions for {$managedUser->name}; role defaults now apply.",
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()
            ->route('button-access.index', ['user_id' => $managedUser->id])
            ->with('success', "{$managedUser->name} now inherits the default {$managedUser->role} permissions.");
    }
}
