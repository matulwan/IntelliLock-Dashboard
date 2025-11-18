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
        Schema::table('lab_keys', function (Blueprint $table) {
            if (!Schema::hasColumn('lab_keys', 'last_used_at')) {
                $table->timestamp('last_used_at')->nullable()->after('key_rfid_uid');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_keys', function (Blueprint $table) {
            $table->dropColumn('last_used_at');
        });
    }
};