<?php

namespace Tests\Feature;

use App\Models\Farmer;
use App\Models\SchemaEditorField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SystemSchemaEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_editor_is_only_available_to_system_admins(): void
    {
        $this->get(route('schema-editor.index'))
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('schema-editor.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'technician']))
            ->get(route('schema-editor.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'system_admin']))
            ->get(route('schema-editor.index'))
            ->assertOk()
            ->assertSee('Schema editor')
            ->assertSee('SYSTEM ADMIN')
            ->assertSee(route('schema-editor.index'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('2 administrators');
    }

    public function test_system_admin_can_add_rename_and_delete_only_empty_managed_fields(): void
    {
        $systemAdmin = User::factory()->create(['role' => 'system_admin']);
        $farmer = Farmer::factory()->create();

        $this->actingAs($systemAdmin)
            ->post(route('schema-editor.store'), [
                'table_name' => 'farmers',
                'column_name' => 'custom_notes',
                'column_type' => 'text',
            ])
            ->assertRedirect(route('schema-editor.index'))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Schema::hasColumn('farmers', 'custom_notes'));
        $this->assertDatabaseHas('schema_editor_fields', [
            'table_name' => 'farmers',
            'column_name' => 'custom_notes',
            'column_type' => 'text',
            'created_by' => $systemAdmin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $systemAdmin->id,
            'module' => 'SCHEMA',
            'action' => 'CREATE',
        ]);

        DB::table('farmers')->where('id', $farmer->id)->update(['custom_notes' => 'Preserve this value']);
        $field = SchemaEditorField::query()->sole();

        $this->put(route('schema-editor.update', $field), [
            'column_name' => 'custom_profile_notes',
        ])->assertRedirect(route('schema-editor.index'));

        $this->assertTrue(Schema::hasColumn('farmers', 'custom_profile_notes'));
        $this->assertFalse(Schema::hasColumn('farmers', 'custom_notes'));
        $this->assertSame(
            'Preserve this value',
            DB::table('farmers')->where('id', $farmer->id)->value('custom_profile_notes'),
        );

        $field->refresh();
        $this->from(route('schema-editor.index'))
            ->delete(route('schema-editor.destroy', $field), [
                'confirmation' => 'custom_profile_notes',
            ])
            ->assertRedirect(route('schema-editor.index'))
            ->assertSessionHasErrors('confirmation');

        $this->assertTrue(Schema::hasColumn('farmers', 'custom_profile_notes'));

        DB::table('farmers')->where('id', $farmer->id)->update(['custom_profile_notes' => null]);

        $this->delete(route('schema-editor.destroy', $field), [
            'confirmation' => 'custom_profile_notes',
        ])->assertRedirect(route('schema-editor.index'));

        $this->assertFalse(Schema::hasColumn('farmers', 'custom_profile_notes'));
        $this->assertDatabaseMissing('schema_editor_fields', ['id' => $field->id]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $systemAdmin->id,
            'module' => 'SCHEMA',
            'action' => 'DELETE',
        ]);
    }

    public function test_schema_editor_rejects_protected_tables_and_untrusted_identifiers(): void
    {
        $systemAdmin = User::factory()->create(['role' => 'system_admin']);
        $this->actingAs($systemAdmin);

        $this->from(route('schema-editor.index'))
            ->post(route('schema-editor.store'), [
                'table_name' => 'users',
                'column_name' => 'custom_notes',
                'column_type' => 'string',
            ])
            ->assertRedirect(route('schema-editor.index'))
            ->assertSessionHasErrors('table_name');

        $this->from(route('schema-editor.index'))
            ->post(route('schema-editor.store'), [
                'table_name' => 'farmers',
                'column_name' => 'notes; DROP TABLE users',
                'column_type' => 'string',
            ])
            ->assertRedirect(route('schema-editor.index'))
            ->assertSessionHasErrors('column_name');

        $this->from(route('schema-editor.index'))
            ->post(route('schema-editor.store'), [
                'table_name' => 'farmers',
                'column_name' => 'custom_unapproved',
                'column_type' => 'json',
            ])
            ->assertRedirect(route('schema-editor.index'))
            ->assertSessionHasErrors('column_type');

        $this->assertFalse(Schema::hasColumn('farmers', 'custom_unapproved'));
        $this->assertDatabaseCount('schema_editor_fields', 0);
    }
}
