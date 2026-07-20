<?php

namespace App\Policies;

use App\Enums\AccessLevelEnum;
use App\Enums\RoleEnum;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use App\Support\Rbac\AccessControl;
use App\Support\Rbac\TeamMembership;

class ProjectFilePolicy
{
    public function __construct(private AccessControl $accessControl, private TeamMembership $teamMembership) {}

    public function viewAny(User $user, Project $project): bool
    {
        return $this->accessControl->can($user, 'projects') !== AccessLevelEnum::Blocked
            && $this->teamMembership->belongsTo($user, $project->team);
    }

    public function view(User $user, ProjectFile $projectFile): bool
    {
        return $this->accessControl->can($user, 'projects') !== AccessLevelEnum::Blocked
            && $this->teamMembership->belongsTo($user, $projectFile->project->team);
    }

    public function create(User $user): bool
    {
        return $this->accessControl->can($user, 'projects') === AccessLevelEnum::Full
            && $user->role === RoleEnum::TeamLeader;
    }
}
