<?php

namespace App\Services;

use App\Enums\ProjectStatusEnum;
use App\Enums\ProposalStatusEnum;
use App\Events\ProposalReviewed;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProposalService
{
    /** الحد الأقصى لعدد مرات الرفض المسموحة قبل منع إعادة التقديم (تقديم أولي + محاولتا إعادة تقديم) */
    private const MAX_REJECTIONS = 3;

    public function submit(array $data, UploadedFile $pdf, User $leader): Proposal
    {
        $project = Project::findOrFail($data['project_id']);

        if ($project->team->leader_id !== $leader->id) {
            throw ValidationException::withMessages(['project_id' => 'هذا المشروع مش تبع فريقك.']);
        }

        if ($project->proposal()->exists()) {
            throw ValidationException::withMessages(['project_id' => 'يوجد مقترح مسبقاً لهذا المشروع، استخدم التعديل.']);
        }

        $path = Storage::putFile('proposals', $pdf);

        $proposal = Proposal::create([
            'project_id' => $project->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'problems' => $data['problems'] ?? null,
            'solutions' => $data['solutions'] ?? null,
            'features_value' => $data['features_value'] ?? null,
            'pdf_path' => $path,
            'status' => ProposalStatusEnum::Pending,
            'submitted_by' => $leader->id,
        ]);

        // نوع المشروع يحدده الطالب مع المقترح (إجباري)، وبعدها يعدّله المشرف فقط
        $project->update(['types' => $data['types']]);

        $this->logSubmission($proposal, $leader, 1);

        return $proposal->fresh();
    }

    public function resubmit(Proposal $proposal, array $data, ?UploadedFile $pdf): Proposal
    {
        if ($proposal->status === ProposalStatusEnum::Approved) {
            throw ValidationException::withMessages(['status' => 'المقترح معتمد أصلاً، ما بينعدّل.']);
        }

        if ($proposal->rejection_count >= self::MAX_REJECTIONS) {
            throw ValidationException::withMessages(['status' => 'تم استنفاد جميع محاولات إعادة التقديم المسموحة. يرجى التواصل مع لجنة الإشراف.']);
        }

        // إعادة التقديم لا تغيّر نوع المشروع، إلا لمشروع قديم بلا نوع
        if (! empty($data['types']) && empty($proposal->project?->types)) {
            $proposal->project->update(['types' => $data['types']]);
        }

        $path = $pdf ? Storage::putFile('proposals', $pdf) : $proposal->pdf_path;
        $attempt = $proposal->rejection_count + 1;

        $proposal->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'problems' => $data['problems'] ?? null,
            'solutions' => $data['solutions'] ?? null,
            'features_value' => $data['features_value'] ?? null,
            'pdf_path' => $path,
            'status' => ProposalStatusEnum::Pending,
            'rejection_reason' => null,
            'reviewed_by' => null,
        ]);

        $proposal = $proposal->fresh();

        $this->logSubmission($proposal, $proposal->submittedBy, $attempt);

        return $proposal;
    }

    /**
     * التقديم بيعدّل نفس السطر (name/description/...) بدل ما يحتفظ بنسخة قديمة، فبدون هذا اللوج
     * ما في طريقة نعرض "شو قدّم أول مرة" بعد ما ينعدّل — بنسجّل نسخة (snapshot) من كل محاولة تقديم
     * بجدول AuditLog عشان يبنى منها سجل مراجعات كامل (كل محاولة + سبب رفضها إن وجد).
     */
    private function logSubmission(Proposal $proposal, User $submitter, int $attempt): void
    {
        AuditLog::create([
            'user_id' => $submitter->id,
            'action' => $attempt === 1 ? 'submit' : 'resubmit',
            'entity' => 'proposal',
            'entity_id' => $proposal->id,
            'meta' => [
                'attempt' => $attempt,
                'name' => $proposal->name,
                'description' => $proposal->description,
                'problems' => $proposal->problems,
                'solutions' => $proposal->solutions,
                'features_value' => $proposal->features_value,
            ],
        ]);
    }

    public function approve(Proposal $proposal, User $reviewer): Proposal
    {
        if ($proposal->status !== ProposalStatusEnum::Pending) {
            throw ValidationException::withMessages(['status' => 'المقترح تمت مراجعته مسبقاً.']);
        }

        return DB::transaction(function () use ($proposal, $reviewer) {
            $proposal->update([
                'status' => ProposalStatusEnum::Approved,
                'reviewed_by' => $reviewer->id,
                'rejection_reason' => null,
            ]);

            $proposal->project()->update([
                'status' => ProjectStatusEnum::InProgress,
                'name' => $proposal->name,
                'description' => $proposal->description,
            ]);

            $proposal = $proposal->fresh();

            ProposalReviewed::dispatch($proposal);

            return $proposal;
        });
    }

    public function reject(Proposal $proposal, User $reviewer, string $reason): Proposal
    {
        if ($proposal->status !== ProposalStatusEnum::Pending) {
            throw ValidationException::withMessages(['status' => 'المقترح تمت مراجعته مسبقاً.']);
        }

        $proposal->update([
            'status' => ProposalStatusEnum::Rejected,
            'reviewed_by' => $reviewer->id,
            'rejection_reason' => $reason,
            'rejection_count' => $proposal->rejection_count + 1,
        ]);

        $proposal = $proposal->fresh();

        AuditLog::create([
            'user_id' => $reviewer->id,
            'action' => 'reject',
            'entity' => 'proposal',
            'entity_id' => $proposal->id,
            'meta' => ['reason' => $reason, 'rejection_count' => $proposal->rejection_count],
        ]);

        ProposalReviewed::dispatch($proposal);

        return $proposal;
    }
}
