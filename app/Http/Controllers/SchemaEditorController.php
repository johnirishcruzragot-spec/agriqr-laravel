<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SchemaEditorField;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class SchemaEditorController extends Controller
{
    private const PROTECTED_TABLES = [
        'audit_logs',
        'cache',
        'cache_locks',
        'failed_jobs',
        'job_batches',
        'jobs',
        'migrations',
        'password_reset_tokens',
        'schema_editor_fields',
        'sessions',
        'user_role_migration_snapshots',
        'users',
    ];

    private const COLUMN_TYPES = [
        'string' => 'Short text (255 characters)',
        'text' => 'Long text',
        'integer' => 'Whole number',
        'decimal' => 'Decimal number (2 places)',
        'boolean' => 'Yes or no',
        'date' => 'Date',
        'datetime' => 'Date and time',
    ];

    public function index(): View
    {
        $tableNames = $this->editableTables();
        $fields = SchemaEditorField::query()
            ->whereIn('table_name', $tableNames)
            ->orderBy('table_name')
            ->orderBy('column_name')
            ->get()
            ->groupBy('table_name');

        $tables = array_map(fn (string $tableName): array => [
            'name' => $tableName,
            'columns' => Schema::getColumns($tableName),
            'managedFields' => $fields->get($tableName, collect()),
        ], $tableNames);

        return view('schema-editor.index', [
            'tables' => $tables,
            'columnTypes' => self::COLUMN_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'table_name' => ['required', 'string', Rule::in($this->editableTables())],
            'column_name' => ['required', 'string', 'max:64', 'regex:/\Acustom_[a-z][a-z0-9_]{0,56}\z/'],
            'column_type' => ['required', 'string', Rule::in(array_keys(self::COLUMN_TYPES))],
        ]);

        if (Schema::hasColumn($data['table_name'], $data['column_name'])) {
            return back()->withErrors([
                'column_name' => 'That field already exists on the selected table.',
            ])->withInput();
        }

        Schema::table($data['table_name'], function (Blueprint $table) use ($data): void {
            match ($data['column_type']) {
                'string' => $table->string($data['column_name'])->nullable(),
                'text' => $table->text($data['column_name'])->nullable(),
                'integer' => $table->integer($data['column_name'])->nullable(),
                'decimal' => $table->decimal($data['column_name'], 12, 2)->nullable(),
                'boolean' => $table->boolean($data['column_name'])->nullable(),
                'date' => $table->date($data['column_name'])->nullable(),
                'datetime' => $table->dateTime($data['column_name'])->nullable(),
            };
        });

        $field = SchemaEditorField::create([
            'table_name' => $data['table_name'],
            'column_name' => $data['column_name'],
            'column_type' => $data['column_type'],
            'created_by' => $request->user()->id,
        ]);

        $this->recordChange($request, 'CREATE', $field, "Added {$field->column_name} to {$field->table_name}.");

        return redirect()->route('schema-editor.index')->with('success', 'Field added.');
    }

    public function update(Request $request, SchemaEditorField $schemaEditorField): RedirectResponse
    {
        $this->ensureManagedFieldIsEditable($schemaEditorField);

        $data = $request->validate([
            'column_name' => ['required', 'string', 'max:64', 'regex:/\Acustom_[a-z][a-z0-9_]{0,56}\z/'],
        ]);

        if ($data['column_name'] === $schemaEditorField->column_name) {
            return back()->withErrors([
                'column_name' => 'Choose a different field name.',
            ])->withInput();
        }

        if (Schema::hasColumn($schemaEditorField->table_name, $data['column_name'])) {
            return back()->withErrors([
                'column_name' => 'That field already exists on the selected table.',
            ])->withInput();
        }

        Schema::table($schemaEditorField->table_name, function (Blueprint $table) use ($schemaEditorField, $data): void {
            $table->renameColumn($schemaEditorField->column_name, $data['column_name']);
        });

        $oldName = $schemaEditorField->column_name;
        $schemaEditorField->update(['column_name' => $data['column_name']]);
        $this->recordChange(
            $request,
            'UPDATE',
            $schemaEditorField,
            "Renamed {$oldName} to {$schemaEditorField->column_name} on {$schemaEditorField->table_name}.",
        );

        return redirect()->route('schema-editor.index')->with('success', 'Field renamed.');
    }

    public function destroy(Request $request, SchemaEditorField $schemaEditorField): RedirectResponse
    {
        $this->ensureManagedFieldIsEditable($schemaEditorField);

        $request->validate([
            'confirmation' => ['required', 'string', Rule::in([$schemaEditorField->column_name])],
        ]);

        if (DB::table($schemaEditorField->table_name)
            ->whereNotNull($schemaEditorField->column_name)
            ->exists()) {
            return back()->withErrors([
                'confirmation' => 'This field contains data. Clear or export its values before deleting it.',
            ]);
        }

        $tableName = $schemaEditorField->table_name;
        $columnName = $schemaEditorField->column_name;

        Schema::table($tableName, function (Blueprint $table) use ($columnName): void {
            $table->dropColumn($columnName);
        });

        $schemaEditorField->delete();
        $this->recordChange($request, 'DELETE', $schemaEditorField, "Deleted empty field {$columnName} from {$tableName}.");

        return redirect()->route('schema-editor.index')->with('success', 'Empty field deleted.');
    }

    /**
     * @return list<string>
     */
    private function editableTables(): array
    {
        return array_values(array_filter(
            Schema::getTableListing(schemaQualified: false),
            fn (string $tableName): bool => ! in_array($tableName, self::PROTECTED_TABLES, true)
                && ! str_starts_with($tableName, 'sqlite_'),
        ));
    }

    private function ensureManagedFieldIsEditable(SchemaEditorField $field): void
    {
        abort_unless(in_array($field->table_name, $this->editableTables(), true), 404);
        abort_unless(Schema::hasColumn($field->table_name, $field->column_name), 404);
    }

    private function recordChange(
        Request $request,
        string $action,
        SchemaEditorField $field,
        string $description,
    ): void {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'module' => 'SCHEMA',
            'record_id' => $field->id,
            'description' => $description,
            'ip_address' => $request->ip(),
        ]);
    }
}
