<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// الإعلانات والملفات من الإدارة العامة كمراسلة واحدة (batch) لعدة مستلمين:
// - batch: يجمع صفوف الإرسال الواحد (صف لكل مجموعة أو شخص) لعرضه كإرسال واحد في "المرسَلة"
// - type: إعلان أو ملف (الملف = إعلان مرفقاته إلزامية)
// - kind=user: إعلان لشخص بعينه (عضو لجنة إشراف أو مشرف) بلا تقييم
// - channels: قنوات الإرسال المستخدمة (المنصة دائمًا + واتساب/بريد اختياريًا)
// - announcement_attachments: مرفقات تُخزَّن مرة واحدة لكل batch
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->enum('kind', ['team', 'supervisor', 'user'])->change();
            $table->uuid('batch')->nullable()->after('id')->index();
            $table->enum('type', ['announcement', 'file'])->default('announcement')->after('kind');
            $table->json('channels')->nullable()->after('rating');
        });

        Schema::create('announcement_attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch')->index();
            $table->string('name');
            $table->string('path');
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_attachments');

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['batch', 'type', 'channels']);
            $table->enum('kind', ['team', 'supervisor'])->change();
        });
    }
};
