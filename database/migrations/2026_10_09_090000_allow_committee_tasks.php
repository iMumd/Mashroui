<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// مهام لجنة الإشراف: مهمة بلا فريق تُسند لعضو لجنة، وتُربط بالفصل مباشرة (term_id) لأن الفصل كان يأتي من الفريق
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->change();
            $table->foreignId('term_id')->nullable()->after('team_id')->constrained('academic_terms')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('term_id');
            $table->foreignId('team_id')->nullable(false)->change();
        });
    }
};
