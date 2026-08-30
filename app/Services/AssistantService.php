<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class AssistantService
{
    public function chat(int $studentId, string $message): string
    {
        $response = Http::asMultipart()
            ->timeout(55)
            ->post(config('services.gp_chat.url'), [
                ['name' => 'message', 'contents' => $message],
                ['name' => 'student_id', 'contents' => $studentId],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('gp_chat_unreachable');
        }

        $reply = $response->json('reply');

        if (! is_string($reply) || trim($reply) === '') {
            throw new RuntimeException('gp_chat_empty_reply');
        }

        return $reply;
    }
}
