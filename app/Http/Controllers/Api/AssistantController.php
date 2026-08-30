<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AssistantService;
use Illuminate\Http\Request;
use Throwable;

class AssistantController extends Controller
{
    public function chat(Request $request, AssistantService $assistant)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        try {
            $reply = $assistant->chat($request->user()->id, $data['message']);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'تعذّر الوصول إلى المساعد الآلي حالياً، حاول مرة أخرى بعد قليل.',
            ], 502);
        }

        return response()->json(['reply' => $reply]);
    }
}
