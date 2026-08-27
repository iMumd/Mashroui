<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProposalResource;
use App\Models\Proposal;
use App\Services\ProposalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ProposalController extends Controller
{
    public function show(Proposal $proposal)
    {
        Gate::authorize('view', $proposal);

        return new ProposalResource($proposal->load('project', 'submittedBy', 'reviewedBy'));
    }

    public function download(Proposal $proposal)
    {
        Gate::authorize('view', $proposal);

        $extension = pathinfo($proposal->pdf_path, PATHINFO_EXTENSION) ?: 'pdf';

        return Storage::download($proposal->pdf_path, "{$proposal->name}.{$extension}");
    }

    public function store(Request $request, ProposalService $service)
    {
        Gate::authorize('create', Proposal::class);

        $data = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'problems' => ['nullable', 'string'],
            'solutions' => ['nullable', 'string'],
            'features_value' => ['nullable', 'string'],
            'pdf' => ['required', 'file', 'mimes:pdf,doc,docx,ppt,pptx', 'max:10240'],
        ]);

        $proposal = $service->submit($data, $request->file('pdf'), $request->user());

        return response()->json($proposal, 201);
    }

    public function update(Request $request, Proposal $proposal, ProposalService $service)
    {
        Gate::authorize('update', $proposal);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'problems' => ['nullable', 'string'],
            'solutions' => ['nullable', 'string'],
            'features_value' => ['nullable', 'string'],
            'pdf' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx', 'max:10240'],
        ]);

        $proposal = $service->resubmit($proposal, $data, $request->file('pdf'));

        return response()->json($proposal);
    }

    public function approve(Proposal $proposal, ProposalService $service, Request $request)
    {
        Gate::authorize('review', $proposal);

        return response()->json($service->approve($proposal, $request->user()));
    }

    public function reject(Request $request, Proposal $proposal, ProposalService $service)
    {
        Gate::authorize('review', $proposal);

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        return response()->json($service->reject($proposal, $request->user(), $data['rejection_reason']));
    }
}
