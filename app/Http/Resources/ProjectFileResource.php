<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'stage' => $this->stage,
            'file_path' => $this->file_path,
            'version' => $this->version,
            'uploaded_by' => $this->uploadedBy ? new UserResource($this->uploadedBy) : null,
            'uploaded_at' => $this->uploaded_at,
        ];
    }
}
