<?php

namespace App\Policies;

use App\Enums\AccessLevelEnum;
use App\Enums\RoleEnum;
use App\Models\FinalReport;
use App\Models\User;
use App\Support\Rbac\AccessControl;

class FinalReportPolicy
{
    public function __construct(private AccessControl $accessControl) {}

    public function viewAny(User $user): bool
    {
        return $this->accessControl->can($user, 'projects') !== AccessLevelEnum::Blocked;
    }

    public function view(User $user, FinalReport $finalReport): bool
    {
        return $this->accessControl->can($user, 'projects') !== AccessLevelEnum::Blocked;
    }

    public function create(User $user): bool
    {
        return $this->accessControl->can($user, 'projects') === AccessLevelEnum::Full
            && $user->role === RoleEnum::TeamLeader;
    }
}
