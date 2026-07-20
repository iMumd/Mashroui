<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinalReport;
use App\Models\Project;
use App\Services\FinalReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FinalReportController extends Controller
{
    public function index(Project $project)
    {
        Gate::authorize('viewAny', [FinalReport::class, $project]);

        return response()->json($project->finalReports()->with('uploadedBy')->latest()->get());
    }

    public function store(Request $request, Project $project, FinalReportService $service)
    {
        Gate::authorize('create', FinalReport::class);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $data['project_id'] = $project->id;

        $finalReport = $service->upload($data, $request->file('file'), $request->user());

        return response()->json($finalReport, 201);
    }
}
