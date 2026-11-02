<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farmers', function (Blueprint $table) {
            $table->id();
            $table->string('farmer_id', 30)->unique();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('suffix', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('barangay', 100)->nullable();
            $table->string('municipality', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('contact_number', 30)->nullable();
            $table->text('farm_information')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('farm_info', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('farm_size_hectares', 10, 2)->nullable();
            $table->string('farm_location')->nullable();
            $table->string('tenure_type', 20)->nullable();
            $table->string('landowner_name', 150)->nullable();
            $table->string('landowner_contact', 30)->nullable();
            $table->text('crop_information')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('rsbsa_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('rsbsa_number', 100)->unique();
            $table->string('registration_status', 20)->default('registered');
            $table->string('verification_status', 20)->default('unverified');
            $table->decimal('farm_size', 10, 2)->nullable();
            $table->string('farm_size_unit', 20)->default('hectare');
            $table->text('crop_information')->nullable();
            $table->date('registration_date')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsbsa_records');
        Schema::dropIfExists('farm_info');
        Schema::dropIfExists('farmers');
    }
};
