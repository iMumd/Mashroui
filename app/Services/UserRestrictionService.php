<?php

namespace App\Services;

use App\Enums\AccessLevelEnum;
use App\Enums\RoleEnum;
use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use App\Models\UserRestriction;
use Illuminate\Validation\ValidationException;

class UserRestrictionService
{
    public function restrict(User $target, string $module, AccessLevelEnum $level, User $actor): UserRestriction
    {
        $this->assertCanManage($target, $module, $actor);

        $restriction = UserRestriction::updateOrCreate(
            ['user_id' => $target->id, 'module' => $module],
            ['level' => $level, 'restricted_by' => $actor->id],
        );

        AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'restrict',
            'entity' => 'user',
            'entity_id' => $target->id,
            'meta' => ['module' => $module, 'level' => $level->value],
        ]);

        return $restriction;
    }

    public function unrestrict(UserRestriction $restriction, User $actor): void
    {
        $this->assertCanManage($restriction->user, $restriction->module, $actor);

        AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'unrestrict',
            'entity' => 'user',
            'entity_id' => $restriction->user_id,
            'meta' => ['module' => $restriction->module],
        ]);

        $restriction->delete();
    }

    public function canView(User $target, User $actor): bool
    {
        if ($actor->role === RoleEnum::SuperAdmin) {
            return true;
        }

        return $actor->role === RoleEnum::Supervisor && $this->isOwnTeamLeader($actor, $target);
    }

    private function assertCanManage(User $target, string $module, User $actor): void
    {
        if ($actor->role === RoleEnum::SuperAdmin) {
            if ($target->role !== RoleEnum::Supervisor) {
                throw ValidationException::withMessages(['user_id' => 'التقييد الفردي متاح للمشرفين فقط.']);
            }

            return;
        }

        if ($target->role !== RoleEnum::TeamLeader || $module !== 'tasks' || ! $this->isOwnTeamLeader($actor, $target)) {
            throw ValidationException::withMessages(['user_id' => 'المشرف يقدر يقيّد قائد فريقه على وحدة المهام فقط.']);
        }
    }

    private function isOwnTeamLeader(User $supervisor, User $leader): bool
    {
        return Team::where('supervisor_id', $supervisor->id)->where('leader_id', $leader->id)->exists();
    }
}
