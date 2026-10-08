<?php

namespace App\Services;

use App\Enums\ProjectStatusEnum;
use App\Models\Project;
use Illuminate\Validation\ValidationException;

class ProjectService
{
    public function complete(Project $project): Project
    {
        if ($project->status !== ProjectStatusEnum::InProgress) {
            throw ValidationException::withMessages(['status' => 'المشروع لازم يكون قيد التنفيذ حتى يُنهى.']);
        }

        $project->update([
            'status' => ProjectStatusEnum::Completed,
            'completed_at' => now(),
        ]);

        return $project;
    }
}
