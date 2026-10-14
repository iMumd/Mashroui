<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Enums\TaskStatusEnum;
use App\Events\TaskCreated;
use App\Models\AcademicTerm;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Support\CurrentTerm;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskService
{
    public function create(array $data, User $creator): Task
    {
        $team = Team::findOrFail($data['team_id']);

        if ($creator->role !== RoleEnum::SuperAdmin && $creator->id !== $team->supervisor_id && $creator->id !== $team->leader_id) {
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

    /**
     * مهمة واحدة من الإدارة العامة لعدة مجموعات دفعة واحدة: مهمة لكل مجموعة، وإشعار واحد فقط لكل مشرف
     * (أو مسؤول فريق) مهما تعدّدت مجموعاته المستلمة — بدل إشعار لكل مجموعة.
     *
     * @return Collection<int, Task>
     */
    public function createForTeams(array $data, User $creator): Collection
    {
        $teamIds = array_values(array_unique(array_map('intval', $data['team_ids'])));
        $teams = Team::whereIn('id', $teamIds)->get();

        if ($teams->count() !== count($teamIds)) {
            throw ValidationException::withMessages(['team_ids' => 'إحدى المجموعات غير موجودة في الفصل الحالي.']);
        }

        // المكلّفون من طلاب المجموعات المحددة: كل مجموعة تأخذ المحدَّدين من أعضائها، ومجموعة بلا محدَّدين = كل أعضائها
        $requested = array_values(array_unique(array_map('intval', $data['assignee_ids'] ?? [])));
        $members = DB::table('team_members')->whereIn('team_id', $teamIds)->get(['team_id', 'student_id']);
        if (array_diff($requested, $members->pluck('student_id')->map('intval')->all())) {
            throw ValidationException::withMessages(['assignee_ids' => 'أحد الأشخاص المكلّفين مش عضو بالمجموعات المحددة.']);
        }

        $tasks = DB::transaction(fn () => $teams->map(function (Team $team) use ($data, $creator, $requested, $members) {
            $assigneeIds = $members->where('team_id', $team->id)->pluck('student_id')->map('intval')->intersect($requested)->values()->all();
            $task = Task::create([
                'team_id' => $team->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'status' => TaskStatusEnum::Pending,
                'created_by' => $creator->id,
            ]);
            $task->assignees()->sync($assigneeIds);

            return $task;
        }));

        $teams->flatMap(fn (Team $t) => array_filter([$t->supervisor_id, $t->leader_id]))
            ->reject(fn ($id) => $id === $creator->id)
            ->countBy()
            ->each(fn ($count, $userId) => Notification::create([
                'user_id' => $userId,
                'type' => 'task_created',
                'message' => $count > 1
                    ? "مهمة جديدة: \"{$data['title']}\" لـ {$count} من مجموعاتك."
                    : "مهمة جديدة: \"{$data['title']}\".",
            ]));

        return $tasks;
    }

    /** مهمة من الإدارة العامة لعضو لجنة إشراف أو لمشرف — بلا فريق، ومربوطة بالفصل الحالي */
    public function createForStaff(array $data, User $creator): Task
    {
        $assigneeIds = $this->validateStaffAssignees([$data['user_id']]);

        return DB::transaction(function () use ($data, $creator, $assigneeIds) {
            $task = Task::create([
                'team_id' => null,
                'term_id' => app(CurrentTerm::class)->get() ?? AcademicTerm::where('is_current', true)->value('id'),
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
        $ids = $task->team ? $this->validateAssignees($task->team, $assigneeIds) : $this->validateStaffAssignees($assigneeIds);

        $task->assignees()->sync($ids);

        return $task;
    }

    public function changeStatus(Task $task, TaskStatusEnum $status, User $actor): Task
    {
        // مهمة فريق: الطالب المنتسب لها فقط (المكلّف، أو أي عضو إن كانت لكل الفريق). مهمة بلا فريق: المكلّف بها (لجنة/مشرف)
        $allowed = $task->team_id !== null ? $task->isStudentAssignee($actor) : $task->isStaffAssignee($actor);

        if (! $allowed) {
            throw ValidationException::withMessages(['status' => 'تغيير حالة المهمة متاح للطالب المنتسب لها فقط.']);
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

    /** مكلّفو المهمة بلا فريق: أعضاء لجنة إشراف أو مشرفون */
    private function validateStaffAssignees(array $assigneeIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $assigneeIds)));

        if (User::whereIn('id', $ids)->whereIn('role', [RoleEnum::Committee, RoleEnum::Supervisor])->count() !== count($ids)) {
            throw ValidationException::withMessages(['assignee_ids' => 'المكلّف يجب أن يكون عضوًا في لجنة الإشراف أو مشرفًا.']);
        }

        return $ids;
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
