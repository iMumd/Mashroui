<?php

namespace App\Policies;

use App\Enums\AccessLevelEnum;
use App\Enums\RoleEnum;
use App\Models\TaskFile;
use App\Models\User;
use App\Support\Rbac\AccessControl;

class TaskFilePolicy
{
    public function __construct(private AccessControl $accessControl) {}

    public function viewAny(User $user): bool
    {
        return $this->accessControl->can($user, 'tasks') !== AccessLevelEnum::Blocked;
    }

    public function view(User $user, TaskFile $taskFile): bool
    {
        return $this->accessControl->can($user, 'tasks') !== AccessLevelEnum::Blocked;
    }

    public function create(User $user): bool
    {
        return $this->accessControl->can($user, 'tasks') === AccessLevelEnum::Full
            && in_array($user->role, [RoleEnum::Student, RoleEnum::TeamLeader], true);
    }
}
