<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_role_migration_snapshots')) {
            Schema::create('user_role_migration_snapshots', function (Blueprint $table): void {
                $table->unsignedBigInteger('user_id')->primary();
                $table->string('previous_role', 30);
            });
        }

        DB::table('users')
            ->whereIn('role', ['super_admin', 'admin', 'staff', 'technician', 'custodian', 'supply_officer'])
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    DB::table('user_role_migration_snapshots')->insertOrIgnore([
                        'user_id' => $user->id,
                        'previous_role' => $user->role,
                    ]);
                }
            });

        DB::table('users')->whereIn('role', ['super_admin', 'admin'])->update(['role' => 'admin']);
        DB::table('users')->whereIn('role', ['staff', 'technician', 'custodian', 'supply_officer'])
            ->update(['role' => 'technician']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_role_migration_snapshots')) {
            return;
        }

        DB::table('user_role_migration_snapshots')
            ->orderBy('user_id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $currentRole = DB::table('users')->where('id', $user->user_id)->value('role');
                    $expectedCurrentRole = in_array($user->previous_role, ['super_admin', 'admin'], true)
                        ? 'admin'
                        : 'technician';

                    if ($currentRole !== $expectedCurrentRole) {
                        continue;
                    }

                    DB::table('users')
                        ->where('id', $user->user_id)
                        ->update(['role' => $user->previous_role]);
                }
            }, 'user_id');

        Schema::drop('user_role_migration_snapshots');
    }
};
