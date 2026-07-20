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
            'uploaded_by' => $this->uploadedBy ? new UserResource($this->uploadedBy) : null,
            'created_at' => $this->created_at,
        ];
    }
}
