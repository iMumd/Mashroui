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
}
