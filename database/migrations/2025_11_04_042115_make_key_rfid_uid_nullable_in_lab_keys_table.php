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
            // Drop unique constraint first
            $table->dropUnique(['key_rfid_uid']);
            
            // Make nullable
            $table->string('key_rfid_uid')->nullable()->change();
            
            // Re-add unique constraint (allows multiple NULLs)
            $table->unique('key_rfid_uid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_keys', function (Blueprint $table) {
            // Drop unique constraint
            $table->dropUnique(['key_rfid_uid']);
            
            // Make not nullable
            $table->string('key_rfid_uid')->nullable(false)->change();
            
            // Re-add unique constraint
            $table->unique('key_rfid_uid');
        });
    }
};
