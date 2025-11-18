<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('iot_devices', function (Blueprint $table) {
            $table->id();
            $table->string('terminal_name')->unique();
            $table->string('device_type')->default('access_control');
            $table->enum('status', ['online', 'offline', 'error'])->default('offline');
            $table->string('ip_address')->nullable();
            $table->integer('wifi_strength')->nullable();
            $table->string('uptime')->nullable();
            $table->bigInteger('free_memory')->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->timestamps();
            
            $table->index('status');
            $table->index('last_seen');
        });
    }

    public function down()
    {
        Schema::dropIfExists('iot_devices');
    }
};