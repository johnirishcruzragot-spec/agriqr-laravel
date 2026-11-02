<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subsidy_programs', function (Blueprint $table) {
            $table->id();
            $table->string('program_code', 50)->unique();
            $table->string('program_name', 150);
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('eligibility_rules')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('subsidy_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subsidy_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supply_id')->constrained()->restrictOnDelete();
            $table->decimal('maximum_quantity', 12, 2)->nullable();
            $table->timestamps();
            $table->unique(['subsidy_program_id', 'supply_id']);
        });

        Schema::create('distributions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number', 50)->unique();
            $table->foreignId('farmer_id')->constrained()->restrictOnDelete();
            $table->foreignId('subsidy_program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->string('eligibility_status', 20)->default('eligible');
            $table->string('transaction_status', 20)->default('completed')->index();
            $table->text('remarks')->nullable();
            $table->timestamp('distributed_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('distribution_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distribution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supply_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 50);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distribution_items');
        Schema::dropIfExists('distributions');
        Schema::dropIfExists('subsidy_items');
        Schema::dropIfExists('subsidy_programs');
    }
};
