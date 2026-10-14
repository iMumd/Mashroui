<?php

namespace Database\Seeders;

use App\Enums\ProjectStatusEnum;
use App\Enums\RoleEnum;
use App\Models\Evaluation;
use App\Models\FinalReport;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * بيانات تجريبية لأرشيف المشاريع (مشاريع الفصل الحالي) وفق مسار الاعتماد: المشرف يوافق ← لجنة الإشراف تعتمد ← يُؤرشف.
 * - كل مشروع ليس ثالثًا في الترتيب: تقريره معتمد من المشرف واللجنة، والمشروع مكتمل (مؤرشف).
 * - الباقي قيد التنفيذ، وتقاريرهم بالتناوب: بانتظار اللجنة · بانتظار المشرف · مرفوض بملاحظة · لم يُرفع.
 * - المميّزة: أعلى 5 مشاريع مؤرشفة بمتوسط درجات طلابها (EvaluationResultsSeeder).
 * ثابت لكل تشغيل (الترتيب بالرقم)، فيمكن تشغيله أكثر من مرة.
 */
class ArchiveDemoSeeder extends Seeder
{
    private const PENDING = ['pending_committee', 'pending_supervisor', 'rejected', null];

    public function run(): void
    {
        $committee = User::where('role', RoleEnum::Committee)->orderBy('id')->value('id');
        $projects = Project::with('team.members', 'proposal')->whereNotNull('team_id')->orderBy('id')->get();

        foreach ($projects->values() as $i => $project) {
            // مشروع بلا مقترح (فريق حسابي التجربة) يبقى قيد التنفيذ بلا تقرير
            if (! $project->proposal) {
                continue;
            }

            $archived = $i % 3 !== 2;
            $status = $archived ? 'approved' : self::PENDING[intdiv($i, 3) % count(self::PENDING)];
            $uploader = $project->team?->leader_id ?? $project->team?->members->first()?->student_id;

            $project->update([
                'status' => $archived ? ProjectStatusEnum::Completed : ProjectStatusEnum::InProgress,
                'completed_at' => $archived ? now()->subDays(3 + $i)->setTime(12, 0) : null,
                'is_featured' => false,
            ]);

            $latest = $project->finalReports()->latest('id')->first();
            if (! $status || ! $uploader) {
                continue;
            }

            // التقرير النهائي يُرفع بعد اعتماد المقترح
            $project->proposal()->update(['status' => 'approved']);

            $day = now()->subDays(10 + $i);
            $reviewedBySup = $status !== 'pending_supervisor'; // الرفض أيضًا يسجّل مرحلته (من المشرف أو اللجنة)
            $byCommittee = $status === 'approved' || ($status === 'rejected' && intdiv($i, 12) % 2); // رفض اللجنة بالتناوب
            $report = $latest ?? new FinalReport(['project_id' => $project->id]);
            $report->created_at = $day; // الرفع قبل تواريخ المراجعة
            $report->fill([
                'pdf_path' => $latest?->pdf_path ?? 'final_reports/demo-'.$project->id.'.pdf',
                'video_url' => $latest?->video_url ?? 'https://www.youtube.com/watch?v=YE7VzlLtp-4',
                'uploaded_by' => $latest?->uploaded_by ?? $uploader,
                'review_status' => $status,
                'supervisor_reviewed_by' => $reviewedBySup ? $project->supervisor_id : null,
                'supervisor_reviewed_at' => $reviewedBySup ? $day->copy()->addDays(2) : null,
                'committee_reviewed_by' => $byCommittee ? $committee : null,
                'committee_reviewed_at' => $byCommittee ? $day->copy()->addDays(4) : null,
                'review_note' => $status === 'rejected' ? 'يرجى استكمال فصل النتائج وإضافة دليل المستخدم ثم إعادة الرفع.' : null,
            ])->save();
        }

        $done = $projects->filter(fn (Project $p) => $p->status === ProjectStatusEnum::Completed);
        $avg = Evaluation::whereIn('team_id', $done->pluck('team_id'))->get()->groupBy('team_id')->map->avg('score');

        $done->sortByDesc(fn (Project $p) => $avg->get($p->team_id, 0))->take(5)
            ->each(fn (Project $p) => $p->update(['is_featured' => true]));
    }
}
