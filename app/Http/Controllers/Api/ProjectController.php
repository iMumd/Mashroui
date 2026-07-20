<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Scopes\TermScope;

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
}
