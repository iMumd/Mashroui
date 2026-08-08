<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectStatusEnum;
use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Project;
use App\Models\Scopes\TermScope;
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

        return response()->json([
            'completed_projects' => Project::withoutGlobalScope(TermScope::class)
                ->where('status', ProjectStatusEnum::Completed)
                ->count(),
            'total_projects_this_term' => Project::count(),
            'departments_count' => Department::count(),
            'students_count' => User::where('role', RoleEnum::Student)->count(),
            'average_completion' => $percentages->isEmpty() ? 0.0 : round($percentages->avg(), 1),
            'status_distribution' => [
                'completed' => Project::where('status', ProjectStatusEnum::Completed)->count(),
                'in_progress' => Project::where('status', ProjectStatusEnum::InProgress)->count(),
                'proposed' => Project::where('status', ProjectStatusEnum::Proposed)->count(),
            ],
        ]);
    }
}
