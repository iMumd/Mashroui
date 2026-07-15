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
            && $user->role === RoleEnum::Supervisor;
    }

    public function update(User $user, Meeting $meeting): bool
    {
        return $this->accessControl->can($user, 'meetings') === AccessLevelEnum::Full
            && $user->id === $meeting->created_by;
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        return $this->update($user, $meeting);
    }
}
