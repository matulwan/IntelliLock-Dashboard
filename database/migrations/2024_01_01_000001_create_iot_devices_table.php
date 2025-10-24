<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iot_devices', function (Blueprint $table) {
            $table->id();
            $table->string('terminal_name')->unique();
            $table->string('device_type')->default('access_control');
            $table->enum('status', ['online', 'offline', 'error'])->default('offline');
            $table->ipAddress('ip_address')->nullable();
            $table->integer('wifi_strength')->nullable(); // dBm value
            $table->integer('uptime')->nullable(); // seconds
            $table->integer('free_memory')->nullable(); // bytes
            $table->timestamp('last_seen')->nullable();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iot_devices');
    }
};
