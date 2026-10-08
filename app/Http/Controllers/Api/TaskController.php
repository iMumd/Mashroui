<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\AuditLog;
use App\Models\Task;
use App\Models\Team;
use App\Services\ProgressService;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index(Team $team)
    {
        Gate::authorize('viewAny', [Task::class, $team]);

        return TaskResource::collection($team->tasks()->with('createdBy', 'assignees')->get());
    }

    public function trashed(Team $team)
    {
        Gate::authorize('viewAny', [Task::class, $team]);

        return TaskResource::collection(
            $team->tasks()->onlyTrashed()->with('createdBy', 'assignees')->get()
        );
    }

    public function restore(Request $request, int $task)
    {
        $task = Task::onlyTrashed()->findOrFail($task);

        Gate::authorize('restore', $task);

        $task->restore();

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'restore',
            'entity' => 'task',
            'entity_id' => $task->id,
            'meta' => ['team_id' => $task->team_id, 'title' => $task->title],
        ]);

        return new TaskResource($task->load('createdBy', 'assignees'));
    }

    public function forceDelete(Request $request, int $task)
    {
        $task = Task::onlyTrashed()->findOrFail($task);

        Gate::authorize('forceDelete', $task);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'force_delete',
            'entity' => 'task',
            'entity_id' => $task->id,
            'meta' => ['team_id' => $task->team_id, 'title' => $task->title],
        ]);

        $task->forceDelete();

        return response()->json(null, 204);
    }

    public function store(Request $request, Team $team, TaskService $service)
    {
        Gate::authorize('create', Task::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $data['team_id'] = $team->id;

        $task = $service->create($data, $request->user());

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'create',
            'entity' => 'task',
            'entity_id' => $task->id,
            'meta' => ['team_id' => $task->team_id, 'title' => $task->title],
        ]);

        return (new TaskResource($task->load('createdBy', 'assignees')))->response()->setStatusCode(201);
    }

    public function show(Task $task)
    {
        Gate::authorize('view', $task);

        return new TaskResource($task->load('createdBy', 'assignees', 'files.uploadedBy', 'notes.user'));
    }

    public function update(Request $request, Task $task, TaskService $service)
    {
        Gate::authorize('update', $task);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'assignee_ids' => ['sometimes', 'array'],
            'assignee_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $task->update(array_intersect_key($data, array_flip(['title', 'description', 'due_date'])));

        if (array_key_exists('assignee_ids', $data)) {
            $service->syncAssignees($task, $data['assignee_ids'] ?? []);
        }

        return new TaskResource($task->load('createdBy', 'assignees'));
    }

    // سجل حركة المهمة: الإنشاء وتغييرات الحالة والاسترجاع/الحذف، بترتيب زمني
    public function activity(Task $task)
    {
        Gate::authorize('view', $task);

        $entries = AuditLog::where('entity', 'task')
            ->where('entity_id', $task->id)
            ->with('user:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn (AuditLog $log) => [
                'action' => $log->action,
                'meta' => $log->meta,
                'user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
                'created_at' => $log->created_at,
            ]);

        return response()->json($entries);
    }

    public function destroy(Request $request, Task $task)
    {
        Gate::authorize('delete', $task);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'delete',
            'entity' => 'task',
            'entity_id' => $task->id,
            'meta' => ['team_id' => $task->team_id, 'title' => $task->title],
        ]);

        $task->delete();

        return response()->json(null, 204);
    }

    public function changeStatus(Request $request, Task $task, TaskService $service)
    {
        Gate::authorize('changeStatus', $task);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_column(TaskStatusEnum::cases(), 'value'))],
        ]);

        $task = $service->changeStatus($task, TaskStatusEnum::from($data['status']), $request->user());

        return new TaskResource($task->load('createdBy', 'assignees'));
    }

    public function progress(Team $team, ProgressService $service)
    {
        Gate::authorize('viewAny', [Task::class, $team]);

        return response()->json($service->forTeam($team));
    }
}
