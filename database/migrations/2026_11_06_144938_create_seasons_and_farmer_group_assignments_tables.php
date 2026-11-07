<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('season_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->restrictOnDelete();
            $table->string('seed_type', 20);
            $table->timestamps();

            $table->unique(['season_id', 'seed_type']);
        });

        Schema::create('farmer_season_group_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_group_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['farmer_id', 'ended_at']);
        });

        Schema::create('program_season_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subsidy_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_group_id')->constrained()->restrictOnDelete();
            $table->decimal('rate_per_hectare', 12, 2);
            $table->timestamps();

            $table->unique(['subsidy_program_id', 'season_group_id']);
        });

        Schema::table('subsidy_items', function (Blueprint $table) {
            $table->decimal('unit_value', 12, 2)->default(0)->after('maximum_quantity');
        });

        Schema::table('distribution_items', function (Blueprint $table) {
            $table->decimal('unit_value', 12, 2)->default(0)->after('quantity');
            $table->decimal('line_value', 14, 2)->default(0)->after('unit_value');
        });

        Schema::table('distributions', function (Blueprint $table) {
            $table->foreignId('season_id')->nullable()->after('subsidy_program_id')->constrained()->restrictOnDelete();
            $table->foreignId('season_group_id')->nullable()->after('season_id')->constrained()->restrictOnDelete();
            $table->decimal('rate_per_hectare', 12, 2)->nullable();
            $table->decimal('eligible_hectares', 10, 4)->nullable();
            $table->decimal('value_cap', 14, 2)->nullable();
            $table->decimal('assistance_value', 14, 2)->nullable();
            $table->index(
                ['farmer_id', 'subsidy_program_id', 'season_id', 'transaction_status'],
                'distributions_farmer_program_season_status_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('distributions', function (Blueprint $table) {
            $table->dropIndex('distributions_farmer_program_season_status_index');
            $table->dropForeign(['season_group_id']);
            $table->dropForeign(['season_id']);
            $table->dropColumn([
                'season_id',
                'season_group_id',
                'rate_per_hectare',
                'eligible_hectares',
                'value_cap',
                'assistance_value',
            ]);
        });

        Schema::table('distribution_items', function (Blueprint $table) {
            $table->dropColumn(['unit_value', 'line_value']);
        });

        Schema::table('subsidy_items', function (Blueprint $table) {
            $table->dropColumn('unit_value');
        });

        Schema::dropIfExists('program_season_groups');
        Schema::dropIfExists('farmer_season_group_assignments');
        Schema::dropIfExists('season_groups');
        Schema::dropIfExists('seasons');
    }
};
