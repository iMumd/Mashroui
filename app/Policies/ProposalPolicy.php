<?php

namespace App\Policies;

use App\Enums\AccessLevelEnum;
use App\Enums\RoleEnum;
use App\Models\Proposal;
use App\Models\User;
use App\Support\Rbac\AccessControl;
use App\Support\Rbac\TeamMembership;

class ProposalPolicy
{
    public function __construct(private AccessControl $accessControl, private TeamMembership $teamMembership) {}

    public function viewAny(User $user): bool
    {
        return $this->accessControl->can($user, 'proposals') !== AccessLevelEnum::Blocked;
    }

    public function view(User $user, Proposal $proposal): bool
    {
        return $this->accessControl->can($user, 'proposals') !== AccessLevelEnum::Blocked
            && $this->teamMembership->belongsTo($user, $proposal->project->team);
    }

    public function create(User $user): bool
    {
        // أي عضو في الفريق يقدّم المقترح (لا مفهوم قائد في الواجهة)
        return $this->accessControl->can($user, 'proposals') === AccessLevelEnum::Full
            && $this->teamMembership->inAnyTeam($user);
    }

    public function update(User $user, Proposal $proposal): bool
    {
        if ($this->accessControl->can($user, 'proposals') !== AccessLevelEnum::Full) {
            return false;
        }

        // من قدّم المقترح أو أي عضو في الفريق يقدر يعيد التقديم
        return $user->id === $proposal->submitted_by
            || $this->teamMembership->isStudentOf($user, $proposal->project->team);
    }

    public function review(User $user, Proposal $proposal): bool
    {
        if ($this->accessControl->can($user, 'proposals') !== AccessLevelEnum::Full) {
            return false;
        }

        if ($user->role === RoleEnum::Committee) {
            return true;
        }

        return $user->role === RoleEnum::Supervisor && $user->id === $proposal->project->supervisor_id;
    }
}
