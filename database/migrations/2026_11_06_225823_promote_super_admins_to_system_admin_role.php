<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('user_role_migration_snapshots')) {
            return;
        }

        DB::table('user_role_migration_snapshots')
            ->where('previous_role', 'super_admin')
            ->orderBy('user_id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    DB::table('users')
                        ->where('id', $user->user_id)
                        ->where('role', 'admin')
                        ->update(['role' => 'system_admin']);
                }
            }, 'user_id');
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_role_migration_snapshots')) {
            return;
        }

        DB::table('user_role_migration_snapshots')
            ->where('previous_role', 'super_admin')
            ->orderBy('user_id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    DB::table('users')
                        ->where('id', $user->user_id)
                        ->where('role', 'system_admin')
                        ->update(['role' => 'admin']);
                }
            }, 'user_id');
    }
};
