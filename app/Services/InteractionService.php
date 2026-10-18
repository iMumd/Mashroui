<?php

namespace App\Services;

use App\Enums\TaskStatusEnum;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\TaskFile;
use App\Models\TaskNote;
use App\Models\Team;
use App\Models\User;

/**
 * مدى تفاعل المشرف مع مجموعاته: يُحسب من عدد المهام المرفوعة ومدى استجابة الطلاب
 * (إنجاز المهام، الملفات والملاحظات المرفوعة عليها، والاجتماعات). الناتج أرقام خام
 * + درجة تقديرية 0-100 ليسهل عرض نشاط كل مجموعة ومقارنتها.
 */
class InteractionService
{
    public function forTeam(Team $team): array
    {
        $taskIds = $team->tasks()->pluck('id');
        $tasksTotal = $taskIds->count();
        $tasksDone = $team->tasks()->where('status', TaskStatusEnum::Done)->count();

        $notesCount = $taskIds->isEmpty() ? 0 : TaskNote::whereIn('task_id', $taskIds)->count();
        $filesCount = $taskIds->isEmpty() ? 0 : TaskFile::whereIn('task_id', $taskIds)->count();
        $meetingsCount = $team->meetings()->count();

        $completionRatio = $tasksTotal === 0 ? 0.0 : round($tasksDone / $tasksTotal * 100, 1);

        return [
            'tasks_total' => $tasksTotal,
            'tasks_done' => $tasksDone,
            'completion_ratio' => $completionRatio,
            'notes_count' => $notesCount,
            'files_count' => $filesCount,
            'meetings_count' => $meetingsCount,
            'activity_score' => $this->activityScore($tasksTotal, $completionRatio, $notesCount + $filesCount, $meetingsCount),
        ];
    }

    /** ملخّص تفاعل مشرف عبر كل فرقه */
    public function forSupervisor(User $supervisor): array
    {
        $teams = Team::where('supervisor_id', $supervisor->id)->with('project', 'members.student')->get();

        $perTeam = $teams->map(fn (Team $team) => [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'section' => $team->section,
                'members_count' => $team->members->count(),
                'members' => $team->members->map(fn ($m) => [
                    'id' => $m->student_id,
                    'name' => $m->student?->name,
                    'region' => $m->student?->region,
                    'is_leader' => (bool) $m->is_leader,
                ])->values()->all(),
            ],
            'project' => $team->project ? [
                'id' => $team->project->id,
                'name' => $team->project->name,
                'status' => $team->project->status,
            ] : null,
            'interaction' => $this->forTeam($team),
        ])->values();

        $avgActivity = $perTeam->isEmpty() ? 0.0 : round($perTeam->avg(fn ($t) => $t['interaction']['activity_score']), 1);

        return [
            'teams_count' => $teams->count(),
            'students_count' => $teams->sum(fn (Team $team) => $team->members->count()),
            'average_activity' => $avgActivity,
            'teams' => $perTeam->all(),
        ];
    }

    /**
     * درجة تقديرية 0-100: نصفها من انتظام رفع المهام والاجتماعات (نشاط المشرف)،
     * ونصفها من استجابة الطلاب (نسبة الإنجاز + الملفات/الملاحظات).
     */
    private function activityScore(int $tasksTotal, float $completionRatio, int $engagementItems, int $meetings): int
    {
        $supervisorSide = min(100, $tasksTotal * 12 + $meetings * 10);
        $studentSide = min(100, $completionRatio + $engagementItems * 4);

        return (int) round($supervisorSide * 0.5 + $studentSide * 0.5);
    }
}
