<?php

namespace App\Jobs;

use App\Enums\DeliveryStatusEnum;
use App\Enums\NotificationChannelEnum;
use App\Models\InviteLink;
use App\Models\MessageDelivery;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendCredentialsMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(public MessageDelivery $delivery, public InviteLink $invite) {}

    public function handle(): void
    {
        try {
            $link = config('app.url')."/invite/{$this->invite->token}";
            $user = $this->delivery->user;
            $message = "مرحباً {$user->name}، رابط تفعيل حسابك: {$link}";
            $waLink = null;

            match ($this->delivery->channel) {
                NotificationChannelEnum::Email => Mail::raw(
                    $message,
                    fn ($mail) => $mail->to($user->email)->subject('دعوة لتفعيل حسابك - مشروعي')
                ),
                NotificationChannelEnum::Whatsapp => $waLink = $this->buildWhatsappLink($user, $message),
            };

            $this->delivery->update([
                'status' => DeliveryStatusEnum::Sent,
                'link' => $waLink,
                'sent_at' => now(),
                'error' => null,
            ]);
        } catch (\Throwable $e) {
            $this->delivery->update([
                'status' => DeliveryStatusEnum::Failed,
                'error' => $e->getMessage(),
                'retries' => $this->delivery->retries + 1,
            ]);
        }
    }

    private function buildWhatsappLink(User $user, string $message): string
    {
        if (! $user->whatsapp) {
            throw new \RuntimeException('لا يوجد رقم واتساب مسجّل لهذا المستخدم.');
        }

        return "https://wa.me/{$user->whatsapp}?text=".urlencode($message);
    }
}
