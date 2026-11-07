<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\ButtonAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ButtonAccessController extends Controller
{
    public function index(ButtonAccess $access): View
    {
        return view('button-access.index', [
            'actions' => collect($access->actions())->groupBy('section', true),
            'roles' => $access->roles(),
            'values' => $access->valuesForForm(),
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
                $values[$role][$key] = in_array(
                    $key,
                    $selectedButtons[$role] ?? [],
                    true,
                );
            }
        }

        $user = $request->user();

        DB::transaction(function () use ($access, $values, $user, $request): void {
            $access->save($values, $user->id);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'UPDATE',
                'module' => 'BUTTON_ACCESS',
                'description' => 'Updated configurable button and action access for Admin and Technician roles.',
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('button-access.index')->with('success', 'Button access settings saved.');
    }
}
