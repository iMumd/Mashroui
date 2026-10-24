<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\TeamResource;
use App\Models\AuditLog;
use App\Models\Team;
use App\Models\TeamMember;
use App\Services\TeamService;
use App\Support\CurrentTerm;
use App\Support\Rbac\TeamMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Team::with('members.student', 'supervisor', 'leader', 'project.proposal', 'project.finalReports');

        // الإدارة واللجنة يرون الكل (اللجنة مقيّدة أصلًا بتخصصها عبر SpecializationScope)؛
        // المشرف والطالب يرون فرقهم فقط — لا يُعتمد على تقييد الواجهة وحده.
        if (! in_array($user->role, [RoleEnum::SuperAdmin, RoleEnum::Committee], true)) {
            $query->where(function ($q) use ($user) {
                $q->where('supervisor_id', $user->id)
                    ->orWhere('leader_id', $user->id)
                    ->orWhereHas('members', fn ($m) => $m->where('student_id', $user->id));
            });
        }

        return TeamResource::collection($query->get());
    }

    public function store(Request $request, TeamService $teamService, CurrentTerm $currentTerm)
    {
        Gate::authorize('create', Team::class);

        $termId = $currentTerm->get();
        abort_if($termId === null, 422, 'لا يوجد فصل دراسي محدد.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'section' => ['nullable', 'string', 'max:50'],
            // المشرف والطلاب من الفصل الحالي فقط (كل فصل له مشرفوه وطلابه)
            'supervisor_id' => ['required', $this->currentTermUser(RoleEnum::Supervisor, $termId)],
            'specialization_id' => ['required', 'exists:specializations,id'],
            'member_ids' => ['required', 'array', 'min:1', 'max:4'],
            'member_ids.*' => [
                'distinct',
                $this->currentTermUser(RoleEnum::Student, $termId),
            ],
            'leader_id' => ['required', Rule::in($request->input('member_ids', []))],
        ]);

        $team = $teamService->create($data);

        return (new TeamResource($team->load('supervisor', 'leader')))->response()->setStatusCode(201);
    }

    public function show(Request $request, Team $team, TeamMembership $membership)
    {
        // منع تعداد/قراءة فرق الآخرين: المشرف والطالب لفرقهم فقط، الإدارة واللجنة ضمن نطاقهم.
        abort_unless($membership->belongsTo($request->user(), $team), 403);

        return new TeamResource($team->load('members.student', 'supervisor', 'leader', 'project.proposal', 'project.finalReports'));
    }

    public function update(Request $request, Team $team, CurrentTerm $currentTerm)
    {
        Gate::authorize('update', $team);

        abort_if($currentTerm->get() === null, 422, 'لا يوجد فصل دراسي محدد.');

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'section' => ['sometimes', 'nullable', 'string', 'max:50'],
            'supervisor_id' => ['sometimes', 'required', $this->currentTermUser(RoleEnum::Supervisor, $currentTerm->get())],
            'specialization_id' => ['sometimes', 'required', 'exists:specializations,id'],
        ]);

        $team->update($data);

        return new TeamResource($team->load('members.student', 'supervisor', 'leader', 'project.proposal', 'project.finalReports'));
    }

    public function destroy(Request $request, Team $team)
    {
        Gate::authorize('delete', $team);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'delete',
            'entity' => 'team',
            'entity_id' => $team->id,
            'meta' => ['name' => $team->name],
        ]);

        $team->delete();

        return response()->json(null, 204);
    }

    public function trashed()
    {
        return TeamResource::collection(
            Team::onlyTrashed()
                ->with('members.student', 'supervisor', 'leader', 'project.proposal', 'project.finalReports')
                ->get()
        );
    }

    public function restore(Request $request, int $team)
    {
        $team = Team::onlyTrashed()->findOrFail($team);

        Gate::authorize('restore', $team);

        $team->restore();

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'restore',
            'entity' => 'team',
            'entity_id' => $team->id,
            'meta' => ['name' => $team->name],
        ]);

        return new TeamResource($team->load('members.student', 'supervisor', 'leader', 'project.proposal', 'project.finalReports'));
    }

    public function addMember(Request $request, Team $team, TeamService $teamService, CurrentTerm $currentTerm)
    {
        Gate::authorize('update', $team);

        abort_if($currentTerm->get() === null, 422, 'لا يوجد فصل دراسي محدد.');

        $data = $request->validate([
            'student_id' => ['required', $this->currentTermUser(RoleEnum::Student, $currentTerm->get())],
        ]);

        $team = $teamService->addMember($team, $data['student_id']);

        return new TeamResource($team);
    }

    public function removeMember(Request $request, Team $team, TeamMember $member, TeamService $teamService)
    {
        Gate::authorize('update', $team);

        $team = $teamService->removeMember($team, $member);

        return new TeamResource($team);
    }

    public function updateLeader(Request $request, Team $team, TeamService $teamService)
    {
        Gate::authorize('manageLeader', $team);

        $data = $request->validate([
            'student_id' => ['required', 'integer'],
        ]);

        $team = $teamService->updateLeader($team, $data['student_id']);

        return new TeamResource($team);
    }

    /** قاعدة وجود مستخدم بدور معيّن داخل الفصل الحالي — تُستخدم في تحقق المشرف والطلاب */
    private function currentTermUser(RoleEnum $role, ?int $termId): Exists
    {
        return Rule::exists('users', 'id')
            ->where('role', $role->value)
            ->where('term_id', $termId);
    }
}
