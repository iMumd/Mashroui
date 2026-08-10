<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FinalReportResource;
use App\Models\FinalReport;
use App\Models\Project;
use App\Services\FinalReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class FinalReportController extends Controller
{
    public function index(Project $project)
    {
        Gate::authorize('viewAny', [FinalReport::class, $project]);

        return FinalReportResource::collection($project->finalReports()->with('uploadedBy')->latest()->get());
    }

    public function download(FinalReport $finalReport)
    {
        Gate::authorize('view', $finalReport);

        return Storage::download($finalReport->pdf_path, 'final-report.pdf');
    }

    public function store(Request $request, Project $project, FinalReportService $service)
    {
        Gate::authorize('create', FinalReport::class);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'video_url' => ['nullable', 'url', 'max:255'],
        ]);

        $data['project_id'] = $project->id;

        $finalReport = $service->upload($data, $request->file('file'), $request->user());

        return response()->json($finalReport, 201);
    }
}
