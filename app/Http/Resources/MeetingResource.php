<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'team_id' => $this->team_id,
            'title' => $this->title,
            'scheduled_at' => $this->scheduled_at,
            'google_meet_link' => $this->google_meet_link,
            'notes' => $this->notes,
            'team' => $this->whenLoaded('team'),
            'created_by' => $this->createdBy ? new UserResource($this->createdBy) : null,
        ];
    }
}
