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
        Schema::table('farmers', function (Blueprint $table) {
            $table->string('photo_path')->nullable();
        });

        Schema::table('rsbsa_records', function (Blueprint $table) {
            $table->date('date_encoded')->nullable();
            $table->string('ffrs_generated_number', 100)->nullable();
            $table->string('gender', 30)->nullable();
            $table->date('birthdate')->nullable();
            $table->boolean('is_farmer')->nullable();
            $table->boolean('is_farmworker')->nullable();
            $table->boolean('is_fisherfolk')->nullable();
            $table->boolean('is_agribusiness')->nullable();
            $table->boolean('is_ip')->nullable();
            $table->string('tribe', 150)->nullable();
            $table->boolean('is_organic_practitioner')->nullable();
            $table->boolean('is_arb')->nullable();
            $table->string('farmer_address_1', 255)->nullable();
            $table->string('farmer_address_2', 255)->nullable();
            $table->string('farmer_address_3', 255)->nullable();
        });

        Schema::create('farmer_parcels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_id')->constrained()->cascadeOnDelete();
            $table->string('parcel_number', 50)->nullable();
            $table->string('parcel_address_1', 255)->nullable();
            $table->string('parcel_address_2', 255)->nullable();
            $table->string('parcel_address_3', 255)->nullable();
            $table->decimal('parcel_area', 12, 4)->nullable();
            $table->string('commodity_name', 150)->nullable();
            $table->decimal('crop_area', 12, 4)->nullable();
            $table->string('farm_type', 100)->nullable();
            $table->decimal('number_of_heads', 12, 2)->nullable();
            $table->string('agency', 150)->nullable();
            $table->string('ownership_type', 100)->nullable();
            $table->string('owner_name', 150)->nullable();
            $table->timestamps();

            $table->unique(['farmer_id', 'parcel_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farmer_parcels');

        Schema::table('rsbsa_records', function (Blueprint $table) {
            $table->dropColumn([
                'date_encoded',
                'ffrs_generated_number',
                'gender',
                'birthdate',
                'is_farmer',
                'is_farmworker',
                'is_fisherfolk',
                'is_agribusiness',
                'is_ip',
                'tribe',
                'is_organic_practitioner',
                'is_arb',
                'farmer_address_1',
                'farmer_address_2',
                'farmer_address_3',
            ]);
        });

        Schema::table('farmers', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
