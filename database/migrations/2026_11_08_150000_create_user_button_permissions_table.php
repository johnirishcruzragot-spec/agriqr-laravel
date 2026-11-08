<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('user_button_permissions', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('button_key', 100); $table->boolean('is_allowed')->default(false);
            $table->timestamps(); $table->unique(['user_id', 'button_key']); $table->index('button_key');
        });
    }
    public function down(): void { Schema::dropIfExists('user_button_permissions'); }
};
