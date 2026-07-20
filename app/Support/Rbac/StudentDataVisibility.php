<?php

namespace App\Support\Rbac;

use App\Enums\RoleEnum;
use App\Models\Team;
use App\Models\User;

class StudentDataVisibility
{
    public function __construct(private TeamMembership $teamMembership) {}

    public function canSeeContactInfo(User $viewer, User $target): bool
    {
        if ($target->role !== RoleEnum::Student) {
            return true;
        }

        if ($viewer->id === $target->id) {
            return true;
        }

        if (in_array($viewer->role, [RoleEnum::SuperAdmin, RoleEnum::Committee], true)) {
            return true;
        }

        return Team::where(function ($query) use ($viewer) {
            $query->where('supervisor_id', $viewer->id)->orWhere('leader_id', $viewer->id);
        })->where(function ($query) use ($target) {
            $query->where('leader_id', $target->id)->orWhereHas('members', fn ($m) => $m->where('student_id', $target->id));
        })->exists();
    }
}
