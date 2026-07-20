<?php

namespace App\Services;

use App\Enums\TaskStatusEnum;
use App\Models\Team;

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
}
