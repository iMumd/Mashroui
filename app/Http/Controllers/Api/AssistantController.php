<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AssistantService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class AssistantController extends Controller
{
    public function chat(Request $request, AssistantService $assistant)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'history' => ['sometimes', 'array', 'max:30'],
            'history.*.role' => ['required_with:history', Rule::in(['user', 'assistant'])],
            'history.*.content' => ['required_with:history', 'string', 'max:4000'],
        ]);

        try {
            $reply = $assistant->chat($data['history'] ?? [], $data['message']);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'تعذّر الوصول إلى المساعد الآلي حالياً، حاول مرة أخرى بعد قليل.',
            ], 502);
        }

        return response()->json(['reply' => $reply]);
    }
}
