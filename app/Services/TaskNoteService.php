<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Models\Task;
use App\Models\TaskNote;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class TaskNoteService
{
    public function add(Task $task, string $note, User $author): TaskNote
    {
        $team = $task->team()->with('members')->first();

        // الإدارة العامة تعلّق على أي مهمة أرسلتها أو تتابعها؛ مهمة بلا فريق يعلّق عليها المكلّف بها
        // ومُنشئ المهمة (لجنة الإشراف) يعلّق على ما أرسله
        $isMember = $author->role === RoleEnum::SuperAdmin
            || $author->id === $task->created_by
            || ($team === null
                ? $task->isStaffAssignee($author)
                : $author->id === $team->leader_id
                    || $author->id === $team->supervisor_id
                    || $team->members->contains('student_id', $author->id));

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
