<?php

namespace App\Policies;

use App\Enums\AccessLevelEnum;
use App\Enums\RoleEnum;
use App\Models\Task;
use App\Models\TaskNote;
use App\Models\User;
use App\Support\Rbac\AccessControl;
use App\Support\Rbac\TeamMembership;

class TaskNotePolicy
{
    public function __construct(private AccessControl $accessControl, private TeamMembership $teamMembership) {}

    public function viewAny(User $user, Task $task): bool
    {
        return $this->accessControl->can($user, 'tasks') !== AccessLevelEnum::Blocked
            && $this->teamMembership->belongsTo($user, $task->team);
    }

    public function view(User $user, TaskNote $taskNote): bool
    {
        return $this->accessControl->can($user, 'tasks') !== AccessLevelEnum::Blocked
            && $this->teamMembership->belongsTo($user, $taskNote->task->team);
    }

    public function create(User $user): bool
    {
        return $this->accessControl->can($user, 'tasks') === AccessLevelEnum::Full
            && in_array($user->role, [RoleEnum::Supervisor, RoleEnum::TeamLeader, RoleEnum::Student], true);
    }
}
