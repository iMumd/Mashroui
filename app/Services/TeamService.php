<?php

namespace App\Services;

use App\Enums\ProjectStatusEnum;
use App\Enums\RoleEnum;
use App\Models\Project;
use App\Models\Specialization;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Support\CurrentTerm;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamService
{
    public function __construct(private CurrentTerm $currentTerm) {}

    public function create(array $data): Team
    {
        $termId = $this->currentTerm->get();

        if (! $termId) {
            throw ValidationException::withMessages(['term_id' => 'لا يوجد فصل دراسي محدد.']);
        }

        $conflict = TeamMember::whereIn('student_id', $data['member_ids'])
            ->whereHas('team', fn ($query) => $query->where('term_id', $termId))
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages(['member_ids' => 'أحد الطلاب منضم لفريق آخر بنفس الفصل.']);
        }

        return DB::transaction(function () use ($data, $termId) {
            $team = Team::create([
                'name' => $data['name'],
                'supervisor_id' => $data['supervisor_id'],
                'specialization_id' => $data['specialization_id'],
                'term_id' => $termId,
                'leader_id' => $data['leader_id'],
            ]);

            foreach ($data['member_ids'] as $studentId) {
                TeamMember::create([
                    'team_id' => $team->id,
                    'student_id' => $studentId,
                    'is_leader' => $studentId === $data['leader_id'],
                ]);
            }

            User::whereKey($data['leader_id'])->update(['role' => RoleEnum::TeamLeader]);

            $departmentId = Specialization::whereKey($data['specialization_id'])->value('department_id');

            Project::create([
                'team_id' => $team->id,
                'supervisor_id' => $data['supervisor_id'],
                'department_id' => $departmentId,
                'specialization_id' => $data['specialization_id'],
                'term_id' => $termId,
                'status' => ProjectStatusEnum::Proposed,
                'is_featured' => false,
            ]);

            return $team->load('members.student', 'project');
        });
    }

    public function addMember(Team $team, int $studentId): Team
    {
        $termId = $this->currentTerm->get();

        if ($team->members()->count() >= 4) {
            throw ValidationException::withMessages(['student_id' => 'الفريق مكتمل بالحد الأقصى (4 أعضاء).']);
        }

        $conflict = TeamMember::where('student_id', $studentId)
            ->whereHas('team', fn ($query) => $query->where('term_id', $termId))
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages(['student_id' => 'الطالب منضم لفريق آخر بنفس الفصل.']);
        }

        TeamMember::create([
            'team_id' => $team->id,
            'student_id' => $studentId,
            'is_leader' => false,
        ]);

        return $team->load('members.student', 'supervisor', 'leader', 'project.proposal', 'project.finalReports');
    }

    public function removeMember(Team $team, TeamMember $member): Team
    {
        if ($member->team_id !== $team->id) {
            throw ValidationException::withMessages(['member' => 'العضو لا ينتمي لهذا الفريق.']);
        }

        if ($member->is_leader) {
            throw ValidationException::withMessages(['member' => 'لا يمكن حذف قائد الفريق — عيّني قائدًا آخر أولاً.']);
        }

        if ($team->members()->count() <= 1) {
            throw ValidationException::withMessages(['member' => 'لا يمكن ترك الفريق بدون أعضاء.']);
        }

        $member->delete();

        return $team->load('members.student', 'supervisor', 'leader', 'project.proposal', 'project.finalReports');
    }

    public function updateLeader(Team $team, int $studentId): Team
    {
        $member = $team->members()->where('student_id', $studentId)->first();

        if (! $member) {
            throw ValidationException::withMessages(['student_id' => 'العضو لا ينتمي لهذا الفريق.']);
        }

        return DB::transaction(function () use ($team, $member, $studentId) {
            $previousLeaderId = $team->leader_id;

            $team->members()->update(['is_leader' => false]);
            $member->update(['is_leader' => true]);
            $team->update(['leader_id' => $studentId]);

            User::whereKey($studentId)->update(['role' => RoleEnum::TeamLeader]);

            if ($previousLeaderId && $previousLeaderId !== $studentId) {
                User::whereKey($previousLeaderId)->update(['role' => RoleEnum::Student]);
            }

            return $team->load('members.student', 'supervisor', 'leader', 'project.proposal', 'project.finalReports');
        });
    }
}
