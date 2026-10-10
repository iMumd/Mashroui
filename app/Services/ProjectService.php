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

        // الأرشفة بعد اعتماد التقرير النهائي من المشرف ثم لجنة الإشراف فقط
        if ($project->finalReports()->latest('id')->value('review_status') !== 'approved') {
            throw ValidationException::withMessages(['status' => 'لا يُؤرشف المشروع قبل موافقة المشرف ثم اعتماد لجنة الإشراف على التقرير النهائي.']);
        }

        $project->update([
            'status' => ProjectStatusEnum::Completed,
            'completed_at' => now(),
        ]);

        return $project;
    }
}
