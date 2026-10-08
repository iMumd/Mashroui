<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsappService
{
    public function send(string $phone, string $message): void
    {
        $response = Http::asMultipart()
            ->timeout(30)
            ->withHeaders(['token' => config('services.whatsapp.token')])
            ->post(config('services.whatsapp.url'), [
                ['name' => 'message', 'contents' => $message],
                ['name' => 'phone', 'contents' => $phone],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('whatsapp_send_failed_'.$response->status());
        }
    }
}
