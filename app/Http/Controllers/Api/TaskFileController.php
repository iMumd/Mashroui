<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskFileResource;
use App\Models\Task;
use App\Models\TaskFile;
use App\Rules\AllowedDocumentTypes;
use App\Services\TaskFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskFileController extends Controller
{
    public function index(Task $task)
    {
        Gate::authorize('viewAny', [TaskFile::class, $task]);

        return TaskFileResource::collection($task->files()->with('uploadedBy')->get());
    }

    public function store(Request $request, Task $task, TaskFileService $service)
    {
        // الطلاب يرفعون تسليماتهم؛ ومُنشئ المهمة (لجنة الإشراف) يرفق ملفاتها
        if ($request->user()->id !== $task->created_by) {
            Gate::authorize('create', TaskFile::class);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:'.AllowedDocumentTypes::MIMES, 'max:10240'],
        ]);

        $taskFile = $service->upload($task, $request->file('file'), $request->user());

        return response()->json($taskFile, 201);
    }
}
