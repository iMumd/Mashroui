<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectStatusEnum;
use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\ProgressService;
use Illuminate\Http\Request;

class CommitteeDashboardController extends Controller
{
    public function index(Request $request, ProgressService $progressService)
    {
        abort_unless(
            in_array($request->user()->role, [RoleEnum::Committee, RoleEnum::SuperAdmin], true),
            403
        );

        $teams = Team::all();
        $percentages = $teams->map(fn (Team $team) => $progressService->forTeam($team)['percentage']);

        // تصنيف الطلاب المنضمين لفرق هذا الفصل حسب الجنس ومكان التواجد
        $assignedStudents = User::where('role', RoleEnum::Student)->whereHas('teamMemberships.team');

        $byGender = (clone $assignedStudents)->selectRaw('gender, count(*) c')->groupBy('gender')->pluck('c', 'gender');
        $byRegion = (clone $assignedStudents)->selectRaw('region, count(*) c')->groupBy('region')->pluck('c', 'region');

        $latestTasks = Task::with('team:id,name', 'assignees:id,name')
            ->latest()
            ->take(10)
            ->get(['id', 'team_id', 'title', 'status', 'due_date', 'created_at']);

        return response()->json([
            'completed_projects' => Project::where('status', ProjectStatusEnum::Completed)->count(),
            'total_projects_this_term' => Project::count(),
            'departments_count' => Department::whereHas('specializations.teams')->count(),
            'students_count' => User::where('role', RoleEnum::Student)
                ->whereHas('teamMemberships.team')
                ->count(),
            'average_completion' => $percentages->isEmpty() ? 0.0 : round($percentages->avg(), 1),
            'status_distribution' => [
                'completed' => Project::where('status', ProjectStatusEnum::Completed)->count(),
                'in_progress' => Project::where('status', ProjectStatusEnum::InProgress)->count(),
                'proposed' => Project::where('status', ProjectStatusEnum::Proposed)->count(),
            ],
            'students_by_gender' => [
                'male' => (int) ($byGender['male'] ?? 0),
                'female' => (int) ($byGender['female'] ?? 0),
            ],
            'students_by_region' => [
                'gaza' => (int) ($byRegion['gaza'] ?? 0),
                'south' => (int) ($byRegion['south'] ?? 0),
            ],
            'latest_tasks' => $latestTasks,
        ]);
    }
}
