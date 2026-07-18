<?php

namespace App\Services;

use App\Enums\ProposalStatusEnum;
use App\Models\FinalReport;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FinalReportService
{
    public function upload(array $data, UploadedFile $file, User $leader): FinalReport
    {
        $project = Project::with('proposal')->findOrFail($data['project_id']);

        if ($project->team->leader_id !== $leader->id) {
            throw ValidationException::withMessages(['project_id' => 'هذا المشروع مش تبع فريقك.']);
        }

        if ($project->proposal?->status !== ProposalStatusEnum::Approved) {
            throw ValidationException::withMessages(['project_id' => 'الرفع يُتاح بعد اعتماد المقترح فقط.']);
        }

        return FinalReport::create([
            'project_id' => $project->id,
            'pdf_path' => Storage::putFile('final_reports', $file),
            'uploaded_by' => $leader->id,
        ]);
    }
}
