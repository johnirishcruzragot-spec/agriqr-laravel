<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereIn('role', ['custodian', 'supply_officer'])
            ->update(['role' => 'staff']);
    }

    public function down(): void
    {
        // Do not restore retired roles after accounts may have been reassigned.
    }
};
