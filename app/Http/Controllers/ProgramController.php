<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SubsidyProgram;
use App\Models\Supply;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProgramController extends Controller
{
    public function index(): View
    {
        $programs = SubsidyProgram::withCount('supplies')
            ->with('supplies')
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('programs.index', compact('programs'));
    }

    public function create(): View
    {
        return view('programs.form', [
            'program' => new SubsidyProgram,
            'supplies' => Supply::where('status', 'active')->orderBy('supply_name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        DB::transaction(function () use ($data, $request) {
            $program = SubsidyProgram::create([
                ...$data['program'],
                'created_by' => $request->user()->id,
            ]);
            $program->supplies()->sync($data['supplies']);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'CREATE',
                'module' => 'PROGRAM',
                'record_id' => $program->id,
                'description' => "Created subsidy program {$program->program_code}.",
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('programs.index')->with('success', 'Subsidy program created.');
    }

    public function edit(SubsidyProgram $program): View
    {
        $program->load('supplies');

        return view('programs.form', [
            'program' => $program,
            'supplies' => Supply::where('status', 'active')->orderBy('supply_name')->get(),
        ]);
    }

    public function update(Request $request, SubsidyProgram $program): RedirectResponse
    {
        $data = $this->validatedData($request, $program);

        DB::transaction(function () use ($data, $program, $request) {
            $program->update($data['program']);
            $program->supplies()->sync($data['supplies']);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'UPDATE',
                'module' => 'PROGRAM',
                'record_id' => $program->id,
                'description' => "Updated subsidy program {$program->program_code}.",
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('programs.index')->with('success', 'Subsidy program updated.');
    }

    private function validatedData(Request $request, ?SubsidyProgram $program = null): array
    {
        $validated = $request->validate([
            'program_code' => [
                'required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('subsidy_programs', 'program_code')->ignore($program),
            ],
            'program_name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'eligibility_rules' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:draft,active,paused,completed,archived'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.supply_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('supplies', 'id')->where('status', 'active'),
            ],
            'items.*.maximum_quantity' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
        ]);

        $items = collect($validated['items'])
            ->mapWithKeys(fn (array $item) => [
                $item['supply_id'] => ['maximum_quantity' => $item['maximum_quantity'] ?? null],
            ])
            ->all();

        return [
            'program' => collect($validated)->except('items')->all(),
            'supplies' => $items,
        ];
    }
}
