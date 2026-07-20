<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProposalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'name' => $this->name,
            'description' => $this->description,
            'problems' => $this->problems,
            'solutions' => $this->solutions,
            'features_value' => $this->features_value,
            'pdf_path' => $this->pdf_path,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'project' => $this->whenLoaded('project'),
            'submitted_by' => $this->submittedBy ? new UserResource($this->submittedBy) : null,
            'reviewed_by' => $this->reviewedBy ? new UserResource($this->reviewedBy) : null,
        ];
    }
}
