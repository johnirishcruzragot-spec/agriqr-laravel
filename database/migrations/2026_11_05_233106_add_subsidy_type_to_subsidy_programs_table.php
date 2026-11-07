<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subsidy_programs', function (Blueprint $table) {
            $table->string('subsidy_type', 20)->default('in_kind');
        });
    }

    public function down(): void
    {
        Schema::table('subsidy_programs', function (Blueprint $table) {
            $table->dropColumn('subsidy_type');
        });
    }
};
