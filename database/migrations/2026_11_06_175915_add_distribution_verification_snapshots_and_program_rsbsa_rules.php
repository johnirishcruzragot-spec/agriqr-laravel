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
            $table->boolean('requires_verified_rsbsa')->default(true)->after('season_id');
        });

        Schema::table('distributions', function (Blueprint $table) {
            $table->timestamp('face_verified_at')->nullable()->after('eligible_hectares');
            $table->foreignId('face_verified_by')->nullable()->after('face_verified_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('distribution_items', function (Blueprint $table) {
            $table->decimal('maximum_quantity_per_farmer', 12, 2)->nullable()->after('quantity_cap');
            $table->decimal('computed_quantity', 12, 2)->nullable()->after('maximum_quantity_per_farmer');
            $table->string('reduction_reason', 500)->nullable()->after('computed_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('distribution_items', function (Blueprint $table) {
            $table->dropColumn([
                'maximum_quantity_per_farmer',
                'computed_quantity',
                'reduction_reason',
            ]);
        });

        Schema::table('distributions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('face_verified_by');
            $table->dropColumn('face_verified_at');
        });

        Schema::table('subsidy_programs', function (Blueprint $table) {
            $table->dropColumn('requires_verified_rsbsa');
        });
    }
};
