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
        // If the table already exists (as in current environment), skip creation to avoid errors.
        if (Schema::hasTable('access_logs')) {
            return;
        }

        Schema::create('access_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action');
            // Align with code expecting relation: User::hasMany(AccessLog::class, 'user', 'name')
            $table->string('user')->nullable();
            $table->string('key_name')->nullable();
            $table->string('device')->default('main_controller');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_logs');
    }
};
