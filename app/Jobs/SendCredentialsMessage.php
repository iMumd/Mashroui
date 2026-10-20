<?php

namespace App\Jobs;

use App\Enums\DeliveryStatusEnum;
use App\Enums\NotificationChannelEnum;
use App\Models\InviteLink;
use App\Models\MessageDelivery;
use App\Models\User;
use App\Services\WhatsappService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendCredentialsMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(public MessageDelivery $delivery, public InviteLink $invite) {}

    public function handle(WhatsappService $whatsapp): void
    {
        try {
            $user = $this->delivery->user;
            // الرابط مرتبط ببريد صاحبه: البريد جزء من الرابط ويُتحقق منه عند تعيين كلمة المرور
            $link = config('app.frontend_url').'/reset-password?token='.$this->invite->token.'&email='.urlencode($user->email);
            $message = "مرحباً {$user->name}، رابط تفعيل حسابك على منصة مسار: {$link}";

            match ($this->delivery->channel) {
                NotificationChannelEnum::Email => Mail::raw(
                    $message,
                    fn ($mail) => $mail->to($user->email)->subject('دعوة لتفعيل حسابك - مسار')
                ),
                NotificationChannelEnum::Whatsapp => $this->sendWhatsapp($whatsapp, $user, $message),
            };

            $this->delivery->update([
                'status' => DeliveryStatusEnum::Sent,
                'sent_at' => now(),
                'error' => null,
            ]);
        } catch (Throwable $e) {
            $this->delivery->update([
                'status' => DeliveryStatusEnum::Failed,
                'error' => $e->getMessage(),
                'retries' => $this->delivery->retries + 1,
            ]);
        }
    }

    private function sendWhatsapp(WhatsappService $whatsapp, User $user, string $message): void
    {
        if (! $user->whatsapp) {
            throw new RuntimeException('لا يوجد رقم واتساب مسجّل لهذا المستخدم.');
        }

        $whatsapp->send($user->whatsapp, $message);
    }
}
