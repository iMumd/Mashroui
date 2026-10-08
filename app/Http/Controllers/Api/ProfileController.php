<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Services\InteractionService;
use App\Support\Rbac\StudentDataVisibility;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    // GET /supervisors/{user}/profile — بيانات المشرف ومجموعاته ومدى تفاعله مع كلٍّ منها
    public function supervisor(Request $request, User $user, InteractionService $interaction)
    {
        abort_unless($user->role === RoleEnum::Supervisor, 404);

        $viewer = $request->user();
        abort_unless(
            $viewer->id === $user->id || in_array($viewer->role, [RoleEnum::Committee, RoleEnum::SuperAdmin], true),
            403
        );

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'whatsapp' => $user->whatsapp,
            'employee_number' => $user->employee_number,
            'specialization_id' => $user->specialization_id,
            'interaction' => $interaction->forSupervisor($user),
        ]);
    }

    // GET /students/{user}/profile — المشرف، الزملاء وأماكن تواجدهم، وتفاصيل المشروع
    public function student(Request $request, User $user)
    {
        abort_unless($user->role === RoleEnum::Student, 404);

        $viewer = $request->user();

        $team = Team::with(['supervisor', 'members.student', 'project.specialization', 'leader'])
            ->where(fn ($q) => $q->whereHas('members', fn ($m) => $m->where('student_id', $user->id))->orWhere('leader_id', $user->id))
            ->first();

        $isStaff = in_array($viewer->role, [RoleEnum::Committee, RoleEnum::SuperAdmin], true);
        $isSelf = $viewer->id === $user->id;
        $isSupervisor = $team && $team->supervisor_id === $viewer->id;
        $isTeammate = $team && (
            $team->leader_id === $viewer->id
            || $team->members->contains(fn ($m) => $m->student_id === $viewer->id)
        );

        abort_unless($isStaff || $isSelf || $isSupervisor || $isTeammate, 403);

        $visibility = app(StudentDataVisibility::class);
        $canSeeContact = $visibility->canSeeContactInfo($viewer, $user);

        $teammates = $team
            ? $team->members
                ->filter(fn ($m) => $m->student_id !== $user->id)
                ->map(fn ($m) => [
                    'id' => $m->student_id,
                    'name' => $m->student?->name,
                    'region' => $m->student?->region,
                    'is_leader' => $m->is_leader,
                ])->values()->all()
            : [];

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'gender' => $user->gender,
            'region' => $user->region, // مكان تواجد الطالب نفسه
            'university_number' => $user->university_number,
            'email' => $canSeeContact ? $user->email : null,
            'whatsapp' => $canSeeContact ? $user->whatsapp : null,
            'section' => $team?->section,
            'supervisor' => $team?->supervisor ? [
                'id' => $team->supervisor->id,
                'name' => $team->supervisor->name,
                'whatsapp' => $canSeeContact ? $team->supervisor->whatsapp : null,
            ] : null,
            'teammates' => $teammates,
            'project' => $team?->project ? [
                'id' => $team->project->id,
                'name' => $team->project->name,
                'status' => $team->project->status,
                'degree' => $team->project->specialization?->degree,
                'specialization' => $team->project->specialization?->name,
            ] : null,
        ]);
    }
}
