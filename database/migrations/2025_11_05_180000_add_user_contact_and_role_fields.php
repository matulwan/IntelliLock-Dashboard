<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'matrix_number')) {
                $table->string('matrix_number')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('Student')->after('matrix_number');
            }
            // Ensure rfid_uid exists (older migration may have added it)
            if (!Schema::hasColumn('users', 'rfid_uid')) {
                $table->string('rfid_uid')->nullable()->after('role');
            }
            // Ensure fingerprint_id exists (older migration may have added it)
            if (!Schema::hasColumn('users', 'fingerprint_id')) {
                $table->integer('fingerprint_id')->nullable()->after('rfid_uid');
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
            if (Schema::hasColumn('users', 'phone')) {
                $table->dropColumn('phone');
            }
            if (Schema::hasColumn('users', 'matrix_number')) {
                $table->dropColumn('matrix_number');
            }
            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
            // Do not drop rfid_uid/fingerprint_id/iot_access/notes here to avoid data loss from other migrations
        });
    }
};


