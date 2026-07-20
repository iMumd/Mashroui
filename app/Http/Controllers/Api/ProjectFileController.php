<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectFileResource;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Rules\AllowedDocumentTypes;
use App\Services\ProjectFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectFileController extends Controller
{
    public function index(Project $project)
    {
        Gate::authorize('viewAny', [ProjectFile::class, $project]);

        return ProjectFileResource::collection($project->files()->with('uploadedBy')->latest('uploaded_at')->get());
    }

    public function store(Request $request, Project $project, ProjectFileService $service)
    {
        Gate::authorize('create', ProjectFile::class);

        $data = $request->validate([
            'stage' => ['nullable', 'string', 'max:120'],
            'file' => ['required', 'file', 'mimes:'.AllowedDocumentTypes::MIMES, 'max:20480'],
        ]);

        $data['project_id'] = $project->id;

        $projectFile = $service->upload($data, $request->file('file'), $request->user());

        return response()->json($projectFile, 201);
    }
}
