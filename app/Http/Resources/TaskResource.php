<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'team_id' => $this->team_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'created_by' => $this->createdBy ? new UserResource($this->createdBy) : null,
            'files' => TaskFileResource::collection($this->whenLoaded('files')),
            'notes' => TaskNoteResource::collection($this->whenLoaded('notes')),
        ];
    }
}
