<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_forms', function (Blueprint $table) {
            // نموذج خاص بقسم معيّن (الملتيميديا تختلف عن البرمجيات…)؛ null = نموذج افتراضي لكل الأقسام
            $table->foreignId('department_id')->nullable()->after('kind')->constrained('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_forms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
