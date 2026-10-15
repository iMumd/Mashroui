<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinalReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'pdf_path' => $this->pdf_path,
            'video_url' => $this->video_url,
            'uploaded_by' => $this->uploadedBy ? new UserResource($this->uploadedBy) : null,
            'review_status' => $this->review_status,
            'review_note' => $this->review_note,
            'supervisor_reviewer' => $this->supervisorReviewer?->only(['id', 'name']),
            'supervisor_reviewed_at' => $this->supervisor_reviewed_at,
            'committee_reviewer' => $this->committeeReviewer?->only(['id', 'name']),
            'committee_reviewed_at' => $this->committee_reviewed_at,
            'created_at' => $this->created_at,
        ];
    }
}
