<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\Discussion;
use App\Models\User;

class DiscussionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Discussion $discussion): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === RoleEnum::Committee;
    }

    public function update(User $user, Discussion $discussion): bool
    {
        return $user->role === RoleEnum::Committee;
    }

    public function delete(User $user, Discussion $discussion): bool
    {
        return $user->role === RoleEnum::Committee;
    }
}
