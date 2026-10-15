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
use Illuminate\Support\Facades\URL;

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

    // بيرجع رابط مؤقّت (موقّع) صالح لـ5 دقائق — نفس منطق ProposalController::downloadLink
    public function downloadLink(FinalReport $finalReport)
    {
        Gate::authorize('view', $finalReport);

        $url = URL::temporarySignedRoute('final-reports.download-signed', now()->addMinutes(5), ['finalReport' => $finalReport->id]);

        return response()->json(['url' => $url]);
    }

    public function downloadSigned(FinalReport $finalReport)
    {
        return Storage::response($finalReport->pdf_path, 'final-report.pdf');
    }

    // POST /final-reports/{finalReport}/review — موافقة/رفض حسب المرحلة (المشرف ثم لجنة الإشراف)
    public function review(Request $request, FinalReport $finalReport, FinalReportService $service)
    {
        Gate::authorize('view', $finalReport);

        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        return new FinalReportResource($service->review($finalReport, $request->user(), $data['decision'] === 'approve', $data['note'] ?? null));
    }

    public function store(Request $request, Project $project, FinalReportService $service)
    {
        Gate::authorize('create', FinalReport::class);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:102400'],
            'video_url' => ['required', 'url', 'max:255'],
            'cover' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
        ]);

        $data['project_id'] = $project->id;

        $finalReport = $service->upload($data, $request->file('file'), $request->user(), $request->file('cover'));

        return response()->json($finalReport, 201);
    }
}
