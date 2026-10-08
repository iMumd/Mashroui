<?php

namespace App\Services;

use App\Enums\TaskStatusEnum;
use App\Events\TaskCreated;
use App\Models\AuditLog;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskService
{
    public function create(array $data, User $creator): Task
    {
        $team = Team::findOrFail($data['team_id']);

        if ($creator->id !== $team->supervisor_id && $creator->id !== $team->leader_id) {
            throw ValidationException::withMessages(['team_id' => 'هذا الفريق مش تبعك.']);
        }

        $assigneeIds = $this->validateAssignees($team, $data['assignee_ids'] ?? []);

        return DB::transaction(function () use ($data, $team, $creator, $assigneeIds) {
            $task = Task::create([
                'team_id' => $team->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'status' => TaskStatusEnum::Pending,
                'created_by' => $creator->id,
            ]);

            $task->assignees()->sync($assigneeIds);

            TaskCreated::dispatch($task);

            return $task;
        });
    }

    /** يحدّث قائمة المكلّفين بالمهمة (قائد الفريق أو المشرف فقط عبر الـPolicy) */
    public function syncAssignees(Task $task, array $assigneeIds): Task
    {
        $ids = $this->validateAssignees($task->team, $assigneeIds);

        $task->assignees()->sync($ids);

        return $task;
    }

    public function changeStatus(Task $task, TaskStatusEnum $status, User $actor): Task
    {
        $isLeader = $actor->id === $task->team->leader_id;
        $isAssignee = $task->assignees()->whereKey($actor->id)->exists();

        if (! $isLeader && ! $isAssignee) {
            throw ValidationException::withMessages(['status' => 'تغيير حالة المهمة متاح لقائد الفريق أو الطالب المكلف بها فقط.']);
        }

        if (! $isLeader && ! in_array($status, [TaskStatusEnum::InProgress, TaskStatusEnum::Done], true)) {
            throw ValidationException::withMessages(['status' => 'يمكنك فقط التبديل بين "قيد التنفيذ" و"مكتمل".']);
        }

        $from = $task->status;
        $task->update(['status' => $status]);

        // سجل الحركة لكل مهمة: من غيّر الحالة ومن أي حالة لأي حالة
        AuditLog::create([
            'user_id' => $actor->id,
            'action' => 'status_change',
            'entity' => 'task',
            'entity_id' => $task->id,
            'meta' => [
                'team_id' => $task->team_id,
                'from' => $from->value,
                'to' => $status->value,
            ],
        ]);

        return $task;
    }

    /**
     * يتحقق أن كل مكلّف عضو فعلي بالفريق، ويرجّع قائمة معرّفات فريدة.
     *
     * @param  array<int|string>  $assigneeIds
     * @return array<int>
     */
    private function validateAssignees(Team $team, array $assigneeIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $assigneeIds)));

        if ($ids === []) {
            return [];
        }

        $memberIds = $team->members()->pluck('student_id')->all();

        foreach ($ids as $id) {
            if (! in_array($id, $memberIds, true)) {
                throw ValidationException::withMessages(['assignee_ids' => 'أحد الأشخاص المكلّفين مش عضو بهذا الفريق.']);
            }
        }

        return $ids;
    }
}
