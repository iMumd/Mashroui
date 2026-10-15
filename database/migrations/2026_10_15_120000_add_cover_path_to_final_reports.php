<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** صورة المشروع (غلاف فيديو العرض) يرفعها الطالب مع التقرير النهائي وتظهر في معرض المشاريع */
    public function up(): void
    {
        Schema::table('final_reports', function (Blueprint $table) {
            $table->string('cover_path')->nullable()->after('video_url');
        });
    }

    public function down(): void
    {
        Schema::table('final_reports', function (Blueprint $table) {
            $table->dropColumn('cover_path');
        });
    }
};
