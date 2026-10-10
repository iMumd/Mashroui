<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// تقييم المشرفين: إرسال من المشرف العام لعدة مشرفين، لكل مشرف نجوم (rating) وملاحظة عن تقدّم مجموعاته
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->enum('type', ['announcement', 'file', 'rating'])->default('announcement')->change();
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->enum('type', ['announcement', 'file'])->default('announcement')->change();
        });
    }
};
