<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\MessageTemplate;
use App\Models\User;

class MessageTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [RoleEnum::SuperAdmin, RoleEnum::Committee, RoleEnum::Supervisor], true);
    }

    public function create(User $user): bool
    {
        return $user->role === RoleEnum::SuperAdmin;
    }

    public function update(User $user, MessageTemplate $messageTemplate): bool
    {
        return $user->role === RoleEnum::SuperAdmin;
    }

    public function delete(User $user, MessageTemplate $messageTemplate): bool
    {
        return $user->role === RoleEnum::SuperAdmin;
    }
}
