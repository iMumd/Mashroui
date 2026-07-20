<?php

namespace App\Policies;

use App\Enums\AccessLevelEnum;
use App\Enums\RoleEnum;
use App\Models\Meeting;
use App\Models\Team;
use App\Models\User;
use App\Support\Rbac\AccessControl;
use App\Support\Rbac\TeamMembership;

class MeetingPolicy
{
    public function __construct(private AccessControl $accessControl, private TeamMembership $teamMembership) {}

    public function viewAny(User $user, Team $team): bool
    {
        return $this->accessControl->can($user, 'meetings') !== AccessLevelEnum::Blocked
            && $this->teamMembership->belongsTo($user, $team);
    }

    public function view(User $user, Meeting $meeting): bool
    {
        return $this->accessControl->can($user, 'meetings') !== AccessLevelEnum::Blocked
            && $this->teamMembership->belongsTo($user, $meeting->team);
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
