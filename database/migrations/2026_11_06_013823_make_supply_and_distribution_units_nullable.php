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
        DB::table('supplies')
            ->whereIn('category', ['seed', 'seeds'])
            ->update(['category' => 'Seeds']);

        Schema::table('supplies', function (Blueprint $table) {
            $table->string('unit', 50)->nullable()->change();
        });

        Schema::table('distribution_items', function (Blueprint $table) {
            $table->string('unit', 50)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('supplies')->whereNull('unit')->update(['unit' => '']);
        DB::table('distribution_items')->whereNull('unit')->update(['unit' => '']);

        // Keep the standardized category labels when rolling back unit nullability.
        Schema::table('supplies', function (Blueprint $table) {
            $table->string('unit', 50)->nullable(false)->change();
        });

        Schema::table('distribution_items', function (Blueprint $table) {
            $table->string('unit', 50)->nullable(false)->change();
        });
    }
};
