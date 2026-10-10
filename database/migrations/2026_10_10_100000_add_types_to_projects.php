<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// نوع المشروع: قائمة أنواع لكل مشروع (مثل تصميم، VR، ألعاب) — يحددها الطالب والمشرف واللجنة والإدارة العامة
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->json('types')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('types');
        });
    }
};
