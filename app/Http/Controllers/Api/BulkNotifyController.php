<?php

namespace App\Http\Controllers\Api;

use App\Enums\NotificationChannelEnum;
use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Services\BulkNotifyService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BulkNotifyController extends Controller
{
    public function preview(Request $request, BulkNotifyService $service)
    {
        abort_unless($request->user()->role === RoleEnum::SuperAdmin, 403);

        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['distinct', 'exists:users,id'],
            'channel' => ['required', Rule::in(['email', 'whatsapp'])],
        ]);

        return response()->json(
            $service->preview($data['user_ids'], NotificationChannelEnum::from($data['channel']))
        );
    }

    public function send(Request $request, BulkNotifyService $service)
    {
        abort_unless($request->user()->role === RoleEnum::SuperAdmin, 403);

        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['distinct', 'exists:users,id'],
            'channel' => ['required', Rule::in(['email', 'whatsapp'])],
        ]);

        return response()->json(
            $service->send($data['user_ids'], NotificationChannelEnum::from($data['channel']))
        );
    }
}
