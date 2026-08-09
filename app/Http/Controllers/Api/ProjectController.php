<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Scopes\TermScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    public function featured()
    {
        $projects = Project::withoutGlobalScope(TermScope::class)
            ->with(['department:id,name', 'specialization:id,name'])
            ->where('is_featured', true)
            ->select(['id', 'name', 'description', 'department_id', 'specialization_id'])
            ->orderByDesc('id')
            ->paginate(6);

        return response()->json($projects);
    }

    public function update(Request $request, Project $project)
    {
        Gate::authorize('update', $project);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'description' => ['sometimes', 'required', 'string'],
            'is_featured' => ['sometimes', 'boolean'],
        ]);

        $project->update($data);

        return response()->json($project);
    }

    public function complete(Request $request, Project $project)
    {
        Gate::authorize('update', $project);

        if ($project->status !== ProjectStatusEnum::InProgress) {
            throw ValidationException::withMessages(['status' => 'المشروع لازم يكون قيد التنفيذ حتى يُنهى.']);
        }

        $project->update([
            'status' => ProjectStatusEnum::Completed,
            'completed_at' => now(),
        ]);

        return response()->json($project);
    }
}
