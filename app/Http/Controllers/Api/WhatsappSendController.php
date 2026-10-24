<?php

namespace App\Http\Controllers\Api;

use App\Enums\DeliveryStatusEnum;
use App\Enums\NotificationChannelEnum;
use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsappMessage;
use App\Models\MessageDelivery;
use App\Models\MessageTemplate;
use App\Rules\WhatsappNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WhatsappSendController extends Controller
{
    /** فاصل التأخير بين كل رسالة والتالية عند الإرسال لأكثر من مستلم دفعة وحدة */
    private const STAGGER_SECONDS = 2;

    public function send(Request $request)
    {
        abort_unless(
            in_array($request->user()->role, [RoleEnum::Supervisor, RoleEnum::Committee, RoleEnum::SuperAdmin], true),
            403
        );

        // لا نرجّع "تم الإرسال" بينما الخدمة غير مهيّأة: نكشف نقص الإعداد بوضوح بدل فشل صامت في الطابور
        abort_if(
            blank(config('services.whatsapp.url')) || blank(config('services.whatsapp.token')),
            422,
            'خدمة واتساب غير مهيّأة على الخادم — يلزم ضبط WHATSAPP_WEBHOOK_URL وWHATSAPP_WEBHOOK_TOKEN.'
        );

        $data = $request->validate([
            'recipients' => ['required', 'array', 'min:1', 'max:100'],
            'recipients.*.phone' => ['required', new WhatsappNumber],
            'recipients.*.name' => ['nullable', 'string', 'max:150'],
            'recipients.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'recipients.*.vars' => ['sometimes', 'array'],
            'message' => ['required_without:template_id', 'nullable', 'string', 'max:2000'],
            'template_id' => ['required_without:message', 'nullable', 'exists:message_templates,id'],
            'vars' => ['nullable', 'array'],
            'context' => ['nullable', 'string', 'max:60'],
        ]);

        $template = isset($data['template_id']) ? MessageTemplate::findOrFail($data['template_id']) : null;
        $context = $data['context'] ?? ($template?->category ?? 'reminder');

        $deliveryIds = DB::transaction(function () use ($data, $template, $context) {
            $ids = [];
            $delay = 0;

            foreach ($data['recipients'] as $recipient) {
                $vars = array_merge($data['vars'] ?? [], $recipient['vars'] ?? [], ['name' => $recipient['name'] ?? '']);
                $message = $template ? $template->render($vars) : $data['message'];

                $delivery = MessageDelivery::create([
                    'user_id' => $recipient['user_id'] ?? null,
                    'context' => $context,
                    'channel' => NotificationChannelEnum::Whatsapp,
                    'status' => DeliveryStatusEnum::Pending,
                    'retries' => 0,
                ]);

                SendWhatsappMessage::dispatch($delivery, $recipient['phone'], $message)
                    ->delay(now()->addSeconds($delay));

                $ids[] = $delivery->id;
                $delay += self::STAGGER_SECONDS;
            }

            return $ids;
        });

        return response()->json(['queued' => count($deliveryIds), 'delivery_ids' => $deliveryIds]);
    }
}
