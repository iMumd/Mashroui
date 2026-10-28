<?php

namespace App\Services;

use App\Events\DiscussionScheduled;
use App\Models\Discussion;
use App\Models\Project;
use App\Support\CurrentTerm;
use App\Support\DiscussionScheduleGuard;
use Illuminate\Validation\ValidationException;

class DiscussionService
{
    public function __construct(
        private CurrentTerm $currentTerm,
        private DiscussionScheduleGuard $guard,
    ) {}

    public function create(array $data): Discussion
    {
        $termId = $this->currentTerm->get();

        if (! $termId) {
            throw ValidationException::withMessages(['term_id' => 'لا يوجد فصل دراسي محدد.']);
        }

        $project = Project::with('team')->findOrFail($data['project_id']);

        $duration = isset($data['duration']) && (int) $data['duration'] > 0 ? (int) $data['duration'] : 30;

        $this->assertNoConflict([
            'place' => $data['place'],
            'discussion_date' => $data['discussion_date'],
            'discussion_time' => $data['discussion_time'],
            'duration' => $duration,
            'committee' => $data['committee'],
            'supervisor_id' => $data['supervisor_id'],
        ], $project->id);

        $discussion = Discussion::create([
            'project_id' => $project->id,
            'supervisor_id' => $data['supervisor_id'],
            'place' => $data['place'],
            'discussion_date' => $data['discussion_date'],
            'discussion_time' => $data['discussion_time'],
            'duration' => $duration,
            'committee' => $data['committee'],
            'whatsapp' => $data['whatsapp'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'term_id' => $termId,
        ]);

        DiscussionScheduled::dispatch($discussion);

        return $discussion;
    }

    public function update(Discussion $discussion, array $data): Discussion
    {
        $effective = [
            'place' => $data['place'] ?? $discussion->place,
            'discussion_date' => $data['discussion_date'] ?? ($discussion->discussion_date?->format('Y-m-d')),
            'discussion_time' => $data['discussion_time'] ?? $discussion->discussion_time,
            'duration' => isset($data['duration']) && (int) $data['duration'] > 0 ? (int) $data['duration'] : $discussion->duration,
            'committee' => $data['committee'] ?? $discussion->committee,
            'supervisor_id' => $discussion->supervisor_id,
        ];

        $this->assertNoConflict($effective, $discussion->project_id);

        $discussion->update($data);

        return $discussion;
    }

    /** يرمي خطأ تحقّق لو الفترة تتعارض مع جدول المناقشات الحالي (قاعة/مناقش/مشرف) */
    private function assertNoConflict(array $slot, int $projectId): void
    {
        $errors = $this->guard->errors(
            $slot,
            $this->guard->existingSlots($slot['discussion_date'], $projectId),
        );

        if ($errors !== []) {
            throw ValidationException::withMessages(['schedule' => $errors]);
        }
    }
}
