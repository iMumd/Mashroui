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
        return $this->accessControl->can($user, 'proposals') === AccessLevelEnum::Full
            && $user->role === RoleEnum::TeamLeader;
    }

    public function update(User $user, Proposal $proposal): bool
    {
        return $this->accessControl->can($user, 'proposals') === AccessLevelEnum::Full
            && $user->id === $proposal->submitted_by;
    }

    public function review(User $user, Proposal $proposal): bool
    {
        return $this->accessControl->can($user, 'proposals') === AccessLevelEnum::Full
            && $user->role === RoleEnum::Committee;
    }
}
