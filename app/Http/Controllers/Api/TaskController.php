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

        return TaskResource::collection($team->tasks()->with('createdBy')->get());
    }

    public function store(Request $request, Team $team, TaskService $service)
    {
        Gate::authorize('create', Task::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
        ]);

        $data['team_id'] = $team->id;

        $task = $service->create($data, $request->user());

        return response()->json($task, 201);
    }

    public function show(Task $task)
    {
        Gate::authorize('view', $task);

        return new TaskResource($task->load('createdBy', 'files.uploadedBy', 'notes.user'));
    }

    public function update(Request $request, Task $task)
    {
        Gate::authorize('update', $task);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
        ]);

        $task->update($data);

        return response()->json($task);
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

        return response()->json($task);
    }

    public function progress(Team $team, ProgressService $service)
    {
        Gate::authorize('viewAny', [Task::class, $team]);

        return response()->json($service->forTeam($team));
    }
}
