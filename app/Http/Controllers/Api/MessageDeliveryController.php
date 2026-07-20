<?php

namespace App\Http\Controllers\Api;

use App\Enums\DeliveryStatusEnum;
use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Jobs\SendCredentialsMessage;
use App\Models\MessageDelivery;
use App\Services\BulkNotifyService;
use Illuminate\Http\Request;

class MessageDeliveryController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->role === RoleEnum::SuperAdmin, 403);

        $query = MessageDelivery::with('user')->latest();

        if ($request->filled('context')) {
            $query->where('context', $request->string('context'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json($query->get());
    }

    public function retry(Request $request, MessageDelivery $delivery, BulkNotifyService $service)
    {
        abort_unless($request->user()->role === RoleEnum::SuperAdmin, 403);
        abort_unless($delivery->status === DeliveryStatusEnum::Failed, 422, 'بس الرسائل الفاشلة قابلة لإعادة المحاولة.');

        $invite = $service->activeInviteFor($delivery->user);

        abort_unless($invite, 422, 'لا يوجد رابط دعوة نشط لهذا المستخدم.');

        $delivery->update(['status' => DeliveryStatusEnum::Pending]);

        SendCredentialsMessage::dispatch($delivery, $invite);

        return response()->json($delivery->fresh());
    }
}
