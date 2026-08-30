<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Scopes\TermScope;
use Illuminate\Http\Request;

class AiProjectExportController extends Controller
{
    public function index(Request $request)
    {
        $projects = Project::withoutGlobalScope(TermScope::class)
            ->with([
                'department:id,name',
                'specialization:id,name',
                'academicTerm:id,name',
                'proposal:id,project_id,name,description,problems,solutions,features_value',
            ])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->proposal?->name ?? $project->name,
                'description' => $project->proposal?->description ?? $project->description,
                'problems' => $project->proposal?->problems,
                'solutions' => $project->proposal?->solutions,
                'features_value' => $project->proposal?->features_value,
                'department' => $project->department?->name,
                'specialization' => $project->specialization?->name,
                'term' => $project->academicTerm?->name,
                'status' => $project->status->value,
            ]);

        return response()->json($projects);
    }
}
