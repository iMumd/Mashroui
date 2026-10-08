<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MessageTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MessageTemplateController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', MessageTemplate::class);

        $query = MessageTemplate::query()->orderByDesc('id');

        if ($request->filled('channel')) {
            $query->where('channel', $request->string('channel'));
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        Gate::authorize('create', MessageTemplate::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'channel' => ['required', Rule::in(['email', 'whatsapp'])],
            'subject' => ['required_if:channel,email', 'nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:60'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $data['created_by'] = $request->user()->id;

        return response()->json(MessageTemplate::create($data), 201);
    }

    public function update(Request $request, MessageTemplate $messageTemplate)
    {
        Gate::authorize('update', $messageTemplate);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'channel' => ['sometimes', 'required', Rule::in(['email', 'whatsapp'])],
            'subject' => ['required_if:channel,email', 'nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:60'],
            'body' => ['sometimes', 'required', 'string', 'max:2000'],
        ]);

        $messageTemplate->update($data);

        return response()->json($messageTemplate);
    }

    public function destroy(MessageTemplate $messageTemplate)
    {
        Gate::authorize('delete', $messageTemplate);

        $messageTemplate->delete();

        return response()->json(['status' => 'deleted']);
    }
}
