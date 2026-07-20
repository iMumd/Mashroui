<?php

namespace App\Listeners;

use App\Events\MeetingCreated;
use App\Models\Notification;

class SendMeetingCreatedNotification
{
    public function handle(MeetingCreated $event): void
    {
        $meeting = $event->meeting;
        $team = $meeting->team;

        $recipient = $meeting->created_by === $team->supervisor_id ? $team->leader_id : $team->supervisor_id;

        Notification::create([
            'user_id' => $recipient,
            'type' => 'meeting',
            'message' => "تم تحديد اجتماع جديد: \"{$meeting->title}\".",
        ]);
    }
}
