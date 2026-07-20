<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'note' => $this->note,
            'user' => $this->user ? new UserResource($this->user) : null,
            'created_at' => $this->created_at,
        ];
    }
}
