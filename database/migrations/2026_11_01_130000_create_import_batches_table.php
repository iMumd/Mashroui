<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// دفعة مزامنة واحدة (رفع نسخة جديدة من كشف الطلبة أو الفرق):
// - kind: نوع المزامنة (كشف الطلبة student_roster / الفرق teams)
// - summary: عدّادات الفرق المعروضة للمستخدم (مضاف/معدّل/محذوف/مُستعاد/بلا تغيير)
// - reverse: لقطة عكسية تكفي للتراجع خطوة واحدة (معرّفات المُنشأين، والمؤرشفين،
//   والمُستعادين، والقيم السابقة للمعدّلين) — لا نحتفظ إلا بآخر دفعة قابلة للتراجع
// - status: applied (قابلة للتراجع) / reverted (رُجِع عنها) / superseded (تجاوزتها دفعة أحدث)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('term_id')->nullable()->constrained('academic_terms')->nullOnDelete();
            $table->enum('kind', ['student_roster', 'teams'])->default('student_roster')->index();
            $table->string('original_filename')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('summary')->nullable();
            $table->json('reverse')->nullable();
            $table->enum('status', ['applied', 'reverted', 'superseded'])->default('applied')->index();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
