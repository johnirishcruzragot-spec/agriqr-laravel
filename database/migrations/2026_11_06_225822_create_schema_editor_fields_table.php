<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schema_editor_fields', function (Blueprint $table) {
            $table->id();
            $table->string('table_name', 64);
            $table->string('column_name', 64);
            $table->string('column_type', 20);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['table_name', 'column_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('schema_editor_fields') && DB::table('schema_editor_fields')->exists()) {
            throw new RuntimeException(
                'Schema editor fields must be removed through the System Admin schema editor before rolling back this migration.',
            );
        }

        Schema::dropIfExists('schema_editor_fields');
    }
};
