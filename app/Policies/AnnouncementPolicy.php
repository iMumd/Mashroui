<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\Team;
use App\Models\User;
use App\Support\Rbac\TeamMembership;

class AnnouncementPolicy
{
    public function __construct(private TeamMembership $teamMembership) {}

    /** نشر إعلان لفريق: اللجنة/المشرف العام أو المشرف المالك للفريق */
    public function createForTeam(User $user, Team $team): bool
    {
        return in_array($user->role, [RoleEnum::Committee, RoleEnum::SuperAdmin], true)
            || ($user->role === RoleEnum::Supervisor && $team->supervisor_id === $user->id);
    }

    /** رؤية إعلانات فريق: أعضاؤه ومشرفه واللجنة والمشرف العام */
    public function viewTeam(User $user, Team $team): bool
    {
        return $this->teamMembership->belongsTo($user, $team);
    }

    /** إعلان المشرف العام للمشرفين (تقييم بالنجوم عن تقدّم مجموعاتهم) — المشرف العام فقط */
    public function createForSupervisor(User $user): bool
    {
        return $user->role === RoleEnum::SuperAdmin;
    }
}
