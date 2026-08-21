<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Project;
use App\Models\Scopes\TermScope;
use App\Models\Team;
use App\Models\User;
use App\Services\ProgressService;

class StatsController extends Controller
{
    public function index(ProgressService $progressService)
    {
        $teams = Team::withoutGlobalScope(TermScope::class)->get();
        $percentages = $teams->map(fn (Team $team) => $progressService->forTeam($team)['percentage']);

        return response()->json([
            'departments' => Department::count(),
            'teams' => $teams->count(),
            'projects' => Project::withoutGlobalScope(TermScope::class)->count(),
            'supervisors' => User::where('role', RoleEnum::Supervisor)->count(),
            'committee' => User::where('role', RoleEnum::Committee)->count(),
            'students' => User::where('role', RoleEnum::Student)->count(),
            'avg_completion' => $percentages->isEmpty() ? 0.0 : round($percentages->avg(), 1),
        ]);
    }
}
