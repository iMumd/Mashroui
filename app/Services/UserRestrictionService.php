<?php

namespace App\Services;

use App\Enums\AccessLevelEnum;
use App\Enums\RoleEnum;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserRestriction;
use Illuminate\Validation\ValidationException;

class UserRestrictionService
{
    public function restrict(User $target, string $module, AccessLevelEnum $level, User $admin): UserRestriction
    {
        if ($target->role !== RoleEnum::Supervisor) {
            throw ValidationException::withMessages(['user_id' => 'التقييد الفردي متاح للمشرفين فقط.']);
        }

        $restriction = UserRestriction::updateOrCreate(
            ['user_id' => $target->id, 'module' => $module],
            ['level' => $level, 'restricted_by' => $admin->id],
        );

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'restrict',
            'entity' => 'user',
            'entity_id' => $target->id,
            'meta' => ['module' => $module, 'level' => $level->value],
        ]);

        return $restriction;
    }

    public function unrestrict(UserRestriction $restriction, User $admin): void
    {
        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'unrestrict',
            'entity' => 'user',
            'entity_id' => $restriction->user_id,
            'meta' => ['module' => $restriction->module],
        ]);

        $restriction->delete();
    }
}
