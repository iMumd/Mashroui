<?php

namespace App\Support\Rbac;

use App\Enums\AccessLevelEnum;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserRestriction;

class AccessControl
{
    // القيود تقتصر على قبول/رفض المقترحات؛ المهام والاجتماعات والمشاريع لم تعد قابلة للتقييد.
    public const MODULES = ['proposals'];

    public function can(User $user, string $module): AccessLevelEnum
    {
        // أي وحدة خارج القائمة (مهام/اجتماعات/مشاريع) غير قابلة للتقييد — صلاحية كاملة دائمًا،
        // حتى لو بقيت صفوف قيود قديمة في قاعدة البيانات.
        if (! in_array($module, self::MODULES, true)) {
            return AccessLevelEnum::Full;
        }

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
