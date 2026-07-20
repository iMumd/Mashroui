<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskNote;
use App\Services\TaskNoteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskNoteController extends Controller
{
    public function index(Task $task)
    {
        Gate::authorize('viewAny', TaskNote::class);

        return response()->json($task->notes()->with('user')->get());
    }

    public function store(Request $request, Task $task, TaskNoteService $service)
    {
        Gate::authorize('create', TaskNote::class);

        $data = $request->validate([
            'note' => ['required', 'string'],
        ]);

        $taskNote = $service->add($task, $data['note'], $request->user());

        return response()->json($taskNote, 201);
    }
}
