<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// مسار اعتماد التقرير النهائي قبل الأرشفة: المشرف يوافق ← لجنة الإشراف تعتمد ← يُؤرشف المشروع (مكتمل).
// review_status: pending_supervisor → pending_committee → approved، أو rejected (بملاحظة) فيُعاد الرفع.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('final_reports', function (Blueprint $table) {
            $table->enum('review_status', ['pending_supervisor', 'pending_committee', 'approved', 'rejected'])->default('pending_supervisor')->after('video_url');
            $table->foreignId('supervisor_reviewed_by')->nullable()->after('review_status')->constrained('users')->nullOnDelete();
            $table->timestamp('supervisor_reviewed_at')->nullable()->after('supervisor_reviewed_by');
            $table->foreignId('committee_reviewed_by')->nullable()->after('supervisor_reviewed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('committee_reviewed_at')->nullable()->after('committee_reviewed_by');
            $table->text('review_note')->nullable()->after('committee_reviewed_at');
        });

        // تقارير المشاريع المكتملة سابقًا تُعدّ معتمدة
        DB::table('final_reports')
            ->whereIn('project_id', DB::table('projects')->where('status', 'completed')->pluck('id'))
            ->update(['review_status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('final_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_reviewed_by');
            $table->dropConstrainedForeignId('committee_reviewed_by');
            $table->dropColumn(['review_status', 'supervisor_reviewed_at', 'committee_reviewed_at', 'review_note']);
        });
    }
};
