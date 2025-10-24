<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->foreignId('lab_key_id')->nullable()->after('device')->constrained('lab_keys')->onDelete('set null');
            $table->string('key_name')->nullable()->after('lab_key_id'); // For direct key name storage
        });
    }

    public function down(): void
    {
        Schema::table('access_logs', function (Blueprint $table) {
            $table->dropForeign(['lab_key_id']);
            $table->dropColumn(['lab_key_id', 'key_name']);
        });
    }
};
