<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This table stores system alerts and error scenarios
     * Examples: door left open, RFID not tapped, sensor failures
     */
    public function up(): void
    {
        Schema::create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('device'); // Which device reported the alert
            $table->enum('alert_type', [
                'door_left_open',
                'rfid_not_tapped',
                'sensor_failure',
                'unauthorized_access',
                'low_battery',
                'connection_lost',
                'tamper_detected',
                'other'
            ]);
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->string('title'); // Short alert title
            $table->text('description'); // Detailed description
            $table->enum('status', ['active', 'acknowledged', 'resolved'])->default('active');
            $table->string('user_name')->nullable(); // User involved if applicable
            $table->timestamp('alert_time');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('acknowledged_by')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['device', 'status', 'alert_time']);
            $table->index(['alert_type', 'severity']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_alerts');
    }
};
