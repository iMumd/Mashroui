<?php

namespace App\Listeners;

use App\Events\MeetingCreated;
use App\Models\Notification;
use App\Services\WhatsappService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

class SendMeetingCreatedNotification implements ShouldQueue
{
    public function handle(MeetingCreated $event, WhatsappService $whatsapp): void
    {
        $meeting = $event->meeting;
        $team = $meeting->team;

        $isSupervisorCreator = $meeting->created_by === $team->supervisor_id;
        $recipient = $isSupervisorCreator ? $team->leader : $team->supervisor;

        if (! $recipient) {
            return;
        }

        $localTime = $meeting->scheduled_at->clone()->timezone('Asia/Hebron')->format('Y-m-d H:i');
        $message = "تم تحديد اجتماع جديد: \"{$meeting->title}\" بتاريخ {$localTime}.";

        Notification::create([
            'user_id' => $recipient->id,
            'type' => 'meeting',
            'message' => $message,
        ]);

        if ($recipient->whatsapp) {
            try {
                $whatsapp->send($recipient->whatsapp, $message);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
