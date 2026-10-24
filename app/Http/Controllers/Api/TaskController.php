<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Enums\TaskStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\AuditLog;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\ProgressService;
use App\Services\TaskService;
use App\Support\CurrentTerm;
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

    /**
     * كل مهام الفصل الحالي (مهام المجموعات + مهام لجنة الإشراف + مهام المشرفين) للإدارة العامة ولجنة الإشراف، مع فلاتر مستقلة:
     * audience (groups|committee|supervisors), team_id, supervisor_id, assignee_id, status (pending|in_progress|review|done|late),
     * due (late|week|none), q (العنوان أو اسم المكلّف).
     * المشرف يصل فقط لمهامه المسندة إليه من الإدارة (audience=supervisors + assignee_id=نفسه إجباريًا).
     * meta.counts = عدد المهام لكل حالة بكل الفلاتر ما عدا status (لملخص الحالات القابل للضغط).
     */
    public function overview(Request $request)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [RoleEnum::SuperAdmin, RoleEnum::Committee, RoleEnum::Supervisor], true), 403);

        if ($user->role === RoleEnum::Supervisor) {
            $request->merge(['audience' => 'supervisors', 'assignee_id' => $user->id, 'team_id' => null, 'supervisor_id' => null]);
        }

        $data = $request->validate([
            'audience' => ['nullable', Rule::in(['groups', 'committee', 'supervisors'])],
            'team_id' => ['nullable', 'integer'],
            'supervisor_id' => ['nullable', 'integer'],
            'degree' => ['nullable', Rule::in(['diploma', 'bachelor'])],
            'department_id' => ['nullable', 'integer'],
            'specialization_id' => ['nullable', 'integer'],
            'assignee_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in([...array_column(TaskStatusEnum::cases(), 'value'), 'late'])],
            'due' => ['nullable', Rule::in(['late', 'week', 'none'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $today = now()->toDateString();
        $late = fn ($q) => $q->where('status', '!=', TaskStatusEnum::Done->value)->whereDate('due_date', '<', $today);

        // مهام المجموعات: whereHas('team') يطبّق TermScope على الفرق → الفصل الحالي فقط
        $groups = fn ($q) => $q->whereHas('team', fn ($q) => $q
            ->when($data['team_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->when($data['supervisor_id'] ?? null, fn ($q, $id) => $q->where('supervisor_id', $id))
            ->when($data['specialization_id'] ?? null, fn ($q, $id) => $q->where('specialization_id', $id))
            ->when($data['department_id'] ?? null, fn ($q, $id) => $q->whereHas('specialization', fn ($q) => $q->where('department_id', $id)))
            ->when($data['degree'] ?? null, fn ($q, $d) => $q->whereHas('specialization', fn ($q) => $q->where('degree', $d))));
        // مهام بلا فريق (لجنة الإشراف أو المشرفين): الفصل من term_id، والجهة من دور المكلّف
        $term = app(CurrentTerm::class)->get();
        $staff = fn ($q, ?RoleEnum $role = null) => $q->whereNull('team_id')
            ->when($term, fn ($q, $id) => $q->where('term_id', $id))
            ->when($role, fn ($q) => $q->whereHas('assignees', fn ($q) => $q->where('role', $role)));
        // فلتر المجموعة أو المشرف يخص مهام المجموعات فقط
        $teamFilter = isset($data['team_id']) || isset($data['degree']) || isset($data['specialization_id']) || isset($data['department_id']);
        $audience = $data['audience'] ?? ($teamFilter || isset($data['supervisor_id']) ? 'groups' : null);

        // جهة "المجموعات" تشمل مهام المشرفين أنفسهم (منفصلة عن لجنة الإشراف)؛ فلتر المشرف = مهام مجموعاته + مهامه هو
        $supervisorsStaff = fn ($q) => $staff($q, RoleEnum::Supervisor)
            ->when($data['supervisor_id'] ?? null, fn ($q, $id) => $q->whereHas('assignees', fn ($q) => $q->whereKey($id)));

        $base = Task::query()
            ->where(fn ($q) => match ($audience) {
                // فلاتر المجموعة/الدرجة/التخصص تخص مهام المجموعات وحدها
                'groups' => $teamFilter ? $groups($q) : $groups($q)->orWhere(fn ($q) => $supervisorsStaff($q)),
                'committee' => $staff($q, RoleEnum::Committee),
                'supervisors' => $staff($q, RoleEnum::Supervisor),
                default => $groups($q)->orWhere(fn ($q) => $staff($q)),
            })
            ->when($data['assignee_id'] ?? null, fn ($q, $id) => $q->whereHas('assignees', fn ($q) => $q->whereKey($id)))
            ->when($data['due'] ?? null, fn ($q, $due) => match ($due) {
                'late' => $late($q),
                'week' => $q->whereBetween('due_date', [$today, now()->addDays(7)->toDateString()]),
                'none' => $q->whereNull('due_date'),
            })
            ->when($data['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('title', 'like', "%{$term}%")
                ->orWhereHas('assignees', fn ($q) => $q->where('name', 'like', "%{$term}%"))));

        $counts = (clone $base)->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');

        // ponytail: بلا ترقيم صفحات — كل مهام الفصل دفعة واحدة؛ أضف paginate إذا تجاوزت بضعة آلاف
        $tasks = (clone $base)
            ->when($data['status'] ?? null, fn ($q, $status) => $status === 'late' ? $late($q) : $q->where('status', $status))
            ->with('team:id,name,section,supervisor_id', 'team.supervisor:id,name', 'assignees:id,name', 'createdBy')
            ->orderByRaw('CASE WHEN status != ? AND due_date < ? THEN 0 ELSE 1 END', [TaskStatusEnum::Done->value, $today])
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->get();

        return TaskResource::collection($tasks)->additional(['meta' => [
            'counts' => [
                ...collect(TaskStatusEnum::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($counts[$s->value] ?? 0)])->all(),
                'late' => $late(clone $base)->count(),
                'all' => (int) $counts->sum(),
            ],
        ]]);
    }

    /** POST /tasks/batch — الإدارة العامة ترسل مهمة لعدة مجموعات في طلب واحد (إشعار واحد لكل مشرف) */
    public function storeForTeams(Request $request, TaskService $service)
    {
        abort_unless(in_array($request->user()->role, [RoleEnum::SuperAdmin, RoleEnum::Committee], true), 403); // الإدارة العامة ولجنة الإشراف

        $data = $request->validate([
            'team_ids' => ['required', 'array', 'min:1'],
            'team_ids.*' => ['integer'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $tasks = $service->createForTeams($data, $request->user());

        $tasks->each(fn (Task $task) => AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'create',
            'entity' => 'task',
            'entity_id' => $task->id,
            'meta' => ['team_id' => $task->team_id, 'title' => $task->title],
        ]));

        return TaskResource::collection($tasks->each->load('createdBy', 'assignees'))->response()->setStatusCode(201);
    }

    /** POST /staff/tasks — الإدارة العامة ترسل مهمة لعضو لجنة إشراف أو لمشرف (مهمة لكل شخص) */
    public function storeForStaff(Request $request, TaskService $service)
    {
        abort_unless(in_array($request->user()->role, [RoleEnum::SuperAdmin, RoleEnum::Committee], true), 403); // الإدارة العامة ولجنة الإشراف

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
        ]);

        // لجنة الإشراف تكلّف المشرفين فقط؛ تكليف أعضاء اللجنة للإدارة العامة وحدها
        abort_if(
            $request->user()->role === RoleEnum::Committee && User::find($data['user_id'])?->role !== RoleEnum::Supervisor,
            403,
            'لجنة الإشراف ترسل المهام للمشرفين والمجموعات فقط.'
        );

        $task = $service->createForStaff($data, $request->user());

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'create',
            'entity' => 'task',
            'entity_id' => $task->id,
            'meta' => ['team_id' => null, 'title' => $task->title],
        ]);

        return (new TaskResource($task->load('createdBy', 'assignees')))->response()->setStatusCode(201);
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
