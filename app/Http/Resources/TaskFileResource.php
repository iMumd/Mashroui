<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'file_path' => $this->file_path,
            'uploaded_by' => $this->uploadedBy ? new UserResource($this->uploadedBy) : null,
            'created_at' => $this->created_at,
        ];
    }
}
