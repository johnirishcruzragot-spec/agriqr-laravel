<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barangays', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('normalized_name', 100)->unique();
            $table->boolean('is_active')->default(false)->index();
            $table->timestamps();
        });

        $configuredBarangays = json_decode(
            (string) DB::table('system_settings')->where('key', 'barangays')->value('value'),
            true,
        );
        $configuredBarangays = is_array($configuredBarangays) ? $configuredBarangays : [];
        $names = [];

        foreach ($configuredBarangays as $name) {
            if (is_string($name) && trim($name) !== '') {
                $names[mb_strtolower(trim($name))] = ['name' => trim($name), 'is_active' => true];
            }
        }

        foreach (DB::table('farmers')->whereNotNull('barangay')->pluck('barangay') as $name) {
            if (is_string($name) && trim($name) !== '') {
                $key = mb_strtolower(trim($name));
                $names[$key] ??= ['name' => trim($name), 'is_active' => false];
            }
        }

        $now = now();
        foreach ($names as $normalizedName => $barangay) {
            DB::table('barangays')->insert([
                'name' => $barangay['name'],
                'normalized_name' => $normalizedName,
                'is_active' => $barangay['is_active'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('farmers', function (Blueprint $table): void {
            $table->foreignId('barangay_id')
                ->nullable()
                ->after('barangay')
                ->constrained('barangays')
                ->restrictOnDelete();
        });

        $barangayIds = DB::table('barangays')->pluck('id', 'normalized_name')->all();
        DB::table('farmers')
            ->whereNotNull('barangay')
            ->orderBy('id')
            ->chunkById(100, function ($farmers) use ($barangayIds): void {
                foreach ($farmers as $farmer) {
                    $normalizedName = mb_strtolower(trim((string) $farmer->barangay));

                    if (isset($barangayIds[$normalizedName])) {
                        DB::table('farmers')->where('id', $farmer->id)->update([
                            'barangay_id' => $barangayIds[$normalizedName],
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('farmers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('barangay_id');
        });

        Schema::dropIfExists('barangays');
    }
};
