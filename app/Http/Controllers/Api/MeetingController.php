<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\Team;
use App\Services\MeetingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MeetingController extends Controller
{
    public function index(Team $team)
    {
        Gate::authorize('viewAny', Meeting::class);

        return response()->json($team->meetings()->with('createdBy')->orderBy('scheduled_at')->get());
    }

    public function store(Request $request, Team $team, MeetingService $service)
    {
        Gate::authorize('create', Meeting::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'scheduled_at' => ['required', 'date'],
            'google_meet_link' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['team_id'] = $team->id;

        $meeting = $service->create($data, $request->user());

        return response()->json($meeting, 201);
    }

    public function show(Meeting $meeting)
    {
        Gate::authorize('view', $meeting);

        return response()->json($meeting->load('createdBy', 'team'));
    }
}
