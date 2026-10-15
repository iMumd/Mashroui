<?php

namespace App\Services;

use App\Enums\ProjectStatusEnum;
use App\Enums\RoleEnum;
use App\Events\FileUploaded;
use App\Models\FinalReport;
use App\Models\Project;
use App\Models\User;
use App\Support\Rbac\TeamMembership;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FinalReportService
{
    public function upload(array $data, UploadedFile $file, User $leader, ?UploadedFile $cover = null): FinalReport
    {
        $project = Project::findOrFail($data['project_id']);

        if (! app(TeamMembership::class)->isStudentOf($leader, $project->team)) {
            throw ValidationException::withMessages(['project_id' => 'هذا المشروع مش تبع فريقك.']);
        }

        // نموذج التقرير النهائي مفتوح للطالب في أي وقت (بلا شرط اعتماد المقترح)
        $finalReport = FinalReport::create([
            'project_id' => $project->id,
            'pdf_path' => Storage::putFile('final_reports', $file),
            'video_url' => $data['video_url'] ?? null,
            // صورة المشروع عامة (تظهر في المعرض) فتُحفظ على قرص public
            'cover_path' => $cover?->store('project_covers', 'public'),
            'uploaded_by' => $leader->id,
        ]);

        FileUploaded::dispatch($finalReport, $leader);

        return $finalReport;
    }

    /**
     * مراجعة التقرير النهائي حسب مرحلته:
     * بانتظار المشرف ← مشرف المشروع (أو الإدارة العامة) يوافق فينتقل للجنة، أو يرفض.
     * بانتظار اللجنة ← لجنة الإشراف (أو الإدارة العامة) تعتمد فيُؤرشف المشروع (مكتمل)، أو ترفض.
     * الرفض يتطلب ملاحظة، ويُعيد الطلاب رفع تقرير جديد (يبدأ المسار من جديد).
     */
    public function review(FinalReport $report, User $user, bool $approve, ?string $note): FinalReport
    {
        $project = $report->project;
        $isAdmin = $user->role === RoleEnum::SuperAdmin;

        if ($report->review_status === 'pending_supervisor') {
            abort_unless($isAdmin || $user->id === ($project->supervisor_id ?? $project->team?->supervisor_id), 403, 'موافقة هذه المرحلة لمشرف المشروع.');
            $stage = ['supervisor_reviewed_by' => $user->id, 'supervisor_reviewed_at' => now()];
            $next = 'pending_committee';
        } elseif ($report->review_status === 'pending_committee') {
            abort_unless($isAdmin || $user->role === RoleEnum::Committee, 403, 'اعتماد هذه المرحلة للجنة الإشراف.');
            $stage = ['committee_reviewed_by' => $user->id, 'committee_reviewed_at' => now()];
            $next = 'approved';
        } else {
            throw ValidationException::withMessages(['review_status' => 'هذا التقرير تمت مراجعته — لا مراجعة إلا لتقرير بانتظار المشرف أو اللجنة.']);
        }

        if (! $approve && ! trim((string) $note)) {
            throw ValidationException::withMessages(['note' => 'اكتب سبب الرفض ليعدّل الطلاب التقرير.']);
        }

        $report->update([...$stage, 'review_status' => $approve ? $next : 'rejected', 'review_note' => $note ?: null]);

        // اعتماد اللجنة = أرشفة المشروع
        if ($approve && $next === 'approved') {
            $project->update(['status' => ProjectStatusEnum::Completed, 'completed_at' => now()]);
        }

        return $report->fresh(['supervisorReviewer:id,name', 'committeeReviewer:id,name']);
    }
}
