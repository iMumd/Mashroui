<?php

namespace App\Policies;

use App\Enums\AccessLevelEnum;
use App\Enums\RoleEnum;
use App\Models\Project;
use App\Models\User;
use App\Support\Rbac\AccessControl;

class ProjectPolicy
{
    public function __construct(private AccessControl $accessControl) {}

    public function viewAny(User $user): bool
    {
        return $this->accessControl->can($user, 'projects') !== AccessLevelEnum::Blocked;
    }

    public function view(User $user, Project $project): bool
    {
        return $this->accessControl->can($user, 'projects') !== AccessLevelEnum::Blocked;
    }

    public function create(User $user): bool
    {
        return $this->accessControl->can($user, 'projects') === AccessLevelEnum::Full
            && in_array($user->role, [RoleEnum::Supervisor, RoleEnum::Committee], true);
    }

    public function update(User $user, Project $project): bool
    {
        if ($this->accessControl->can($user, 'projects') !== AccessLevelEnum::Full) {
            return false;
        }

        return $user->role === RoleEnum::Committee
            || ($user->role === RoleEnum::Supervisor && $project->supervisor_id === $user->id);
    }

    /** نوع المشروع: يحدده الطالب مع المقترح، وبعدها يعدّله مشرف المشروع فقط (والإدارة العامة عبر Gate::before) */
    /** أفضل مشاريع التخرج في الصفحة الرئيسية (is_featured): لجنة الإشراف فقط (والإدارة العامة عبر Gate::before) */
    public function feature(User $user, Project $project): bool
    {
        return $user->role === RoleEnum::Committee;
    }

    public function updateTypes(User $user, Project $project): bool
    {
        return $user->role === RoleEnum::Supervisor && $project->supervisor_id === $user->id;
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->accessControl->can($user, 'projects') === AccessLevelEnum::Full
            && $user->role === RoleEnum::Committee;
    }
}
