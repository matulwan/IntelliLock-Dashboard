<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This table stores photos captured by ESP32-CAM during access events
     */
    public function up(): void
    {
        Schema::create('event_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_log_id')->nullable()->constrained('access_logs')->onDelete('cascade');
            $table->foreignId('key_transaction_id')->nullable()->constrained('key_transactions')->onDelete('cascade');
            $table->string('photo_path'); // Path to stored photo
            $table->string('device')->default('lab_key_box'); // Which ESP32-CAM captured this
            $table->string('event_type')->default('access'); // access, checkout, checkin, alert
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Index for faster queries
            $table->index(['device', 'event_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_photos');
    }
};
