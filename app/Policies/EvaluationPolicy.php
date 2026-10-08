<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\Evaluation;
use App\Models\Team;
use App\Models\User;

class EvaluationPolicy
{
    public function manage(User $user, Team $team): bool
    {
        return $user->id === $team->supervisor_id;
    }

    public function view(User $user, Evaluation $evaluation): bool
    {
        return $user->id === $evaluation->supervisor_id
            || $user->id === $evaluation->student_id
            || in_array($user->role, [RoleEnum::Committee, RoleEnum::SuperAdmin], true);
    }
}
