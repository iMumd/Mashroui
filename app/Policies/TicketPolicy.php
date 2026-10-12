<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /** فريق الدعم: اللجنة والمشرف العام يرون ويردّون على كل التذاكر */
    public function isSupportStaff(User $user): bool
    {
        return in_array($user->role, [RoleEnum::Committee, RoleEnum::SuperAdmin], true);
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $user->id === $ticket->user_id || $this->isSupportStaff($user);
    }

    /** الرد متاح لصاحب التذكرة أو الدعم، وفقط ما دامت مفتوحة */
    public function reply(User $user, Ticket $ticket): bool
    {
        return $ticket->status->value === 'open' && $this->view($user, $ticket);
    }

    public function close(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    /** الأرشفة للدعم فقط وبعد أن يرد الدعم على التذكرة مرة واحدة على الأقل */
    public function archive(User $user, Ticket $ticket): bool
    {
        return $this->isSupportStaff($user)
            && $ticket->archived_at === null
            && $ticket->replies()->where('user_id', '!=', $ticket->user_id)->exists();
    }

    public function unarchive(User $user, Ticket $ticket): bool
    {
        return $this->isSupportStaff($user) && $ticket->archived_at !== null;
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $this->isSupportStaff($user);
    }

    /** إعادة الفتح للدعم فقط */
    public function reopen(User $user, Ticket $ticket): bool
    {
        return $this->isSupportStaff($user);
    }
}
