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
        // القيادة صلاحية داخل الفريق (leader_id) لا دور مستقل — أي طالب يقود فريقًا يقدر يقدّم المقترح
        return $this->accessControl->can($user, 'proposals') === AccessLevelEnum::Full
            && $this->teamMembership->leadsAnyTeam($user);
    }

    public function update(User $user, Proposal $proposal): bool
    {
        if ($this->accessControl->can($user, 'proposals') !== AccessLevelEnum::Full) {
            return false;
        }

        // من قدّم المقترح أو القائد الحالي للفريق (لو انتقلت القيادة بعد الرفض) يقدر يعيد التقديم
        return $user->id === $proposal->submitted_by
            || $user->id === $proposal->project->team->leader_id;
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
