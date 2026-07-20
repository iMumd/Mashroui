<?php

namespace App\Http\Resources;

use App\Support\Rbac\StudentDataVisibility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $canSeeContact = $viewer && app(StudentDataVisibility::class)->canSeeContactInfo($viewer, $this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role,
            'status' => $this->status,
            'specialization_id' => $this->specialization_id,
            'email' => $canSeeContact ? $this->email : null,
            'whatsapp' => $canSeeContact ? $this->whatsapp : null,
            'university_number' => $canSeeContact ? $this->university_number : null,
            'employee_number' => $canSeeContact ? $this->employee_number : null,
        ];
    }
}
