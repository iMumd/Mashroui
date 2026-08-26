<?php

namespace App\Services;

use App\Enums\AccessLevelEnum;
use App\Enums\RoleEnum;
use App\Models\AuditLog;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\UserRestriction;
use Illuminate\Validation\ValidationException;

class UserRestrictionService
{
    public function restrict(User $target, string $module, AccessLevelEnum $level, User $actor, string $reason): UserRestriction
    {
        $this->assertCanManage($target, $module, $actor);

        $restriction = UserRestriction::updateOrCreate(
            ['user_id' => $target->id, 'module' => $module],
            ['level' => $level, 'reason' => $reason, 'restricted_by' => $actor->id],
        );

        AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'restrict',
            'entity' => 'user',
            'entity_id' => $target->id,
            'meta' => ['module' => $module, 'level' => $level->value, 'reason' => $reason],
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

        return $actor->role === RoleEnum::Supervisor && $this->isOwnTeamMember($actor, $target);
    }

    private function assertCanManage(User $target, string $module, User $actor): void
    {
        if ($actor->role === RoleEnum::SuperAdmin) {
            if ($target->role !== RoleEnum::Supervisor) {
                throw ValidationException::withMessages(['user_id' => 'التقييد الفردي متاح للمشرفين فقط.']);
            }

            return;
        }

        if ($module !== 'tasks' || ! $this->isOwnTeamMember($actor, $target)) {
            throw ValidationException::withMessages(['user_id' => 'المشرف يقدر يقيّد أعضاء فريقه على وحدة المهام فقط.']);
        }
    }

    private function isOwnTeamMember(User $supervisor, User $member): bool
    {
        return TeamMember::where('student_id', $member->id)
            ->whereHas('team', fn ($q) => $q->where('supervisor_id', $supervisor->id))
            ->exists();
    }
}
