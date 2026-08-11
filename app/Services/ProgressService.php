<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Enums\TaskStatusEnum;
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
        ];
    }

    /** نظرة عامة على تقدّم كل الفرق — لجنة: الكل، مشرف: فرقه فقط */
    public function overview(User $user): Collection
    {
        $query = Team::with('supervisor', 'specialization.department', 'project.department', 'members.student');

        if ($user->role === RoleEnum::Supervisor) {
            $query->where('supervisor_id', $user->id);
        }

        return $query->get()->map(fn (Team $team) => [
            'team' => $team,
            'progress' => $this->forTeam($team),
        ]);
    }
}
