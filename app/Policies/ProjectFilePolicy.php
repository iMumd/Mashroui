<?php

namespace App\Policies;

use App\Enums\AccessLevelEnum;
use App\Enums\RoleEnum;
use App\Models\ProjectFile;
use App\Models\User;
use App\Support\Rbac\AccessControl;

class ProjectFilePolicy
{
    public function __construct(private AccessControl $accessControl) {}

    public function viewAny(User $user): bool
    {
        return $this->accessControl->can($user, 'projects') !== AccessLevelEnum::Blocked;
    }

    public function view(User $user, ProjectFile $projectFile): bool
    {
        return $this->accessControl->can($user, 'projects') !== AccessLevelEnum::Blocked;
    }

    public function create(User $user): bool
    {
        return $this->accessControl->can($user, 'projects') === AccessLevelEnum::Full
            && $user->role === RoleEnum::TeamLeader;
    }
}
