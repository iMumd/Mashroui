<?php

namespace App\Jobs;

use App\Enums\NotificationChannelEnum;
use App\Models\MeetingReminder;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendMeetingReminder implements ShouldQueue
{
    use Queueable;

    public function __construct(public MeetingReminder $reminder) {}

    public function handle(): void
    {
        $meeting = $this->reminder->meeting;
        $team = $meeting->team;

        $recipients = collect([$team->leader, $team->supervisor])->filter();
        $message = "تذكير: اجتماع \"{$meeting->title}\" بتاريخ {$meeting->scheduled_at->format('Y-m-d H:i')}.";

        foreach ($recipients as $user) {
            if ($this->reminder->channel === NotificationChannelEnum::Email) {
                Mail::raw($message, function ($mail) use ($user, $meeting) {
                    $mail->to($user->email)->subject("تذكير اجتماع: {$meeting->title}");
                });
            }

            Notification::create([
                'user_id' => $user->id,
                'type' => 'meeting_reminder',
                'message' => $message,
            ]);
        }

        $this->reminder->update(['sent_at' => now()]);
    }
}
