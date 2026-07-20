<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Services\TeamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index()
    {
        return TeamResource::collection(Team::with('members.student', 'supervisor', 'leader', 'project')->get());
    }

    public function store(Request $request, TeamService $teamService)
    {
        Gate::authorize('create', Team::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'supervisor_id' => ['required', 'exists:users,id'],
            'specialization_id' => ['required', 'exists:specializations,id'],
            'member_ids' => ['required', 'array', 'min:1', 'max:4'],
            'member_ids.*' => [
                'distinct',
                Rule::exists('users', 'id')->where('role', 'student'),
            ],
            'leader_id' => ['required', Rule::in($request->input('member_ids', []))],
        ]);

        $team = $teamService->create($data);

        return response()->json($team, 201);
    }

    public function show(Team $team)
    {
        return new TeamResource($team->load('members.student', 'supervisor', 'leader', 'project'));
    }
}
