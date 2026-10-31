<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Enums\TaskStatusEnum;
use App\Models\Evaluation;
use App\Models\EvaluationSubmission;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

class ProgressService
{
    public function forTeam(Team $team): array
    {
        $total = $team->tasks()->count();
        $done = $team->tasks()->where('status', TaskStatusEnum::Done)->count();

        return [
            'total' => $total,
            'done' => $done,
            'percentage' => $total === 0 ? 0.0 : round($done / $total * 100, 1),
            'students' => $this->studentsForTeam($team),
        ];
    }

    /** تقدّم كل طالب بالفريق بناءً على المهام المكلّف فيها، مع درجة التقييم إن وجدت */
    public function studentsForTeam(Team $team): array
    {
        $evaluations = Evaluation::where('team_id', $team->id)->get()->keyBy('student_id');

        return $team->members()->with('student')->get()->map(function ($member) use ($team, $evaluations) {
            $assigned = $team->tasks()->whereHas('assignees', fn ($q) => $q->whereKey($member->student_id));
            $total = (clone $assigned)->count();
            $done = (clone $assigned)->where('status', TaskStatusEnum::Done)->count();
            $evaluation = $evaluations->get($member->student_id);

            return [
                'id' => $member->student_id,
                'name' => $member->student?->name,
                'is_leader' => $member->is_leader,
                'total' => $total,
                'done' => $done,
                'percentage' => $total === 0 ? 0.0 : round($done / $total * 100, 1),
                'evaluation' => $evaluation ? [
                    'score' => $evaluation->score,
                    'notes' => $evaluation->notes,
                ] : null,
            ];
        })->values()->all();
    }

    /** نظرة عامة على تقدّم كل الفرق — لجنة: الكل، مشرف: فرقه فقط */
    public function overview(User $user): Collection
    {
        // آخر نشاط لكل طالب = آخر استخدام لتوكن دخوله (tokens_max_last_used_at)
        $query = Team::with(['supervisor', 'specialization.department', 'project.department', 'members.student' => fn ($q) => $q->withMax('tokens', 'last_used_at')]);

        if ($user->role === RoleEnum::Supervisor) {
            $query->where('supervisor_id', $user->id);
        }

        $teams = $query->get();
        // نجوم ترشيح المناقشين لكل مشروع — يراها الطاقم (لجنة/إشراف/إدارة) في أرشيف المشاريع فقط
        $stars = EvaluationSubmission::nominationStatsFor($teams->pluck('id')->all());

        return $teams->map(function (Team $team) use ($stars) {
            if ($team->project) {
                $team->project->setAttribute('nomination_stars', $stars[$team->id] ?? ['discussants' => 0, 'nominations' => 0]);
            }

            return [
                'team' => $team,
                'progress' => $this->forTeam($team),
            ];
        });
    }
}
