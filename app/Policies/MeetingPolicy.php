<?php

namespace App\Policies;

use App\Enums\AccessLevelEnum;
use App\Enums\RoleEnum;
use App\Models\Meeting;
use App\Models\User;
use App\Support\Rbac\AccessControl;

class MeetingPolicy
{
    public function __construct(private AccessControl $accessControl) {}

    public function viewAny(User $user): bool
    {
        return $this->accessControl->can($user, 'meetings') !== AccessLevelEnum::Blocked;
    }

    public function view(User $user, Meeting $meeting): bool
    {
        return $this->accessControl->can($user, 'meetings') !== AccessLevelEnum::Blocked;
    }

    public function create(User $user): bool
    {
        return $this->accessControl->can($user, 'meetings') === AccessLevelEnum::Full
            && in_array($user->role, [RoleEnum::Supervisor, RoleEnum::TeamLeader], true);
    }

    public function update(User $user, Meeting $meeting): bool
    {
        if ($this->accessControl->can($user, 'meetings') !== AccessLevelEnum::Full) {
            return false;
        }

        return $user->id === $meeting->created_by
            || $user->id === $meeting->team->supervisor_id
            || $user->id === $meeting->team->leader_id;
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        return $this->update($user, $meeting);
    }
}
