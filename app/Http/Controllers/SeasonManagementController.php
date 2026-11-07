<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Season;
use App\Models\SeasonGroup;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SeasonManagementController extends Controller
{
    public function index(): View
    {
        return view('seasons.index', [
            'seasons' => Season::with(['groups' => fn ($query) => $query->withCount('assignments')])
                ->orderByDesc('id')
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('seasons.form', ['season' => new Season]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:seasons,name'],
        ]);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $season = DB::transaction(function () use ($validated, $request, $user): Season {
            $season = Season::create([
                'name' => $validated['name'],
                'status' => 'active',
            ]);

            foreach (SeasonGroup::SEED_TYPES as $seedType) {
                $season->groups()->create(['seed_type' => $seedType]);
            }

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'CREATE',
                'module' => 'SEASON',
                'record_id' => $season->id,
                'description' => "Created season {$season->name} with Hybrid and Inbred groups.",
                'ip_address' => $request->ip(),
            ]);

            return $season;
        });

        return redirect()->route('seasons.index')->with('success', "Season {$season->name} created.");
    }

    public function edit(Season $season): View
    {
        $season->load([
            'groups' => fn ($query) => $query->withCount([
                'assignments as current_assignments_count' => fn ($query) => $query->whereNull('ended_at'),
            ]),
        ]);

        return view('seasons.form', compact('season'));
    }

    public function update(Request $request, Season $season): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('seasons', 'name')->ignore($season),
            ],
            'status' => ['required', Rule::in(['active', 'closed'])],
        ]);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        DB::transaction(function () use ($validated, $season, $request, $user): void {
            $season->update($validated);
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'UPDATE',
                'module' => 'SEASON',
                'record_id' => $season->id,
                'description' => "Updated season {$season->name}; status is {$season->status}.",
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('seasons.index')->with('success', "Season {$season->name} updated.");
    }
}
