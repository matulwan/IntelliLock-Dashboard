<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Only add columns that don't exist
            if (!Schema::hasColumn('users', 'fingerprint_id')) {
                $table->integer('fingerprint_id')->nullable()->unique()->after('rfid_uid');
            }
            if (!Schema::hasColumn('users', 'iot_access')) {
                $table->boolean('iot_access')->default(false)->after('role');
            }
            if (!Schema::hasColumn('users', 'notes')) {
                $table->text('notes')->nullable()->after('iot_access');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['rfid_uid', 'fingerprint_id', 'role', 'iot_access', 'notes']);
        });
    }
};
