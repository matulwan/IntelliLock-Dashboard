<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key_name'); // Lab A, Lab B, etc.
            $table->string('key_rfid_uid')->unique(); // RFID tag on each key
            $table->string('description')->nullable(); // Lab room details
            $table->enum('status', ['available', 'checked_out'])->default('available');
            $table->string('location')->default('key_box'); // Where key should be
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_keys');
    }
};
