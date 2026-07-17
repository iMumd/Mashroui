<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Team $team): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [RoleEnum::Supervisor, RoleEnum::Committee], true);
    }

    public function update(User $user, Team $team): bool
    {
        return $user->role === RoleEnum::Committee
            || ($user->role === RoleEnum::Supervisor && $team->supervisor_id === $user->id);
    }

    public function delete(User $user, Team $team): bool
    {
        return $this->update($user, $team);
    }
}
