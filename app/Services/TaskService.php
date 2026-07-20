<?php

namespace App\Services;

use App\Enums\TaskStatusEnum;
use App\Events\TaskCreated;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class TaskService
{
    public function create(array $data, User $creator): Task
    {
        $team = Team::findOrFail($data['team_id']);

        if ($creator->id !== $team->supervisor_id && $creator->id !== $team->leader_id) {
            throw ValidationException::withMessages(['team_id' => 'هذا الفريق مش تبعك.']);
        }

        $task = Task::create([
            'team_id' => $team->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'created_by' => $creator->id,
        ]);

        TaskCreated::dispatch($task);

        return $task;
    }

    public function changeStatus(Task $task, TaskStatusEnum $status, User $actor): Task
    {
        if ($actor->id !== $task->team->leader_id) {
            throw ValidationException::withMessages(['status' => 'تغيير حالة المهمة متاح لقائد الفريق فقط.']);
        }

        $task->update(['status' => $status]);

        return $task;
    }
}
