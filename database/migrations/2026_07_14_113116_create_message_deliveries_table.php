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
        Schema::create('message_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('context', 60);
            $table->enum('channel', ['email', 'whatsapp']);
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->text('error')->nullable();
            $table->unsignedInteger('retries')->default(0);
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_deliveries');
    }
};
