<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'specialization_id' => $this->specialization_id,
            'term_id' => $this->term_id,
            'supervisor' => new UserResource($this->whenLoaded('supervisor')),
            'leader' => new UserResource($this->whenLoaded('leader')),
            'project' => $this->whenLoaded('project'),
            'members' => TeamMemberResource::collection($this->whenLoaded('members')),
        ];
    }
}
