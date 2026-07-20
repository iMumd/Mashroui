<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskNote;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class TaskNoteService
{
    public function add(Task $task, string $note, User $author): TaskNote
    {
        $team = $task->team()->with('members')->first();

        $isMember = $author->id === $team->leader_id
            || $author->id === $team->supervisor_id
            || $team->members->contains('student_id', $author->id);

        if (! $isMember) {
            throw ValidationException::withMessages(['task_id' => 'هاي المهمة مش تبعك.']);
        }

        return TaskNote::create([
            'task_id' => $task->id,
            'user_id' => $author->id,
            'note' => $note,
        ]);
    }
}
