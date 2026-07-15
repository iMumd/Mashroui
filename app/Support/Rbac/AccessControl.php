<?php

namespace App\Support\Rbac;

use App\Enums\AccessLevelEnum;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRestriction;

class AccessControl
{
    public function can(User $user, string $module): AccessLevelEnum
    {
        $restriction = UserRestriction::query()
            ->where('user_id', $user->id)
            ->where('module', $module)
            ->first();

        if ($restriction) {
            return $restriction->level;
        }

        $permission = RolePermission::query()
            ->where('role', $user->role)
            ->where('module', $module)
            ->first();

        return $permission?->level ?? AccessLevelEnum::Full;
    }
}
