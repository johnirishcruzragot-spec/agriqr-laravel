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
    private const ROLES = ['super_admin', 'admin', 'staff', 'supply_officer', 'custodian'];

    public function index(): View
    {
        return view('users.index', [
            'users' => User::orderBy('name')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('users.form', ['managedUser' => new User, 'roles' => $this->availableRoles(request())]);
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
                'description' => "Created team account {$managedUser->username}.",
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('users.index')->with('success', 'Team member created.');
    }

    public function edit(User $user): View
    {
        abort_if($user->role === 'super_admin' && request()->user()->role !== 'super_admin', 403);

        return view('users.form', ['managedUser' => $user, 'roles' => $this->availableRoles(request())]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->role === 'super_admin' && $request->user()->role !== 'super_admin', 403);

        $data = $this->validatedData($request, $user);
        $this->authorizeRoleAssignment($request, $data['role']);

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
            $user->role === 'super_admin'
            && $user->is_active
            && ($data['role'] !== 'super_admin' || ! $data['is_active'])
            && User::where('role', 'super_admin')->where('is_active', true)->count() <= 1
        ) {
            return back()->withErrors(['role' => 'At least one active super administrator must remain.'])->withInput();
        }

        DB::transaction(function () use ($request, $user, $data) {
            $user->update($data);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'UPDATE',
                'module' => 'USER',
                'record_id' => $user->id,
                'description' => "Updated team account {$user->username}.",
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('users.index')->with('success', 'Team member updated.');
    }

    private function validatedData(Request $request, ?User $user = null): array
    {
        return $request->validate([
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
            'role' => ['required', Rule::in(self::ROLES)],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function authorizeRoleAssignment(Request $request, string $role): void
    {
        abort_if($role === 'super_admin' && $request->user()->role !== 'super_admin', 403);
    }

    /**
     * @return list<string>
     */
    private function availableRoles(Request $request): array
    {
        return $request->user()->role === 'super_admin'
            ? self::ROLES
            : array_values(array_diff(self::ROLES, ['super_admin']));
    }
}
