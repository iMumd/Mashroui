<?php

namespace App\Jobs;

use App\Enums\NotificationChannelEnum;
use App\Models\MeetingReminder;
use App\Models\Notification;
use App\Services\WhatsappService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendMeetingReminder implements ShouldQueue
{
    use Queueable;

    public function __construct(public MeetingReminder $reminder) {}

    public function handle(WhatsappService $whatsapp): void
    {
        $meeting = $this->reminder->meeting;
        $team = $meeting->team;

        $recipients = collect([$team->leader, $team->supervisor])->filter();
        $localTime = $meeting->scheduled_at->clone()->timezone('Asia/Hebron')->format('Y-m-d H:i');
        $message = "تذكير: اجتماع \"{$meeting->title}\" بتاريخ {$localTime}.";

        foreach ($recipients as $user) {
            if ($this->reminder->channel === NotificationChannelEnum::Email) {
                Mail::raw($message, function ($mail) use ($user, $meeting) {
                    $mail->to($user->email)->subject("تذكير اجتماع: {$meeting->title}");
                });
            }

            if ($this->reminder->channel === NotificationChannelEnum::Whatsapp && $user->whatsapp) {
                try {
                    $whatsapp->send($user->whatsapp, $message);
                } catch (Throwable $e) {
                    report($e);
                }
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
