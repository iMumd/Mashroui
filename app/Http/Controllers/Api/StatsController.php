<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Project;
use App\Models\Scopes\TermScope;
use App\Models\Team;
use App\Models\User;

class StatsController extends Controller
{
    public function index()
    {
        return response()->json([
            'departments' => Department::count(),
            'teams' => Team::withoutGlobalScope(TermScope::class)->count(),
            'projects' => Project::withoutGlobalScope(TermScope::class)->count(),
            'supervisors' => User::where('role', RoleEnum::Supervisor)->count(),
            'committee' => User::where('role', RoleEnum::Committee)->count(),
            'students' => User::where('role', RoleEnum::Student)->count(),
        ]);
    }
}
