<?php

namespace App\Support\Rbac;

use App\Enums\RoleEnum;
use App\Models\Team;
use App\Models\User;

class TeamMembership
{
    public function belongsTo(User $user, Team $team): bool
    {
        if (in_array($user->role, [RoleEnum::SuperAdmin, RoleEnum::Committee], true)) {
            return true;
        }

        if ($user->id === $team->supervisor_id || $user->id === $team->leader_id) {
            return true;
        }

        return $team->members()->where('student_id', $user->id)->exists();
    }
}
