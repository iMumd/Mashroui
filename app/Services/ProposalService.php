<?php

namespace App\Services;

use App\Enums\ProjectStatusEnum;
use App\Enums\ProposalStatusEnum;
use App\Events\ProposalReviewed;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProposalService
{
    public function submit(array $data, UploadedFile $pdf, User $leader): Proposal
    {
        $project = Project::findOrFail($data['project_id']);

        if ($project->team->leader_id !== $leader->id) {
            throw ValidationException::withMessages(['project_id' => 'هذا المشروع مش تبع فريقك.']);
        }

        if ($project->proposal()->exists()) {
            throw ValidationException::withMessages(['project_id' => 'يوجد مقترح مسبقاً لهذا المشروع، استخدم التعديل.']);
        }

        $path = Storage::putFile('proposals', $pdf);

        return Proposal::create([
            'project_id' => $project->id,
            'name' => $data['name'],
            'description' => $data['description'],
            'problems' => $data['problems'],
            'solutions' => $data['solutions'],
            'features_value' => $data['features_value'],
            'pdf_path' => $path,
            'status' => ProposalStatusEnum::Pending,
            'submitted_by' => $leader->id,
        ]);
    }

    public function resubmit(Proposal $proposal, array $data, ?UploadedFile $pdf): Proposal
    {
        if ($proposal->status === ProposalStatusEnum::Approved) {
            throw ValidationException::withMessages(['status' => 'المقترح معتمد أصلاً، ما بينعدّل.']);
        }

        $path = $pdf ? Storage::putFile('proposals', $pdf) : $proposal->pdf_path;

        $proposal->update([
            'name' => $data['name'],
            'description' => $data['description'],
            'problems' => $data['problems'],
            'solutions' => $data['solutions'],
            'features_value' => $data['features_value'],
            'pdf_path' => $path,
            'status' => ProposalStatusEnum::Pending,
            'rejection_reason' => null,
            'reviewed_by' => null,
        ]);

        return $proposal->fresh();
    }

    public function approve(Proposal $proposal, User $reviewer): Proposal
    {
        if ($proposal->status !== ProposalStatusEnum::Pending) {
            throw ValidationException::withMessages(['status' => 'المقترح تمت مراجعته مسبقاً.']);
        }

        return DB::transaction(function () use ($proposal, $reviewer) {
            $proposal->update([
                'status' => ProposalStatusEnum::Approved,
                'reviewed_by' => $reviewer->id,
                'rejection_reason' => null,
            ]);

            $proposal->project()->update(['status' => ProjectStatusEnum::InProgress]);

            $proposal = $proposal->fresh();

            ProposalReviewed::dispatch($proposal);

            return $proposal;
        });
    }

    public function reject(Proposal $proposal, User $reviewer, string $reason): Proposal
    {
        if ($proposal->status !== ProposalStatusEnum::Pending) {
            throw ValidationException::withMessages(['status' => 'المقترح تمت مراجعته مسبقاً.']);
        }

        $proposal->update([
            'status' => ProposalStatusEnum::Rejected,
            'reviewed_by' => $reviewer->id,
            'rejection_reason' => $reason,
        ]);

        $proposal = $proposal->fresh();

        ProposalReviewed::dispatch($proposal);

        return $proposal;
    }
}
