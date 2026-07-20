<?php

namespace App\Listeners;

use App\Events\DiscussionScheduled;
use App\Models\Notification;

class SendDiscussionScheduledNotification
{
    public function handle(DiscussionScheduled $event): void
    {
        $discussion = $event->discussion;
        $message = "تم تحديد موعد المناقشة: {$discussion->discussion_date->toDateString()} في {$discussion->place}.";

        $leaderId = $discussion->project->team->leader_id;

        if ($leaderId) {
            Notification::create([
                'user_id' => $leaderId,
                'type' => 'discussion_scheduled',
                'message' => $message,
            ]);
        }

        Notification::create([
            'user_id' => $discussion->supervisor_id,
            'type' => 'discussion_scheduled',
            'message' => $message,
        ]);
    }
}
