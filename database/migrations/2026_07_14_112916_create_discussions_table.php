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
        Schema::create('discussions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supervisor_id')->constrained('users');
            $table->string('place', 150);
            $table->date('discussion_date');
            $table->time('discussion_time');
            $table->text('committee');
            $table->string('whatsapp', 20)->nullable();
            $table->enum('status', ['confirmed', 'pending'])->default('pending');
            $table->foreignId('term_id')->constrained('academic_terms');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discussions');
    }
};
