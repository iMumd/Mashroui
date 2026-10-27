<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * مناقشون خارجيون دائمون: أشخاص يُدعَون لمناقشة المشاريع دون أن يكونوا مشرفين أو
     * أعضاء لجنة إشراف (ولا حسابات في النظام). سجلّ قابل لإعادة الاستخدام يظهر في قائمة
     * اختيار المناقشين بكل جدول. لجنة المناقشة نفسها تبقى نصًّا في discussions.committee
     * (توافقًا مع منطق التقييمات والتصدير)، وهذا الجدول مصدر الأسماء الجاهزة فقط.
     */
    public function up(): void
    {
        Schema::create('examiners', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('title', 50)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examiners');
    }
};
