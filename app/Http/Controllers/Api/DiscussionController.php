<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Discussion;
use App\Rules\WhatsappNumber;
use App\Services\DiscussionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DiscussionController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Discussion::class);

        $query = Discussion::with('project.department', 'project.specialization', 'supervisor');

        if ($request->filled('department_id')) {
            $query->whereHas('project', fn ($q) => $q->where('department_id', $request->integer('department_id')));
        }

        if ($request->filled('specialization_id')) {
            $query->whereHas('project', fn ($q) => $q->where('specialization_id', $request->integer('specialization_id')));
        }

        return response()->json($query->get());
    }

    public function store(Request $request, DiscussionService $service)
    {
        Gate::authorize('create', Discussion::class);

        $data = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'supervisor_id' => ['required', 'exists:users,id'],
            'place' => ['required', 'string', 'max:150'],
            'discussion_date' => ['required', 'date'],
            'discussion_time' => ['required', 'date_format:H:i'],
            'committee' => ['required', 'string'],
            'whatsapp' => ['nullable', 'string', new WhatsappNumber],
            'status' => ['nullable', Rule::in(['confirmed', 'pending'])],
        ]);

        $discussion = $service->create($data);

        return response()->json($discussion, 201);
    }

    public function show(Discussion $discussion)
    {
        Gate::authorize('view', $discussion);

        return response()->json($discussion->load('project.department', 'project.specialization', 'supervisor'));
    }

    public function update(Request $request, Discussion $discussion, DiscussionService $service)
    {
        Gate::authorize('update', $discussion);

        $data = $request->validate([
            'place' => ['sometimes', 'required', 'string', 'max:150'],
            'discussion_date' => ['sometimes', 'required', 'date'],
            'discussion_time' => ['sometimes', 'required', 'date_format:H:i'],
            'committee' => ['sometimes', 'required', 'string'],
            'whatsapp' => ['nullable', 'string', new WhatsappNumber],
            'status' => ['sometimes', Rule::in(['confirmed', 'pending'])],
        ]);

        $discussion = $service->update($discussion, $data);

        return response()->json($discussion);
    }

    public function destroy(Request $request, Discussion $discussion)
    {
        Gate::authorize('delete', $discussion);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'delete',
            'entity' => 'discussion',
            'entity_id' => $discussion->id,
            'meta' => ['project_id' => $discussion->project_id, 'place' => $discussion->place],
        ]);

        $discussion->delete();

        return response()->json(null, 204);
    }
}
