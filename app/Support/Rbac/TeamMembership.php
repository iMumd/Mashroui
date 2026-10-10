<?php

namespace App\Support\Rbac;

use App\Enums\RoleEnum;
use App\Models\Team;
use App\Models\User;

class TeamMembership
{
    public function belongsTo(User $user, ?Team $team): bool
    {
        if (in_array($user->role, [RoleEnum::SuperAdmin, RoleEnum::Committee], true)) {
            return true;
        }

        // مهمة بلا فريق = مهمة لجنة الإشراف: للإدارة واللجنة فقط (الشرط أعلاه)
        if ($team === null) {
            return false;
        }

        if ($user->id === $team->supervisor_id || $user->id === $team->leader_id) {
            return true;
        }

        return $team->members()->where('student_id', $user->id)->exists();
    }

    /** هل المستخدم قائد لأي فريق في الفصل الحالي — القيادة صلاحية تُمنح داخل الفريق، لا دور مستقل */
    public function leadsAnyTeam(User $user): bool
    {
        return Team::where('leader_id', $user->id)->exists();
    }

    /** هل المستخدم هو القائد الحالي لهذا الفريق تحديدًا */
    public function isLeaderOf(User $user, Team $team): bool
    {
        return $team->leader_id !== null && $team->leader_id === $user->id;
    }
}
