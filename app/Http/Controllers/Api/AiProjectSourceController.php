<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Scopes\TermScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AiProjectSourceController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->role === RoleEnum::SuperAdmin, 403);

        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['proposed', 'in_progress', 'completed'])],
            'department_id' => ['sometimes', 'exists:departments,id'],
            'specialization_id' => ['sometimes', 'exists:specializations,id'],
        ]);

        $projects = Project::withoutGlobalScope(TermScope::class)
            ->with([
                'department:id,name',
                'specialization:id,name',
                'academicTerm:id,name',
                'proposal:id,project_id,name,description,problems,solutions,features_value',
            ])
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($data['department_id'] ?? null, fn ($q, $id) => $q->where('department_id', $id))
            ->when($data['specialization_id'] ?? null, fn ($q, $id) => $q->where('specialization_id', $id))
            ->select(['id', 'name', 'description', 'department_id', 'specialization_id', 'term_id', 'status', 'is_featured'])
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json($projects);
    }
}
