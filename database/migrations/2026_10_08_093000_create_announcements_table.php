<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            // team: إعلان موجّه لفريق (يراه أعضاؤه ومشرفه). supervisor: إعلان من المشرف العام
            // لمشرف محدد عن تقدّم مجموعاته، مع تقييم بالنجوم.
            $table->enum('kind', ['team', 'supervisor']);
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('title', 200)->nullable();
            $table->text('body');
            $table->unsignedTinyInteger('rating')->nullable(); // 1..5 نجوم لإعلانات المشرف العام
            $table->foreignId('term_id')->constrained('academic_terms')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['kind', 'team_id']);
            $table->index(['kind', 'target_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
