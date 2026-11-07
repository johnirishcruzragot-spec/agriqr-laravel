<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subsidy_programs', function (Blueprint $table) {
            $table->foreignId('season_id')->nullable()->after('program_name')->constrained()->restrictOnDelete();
        });

        Schema::table('program_season_groups', function (Blueprint $table) {
            $table->decimal('rate_per_hectare', 12, 2)->nullable()->change();
        });

        Schema::create('program_supply_group_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subsidy_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supply_id')->constrained()->restrictOnDelete();
            $table->foreignId('season_group_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_per_hectare', 12, 4);
            $table->decimal('maximum_quantity_per_farmer', 12, 2);
            $table->timestamps();

            $table->unique(
                ['subsidy_program_id', 'supply_id', 'season_group_id'],
                'program_supply_group_rates_unique',
            );
        });

        Schema::table('distribution_items', function (Blueprint $table) {
            $table->decimal('quantity_rate_per_hectare', 12, 4)->nullable()->after('quantity');
            $table->decimal('quantity_cap', 12, 2)->nullable()->after('quantity_rate_per_hectare');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('distribution_items', function (Blueprint $table) {
            $table->dropColumn(['quantity_rate_per_hectare', 'quantity_cap']);
        });

        Schema::dropIfExists('program_supply_group_rates');

        Schema::table('subsidy_programs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('season_id');
        });
    }
};
