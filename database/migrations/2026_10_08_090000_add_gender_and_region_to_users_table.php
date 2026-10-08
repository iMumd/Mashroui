<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // الجنس (طلاب/طالبات) ومكان التواجد (غزة/جنوب) — للتصنيف والتقارير وجداول المناقشات
            $table->enum('gender', ['male', 'female'])->nullable()->after('university_number');
            $table->enum('region', ['gaza', 'south'])->nullable()->after('gender');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['gender', 'region']);
        });
    }
};
