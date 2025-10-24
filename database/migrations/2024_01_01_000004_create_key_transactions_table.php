<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('key_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_key_id')->constrained('lab_keys')->onDelete('cascade');
            $table->string('user_name'); // Who took/returned the key
            $table->string('user_rfid_uid')->nullable(); // User's RFID card
            $table->integer('user_fingerprint_id')->nullable(); // User's fingerprint ID
            $table->enum('action', ['checkout', 'checkin']); // Take or return key
            $table->timestamp('transaction_time');
            $table->string('device')->default('key_box'); // Which device logged this
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('key_transactions');
    }
};
