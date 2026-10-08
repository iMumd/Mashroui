<?php

namespace App\Jobs;

use App\Enums\DeliveryStatusEnum;
use App\Models\MessageDelivery;
use App\Services\WhatsappService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendWhatsappMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MessageDelivery $delivery,
        public string $phone,
        public string $message,
    ) {}

    public function handle(WhatsappService $whatsapp): void
    {
        try {
            $whatsapp->send($this->phone, $this->message);

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
}
